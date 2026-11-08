<?php

namespace Tests\Feature;

use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MerchantApiTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('currencies')]
    public function test_create_and_retrieve_a_merchant(string $currency): void
    {
        $created = $this->postJson('/api/merchants', ['name' => '  Example Shop  ', 'currency' => $currency])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Example Shop')
            ->assertJsonPath('data.currency', $currency)
            ->assertJsonPath('data.balance', 0)
            ->assertJsonStructure(['data' => ['id', 'name', 'currency', 'balance', 'created_at', 'updated_at']]);

        $this->assertDatabaseCount('merchants', 1);
        $this->getJson('/api/merchants/'.$created->json('data.id'))
            ->assertOk()
            ->assertExactJson($created->json());
    }

    public static function currencies(): array
    {
        return [['EUR'], ['USD'], ['HUF']];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_returns_json_without_creating_a_merchant(array $input, string $field): void
    {
        // API errors must be JSON even without an Accept: application/json header.
        $this->post('/api/merchants', $input)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_INPUT')
            ->assertJsonStructure(['error' => ['message', 'details' => [$field]]]);
        $this->assertDatabaseCount('merchants', 0);
    }

    public static function invalidInputs(): array
    {
        return [
            'missing name' => [['currency' => 'EUR'], 'name'],
            'blank name' => [['name' => '   ', 'currency' => 'EUR'], 'name'],
            'name type' => [['name' => ['Shop'], 'currency' => 'EUR'], 'name'],
            'long name' => [['name' => str_repeat('a', 256), 'currency' => 'EUR'], 'name'],
            'missing currency' => [['name' => 'Shop'], 'currency'],
            'unsupported currency' => [['name' => 'Shop', 'currency' => 'GBP'], 'currency'],
            'lowercase currency' => [['name' => 'Shop', 'currency' => 'eur'], 'currency'],
            'currency type' => [['name' => 'Shop', 'currency' => ['EUR']], 'currency'],
            'client balance' => [['name' => 'Shop', 'currency' => 'EUR', 'balance' => 100], 'balance'],
            'zero balance supplied' => [['name' => 'Shop', 'currency' => 'EUR', 'balance' => 0], 'balance'],
            'null balance supplied' => [['name' => 'Shop', 'currency' => 'EUR', 'balance' => null], 'balance'],
        ];
    }

    public function test_retrieval_exposes_the_current_integer_balance(): void
    {
        $merchant = Merchant::create(['name' => 'Shop', 'currency' => 'EUR', 'balance' => Merchant::MAX_BALANCE]);
        $this->getJson('/api/merchants/'.$merchant->id)
            ->assertOk()
            ->assertJsonPath('data.balance', Merchant::MAX_BALANCE);
    }

    public function test_missing_and_invalid_identifiers_return_json_not_found(): void
    {
        foreach (['999999', 'not-an-id', '-1'] as $id) {
            $this->get('/api/merchants/'.$id)
                ->assertNotFound()
                ->assertJsonPath('error.code', 'NOT_FOUND');
        }
    }
}
