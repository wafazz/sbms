<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row only. Use CompanySetting::current() to read or create it.
 */
class CompanySetting extends Model
{
    protected $fillable = [
        'name',
        'address',
        'logo_path',
        'tax_number',
        'default_tax_rate',
        'bank_name',
        'bank_account',
        'bank_swift',
    ];

    protected function casts(): array
    {
        return [
            'default_tax_rate' => 'decimal:2',
        ];
    }

    public static function current(): self
    {
        // Look up the first row, not a fixed id, so the row survives id gaps.
        return static::query()->first() ?? static::query()->create([
            'name' => config('app.name'),
            'default_tax_rate' => 0,
        ]);
    }
}
