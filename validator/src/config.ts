import { currencies, type Currency } from './transaction-input.ts';

export type Limits = Record<Currency, number>;
export const defaultLimits: Limits = { EUR: 1_000_000, USD: 1_000_000, HUF: 1_000_000 };

export function loadLimits(env: NodeJS.ProcessEnv): Limits {
  const limits = { ...defaultLimits };
  for (const currency of currencies) {
    const raw = env[`MAX_AMOUNT_${currency}`];
    if (raw !== undefined && !/^[1-9]\d*$/.test(raw)) {
      throw new Error(`Invalid limit for ${currency}`);
    }
    const limit = raw === undefined ? defaultLimits[currency] : Number(raw);
    if (!Number.isSafeInteger(limit) || limit > 2_147_483_647) {
      throw new Error(`Invalid limit for ${currency}`);
    }
    limits[currency] = limit;
  }
  return limits;
}
