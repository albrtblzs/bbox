# Validator structure

The validator separates process startup, application construction, HTTP routes, and business rules.

```text
server.ts -> buildApp(limits) -> createRoutes(limits) -> check(body, limits)
```

- `server.ts` reads environment configuration, calls the application factory, opens port 3000, and handles shutdown.
- `app.ts` exports `buildApp`, the application factory. Each call creates a fresh Fastify instance and registers its routes without opening a port.
- `routes.ts` exports `createRoutes`, a route factory returning a Fastify plugin. It captures a copy of the supplied limits and registers `/health` and `/validate` when Fastify loads the plugin.
- `rules.ts` contains configuration validation and the ordinary `check` function. It knows nothing about HTTP servers or databases.

A factory is a function that creates an object or function with its dependencies supplied by the caller. Here, limits are passed explicitly rather than read from the environment inside request handlers. Tests can construct independently configured apps and use `app.inject()` without listening on a network port. Fastify loads registered plugins when `inject()`, `ready()`, or `listen()` is called. See the [Fastify plugin guide](https://fastify.dev/docs/latest/Guides/Plugins-Guide/).

This structure gives future endpoints a clear home. Additional route groups can become separate plugins when needed. The current two routes share one plugin; additional controller classes or a dependency injection framework are unnecessary for these handlers.

## Why import app.js when the source is app.ts?

```ts
import { buildApp } from './app.js';
```

The import names the file used by the compiled application. This project uses `"type": "module"` in `package.json` and `NodeNext` module settings in `tsconfig.json`.

```text
Source                    npm run build              Runtime
src/server.ts             TypeScript compiler        dist/server.js
src/app.ts                ------------------>        dist/app.js
src/routes.ts                                        dist/routes.js
src/rules.ts                                         dist/rules.js
```

During compilation, TypeScript resolves `./app.js` to the source `app.ts` for type checking. The emitted `dist/server.js` keeps `./app.js`, which resolves to `dist/app.js` when `npm start` runs `node dist/server.js`.
Node's relative ESM imports require explicit file extensions. With this configuration, changing the import to `./app.ts` would name a file that the JavaScript build does not emit. See the [TypeScript module reference](https://www.typescriptlang.org/docs/handbook/modules/reference).

The test command uses `tsx`, which resolves these imports while executing TypeScript source directly. Production startup runs compiled JavaScript.

## Contract and verification

The refactor preserves `GET /health` and `POST /validate`, including HTTP 200 approval/rejection decisions. Laravel still owns balance checks and all financial writes. No database or frontend is added here.

```sh
npm test
npm run typecheck
npm run build
```

Tests cover rule boundaries, invalid inputs, startup configuration, HTTP decisions, health routes, and isolation between differently configured application instances.
