# 3. Follow a request through Laravel

Start with the simplest operation: creating a merchant.

```text
POST /api/merchants
{"name":"Example Shop","currency":"EUR"}
```

## Step 1: the application receives the HTTP request

[public/index.php](../../backend/public/index.php) is Laravel's HTTP entry point.
[bootstrap/app.php](../../backend/bootstrap/app.php) configures routes, middleware, and exception rendering.
Our Docker container starts `php artisan serve`, Laravel's development server.
`artisan` is Laravel's command-line entry point, also used for migrations and tests.

Laravel supplies common request processing. For example, ordinary string input is trimmed before the FormRequest validates it.
This is why the merchant test can send `"  Example Shop  "` and receive `"Example Shop"`.

## Step 2: a route selects the controller method

Open [routes/api.php](../../backend/routes/api.php):

```php
Route::post('/merchants', [MerchantController::class, 'store']);
```

The route matches an HTTP method and a URL path to a PHP class method.
Laravel adds the `/api` prefix because this file is registered as the application's API routes.
`MerchantController::class` supplies the class name; `'store'` selects its method.

[routes/web.php](../../backend/routes/web.php) contains the root service identity response.
[routes/console.php](../../backend/routes/console.php) contains command-line route definitions, not payment HTTP endpoints.

## Step 3: a FormRequest validates the input

[StoreMerchantRequest.php](../../backend/app/Http/Requests/StoreMerchantRequest.php) defines rules:

- Name is required, must be a string, and has a maximum length.
- Currency must be one of the supported values.
- A balance field must be absent.

A FormRequest is a Laravel request class with validation and authorization hooks.
Laravel validates it before calling the controller method that asks for it.
`authorize()` currently returns `true` because authentication/authorization were excluded from the assignment.
That is an explicit limitation, not a complete access-control system.

Invalid input stops here. The renderer in `bootstrap/app.php` returns JSON 422 with `INVALID_INPUT`.
The controller does not need a repeated validation `if` block.

## Step 4: the controller coordinates the small operation

Open [MerchantController.php](../../backend/app/Http/Controllers/MerchantController.php):

```php
$merchant = Merchant::create($request->safe()->only(['name', 'currency']));
```

`safe()` accesses validated input; `only()` selects the fields this operation may set.
The controller deliberately does not pass the whole request to the model.
Eloquent creates the database row and gives us a Merchant object with its ID and timestamps.
The model/database defaults give the new merchant a zero balance.

## Step 5: a resource shapes the JSON

[MerchantResource.php](../../backend/app/Http/Resources/MerchantResource.php) selects the response fields.
It returns the ID, name, currency, integer balance, and timestamps under Laravel's `data` wrapper.
The controller explicitly sets HTTP 201 because a record was created.

A resource is a serializer. It does not create a database row or decide whether money can move.
Writing response fields explicitly also avoids accidentally exposing new internal fields later.

## Step 6: retrieval uses route model binding

The retrieval route uses `{merchant}`. Its controller method accepts `Merchant $merchant`.
Laravel connects the parameter name to the model, looks up that ID, and supplies the object.
If the merchant does not exist, execution stops with a 404 before `show()` returns a resource.
The route's numeric constraint also rejects nonnumeric identifiers.

## Where do controller dependencies come from?

The transaction controller asks for `TransactionService $service` as a method argument.
Laravel's service container creates that class. Its constructor asks for TransactionValidator, so the container creates that too.
This is **dependency injection**: an object receives the collaborators it needs instead of creating them everywhere itself.
Our concrete classes need no custom binding. An interface would generally need a binding telling Laravel which implementation to use.

`DB`, `Http`, and `Log` are Laravel facades: static-looking entry points to framework services resolved through the container.
That is also why tests can replace the HTTP client with a fake.

**Exercise:** Explain why input checking belongs in the FormRequest, JSON field selection in the resource, and money rules in the service.

[Next: models, money, and migrations](04-models-money-and-migrations.md)
