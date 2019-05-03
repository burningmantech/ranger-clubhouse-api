<?php

/*
 * Require a state if the country is USA, Canada or Australia unless
 * the account is not longer live (not active, inactive, auditor, prospective, etc.)
 */

namespace App\Validators;

use App\Models\Person;

class StateForCountry {
    public function validate($attribute, $value, $parameters, $validator) {
        $param = $parameters[0] ?? '';
        $data = $validator->getData();

        // Allow state to be blanked out if the account is not consider 'live'
        if ($param == 'live_only') {
            $status = $data['status'] ?? 'none';

            if (!in_array($status, Person::LIVE_STATUSES)) {
                return true;
            }
        }

        $country = $data['country'] ?? null;
        if (in_array($country, [ 'US', 'CA', 'AU'])) {
            // State required.
            return !empty($value);
        }

        return true;
    }
}
