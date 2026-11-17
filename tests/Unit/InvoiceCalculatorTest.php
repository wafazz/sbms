<?php

use App\Support\InvoiceCalculator;

it('rounds each line half up to two decimals', function () {
    // 1.5 x 0.33 = 0.495 → 0.50 (half up, not banker's rounding)
    $result = InvoiceCalculator::calculate([
        ['quantity' => 1.5, 'unit_price' => 0.33],
    ]);

    expect($result['lines'])->toBe(['0.50'])
        ->and($result['subtotal'])->toBe('0.50');
});

it('avoids float drift when summing many lines', function () {
    $items = array_fill(0, 10, ['quantity' => 1, 'unit_price' => 0.1]);

    $result = InvoiceCalculator::calculate($items);

    expect($result['subtotal'])->toBe('1.00')
        ->and($result['total'])->toBe('1.00');
});

it('applies the discount before tax', function () {
    // subtotal 1000.00, discount 100.00 → taxable 900.00, tax 6% = 54.00, total 954.00
    $result = InvoiceCalculator::calculate(
        [['quantity' => 1, 'unit_price' => 1000]],
        discount: 100,
        taxRate: 6,
    );

    expect($result['subtotal'])->toBe('1000.00')
        ->and($result['discount'])->toBe('100.00')
        ->and($result['tax_amount'])->toBe('54.00')
        ->and($result['total'])->toBe('954.00');
});

it('rounds tax half up', function () {
    // taxable 10.01 at 7% = 0.7007 → 0.70
    $result = InvoiceCalculator::calculate([['quantity' => 1, 'unit_price' => 10.01]], taxRate: 7);

    expect($result['tax_amount'])->toBe('0.70')
        ->and($result['total'])->toBe('10.71');
});

it('handles fractional quantities', function () {
    $result = InvoiceCalculator::calculate([['quantity' => 2.5, 'unit_price' => 80]]);

    expect($result['lines'])->toBe(['200.00'])
        ->and($result['total'])->toBe('200.00');
});

it('formats cents as a two-decimal string', function () {
    expect(InvoiceCalculator::format(5))->toBe('0.05')
        ->and(InvoiceCalculator::format(123456))->toBe('1234.56')
        ->and(InvoiceCalculator::format(-250))->toBe('-2.50');
});
