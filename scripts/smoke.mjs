// Run inside a one-off validator container; no host Node installation needed.
import assert from 'node:assert/strict';

const base = process.env.API_BASE_URL ?? 'http://backend:8000';
const mode = process.env.SMOKE_MODE ?? 'healthy';
async function request(path, status, body, key) {
  const response = await fetch(base + '/api' + path, {
    method: body ? 'POST' : 'GET',
    headers: { 'Content-Type': 'application/json', ...(key ? { 'Idempotency-Key': key } : {}) },
    ...(body ? { body: JSON.stringify(body) } : {}),
    signal: AbortSignal.timeout(10_000),
  });
  const result = await response.json();
  assert.equal(response.status, status, JSON.stringify(result));
  return result;
}
const payment = { type: 'payment', amount: 10000, currency: 'EUR' };
let merchantId;
if (mode === 'healthy') {
  const created = await request('/merchants', 201, { name: 'HTTP smoke test', currency: 'EUR' });
  merchantId = created.data.id;
  assert.equal(created.data.balance, 0);
  const path = `/merchants/${merchantId}`;
  const posted = await request(path + '/transactions', 201, payment, 'smoke-payment');
  assert.equal(posted.data.balance_after, 10000);
  const refunded = await request(path + '/transactions', 201, { ...payment, type: 'refund', amount: 2500 }, 'smoke-refund');
  assert.equal(refunded.data.balance_after, 7500);
  assert.deepEqual(await request(path + '/transactions', 201, payment, 'smoke-payment'), posted);
  const conflict = await request(path + '/transactions', 409, { ...payment, amount: 10001 }, 'smoke-payment');
  assert.equal(conflict.error.code, 'IDEMPOTENCY_CONFLICT');
  const insufficient = await request(path + '/transactions', 422, { ...payment, type: 'refund' }, 'smoke-insufficient');
  assert.equal(insufficient.error.code, 'INSUFFICIENT_FUNDS');
  const rejected = await request(path + '/transactions', 422, { ...payment, amount: 1000001 }, 'smoke-rejected');
  assert.equal(rejected.error.code, 'VALIDATOR_REJECTED');
  const history = await request(path + '/transactions', 200);
  assert.equal(history.meta.total, 2);
  assert.deepEqual(history.data.map(row => row.id), [refunded.data.id, posted.data.id]);
  assert.equal((await request(path, 200)).data.balance, 7500);
} else {
  merchantId = Number(process.env.SMOKE_MERCHANT_ID);
  assert.ok(Number.isSafeInteger(merchantId) && merchantId > 0, 'Set SMOKE_MERCHANT_ID to the healthy run output');
  const path = `/merchants/${merchantId}`;
  if (mode === 'outage') {
    const unavailable = await request(path + '/transactions', 503, { ...payment, amount: 100 }, 'smoke-recovery');
    assert.equal(unavailable.error.code, 'VALIDATOR_UNAVAILABLE');
    const replay = await request(path + '/transactions', 201, payment, 'smoke-payment');
    assert.equal(replay.data.balance_after, 10000);
    assert.equal((await request(path, 200)).data.balance, 7500);
    assert.equal((await request(path + '/transactions', 200)).meta.total, 2);
  } else {
    assert.equal(mode, 'recovery', 'Unknown SMOKE_MODE');
    const recovered = await request(path + '/transactions', 201, { ...payment, amount: 100 }, 'smoke-recovery');
    assert.equal(recovered.data.balance_after, 7600);
    assert.equal((await request(path, 200)).data.balance, 7600);
    assert.equal((await request(path + '/transactions', 200)).meta.total, 3);
  }
}
console.log(JSON.stringify({ mode, merchant_id: merchantId, result: 'passed' }));
