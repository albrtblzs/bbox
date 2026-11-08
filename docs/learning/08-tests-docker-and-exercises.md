# 8. Run it, test it, and explain it yourself

## Understand the containers first

[docker-compose.yml](../../docker-compose.yml) starts three programs:

- `backend`: PHP running the Laravel development HTTP server, exposed on host port 8000.
- `validator`: Node.js running compiled TypeScript, accessible inside the Compose network.
- `database`: PostgreSQL, also internal to that network.

Inside the backend container, `database` and `validator` are network hostnames supplied by Compose.
`localhost` inside that container would mean the backend container itself, not your Windows machine or PostgreSQL.

Composer installs PHP dependencies from `composer.lock`. npm installs validator dependencies from `package-lock.json`.
These lockfiles make dependency resolution repeatable. Generated `vendor` and `node_modules` folders stay out of Git.
The backend's starter `package.json` is not needed to run this API: there is no frontend feature to build.

The backend entrypoint creates its local environment/key if necessary and runs pending migrations.
Database health is checked before backend startup. A named volume keeps PostgreSQL data across ordinary container restarts.
The root README explains startup and shutdown; use it as the command reference.

## What each kind of test proves

| Tests | What they exercise |
| --- | --- |
| [LedgerSchemaTest](../../backend/tests/Feature/LedgerSchemaTest.php) | Real PostgreSQL constraints, model relationships and money bounds |
| [MerchantApiTest](../../backend/tests/Feature/MerchantApiTest.php) | Routing, validation, merchant creation/retrieval and JSON responses |
| [TransactionApiTest](../../backend/tests/Feature/TransactionApiTest.php) | Posting behavior, HTTP validator fakes, replay, failures and rollback |
| [TransactionHistoryTest](../../backend/tests/Feature/TransactionHistoryTest.php) | Merchant isolation, stable ordering and pagination |
| [LedgerConcurrencyTest](../../backend/tests/Concurrency/LedgerConcurrencyTest.php) | Two independent HTTP kernels/database connections racing to post |
| [TypeScript rules tests](../../validator/test/rules.test.ts) | Runtime rules, configuration and validator HTTP decisions |
| [Smoke script](../../scripts/smoke.mjs) | Actual HTTP communication between running containers, including outage/recovery |

An HTTP fake returns controlled validator responses so a backend test can simulate malformed output or failure reliably.
It does not prove real networking works; the smoke check provides that complementary evidence.
Likewise, sequential retries do not prove concurrency safety; the separate-process tests cover that gap.

Pest runs the backend test suite and supports the newer function-style concurrency tests.
The earlier tests use PHPUnit class-style syntax, which the runner also supports.
We used PostgreSQL rather than SQLite because the relevant locks, constraints and failure behavior must match the actual database.

All database-backed tests explicitly use `ledger_test`. Run them sequentially; they reset that database.
Do not point the test suite at valuable data. The dedicated database is a practical boundary between demonstrations and resettable test fixtures.

## A practical learning session

Start the services, then create the test database once and run the suite:

```sh
docker compose up --build -d --wait
docker compose exec database createdb -U ledger ledger_test
docker compose exec backend php artisan test
docker compose exec validator npm test
```

If `ledger_test` already exists, skip the `createdb` command.

In PowerShell, create a merchant and keep its generated ID:

```powershell
$merchant = Invoke-RestMethod -Method Post -Uri http://localhost:8000/api/merchants `
  -ContentType application/json -Body '{"name":"Learning Shop","currency":"EUR"}'
$merchantId = $merchant.data.id
$url = "http://localhost:8000/api/merchants/$merchantId/transactions"
$headers = @{ 'Idempotency-Key' = 'learning-payment-1' }
$body = '{"type":"payment","amount":10000,"currency":"EUR"}'
Invoke-RestMethod -Method Post -Uri $url -Headers $headers -ContentType application/json -Body $body
```

Now work through these experiments:

1. Run the last request twice. Expect the same transaction ID and balance snapshot; the merchant balance increases once.
2. Use the same header but change the amount. Expect HTTP 409. PowerShell reports non-success statuses as errors; inspect the response rather than treating that as a broken server.
3. Use a new key and `type: refund`, amount `2500`. Expect balance `7500`.
4. Repeat the original payment. Its `balance_after` remains `10000`, while GET merchant reports the current `7500`.
5. Use a new key to refund more than `7500`. Expect 422 with no new ledger entry.
6. Send an amount as a quoted string. Expect 422 before the validator is called.
7. Follow the root README's smoke script outage/recovery instructions. Restore the validator afterwards.

Run the race tests on their own when studying locks:

```sh
docker compose exec backend php artisan test --testsuite=Concurrency
```

## Interview questions to practice

Try answering before looking at the suggested explanation:

- **Why integer money?** Exact arithmetic with a defined currency/minor-unit convention, explicit bounds and strict JSON validation.
- **Why two idempotency lookups?** The early one avoids redundant work; the locked one catches another request that completed during validation.
- **Why a row lock as well as a database transaction?** Atomic commit prevents partial writes; the lock protects the balance read/check/write sequence from concurrent requests.
- **Why return 503?** A new transaction cannot safely proceed without a trustworthy approval; same-key retries remain possible.
- **Why no distributed transaction?** The validator is stateless and moves no money. All authoritative financial writes occur in one PostgreSQL transaction.
- **What would you improve?** Authentication/merchant authorization, production hosting, audit trails, reconciliation, monitoring, and business-specific refund rules. Explain which concrete requirement would justify each addition.

If a detail is unclear, follow one real request through its files and tests rather than memorizing framework terminology.
Understanding these few mechanisms is more useful than learning every Laravel feature for this assignment.

[Back to the guide index](README.md)
