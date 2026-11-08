<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->fakeValidator(['*' => Http::response(['approved' => true, 'reason' => 'APPROVED'])]);
        Log::spy();
    }

    public function test_payments_refunds_and_exact_balance_refund(): void
    {
        $merchant = $this->merchant();
        $this->postTransaction($merchant, 'pay', 100)->assertCreated()->assertJsonPath('data.balance_after', 100);
        $this->postTransaction($merchant, 'refund', 100, 'refund')->assertCreated()->assertJsonPath('data.balance_after', 0);
        $this->assertSame(0, $merchant->refresh()->balance);
        $this->assertDatabaseCount('transactions', 2);
        Http::assertSent(fn ($request) => $request->url() === 'http://validator:3000/validate'
            && $request->hasHeader('X-Correlation-ID') && $request['currency'] === 'EUR');
        Log::shouldHaveReceived('info')->with('transaction_validation', \Mockery::on(fn ($context) => isset($context['correlation_id'], $context['latency_ms']) && $context['outcome'] === 'approved'
            && ! isset($context['amount'], $context['body'])))->twice();
    }

    public function test_replay_returns_original_response_after_later_changes_and_validator_outage(): void
    {
        $merchant = $this->merchant();
        $original = $this->postTransaction($merchant, 'first', 100)->assertCreated()->json();
        $this->postTransaction($merchant, 'second', 50)->assertCreated();
        $this->fakeValidator(fn () => throw new ConnectionException('offline'));
        $this->postTransaction($merchant, 'first', 100)->assertCreated()->assertExactJson($original);
        Http::assertNothingSent();
        $this->assertSame(150, $merchant->refresh()->balance);
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_existing_key_conflicts_on_amount_type_or_currency_before_validation(): void
    {
        $merchant = $this->merchant();
        $this->postTransaction($merchant, 'key', 100)->assertCreated();
        $this->fakeValidator(fn () => throw new ConnectionException('offline'));
        foreach ([['amount' => 101], ['type' => 'refund'], ['currency' => 'USD']] as $change) {
            $this->withHeader('Idempotency-Key', 'key')->postJson('/api/merchants/'.$merchant->id.'/transactions', [
                ...$this->input(), 'amount' => 100, ...$change,
            ])->assertConflict()->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        }
        Http::assertNothingSent();
        $this->assertSame(100, $merchant->refresh()->balance);
    }

    public function test_failed_business_attempts_do_not_reserve_keys(): void
    {
        $merchant = $this->merchant();
        $this->postTransaction($merchant, 'refund-key', 1, 'refund')->assertUnprocessable()->assertJsonPath('error.code', 'INSUFFICIENT_FUNDS');
        $this->postTransaction($merchant, 'fund', 1)->assertCreated();
        $this->postTransaction($merchant, 'refund-key', 1, 'refund')->assertCreated();
        $this->assertSame(0, $merchant->refresh()->balance);
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_validator_rejection_does_not_reserve_key_or_change_balance(): void
    {
        $merchant = $this->merchant();
        $this->fakeValidator(['*' => Http::response(['approved' => false, 'reason' => 'AMOUNT_LIMIT_EXCEEDED'])]);
        $this->postTransaction($merchant, 'key')->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATOR_REJECTED');
        $this->assertSame(0, $merchant->refresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
        $this->fakeValidator(['*' => Http::response(['approved' => true, 'reason' => 'APPROVED'])]);
        $this->postTransaction($merchant, 'key')->assertCreated();
    }

    #[DataProvider('badValidatorResponses')]
    public function test_invalid_validator_response_fails_closed(mixed $body, int $status): void
    {
        $merchant = $this->merchant();
        $this->fakeValidator(['*' => Http::response($body, $status)]);
        $this->postTransaction($merchant, 'key')->assertStatus(503)->assertJsonPath('error.code', 'VALIDATOR_UNAVAILABLE');
        $this->assertSame(0, $merchant->refresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
        Http::assertSentCount(1);
    }

    public static function badValidatorResponses(): array
    {
        return [
            'server failure' => [['approved' => true, 'reason' => 'APPROVED'], 500],
            'rate limit' => [[], 429],
            'redirect' => [[], 302],
            'invalid json' => ['not-json', 200],
            'missing fields' => [[], 200],
            'coerced boolean' => [['approved' => 1, 'reason' => 'APPROVED'], 200],
            'missing reason' => [['approved' => true], 200],
            'contradiction' => [['approved' => false, 'reason' => 'APPROVED'], 200],
            'unknown reason' => [['approved' => true, 'reason' => 'UNKNOWN'], 200],
        ];
    }

    public function test_connection_failure_is_retryable_and_not_persisted(): void
    {
        $merchant = $this->merchant();
        $this->fakeValidator(fn () => throw new ConnectionException('timeout'));
        $this->postTransaction($merchant, 'key')->assertStatus(503)->assertJsonPath('error.code', 'VALIDATOR_UNAVAILABLE');
        $this->assertSame(0, $merchant->refresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
        $this->fakeValidator(['*' => Http::response(['approved' => true, 'reason' => 'APPROVED'])]);
        $this->postTransaction($merchant, 'key')->assertCreated();
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_never_calls_validator(array $input): void
    {
        $merchant = $this->merchant();
        $this->withHeader('Idempotency-Key', 'key')->postJson('/api/merchants/'.$merchant->id.'/transactions', $input)
            ->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_INPUT');
        Http::assertNothingSent();
        $this->assertDatabaseCount('transactions', 0);
    }

    public static function invalidInputs(): array
    {
        $base = ['type' => 'payment', 'amount' => 1, 'currency' => 'EUR'];
        $cases = [];
        foreach ([0, -1, 1.5, '1', true, null, Transaction::MAX_AMOUNT + 1] as $index => $amount) {
            $cases['amount '.$index] = [[...$base, 'amount' => $amount]];
        }
        $cases['currency mismatch'] = [[...$base, 'currency' => 'USD']];
        $cases['invalid type'] = [[...$base, 'type' => 'other']];
        $cases['empty'] = [[]];

        return $cases;
    }

    public function test_key_must_come_from_header_and_satisfy_contract(): void
    {
        $merchant = $this->merchant();
        $url = '/api/merchants/'.$merchant->id.'/transactions';
        $this->postJson($url, [...$this->input(), 'idempotency_key' => 'body-key'])->assertUnprocessable();
        foreach (['', 'with spaces', str_repeat('a', 129)] as $key) {
            $this->withHeader('Idempotency-Key', $key)->postJson($url, $this->input())->assertUnprocessable();
        }
        Http::assertNothingSent();
    }

    public function test_payments_cannot_exceed_safe_balance_bound(): void
    {
        $merchant = $this->merchant();
        $merchant->update(['balance' => Merchant::MAX_BALANCE]);
        $this->postTransaction($merchant, 'key')->assertUnprocessable()->assertJsonPath('error.code', 'BALANCE_LIMIT_EXCEEDED');
        $this->assertSame(Merchant::MAX_BALANCE, $merchant->refresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_validator_call_does_not_hold_a_new_database_transaction(): void
    {
        $merchant = $this->merchant();
        $baseline = DB::transactionLevel();
        $this->fakeValidator(function () use ($baseline) {
            $this->assertSame($baseline, DB::transactionLevel());

            return Http::response(['approved' => true, 'reason' => 'APPROVED']);
        });
        $this->postTransaction($merchant, 'key')->assertCreated();
    }

    public function test_failure_after_insert_rolls_back_the_entry_and_balance(): void
    {
        $merchant = $this->merchant();
        DB::unprepared("CREATE FUNCTION fail_merchant_update() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'simulated write failure'; END; $$");
        DB::unprepared('CREATE TRIGGER fail_update BEFORE UPDATE ON merchants FOR EACH ROW EXECUTE FUNCTION fail_merchant_update()');
        try {
            app(TransactionService::class)->post($merchant, $this->input(), 'key');
            $this->fail('Expected the injected write failure.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('simulated write failure', $exception->getMessage());
        }
        $this->assertSame(0, $merchant->refresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    private function merchant(): Merchant
    {
        return Merchant::create(['name' => 'Shop', 'currency' => 'EUR']);
    }

    private function fakeValidator(mixed $callback): void
    {
        // Laravel combines successive fakes; replace the factory for each scenario.
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake($callback);
    }

    private function input(): array
    {
        return ['type' => 'payment', 'amount' => 1, 'currency' => 'EUR'];
    }

    private function postTransaction(Merchant $merchant, string $key, int $amount = 1, string $type = 'payment')
    {
        return $this->withHeader('Idempotency-Key', $key)->postJson('/api/merchants/'.$merchant->id.'/transactions', [
            'type' => $type, 'amount' => $amount, 'currency' => 'EUR',
        ]);
    }
}
