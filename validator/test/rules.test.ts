import { test } from 'node:test';
import assert from 'node:assert/strict';
import { check, currencies, loadLimits } from '../src/rules.js';
import { buildApp } from '../src/app.js';

const limits = { EUR: 100, USD: 200, HUF: 300 };
test('payments and refunds support every currency including exact boundaries', () => {
  for (const currency of currencies) for (const type of ['payment', 'refund']) {
    assert.deepEqual(check({ currency, type, amount: limits[currency] }, limits), { approved: true, reason: 'APPROVED' });
    assert.equal(check({ currency, type, amount: limits[currency] + 1 }, limits).reason, 'AMOUNT_LIMIT_EXCEEDED');
  }
});
test('invalid inputs produce stable rejection codes', () => {
  const base = { type: 'payment', amount: 1, currency: 'EUR' };
  for (const input of [null, [], 'text', 1]) assert.equal(check(input, limits).reason, 'INVALID_REQUEST');
  assert.equal(check({ ...base, type: 'other' }, limits).reason, 'INVALID_TYPE');
  for (const amount of [undefined, null, 0, -1, 1.5, '1', Number.MAX_SAFE_INTEGER + 1, NaN, Infinity]) {
    assert.equal(check({ ...base, amount }, limits).reason, 'INVALID_AMOUNT');
  }
  for (const currency of ['eur', 'GBP', null, ['EUR']]) assert.equal(check({ ...base, currency }, limits).reason, 'UNSUPPORTED_CURRENCY');
});
test('limits default independently and malformed environment fails startup', () => {
  assert.deepEqual(loadLimits({ MAX_AMOUNT_EUR: '25' }), { EUR: 25, USD: 1_000_000, HUF: 1_000_000 });
  for (const value of ['', '0', '-1', '1.5', 'NaN', '1e3', '2147483648']) assert.throws(() => loadLimits({ MAX_AMOUNT_EUR: value }), /Invalid limit/);
});
test('HTTP validation returns boolean decisions without coercing amounts', async () => {
  const app = buildApp(limits);
  try {
    for (const [amount, approved, reason] of [[100, true, 'APPROVED'], [101, false, 'AMOUNT_LIMIT_EXCEEDED'], ['1', false, 'INVALID_AMOUNT']] as const) {
      const response = await app.inject({ method: 'POST', url: '/validate', payload: { type: 'payment', amount, currency: 'EUR' } });
      assert.equal(response.statusCode, 200);
      assert.deepEqual(response.json(), { approved, reason });
    }
  } finally { await app.close(); }
});

test('application factories isolate limits and register health routes', async () => {
  const configuration = { EUR: 100, USD: 200, HUF: 300 };
  const first = buildApp(configuration);
  const second = buildApp({ ...configuration, EUR: 50 });
  configuration.EUR = 1;

  try {
    for (const app of [first, second]) {
      const health = await app.inject({ method: 'GET', url: '/health' });
      assert.equal(health.statusCode, 200);
      assert.deepEqual(health.json(), { status: 'ok' });
    }

    const request = { method: 'POST' as const, url: '/validate', payload: { type: 'payment', amount: 75, currency: 'EUR' } };
    assert.deepEqual((await first.inject(request)).json(), { approved: true, reason: 'APPROVED' });
    assert.deepEqual((await second.inject(request)).json(), { approved: false, reason: 'AMOUNT_LIMIT_EXCEEDED' });
  } finally {
    await Promise.all([first.close(), second.close()]);
  }
});
