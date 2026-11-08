import Fastify from 'fastify';
import { check, defaultLimits, type Limits } from './rules.js';

export function buildApp(limits: Limits = defaultLimits) {
  const app = Fastify({ logger: true });
  app.get('/health', async () => ({ status: 'ok' }));
  app.post('/validate', async request => check(request.body, limits));
  return app;
}
