import type { FastifyPluginAsync } from 'fastify';
import { check, type Limits } from './rules.js';

export function createRoutes(limits: Limits): FastifyPluginAsync {
  const configuredLimits = { ...limits };

  return async function routes(app) {
    app.get('/health', async () => ({ status: 'ok' }));
    app.post('/validate', async request => check(request.body, configuredLimits));
  };
}
