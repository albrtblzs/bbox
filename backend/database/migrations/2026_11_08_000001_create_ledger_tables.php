<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('currency', 3);
            $table->bigInteger('balance')->default(0);
            $table->timestampsTz();
            $table->unique(['id', 'currency']);
        });

        DB::statement("ALTER TABLE merchants ADD CONSTRAINT merchants_currency_check CHECK (currency IN ('EUR', 'USD', 'HUF'))");
        DB::statement('ALTER TABLE merchants ADD CONSTRAINT merchants_balance_check CHECK (balance BETWEEN 0 AND 9007199254740991)');

        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_id');
            $table->string('type', 7);
            $table->integer('amount');
            $table->string('currency', 3);
            $table->string('idempotency_key', 128);
            // Snapshot the response balance so later retries do not return today's balance.
            $table->bigInteger('balance_after');
            $table->timestampsTz();
            $table->unique(['merchant_id', 'idempotency_key']);
            $table->foreign(['merchant_id', 'currency'])->references(['id', 'currency'])->on('merchants')->restrictOnDelete();
            $table->index(['merchant_id', 'created_at', 'id']);
        });

        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_check CHECK (type IN ('payment', 'refund'))");
        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_amount_check CHECK (amount BETWEEN 1 AND 2147483647)');
        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_balance_check CHECK (balance_after BETWEEN 0 AND 9007199254740991)');
        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_key_check CHECK (length(trim(idempotency_key)) > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('merchants');
    }
};
