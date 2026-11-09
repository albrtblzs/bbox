import type { FastifyPluginAsync } from 'fastify';
import { check } from '../rules.ts';
import type { Limits } from '../config.ts';

interface ValidationOptions {
  limits: Limits;
}

export const validationRoutes: FastifyPluginAsync<ValidationOptions> = async (app, options) => {
  app.post<{ Body: unknown }>('/validate', async (request) => check(request.body, options.limits));
};
