# Validator structure

The validator separates process startup, application construction, HTTP routes, and business rules.

```text
server.ts -> buildApp(limits) -> application plugin
    -> createRoutes(limits)
        -> routes/health.ts
        -> routes/validate.ts -> check(body, limits)
```

- `server.ts` reads environment configuration, calls the application factory, opens port 3000, and handles shutdown.
- `app.ts` exports `buildApp`, the application factory, and `application`, a typed Fastify application plugin. Each factory call creates a fresh Fastify instance and supplies configuration to the application plugin without opening a port.
- `routes.ts` exports `createRoutes`, a route factory returning a Fastify plugin. It captures a copy of the supplied limits and registers the endpoint plugins.
- `routes/health.ts` registers `/health`; `routes/validate.ts` registers `/validate` and passes the unknown request body to the rules.
- `rules.ts` contains configuration validation and the ordinary `check` function. It knows nothing about HTTP servers or databases.

A factory is a function that creates an object or function with its dependencies supplied by the caller. Here, limits are passed explicitly rather than read from the environment inside request handlers. Tests can construct independently configured apps and use `app.inject()` without listening on a network port. Fastify loads registered plugins when `inject()`, `ready()`, or `listen()` is called. See the [Fastify plugin guide](https://fastify.dev/docs/latest/Guides/Plugins-Guide/).

The [official Fastify demo](https://github.com/fastify/demo) organizes its application as a plugin with separate route modules. We use the same composition pattern with explicit registration. Its directory autoloading is useful for many route groups; these two endpoints do not need an additional dependency for discovery. Its authentication, database plugins, and input coercion settings serve a different API. Our validator preserves strict integer checks and its existing approval/rejection contract.

## TypeScript imports and JavaScript output

```ts
import { buildApp } from './app.ts';
```

Source imports now name the TypeScript files directly. This project uses `"type": "module"`, `NodeNext` module settings, and `rewriteRelativeImportExtensions: true` in `tsconfig.json`.

```text
Source                    npm run build              Runtime
src/server.ts             TypeScript compiler        dist/server.js
src/app.ts                ------------------>        dist/app.js
src/routes.ts                                        dist/routes.js
src/rules.ts                                         dist/rules.js
```

The compiler rewrites the relative `./app.ts` import to `./app.js` in `dist/server.js`. Node then loads `dist/app.js`. This feature was introduced in TypeScript 5.7; our dependency requires at least 5.9.3 and the lockfile pins the installed version. See [TypeScript import rewriting](https://www.typescriptlang.org/tsconfig/rewriteRelativeImportExtensions.html).

The demo itself uses `.js` source imports, which are also valid with NodeNext. The new configuration supports the preference for `.ts` imports while keeping native Node ESM output. Node still requires explicit relative import extensions; switching to bundler resolution alone would not make extensionless output work with `node dist/server.js`.

`verbatimModuleSyntax` requires type-only imports to be marked explicitly, and `noEmitOnError` prevents a failing build from emitting new JavaScript. Strict checking and `noUncheckedIndexedAccess` remain enabled.

The test command and `npm run dev` use `tsx` to execute TypeScript source. Production startup runs compiled JavaScript.

## Editor setup and formatting

Install dependencies locally as well as in Docker so VS Code can resolve Fastify, Node types, and the project's TypeScript version:

```sh
cd validator
npm ci
npm run format
npm run format:check
```

Use Node 22 for development, matching the Docker image. In VS Code, install the recommended Prettier extension and choose **TypeScript: Select TypeScript Version → Use Workspace Version** if prompted. Workspace settings enable TypeScript formatting on save. Prettier handles layout; `npm run typecheck` checks types. See [Prettier installation](https://prettier.io/docs/install).

Git's `M` means a tracked file differs from the local commit; `U` means a file is untracked. These badges are separate from diagnostics and formatting. Commits created remotely must be fetched and synchronized before the local checkout recognizes them. Dependencies stay ignored and must not be committed.

## Contract and verification

The refactor preserves `GET /health` and `POST /validate`, including HTTP 200 approval/rejection decisions. Laravel still owns balance checks and all financial writes. No database or frontend is added here.

```sh
npm test
npm run format:check
npm run typecheck
npm run build
```

Tests cover rule boundaries, invalid inputs, startup configuration, HTTP decisions, health routes, and isolation between differently configured application instances.
