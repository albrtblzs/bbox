<?php

namespace Tests\Feature;

use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TransactionHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_is_merchant_scoped_and_paginated_with_deterministic_ties(): void
    {
        $merchant = Merchant::create(['name' => 'Shop', 'currency' => 'EUR']);
        $other = Merchant::create(['name' => 'Other', 'currency' => 'EUR']);
        $ids = [];
        for ($index = 0; $index < 25; $index++) {
            $ids[] = $this->entry($merchant, 'key-'.$index, '2026-11-08 12:00:00');
        }
        $this->entry($other, 'private', '2026-11-08 14:00:00');
        // A lower ID with a later timestamp must sort ahead of all tied rows.
        DB::table('transactions')->where('id', $ids[0])->update(['created_at' => '2026-11-08 13:00:00']);
        $expected = [$ids[0], ...array_reverse(array_slice($ids, 1))];
        $first = $this->getJson('/api/merchants/'.$merchant->id.'/transactions?per_page=100')
            ->assertOk()->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 25)->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.last_page', 2)->assertJsonStructure(['links' => ['next', 'prev']]);
        $this->assertSame(array_slice($expected, 0, 20), array_column($first->json('data'), 'id'));
        $second = $this->getJson('/api/merchants/'.$merchant->id.'/transactions?page=2')
            ->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('meta.current_page', 2);
        $this->assertSame(array_slice($expected, 20), array_column($second->json('data'), 'id'));
        foreach ([...$first->json('data'), ...$second->json('data')] as $entry) {
            $this->assertSame($merchant->id, $entry['merchant_id']);
            $this->assertArrayHasKey('balance_after', $entry);
            $this->assertArrayNotHasKey('idempotency_key', $entry);
        }
    }

    public function test_empty_and_out_of_range_pages_are_empty_and_missing_merchant_is_404(): void
    {
        $merchant = Merchant::create(['name' => 'Shop', 'currency' => 'EUR']);
        foreach (['', '?page=2'] as $query) {
            $this->getJson('/api/merchants/'.$merchant->id.'/transactions'.$query)
                ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
        }
        $this->getJson('/api/merchants/999999/transactions')->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
    }

    #[DataProvider('invalidPages')]
    public function test_invalid_pages_return_validation_errors(string $query): void
    {
        $merchant = Merchant::create(['name' => 'Shop', 'currency' => 'EUR']);
        $this->getJson('/api/merchants/'.$merchant->id.'/transactions?'.$query)
            ->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_INPUT');
    }

    public static function invalidPages(): array
    {
        return [['page=0'], ['page=-1'], ['page=abc'], ['page[]=1'], ['page=2147483648']];
    }

    private function entry(Merchant $merchant, string $key, string $time): int
    {
        return DB::table('transactions')->insertGetId([
            'merchant_id' => $merchant->id, 'type' => 'payment', 'amount' => 1,
            'currency' => $merchant->currency, 'idempotency_key' => $key, 'balance_after' => 1,
            'created_at' => $time, 'updated_at' => $time,
        ]);
    }
}
