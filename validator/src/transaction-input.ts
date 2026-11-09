export const currencies = ['EUR', 'USD', 'HUF'] as const;
export type Currency = (typeof currencies)[number];

export interface TransactionInput {
  type: 'payment' | 'refund';
  amount: number;
  currency: Currency;
}

export type InputRejection =
  'INVALID_REQUEST' | 'INVALID_TYPE' | 'INVALID_AMOUNT' | 'UNSUPPORTED_CURRENCY';

type InputResult =
  { valid: true; transaction: TransactionInput } | { valid: false; reason: InputRejection };

function isCurrency(value: unknown): value is Currency {
  return currencies.some((currency) => currency === value);
}

export function parseTransaction(input: unknown): InputResult {
  if (!input || typeof input !== 'object' || Array.isArray(input)) {
    return { valid: false, reason: 'INVALID_REQUEST' };
  }
  if (!('type' in input) || (input.type !== 'payment' && input.type !== 'refund')) {
    return { valid: false, reason: 'INVALID_TYPE' };
  }
  if (
    !('amount' in input) ||
    typeof input.amount !== 'number' ||
    !Number.isSafeInteger(input.amount) ||
    input.amount <= 0
  ) {
    return { valid: false, reason: 'INVALID_AMOUNT' };
  }
  if (!('currency' in input) || !isCurrency(input.currency)) {
    return { valid: false, reason: 'UNSUPPORTED_CURRENCY' };
  }

  return {
    valid: true,
    transaction: { type: input.type, amount: input.amount, currency: input.currency },
  };
}
