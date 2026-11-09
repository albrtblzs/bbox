import type { FastifyPluginAsync } from 'fastify';
import type { Limits } from './rules.ts';
import { healthRoutes } from './routes/health.ts';
import { validationRoutes } from './routes/validate.ts';

export function createRoutes(limits: Limits): FastifyPluginAsync {
  const configuredLimits = { ...limits };

  return async function routes(app) {
    app.register(healthRoutes);
    app.register(validationRoutes, { limits: configuredLimits });
  };
}
