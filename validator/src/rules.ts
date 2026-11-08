export const currencies = ['EUR', 'USD', 'HUF'] as const;
export type Currency = typeof currencies[number];
export type Limits = Record<Currency, number>;
export type Decision = { approved: boolean; reason: string };
export const defaultLimits: Limits = { EUR: 1_000_000, USD: 1_000_000, HUF: 1_000_000 };

export function loadLimits(env: NodeJS.ProcessEnv): Limits {
  const limits = { ...defaultLimits };
  for (const currency of currencies) {
    const raw = env[`MAX_AMOUNT_${currency}`];
    if (raw !== undefined && !/^[1-9]\d*$/.test(raw)) throw new Error(`Invalid limit for ${currency}`);
    const limit = raw === undefined ? defaultLimits[currency] : Number(raw);
    if (!Number.isSafeInteger(limit) || limit > 2_147_483_647) throw new Error(`Invalid limit for ${currency}`);
    limits[currency] = limit;
  }
  return limits;
}

export function check(input: unknown, limits: Limits): Decision {
  if (!input || typeof input !== 'object' || Array.isArray(input)) return { approved: false, reason: 'INVALID_REQUEST' };
  const value = input as Record<string, unknown>;
  if (value.type !== 'payment' && value.type !== 'refund') return { approved: false, reason: 'INVALID_TYPE' };
  if (typeof value.amount !== 'number' || !Number.isSafeInteger(value.amount) || value.amount <= 0) return { approved: false, reason: 'INVALID_AMOUNT' };
  if (typeof value.currency !== 'string' || !currencies.includes(value.currency as Currency)) return { approved: false, reason: 'UNSUPPORTED_CURRENCY' };
  if (value.amount > limits[value.currency as Currency]) return { approved: false, reason: 'AMOUNT_LIMIT_EXCEEDED' };
  return { approved: true, reason: 'APPROVED' };
}
