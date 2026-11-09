import type { Limits } from './config.ts';
import {
  parseTransaction,
  type InputRejection,
  type TransactionInput,
} from './transaction-input.ts';

export type Decision =
  | { approved: true; reason: 'APPROVED' }
  | { approved: false; reason: InputRejection | 'AMOUNT_LIMIT_EXCEEDED' };

export function checkAmountLimit(transaction: TransactionInput, limits: Limits): Decision {
  if (transaction.amount > limits[transaction.currency]) {
    return { approved: false, reason: 'AMOUNT_LIMIT_EXCEEDED' };
  }

  return { approved: true, reason: 'APPROVED' };
}

export function check(input: unknown, limits: Limits): Decision {
  const result = parseTransaction(input);
  if (!result.valid) {
    return { approved: false, reason: result.reason };
  }

  return checkAmountLimit(result.transaction, limits);
}
