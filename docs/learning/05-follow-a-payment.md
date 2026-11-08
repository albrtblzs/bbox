# 5. Follow one payment through the code

Assume a EUR merchant has balance `0` and the client sends:

```http
POST /api/merchants/1/transactions
Content-Type: application/json
Idempotency-Key: order-123

{"type":"payment","amount":10000,"currency":"EUR"}
```

Keep [TransactionController.php](../../backend/app/Http/Controllers/TransactionController.php),
[StoreTransactionRequest.php](../../backend/app/Http/Requests/StoreTransactionRequest.php), and
[TransactionService.php](../../backend/app/Services/TransactionService.php) open.

## Step 1: find the merchant and check the request

Laravel resolves the merchant from the route. The FormRequest copies the actual header into its validation input;
a JSON body field cannot substitute for the required header.

The rules accept `payment` or `refund`, a supported currency, a positive bounded JSON integer, and a suitably formatted key.
`10000` is accepted; `"10000"`, `100.5`, and `true` are rejected as money values.
The controller passes only the validated type, amount, and currency plus the validated key to the service.

## Step 2: check for a completed request

The service searches this merchant's transactions for `order-123`.
If an entry exists with identical type, amount, and currency, it returns that entry immediately.
If valid input differs, it throws an idempotency conflict. No validator call is needed for either case.

Request validation still happens before this lookup. A malformed retry can therefore return 422 rather than replaying or conflicting.
For a new key, the service also checks that the currency matches the merchant's currency.

## Step 3: ask the validator

[TransactionValidator.php](../../backend/app/Services/TransactionValidator.php) sends the transaction fields to Fastify.
It requires a successful HTTP response with a well-formed approval/reason pair.
Rejection or an infrastructure failure throws an API exception and stops posting.

No balance update or ledger insert has happened yet. No new database transaction is held while waiting for this network call.

## Step 4: begin a database transaction and lock the merchant

```php
return DB::transaction(function () use ($merchant, $input, $key): Transaction {
    $locked = Merchant::query()->lockForUpdate()->findOrFail($merchant->id);
    // ...
});
```

The service re-reads the row under a write lock. The earlier model may have an old balance if another request completed during validation.
Other transactions trying to update this merchant's row must wait while this lock is held.

The service checks the key again. Another request might have posted the same operation while this one was validating or waiting for the lock.

## Step 5: check the balance rules

For a refund, the requested amount cannot exceed the locked balance.
For a payment, the resulting balance cannot exceed the safe balance limit.
The payment check compares the existing balance with `MAX_BALANCE - amount` before addition.

For our payment, the new balance is `0 + 10000 = 10000`.
For a later refund of `2500`, it would be `10000 - 2500 = 7500`.

## Step 6: save both changes together

The service creates the transaction with its key and `balance_after`, then updates the merchant balance.
Both operations belong to the same database transaction.
If either fails, the exception causes rollback of both. If both succeed, commit makes them visible together.
The row lock is released when that database transaction ends.

There is a test that deliberately makes the merchant update fail after the entry insertion.
It verifies that neither a partial entry nor an incorrect balance remains.

## Step 7: return the transaction resource

[TransactionResource.php](../../backend/app/Http/Resources/TransactionResource.php) selects the public fields and the controller returns HTTP 201.
The resource includes `balance_after: 10000`; it does not include the internal idempotency key.
If the client loses this response, it can retry using `order-123` and get the original result.

**Exercise:** At which step is money actually changed? What remains stored if the validator rejects? What remains stored if the final database update fails?

[Next: retries and concurrency](06-idempotency-and-concurrency.md)
