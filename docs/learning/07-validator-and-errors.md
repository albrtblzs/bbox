# 7. The TypeScript validator and error handling

## Three TypeScript files, three small responsibilities

| File | Responsibility |
| --- | --- |
| [server.ts](../../validator/src/server.ts) | Read configuration, build the app, listen on port 3000, close on shutdown |
| [app.ts](../../validator/src/app.ts) | Register `/health` and `/validate` HTTP endpoints |
| [rules.ts](../../validator/src/rules.ts) | Check values and return approval/rejection decisions |

Separating app construction from listening lets tests inject HTTP requests without reserving a real network port.
Separating the rules lets most cases run as ordinary function tests.
This service deliberately has no database and does not change the merchant balance.

## Why `unknown` matters

TypeScript types are checked during development; they do not validate arbitrary JSON arriving over a network.
`check(input: unknown, limits: Limits)` forces the code to establish what it received.
It checks that the input is an object, the type is payment/refund, the amount is a positive safe integer,
and the currency is supported before applying the configured limit.

The `as Record<string, unknown>` assertion helps TypeScript access properties after an object check.
It is not itself proof that those properties have valid values. The following runtime checks supply that proof.
Likewise, `as Currency` is a compile-time assertion; the `currencies.includes(...)` check actually verifies membership.

By default, each currency accepts at most 1,000,000 minor units. Configuration is validated at startup,
so a typo such as a negative or fractional limit stops the service rather than silently using a surprising rule.

## Approval is a small contract

```json
{"approved":true,"reason":"APPROVED"}
```

```json
{"approved":false,"reason":"AMOUNT_LIMIT_EXCEEDED"}
```

A well-formed business rejection still uses HTTP 200: the validator successfully evaluated the request.
Laravel turns that decision into a public API error, `422 VALIDATOR_REJECTED`.
An HTTP failure such as 500 instead means Laravel did not obtain a trustworthy decision.

## Why Laravel checks the validator's response

The network boundary can return HTML, missing fields, a string instead of a boolean, an unexpected reason,
or contradictory fields such as `approved: false` with reason `APPROVED`.
Laravel verifies the response status and decision structure before accepting it.
Trusting only the presence of a JSON response would be unsafe.

## The failure policy

[TransactionValidator.php](../../backend/app/Services/TransactionValidator.php) uses a two-second request timeout,
a two-second connection timeout, no redirects, and no automatic retries.

- A valid rejection returns public HTTP 422.
- A connection failure, timeout, non-success status, or malformed decision returns public HTTP 503.
- Neither path changes the ledger or reserves the key.
- A previously successful request can replay without contacting the validator.

The timeout bounds how long a new request waits on validation. A caller may retry the same key after a 503.
We avoided hidden automatic retry loops to keep latency and behavior easy to understand.
Production retry/backoff and circuit-breaker policies should be designed around the actual service expectations.

The log contains a generated correlation ID, outcome and elapsed milliseconds, rather than the complete financial request.
The same ID is sent in the validator HTTP header. This is basic diagnostic context, not a full distributed tracing system.

## How PHP exceptions become API errors

[ApiException.php](../../backend/app/Exceptions/ApiException.php) carries a stable error code, human-readable message and HTTP status.
The renderer in [bootstrap/app.php](../../backend/bootstrap/app.php) turns it into an `error` JSON object.
Other renderers handle FormRequest validation errors and missing routes/models.
This gives clients machine-readable codes without exposing database exceptions or internal stack traces through those handled errors.

**Exercise:** Why is a validator rejection different from a validator timeout, and why is a completed replay allowed during either outage?

[Next: tests, Docker, and exercises](08-tests-docker-and-exercises.md)
