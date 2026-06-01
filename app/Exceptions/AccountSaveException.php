<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown inside the account-creation transaction when the Person model fails to
 * save for a non-database reason (e.g. model validation returning false), so the
 * transaction rolls back the reassigned callsign and any partial provisioning.
 */
class AccountSaveException extends RuntimeException
{
}
