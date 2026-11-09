import Fastify from 'fastify';
import { defaultLimits, type Limits } from './rules.js';
import { createRoutes } from './routes.js';

export function buildApp(limits: Limits = defaultLimits) {
  const app = Fastify({ logger: true });
  app.register(createRoutes(limits));
  return app;
}
