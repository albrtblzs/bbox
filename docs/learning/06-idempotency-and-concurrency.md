# 6. Why retries and concurrency need separate protections

## Idempotency: the same logical operation arrives again

Imagine the server commits a payment, but the response is lost.
The client cannot tell whether the payment happened, so it retries.
Without an operation identifier, a second identical-looking payment could also be a legitimate new order.

The client supplies an `Idempotency-Key` to distinguish those cases:

- Same merchant, same key, same validated fields: replay the successful operation.
- Same merchant, same key, different valid fields: HTTP 409 conflict.
- Same merchant, different key: a new operation, even if the money fields look identical.
- Different merchant, same key: allowed; keys are scoped per merchant.

The client should generate one key per logical operation and retain it across retries.
Generating a new key on every retry defeats this protection.

## Why `balance_after` is stored

Suppose payment A adds `10000`, and refund B later removes `2500`.
The current merchant balance is `7500`.

Replaying A must return A's original response, including its original result balance `10000`.
Reading today's merchant balance would change the response to an already completed operation.
The transaction's stored snapshot lets us reproduce the public JSON without re-running the operation.

This implementation reserves keys only for successes. A rejected or unavailable attempt can be tried again with that key.
That is a deliberate policy; a different API could persist every attempt and reserve keys before processing.

## Concurrency: different requests overlap in time

Assume balance `100`. Two requests each want to refund `100`:

| Moment | Request A | Request B |
| --- | --- | --- |
| Initial read | Sees 100 | Sees 100 |
| Check without locking | Thinks refund is allowed | Thinks refund is allowed |
| Risk | Both entries may be recorded even though funds cover only one | Stored balance may also lose an update |

A database transaction alone does not make this read/check/write sequence safe at PostgreSQL's ordinary isolation level.
The merchant row lock makes the second posting wait. Once A commits, B re-reads balance `0` and rejects its refund.

Different merchants use different rows, so they do not all share one global application lock.
A very busy single merchant is serialized; that is a reasonable correctness-first tradeoff for this exercise.

## Why the idempotency lookup happens twice

The first lookup is fast and avoids unnecessary validator calls for normal retries.
However, two new requests can both see that no entry exists, then both call the validator.

The second lookup occurs after acquiring the merchant lock. Only one posting for that merchant can reach its write section at a time.
After the first request commits, the second finds its entry and either replays it or conflicts.
The database unique constraint is an additional protection against duplicate entries.

Two validator calls can therefore happen for one successful operation during a race.
Our validator is stateless: it approves/rejects but does not debit a card or move money.
If validation itself had financial side effects, this design would need additional idempotency or workflow coordination at that boundary.

## Why not keep the lock during HTTP validation?

A slow or unavailable validator could hold up every payment for that merchant for seconds.
Doing the network call first keeps the critical locked section short.
The service then rechecks the key and balance using fresh locked data before writing.

## What this guarantees, and what it does not

The normal posting path applies an operation at most once per merchant/key and prevents overdrawing the balance.
It cannot make the network deliver a response exactly once. The client still needs sensible retries with the same key.
It does not enforce double-entry accounting, original-payment refund limits, or protect against an administrator manually editing database rows.
Those are different requirements.

See [the concurrency tests](../../backend/tests/Concurrency/LedgerConcurrencyTest.php): two separate PHP processes synchronize after their initial lookup and reach the same race window.

**Exercise:** Explain why a unique idempotency key cannot prevent two refunds with different keys from overdrawing the same account.

[Next: validator and errors](07-validator-and-errors.md)
