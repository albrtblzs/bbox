<?php

use App\Models\Merchant;
use App\Models\Transaction;
use Symfony\Component\Process\Process;

function runLedgerRace(Merchant $merchant, array $requests): array
{
    $barrier = sys_get_temp_dir().'/ledger-race-'.bin2hex(random_bytes(8));
    mkdir($barrier);
    $processes = [];
    try {
        foreach ($requests as $index => [$key, $type, $amount]) {
            $process = new Process([
                PHP_BINARY, base_path('tests/Support/post-transaction.php'), (string) $merchant->id,
                json_encode(['type' => $type, 'amount' => $amount, 'currency' => 'EUR'], JSON_THROW_ON_ERROR),
                $key, $barrier, (string) $index,
            ], base_path(), timeout: 15);
            $process->start();
            $processes[] = $process;
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            if (! $process->isSuccessful()) {
                throw new RuntimeException($process->getErrorOutput().$process->getOutput());
            }
            $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    } finally {
        foreach ($processes as $process) {
            $process->stop();
        }
        foreach (glob($barrier.'/*') as $file) {
            unlink($file);
        }
        rmdir($barrier);
    }
}

it('applies concurrent identical requests once and returns the same response', function () {
    $merchant = Merchant::create(['name' => 'Shop', 'currency' => 'EUR']);
    $results = runLedgerRace($merchant, [['same', 'payment', 100], ['same', 'payment', 100]]);
    expect(array_column($results, 'status'))->toBe([201, 201]);
    expect($results[0]['body'])->toBe($results[1]['body']);
    expect($merchant->refresh()->balance)->toBe(100);
    expect(Transaction::count())->toBe(1);
});

it('prevents competing refunds from overdrawing a merchant', function () {
    $merchant = Merchant::create(['name' => 'Shop', 'currency' => 'EUR', 'balance' => 100]);
    $results = runLedgerRace($merchant, [['first', 'refund', 100], ['second', 'refund', 100]]);
    $statuses = array_column($results, 'status');
    sort($statuses);
    expect($statuses)->toBe([201, 422]);
    $failed = array_values(array_filter($results, fn ($result) => $result['status'] === 422));
    expect($failed[0]['body']['error']['code'])->toBe('INSUFFICIENT_FUNDS');
    expect($merchant->refresh()->balance)->toBe(0);
    expect(Transaction::count())->toBe(1);
});

it('conflicts concurrent changed payloads sharing a key without double posting', function () {
    $merchant = Merchant::create(['name' => 'Shop', 'currency' => 'EUR']);
    $results = runLedgerRace($merchant, [['same', 'payment', 100], ['same', 'payment', 101]]);
    $statuses = array_column($results, 'status');
    sort($statuses);
    expect($statuses)->toBe([201, 409]);
    expect(Transaction::count())->toBe(1);
    expect($merchant->refresh()->balance)->toBe(Transaction::firstOrFail()->amount);
});
