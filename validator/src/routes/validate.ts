import type { FastifyPluginAsync } from 'fastify';
import { check, type Limits } from '../rules.ts';

interface ValidationOptions {
  limits: Limits;
}

export const validationRoutes: FastifyPluginAsync<ValidationOptions> = async (app, options) => {
  app.post<{ Body: unknown }>('/validate', async (request) => check(request.body, options.limits));
};
