# Payment Ledger

Laravel 12 API, TypeScript/Fastify validator, and PostgreSQL assignment.

Work is tracked in the [Payment Ledger project](https://github.com/users/albrtblzs/projects/4).

## Development workflow

- Keep `master` as the integration branch.
- Before starting an issue, create `feature/<issue-number>-<short-name>` and move the issue to In Progress.
- Implement and verify only that issue's scope.
- Open a pull request targeting `master` with `Closes #<issue-number>` and validation evidence.
- Keep issues In Progress until their pull requests are merged. Squash-merge automatically after relevant checks pass, as authorized by the repository owner.

See the repository issues for the implementation plan and acceptance criteria.

## Run locally

Prerequisites: Git and Docker Desktop with Linux containers (Docker Compose v2).

```sh
git clone https://github.com/albrtblzs/bbox.git
cd bbox
docker compose up --build -d --wait
```

The API is available at http://localhost:8000; its health endpoint is `/up`.
The validator's `/health` endpoint and PostgreSQL are available only inside the Compose network.
Merchant creation, retrieval, and transaction posting are available. Transaction history is tracked in issue #6.

Startup generates a container-local Laravel key and runs migrations after PostgreSQL is healthy.
PostgreSQL data persists in a named volume. `docker compose down` stops the services without deleting data.
The PHP development server and example database credentials are intended for this local assignment.

Optionally copy `.env.example` to `.env` to change the exposed API port or validator limit settings.
The limits are positive integer minor-unit amounts, defaulting to 1,000,000 per currency. Invalid settings fail validator startup.

## Checks

Create the dedicated test database once (the test suite resets this database):

```sh
docker compose exec database createdb -U ledger ledger_test
```

```sh
docker compose exec backend php artisan test
docker compose exec validator npm test
docker compose exec validator npm run typecheck
docker compose exec validator npm run build
```

Dependency lockfiles are committed. Dependencies are installed inside images; do not commit `vendor`, `node_modules`, `.env`, logs, or generated build files.

## Ledger storage

Merchants have one immutable EUR/USD/HUF currency and an integer balance in hundredths of that currency.
Balances range from 0 to 9,007,199,254,740,991 (the largest exact JavaScript integer).
Transaction amounts range from 1 to 2,147,483,647; the validator will impose a lower configurable business limit.
Transactions contain a `balance_after` snapshot for replaying the original successful response.
PostgreSQL enforces amounts, balance bounds, transaction types, matching merchant currency, and merchant-scoped idempotency-key uniqueness.
Merchant deletion is restricted while ledger entries reference it.

## Merchant API

Create a merchant with a name (1–255 characters after trimming) and uppercase EUR, USD, or HUF currency.
The initial balance is always zero; clients cannot supply a starting balance.

```sh
curl -X POST http://localhost:8000/api/merchants \
  -H 'Content-Type: application/json' \
  -d '{"name":"Example Shop","currency":"EUR"}'
curl http://localhost:8000/api/merchants/1
```

Creation returns HTTP 201 and retrieval HTTP 200. Both use the same response shape:

```json
{"data":{"id":1,"name":"Example Shop","currency":"EUR","balance":0,"created_at":"2026-11-08T00:00:00.000000Z","updated_at":"2026-11-08T00:00:00.000000Z"}}
```

API errors are JSON even without an Accept header. Invalid input returns HTTP 422 with
`error.code: INVALID_INPUT`, `error.message`, and field errors in `error.details`.
Missing merchants return HTTP 404 with `error.code: NOT_FOUND` and `error.message`.

## Post a transaction

```sh
curl -X POST http://localhost:8000/api/merchants/1/transactions \
  -H 'Content-Type: application/json' -H 'Idempotency-Key: payment-001' \
  -d '{"type":"payment","amount":10000,"currency":"EUR"}'
```

Use `refund` to debit the available balance. Amount must be a positive JSON integer; strings and fractions are rejected.
The required header accepts 1–128 letters, digits, `.`, `_`, `:`, or `-`.
HTTP 201 returns `data` with the transaction ID, merchant ID, type, amount, currency, `balance_after`, and UTC timestamps.
An identical key/input replays that original body and 201 status even if the validator is down.
A changed valid input for an existing key returns 409 `IDEMPOTENCY_CONFLICT`.
Only successful transactions reserve keys. After a 503, retry with the same key.

The validator is called outside the database transaction with a two-second timeout, no redirects, and no automatic retries.
An approved request locks the merchant row, rechecks the key, and inserts the transaction and updates the balance in one transaction.
Refunds with insufficient balance return 422 `INSUFFICIENT_FUNDS`; unsafe balance growth returns 422 `BALANCE_LIMIT_EXCEEDED`.
Validator rejection returns 422 `VALIDATOR_REJECTED`; outages, non-success responses and invalid decision bodies return 503 `VALIDATOR_UNAVAILABLE`.
Validation logs contain a generated correlation ID, latency and outcome, without request bodies.
