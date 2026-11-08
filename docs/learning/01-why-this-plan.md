# 1. Why the solution was planned this way

## What the PDF decided for us

The assignment explicitly required Laravel 12 or 13, PostgreSQL, a Node.js/TypeScript validator,
REST communication between the services, and one Docker Compose setup.
It required payments, refunds, merchant balances, transaction listing, idempotency, and sensible handling of validator outages.

Therefore, Laravel and the separate validator were not architecture choices made just to add complexity.
If this were an unconstrained small product, I would consider implementing validation inside the main application first.
A separate microservice adds an HTTP contract, startup dependencies, failure modes, and another deployment unit.
Here, demonstrating that boundary was part of the exercise.

The PDF also emphasized a small, understandable solution within roughly 3–4 hours of implementation effort.
That shaped the scope: no frontend, user accounts, deployment platform, queue, or real payment-provider integration.

## What we chose

| Choice | Reason | Tradeoff |
| --- | --- | --- |
| Laravel 12 | Meets the requirement and provides routing, validation, ORM, and HTTP client support | You need to learn Laravel conventions |
| Fastify | Suggested in the PDF; supports a small typed HTTP service | Still requires runtime checks on incoming JSON |
| One currency per merchant | Prevents mixing incompatible balances | A merchant cannot hold several currency balances |
| Positive integer money amounts | Gives exact arithmetic with clear payment/refund direction | Clients must convert display amounts into minor units |
| Synchronous validation | Gives the caller one immediate success or failure | Validator availability affects new postings |
| Only successful entries stored | Keeps the ledger and replay logic small | Rejected attempts need a separate audit system in a production design |
| Refunds against available balance | Directly satisfies the stated balance rule | Does not restrict refunds per original payment |
| Stored balance plus history | Makes balance reads cheap and history explainable | Both must be updated atomically; reconciliation would be useful later |

Some decisions were confirmed with you during planning, including one currency per merchant and refunds against available balance.
Other details, such as exact integer bounds and the `balance_after` snapshot, were implementation choices supporting those decisions.

## Why the tickets had that order

1. **Bootstrap:** prove the required programs can start together before adding business logic.
2. **Schema:** decide what valid stored data means before building endpoints that write it.
3. **Merchants:** complete the smallest request flow and create the account payments need.
4. **Validator:** establish the second service's decision contract independently.
5. **Posting:** combine merchants, storage, and validation; this contains the hardest correctness rules.
6. **History:** expose already recorded data using consistent serialization and pagination.
7. **Verification and CI:** add real concurrent-process tests and repeatable hosted checks. Relevant tests were also added with earlier tickets.
8. **Submission documentation:** verify the complete user journey and explain the tradeoffs.

This order follows dependencies. A payment needs an existing merchant, database tables, and a validator contract.
A history endpoint needs stored entries. Separating those concerns also kept each PR reviewable.

## Why several classes instead of one large controller

There are different responsibilities:

- Check the HTTP input.
- Decide whether money can move.
- Call another service.
- Read and write database records.
- Decide what JSON to return.

Separating these makes it easier to locate a mistake and explain the code. It also lets tests replace the HTTP validator without replacing the money logic.
This does not mean every endpoint needs a service class: merchant creation is simple enough to stay in its controller.
`app/Services` is our organization choice, not a Laravel requirement.

**Explain it yourself:** Which of these decisions would you revisit if the PDF did not require two services?

[Next: read the PHP](02-php-you-need.md)
