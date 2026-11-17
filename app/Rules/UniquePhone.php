<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * "Not already in use" for a phone number, counting its old 7-digit form as
 * the same line (PhoneNumber::equivalents()) -- so adding 835190199 is
 * refused while a record still stored as 5190199 exists, and vice versa.
 * Soft-deleted customers count too, matching the table's unique index.
 * Expects the value already normalized.
 */
class UniquePhone implements ValidationRule
{
    public function __construct(
        protected string $table,
        protected ?int $ignoreId = null,
        protected string $message = 'This phone number is already in use.',
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! PhoneNumber::isValid($value)) {
            return; // the format rule reports that one
        }

        $taken = DB::table($this->table)
            ->whereIn('phone', PhoneNumber::equivalents($value))
            ->when($this->ignoreId, fn ($q) => $q->where('id', '!=', $this->ignoreId))
            ->exists();

        if ($taken) {
            $fail($this->message);
        }
    }
}
