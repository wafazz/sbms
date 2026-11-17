<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NumberSequenceService
{
    /**
     * Return the next number for a key (e.g. "project:2026").
     * The counter row is locked, so concurrent requests cannot get the same number.
     * Call inside the caller's transaction so the number is rolled back with it.
     */
    public function next(string $key): int
    {
        return DB::transaction(function () use ($key) {
            $now = now();

            DB::table('number_sequences')->insertOrIgnore([
                'key' => $key,
                'last_number' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $row = DB::table('number_sequences')
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            $next = $row->last_number + 1;

            DB::table('number_sequences')
                ->where('id', $row->id)
                ->update(['last_number' => $next, 'updated_at' => $now]);

            return $next;
        });
    }
}
