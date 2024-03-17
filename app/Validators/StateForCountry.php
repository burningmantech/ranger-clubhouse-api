<?php

/*
 * Require a state if the country is USA, Canada or Australia unless
 * the account is not longer live (not active, inactive, auditor, prospective, etc.)
 */

namespace App\Validators;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StateForCountry implements ValidationRule
{
    /**
     * All of the data under validation.
     *
     * @var array<string, mixed>
     */
    protected $data = [];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $country = $data['country'] ?? null;
        if (in_array($country, ['US', 'CA', 'AU']) && empty($value)) {
            // State required.
            $fail("The :attribute is required for the given country.");
        }
    }

    /**
     * Set the data under validation.
     *
     * @param array<string, mixed> $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }
}
