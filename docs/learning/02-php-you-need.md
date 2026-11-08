# 2. The PHP you need to read this project

Use [TransactionService.php](../../backend/app/Services/TransactionService.php) as your reading example.
PHP is the language; Laravel is the framework built with that language.

## Variables, arrays, and object access

```php
$balance = 100;
$input = ['type' => 'payment', 'amount' => 25];
$amount = $input['amount'];
$merchant->balance;
$merchant->transactions();
```

- Variable names start with `$`.
- PHP arrays can act as lists or key/value maps. `=>` connects a key to its value.
- `->` reads a property or calls an instance method. Parentheses distinguish the method call.
- Statements normally end in `;`.
- `.` joins strings, so `'merchant-'.$id` constructs a string.

`Merchant::MAX_BALANCE` accesses a class constant. `Merchant::create(...)` looks like a static method call;
Eloquent provides this creation/query API through its model infrastructure.

## Classes, imports, and visibility

```php
namespace App\Services;
use App\Models\Merchant;

class TransactionService
{
    public function post(Merchant $merchant, array $input, string $key): Transaction
    {
        // ...
    }
}
```

A namespace organizes class names. `use App\Models\Merchant` lets the file write `Merchant` instead of the full name.
Composer's autoloader finds classes according to the mappings in [composer.json](../../backend/composer.json).

`public` allows callers outside the class. `private` limits use to this class. `protected` allows access within the inheritance hierarchy.
`Merchant`, `array`, and `string` describe parameter types; `: Transaction` describes the return type.
`: void` means the method returns no value. `?Transaction` means either a Transaction or `null`.

These files do not enable PHP's `strict_types` mode. Type declarations alone are not a substitute for checking untrusted JSON.
That is why our request validator explicitly uses `is_int()` for money.

## Constructor injection and `readonly`

```php
public function __construct(private readonly TransactionValidator $validator) {}
```

This declares a constructor parameter and a property in one line. The constructor stores the supplied validator on the object.
`readonly` prevents reassigning that property after initialization; it does not make every object it references immutable.
Laravel can construct the validator and supply it automatically. You will see that mechanism in lesson 3.

## Closures: a function passed into another function

```php
return DB::transaction(function () use ($merchant, $input, $key): Transaction {
    // Database work goes here.
});
```

`function () { ... }` creates an unnamed function, also called a closure.
`use (...)` captures variables from the surrounding scope so the closure can use them.
This `use` is different from the import statement at the top of the file.

Laravel calls the closure inside a database transaction. A normal return commits its work; an exception rolls it back.
`fn ($value) => ...` is a shorter arrow-function syntax used for small expressions.

## Operators that matter in the ledger

```php
$type === 'refund';                 // Strict equality: no automatic type conversion.
$reason = $decision['reason'] ?? null; // Use null when the value is absent or null.
$change = $isPayment ? $amount : -$amount; // Conditional expression.
$record = [...$input, 'balance_after' => $balance]; // Spread existing array entries.
```

`if ($existing = $this->existing(...))` first assigns a lookup result, then tests whether it is truthy.
It uses assignment `=`, not comparison. Here the result is either an existing model object or `null`.

`throw new ApiException(...)` stops the normal path. Laravel later translates that exception into an HTTP error response.
`try/catch/finally` handles failures; the validator's `finally` logs its outcome even if a request fails.

**Exercise:** In `TransactionService::post`, identify one typed parameter, one closure capture, one strict comparison, and one exception.

[Next: follow a Laravel request](03-follow-a-request.md)
