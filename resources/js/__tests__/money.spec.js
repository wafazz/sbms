import { describe, it, expect } from 'vitest';
import { calculate, formatCents } from '../support/money.js';

describe('invoice money (mirrors the server)', () => {
    it('matches the server totals for a mixed invoice', () => {
        // 2 x 100.50 = 201.00, 1 x 50 = 50.00 → 251.00, tax 6% = 15.06, total 266.06
        const result = calculate(
            [
                { quantity: 2, unit_price: 100.5 },
                { quantity: 1, unit_price: 50 },
            ],
            0,
            6,
        );

        expect(result.subtotal).toBe('251.00');
        expect(result.tax_amount).toBe('15.06');
        expect(result.total).toBe('266.06');
        expect(result.lines).toEqual(['201.00', '50.00']);
    });

    it('applies the discount before tax', () => {
        const result = calculate([{ quantity: 1, unit_price: 1000 }], 100, 6);

        expect(result.tax_amount).toBe('54.00');
        expect(result.total).toBe('954.00');
    });

    it('rounds half up on lines', () => {
        expect(calculate([{ quantity: 1.5, unit_price: 0.33 }]).lines).toEqual(['0.50']);
    });

    it('formats cents with two decimals', () => {
        expect(formatCents(5)).toBe('0.05');
        expect(formatCents(-250)).toBe('-2.50');
    });
});
