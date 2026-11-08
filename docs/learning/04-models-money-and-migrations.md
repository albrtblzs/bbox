# 4. Models, money, and migrations

## A model and a migration do different jobs

[Merchant.php](../../backend/app/Models/Merchant.php) is the PHP object used to work with merchant rows.
[The ledger migration](../../backend/database/migrations/2026_11_08_000001_create_ledger_tables.php) defines the database tables and constraints.

A migration is a versioned database change. `up()` applies the change; `down()` reverses it.
Laravel records completed migrations so `php artisan migrate` does not recreate the tables on every startup.
Changing the model does not automatically change the database schema. For a deployed schema change, normally add a new migration.

Eloquent is an ORM using an Active Record style: model objects can query, insert, and update their corresponding rows.
For example, `$merchant->update(['balance' => 100])` saves a change to that merchant's row.

## The relationship

```text
merchants
  id = 1, name = Example Shop, currency = EUR, balance = 7500

transactions
  merchant_id = 1, type = payment, amount = 10000, balance_after = 10000
  merchant_id = 1, type = refund,  amount = 2500,  balance_after = 7500
```

One merchant has many transactions; each transaction belongs to one merchant.
Those relationships are declared by `hasMany()` in Merchant and `belongsTo()` in [Transaction.php](../../backend/app/Models/Transaction.php).

`$merchant->transactions()` returns a query/relationship builder, so we can add filters or call `create()`.
`$merchant->transactions` accesses the related collection, loading it if necessary.
The posting service uses the builder to query a specific key without loading the entire history.

## Why money is an integer

For EUR, `10000` means EUR 100.00 and `2500` means EUR 25.00.
The balance after the example above is exactly `10000 - 2500 = 7500`.
Binary floating-point numbers cannot represent every decimal fraction exactly. Integer arithmetic avoids that rounding issue here.

We use hundredths for EUR, USD, and HUF as a project convention. This is not a claim that every real currency or payment provider uses the same minor-unit rules.
A production integration should obtain those rules from its business/provider requirements.

Transaction amounts are always positive. The type decides the direction: payment adds, refund subtracts.
This prevents ambiguous combinations such as a negative refund.

The storage bound for an amount is 2,147,483,647. The validator's default business limit is lower: 1,000,000.
The balance limit is JavaScript's maximum safe integer, 9,007,199,254,740,991, so a JSON client can still represent it exactly.
The Docker PHP runtime is 64-bit and can represent these integer bounds.

## Why validate both in PHP and in PostgreSQL?

Request validation provides clear errors to callers. Database constraints protect data regardless of which application code writes it.

| Constraint | Mistake it prevents |
| --- | --- |
| Nonnegative bounded balance | Overdraft or unsafe stored value |
| Positive amount | Zero/negative entries |
| Supported currency and type | Unrecognized stored values |
| Unique merchant/key pair | Two successful entries claiming the same operation |
| Foreign key on merchant ID and currency | Entry for a nonexistent merchant or a different currency |

The unique `(id, currency)` pair on merchants supports that composite foreign key.
The history index supports queries scoped to a merchant and ordered by time and ID.
Referenced merchants cannot be deleted while their entries exist.

Constraints do not prove that the balance equals the sum of its history. The service maintains that relationship atomically;
periodic reconciliation would provide an additional check in a production system.
This is a simplified merchant ledger, not a full double-entry accounting system.

## Important Eloquent details

- `$fillable` lists fields allowed during mass assignment such as `create([...])`. It is not input validation.
- Merchant allows balance assignment because the internal service must update it. The public creation controller separately restricts input to name and currency.
- `casts()` converts stored money values into PHP integers when reading models.
- The model's updating hook rejects currency changes through Eloquent. This is not a database-level immutable-column policy.
- `balance_after` stores the result of one successful posting. Lesson 6 explains why it is necessary for replay.

**Exercise:** Why can a foreign key prevent a missing merchant, but not prevent two concurrent refunds from both reading the same old balance?

[Next: follow a payment](05-follow-a-payment.md)
