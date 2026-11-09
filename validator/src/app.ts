import Fastify, { type FastifyPluginAsync } from 'fastify';
import { defaultLimits, type Limits } from './rules.ts';
import { createRoutes } from './routes.ts';

interface ApplicationOptions {
  limits: Limits;
}

export const application: FastifyPluginAsync<ApplicationOptions> = async (app, options) => {
  app.register(createRoutes(options.limits));
};

export function buildApp(limits: Limits = defaultLimits) {
  const app = Fastify({ logger: true });
  app.register(application, { limits: { ...limits } });
  return app;
}
