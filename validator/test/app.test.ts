import { test } from 'node:test';
import assert from 'node:assert/strict';
import { buildApp } from '../src/app.js';

test('service exposes its health endpoint', async () => {
  const app = buildApp();
  try {
    const response = await app.inject('/health');
    assert.equal(response.statusCode, 200);
    assert.deepEqual(response.json(), { status: 'ok' });
  } finally { await app.close(); }
});
