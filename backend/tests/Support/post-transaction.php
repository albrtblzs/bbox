<?php

// Separate process: no shared Eloquent connection or enclosing test transaction.
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
$app['config']->set('database.default', 'pgsql');
$app['config']->set('database.connections.pgsql.url', null);
$app['config']->set('database.connections.pgsql.database', 'ledger_test');
[$script, $merchantId, $payload, $key, $barrier, $worker] = $argv;
Http::preventStrayRequests();
Http::fake(function () use ($barrier, $worker) {
    file_put_contents($barrier.'/'.$worker, 'ready');
    $deadline = microtime(true) + 8;
    while (count(glob($barrier.'/*')) < 2) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Concurrency barrier timed out.');
        }
        usleep(10000);
    }

    return Http::response(['approved' => true, 'reason' => 'APPROVED']);
});
$request = Request::create('/api/merchants/'.$merchantId.'/transactions', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => $key,
], $payload);
$kernel = $app->make(HttpKernel::class);
$response = $kernel->handle($request);
echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true)], JSON_THROW_ON_ERROR);
$kernel->terminate($request, $response);
