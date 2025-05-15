<?php

namespace App\Http\Controllers;

use App\Mail\ResetPasswordMail;
use App\Models\ActionLog;
use App\Models\Person;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * Log the user out / Invalidate the session
     *
     * @return JsonResponse
     */

    public function logout(): JsonResponse
    {
        ActionLog::record($this->user, 'auth-logout', 'User logout');
        auth()->logout();

        return response()->json(['status' => 'success']);
    }

    /**
     * Reset an account password by emailing a new temporary password.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function resetPassword(): JsonResponse
    {
        prevent_if_ghd_server('Reset password is not available on the training server.');

        $data = request()->validate([
            'identification' => 'required|email',
        ]);

        $action = [
            'ip' => request_ip(),
            'user_agent' => request()->userAgent(),
            'email' => $data['identification']
        ];

        $person = Person::findByEmail($data['identification']);

        if (!$person) {
            ActionLog::record(null, 'auth-password-reset-fail', 'Password reset failed', $action);
            return response()->json(['status' => 'not-found'], 400);
        }

        if (in_array($person->status, Person::LOCKED_STATUSES)) {
            ActionLog::record(null, 'auth-password-reset-fail', 'Account disabled', $action);
            return response()->json(['status' => 'account-disabled'], 403);
        }

        $token = $person->createTemporaryLoginToken();

        ActionLog::record($person, 'auth-password-reset-success', 'Password reset request', $action);

        if (!mail_send(new ResetPasswordMail($person, $token), false)) {
            return response()->json(['status' => 'mail-fail']);
        }

        return response()->json(['status' => 'success']);
    }
}
