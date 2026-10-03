/**
 * Сравнение decimal-цен source vs Briskly без float-дрейфа.
 */
export function normalizePrice(value: number | string): string {
  const raw = String(value).trim().replace(/\s+/g, '').replace(',', '.');
  if (raw === '' || Number.isNaN(Number(raw))) {
    throw new Error(`Invalid price: ${String(value)}`);
  }
  const num = Number(raw);
  return num.toFixed(2);
}

export function pricesEqual(a: number | string, b: number | string): boolean {
  try {
    return normalizePrice(a) === normalizePrice(b);
  } catch {
    return false;
  }
}
