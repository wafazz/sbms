<?php

namespace App\Support;

/**
 * Invoice money maths in integer cents, so no float rounding errors creep in.
 *
 * Rules:
 *  - line_total = round(quantity x unit_price), half up, to 2 dp
 *  - subtotal   = sum of line totals
 *  - taxable    = subtotal - discount (discount is a fixed amount, applied before tax)
 *  - tax        = round(taxable x tax_rate%), half up
 *  - total      = taxable + tax
 *
 * The server runs this and is the source of truth. The UI shows the same numbers live.
 */
final class InvoiceCalculator
{
    /**
     * @param  array<int, array{quantity: float|int|string, unit_price: float|int|string}>  $items
     * @return array{lines: array<int, string>, subtotal: string, discount: string, tax_rate: string, tax_amount: string, total: string}
     */
    public static function calculate(array $items, float|int|string $discount = 0, float|int|string $taxRate = 0): array
    {
        $lines = [];
        $subtotal = 0;

        foreach ($items as $item) {
            $quantityHundredths = self::toHundredths($item['quantity']);
            $unitCents = self::toCents($item['unit_price']);
            $lineCents = intdiv($quantityHundredths * $unitCents + 50, 100);

            $lines[] = self::format($lineCents);
            $subtotal += $lineCents;
        }

        $discountCents = self::toCents($discount);
        $taxable = $subtotal - $discountCents;
        $rateBasisPoints = self::toBasisPoints($taxRate);
        $taxCents = intdiv($taxable * $rateBasisPoints + 5000, 10000);

        return [
            'lines' => $lines,
            'subtotal' => self::format($subtotal),
            'discount' => self::format($discountCents),
            'tax_rate' => self::format($rateBasisPoints, 2),
            'tax_amount' => self::format($taxCents),
            'total' => self::format($taxable + $taxCents),
        ];
    }

    public static function toCents(float|int|string $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    /** Quantities allow 2 dp, so keep them as hundredths. */
    private static function toHundredths(float|int|string $quantity): int
    {
        return (int) round((float) $quantity * 100);
    }

    /** Tax rates allow 2 dp in percent, so keep them as basis points (7.00% = 700). */
    private static function toBasisPoints(float|int|string $rate): int
    {
        return (int) round((float) $rate * 100);
    }

    /**
     * Cents to a decimal string ("1234" → "12.34").
     * With $scale = 2 the input is already hundredths of a percent ("700" → "7.00").
     */
    public static function format(int $value, int $scale = 2): string
    {
        $sign = $value < 0 ? '-' : '';
        $value = abs($value);
        $divisor = 10 ** $scale;

        return sprintf('%s%d.%02d', $sign, intdiv($value, $divisor), $value % $divisor);
    }
}
