/**
 * Live invoice totals for the UI. Mirrors App\Support\InvoiceCalculator (integer cents).
 * The server recalculates on save and is the source of truth; this only keeps the screen in step while typing.
 */

export function toCents(amount) {
    return Math.round(Number(amount || 0) * 100);
}

export function toHundredths(quantity) {
    return Math.round(Number(quantity || 0) * 100);
}

export function toBasisPoints(rate) {
    return Math.round(Number(rate || 0) * 100);
}

export function formatCents(cents) {
    const sign = cents < 0 ? '-' : '';
    const abs = Math.abs(cents);
    return `${sign}${Math.floor(abs / 100)}.${String(abs % 100).padStart(2, '0')}`;
}

/**
 * @param {Array<{quantity: number|string, unit_price: number|string}>} items
 * @returns {{ lines: string[], subtotal: string, discount: string, tax_amount: string, total: string }}
 */
export function calculate(items, discount = 0, taxRate = 0) {
    let subtotal = 0;
    const lines = items.map((item) => {
        const lineCents = Math.floor((toHundredths(item.quantity) * toCents(item.unit_price) + 50) / 100);
        subtotal += lineCents;
        return formatCents(lineCents);
    });

    const discountCents = toCents(discount);
    const taxable = subtotal - discountCents;
    const taxCents = Math.floor((taxable * toBasisPoints(taxRate) + 5000) / 10000);

    return {
        lines,
        subtotal: formatCents(subtotal),
        discount: formatCents(discountCents),
        tax_amount: formatCents(taxCents),
        total: formatCents(taxable + taxCents),
    };
}
