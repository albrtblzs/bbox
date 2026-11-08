<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LedgerSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_relationships_and_integer_boundaries(): void
    {
        $this->assertSame('ledger_test', DB::connection()->getDatabaseName());
        $merchant = Merchant::create(['name' => 'Shop', 'currency' => 'EUR']);
        $this->assertSame(0, $merchant->refresh()->balance);
        $transaction = $merchant->transactions()->create($this->transactionData());
        $this->assertTrue($transaction->merchant->is($merchant));
        $this->assertSame(Transaction::MAX_AMOUNT, $transaction->refresh()->amount);
        $this->assertSame(Merchant::MAX_BALANCE, $transaction->balance_after);
        $this->assertSame(1, $merchant->transactions()->count());
        $merchant->update(['balance' => Merchant::MAX_BALANCE]);
        $this->assertSame(Merchant::MAX_BALANCE, $merchant->refresh()->balance);
    }

    #[DataProvider('invalidMerchantValues')]
    public function test_database_rejects_invalid_merchants(array $values): void
    {
        $this->expectException(QueryException::class);
        DB::table('merchants')->insert(array_merge(['name' => 'Shop', 'currency' => 'EUR'], $values));
    }

    public static function invalidMerchantValues(): array
    {
        return [
            'negative balance' => [['balance' => -1]],
            'unsafe balance' => [['balance' => Merchant::MAX_BALANCE + 1]],
            'unsupported currency' => [['currency' => 'GBP']],
        ];
    }

    #[DataProvider('invalidTransactionValues')]
    public function test_database_rejects_invalid_transactions(array $values): void
    {
        $merchant = Merchant::create(['name' => 'Shop', 'currency' => 'EUR']);
        $this->expectException(QueryException::class);
        DB::table('transactions')->insert(array_merge($this->transactionData(), ['merchant_id' => $merchant->id], $values));
    }

    public static function invalidTransactionValues(): array
    {
        return [
            'zero' => [['amount' => 0]],
            'negative' => [['amount' => -1]],
            'overflow' => [['amount' => Transaction::MAX_AMOUNT + 1]],
            'unknown type' => [['type' => 'other']],
            'different currency' => [['currency' => 'USD']],
            'unknown merchant' => [['merchant_id' => 999999]],
            'negative result' => [['balance_after' => -1]],
            'unsafe result' => [['balance_after' => Merchant::MAX_BALANCE + 1]],
            'blank key' => [['idempotency_key' => '   ']],
        ];
    }

    public function test_key_is_scoped_to_merchant_but_unique_within_one(): void
    {
        $first = Merchant::create(['name' => 'First', 'currency' => 'EUR']);
        $second = Merchant::create(['name' => 'Second', 'currency' => 'EUR']);
        $first->transactions()->create($this->transactionData());
        $second->transactions()->create($this->transactionData());
        $this->assertDatabaseCount('transactions', 2);
        $this->expectException(QueryException::class);
        $first->transactions()->create($this->transactionData());
    }

    public function test_merchant_currency_cannot_be_changed_through_model(): void
    {
        $merchant = Merchant::create(['name' => 'Shop', 'currency' => 'EUR']);
        $this->expectException(LogicException::class);
        $merchant->update(['currency' => 'USD']);
    }

    private function transactionData(): array
    {
        return [
            'type' => 'payment', 'amount' => Transaction::MAX_AMOUNT, 'currency' => 'EUR',
            'idempotency_key' => 'example-key', 'balance_after' => Merchant::MAX_BALANCE,
        ];
    }
}
