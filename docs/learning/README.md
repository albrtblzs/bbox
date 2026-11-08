# Understand the payment ledger, step by step

This guide assumes you can read basic application code but are new to PHP and Laravel.
Read one lesson, open its linked source files, and explain the example back in your own words before continuing.
The examples describe this repository's implementation, rather than every possible Laravel application.

## The central idea

We organized the solution around three questions:

1. Is this request valid and approved?
2. Has this operation already happened?
3. Can we change the balance and save its history together, safely?

Laravel handles the API and ledger state. Fastify answers the approval question. PostgreSQL protects the stored data.
The assignment required that technology split; the main design work was deciding how those pieces cooperate.

## Reading order

| Lesson | What you should understand afterwards |
| --- | --- |
| [1. Why this plan](01-why-this-plan.md) | Which decisions came from the PDF, and why the tickets had that order |
| [2. Read the PHP](02-php-you-need.md) | The PHP syntax used in the services and controllers |
| [3. Follow a Laravel request](03-follow-a-request.md) | Routes, validation, controllers, models, resources, and dependency injection |
| [4. Understand the database](04-models-money-and-migrations.md) | Eloquent, migrations, relationships, money units, and constraints |
| [5. Follow a payment](05-follow-a-payment.md) | The actual transaction posting sequence, including failure paths |
| [6. Understand retries and races](06-idempotency-and-concurrency.md) | Why we check keys twice, lock rows, and store the resulting balance |
| [7. Understand the validator](07-validator-and-errors.md) | Why a second service exists, how its contract works, and how errors are handled |
| [8. Run and explain it](08-tests-docker-and-exercises.md) | Docker, tests, practical experiments, and interview explanations |

The [root README](../../README.md) is the operational reference: startup commands, API contracts, and limitations.
This guide explains the reasoning behind it.

## A small glossary

| Term | Meaning here |
| --- | --- |
| Merchant | A shop/account whose balance we track |
| Payment | An operation that adds money to the merchant balance |
| Refund | An operation that subtracts available money |
| Ledger entry | One successfully recorded payment or refund |
| Database transaction | A group of database changes committed together or rolled back together |
| Idempotency key | A client-provided identifier used to recognize a retry |
| ORM | A library mapping application objects to database tables; Laravel's ORM is Eloquent |
| Middleware | Request processing that runs around the controller, such as trimming input |

The word **transaction** has two meanings in this project. A payment transaction is a business record.
A database transaction is the mechanism used to safely write that record and update the balance.
