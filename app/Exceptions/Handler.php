<?php

namespace App\Exceptions;

use App\Http\RestApi;
use App\Models\ErrorLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException as CommandRuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Throwable;


class Handler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    const array NO_REPORTING = [
        AuthenticationException::class,
        AuthorizationException::class,
        CommandRuntimeException::class,
        InvalidArgumentException::class,
        ModelNotFoundException::class,
        UnacceptableConditionException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param Throwable $e
     * @return bool
     * @throws Throwable
     */

    public static function report(Throwable $e): bool
    {
        if ($e instanceof ProcessSignaledException) {
            // May see this when an ECS instance is being shutdown -- don't report it.
            if ($e->getSignal() == 9) {
                return false;
            }
        }

        // Report the exception on the console if running in development
        if (app()->isLocal()) {
            return true;
        }

        ErrorLog::recordException($e, 'server-exception');
        return false;
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param Request $request
     * @param Throwable $e
     * @return JsonResponse
     */
    public static function render(Throwable $e, $request): JsonResponse
    {
        // Record not found
        if ($e instanceof ModelNotFoundException) {
            $className = last(explode('\\', $e->getModel()));
            return response()->json(['error' => "$className was not found"], 404);
        }

        // request parameters do not pass validation.
        if ($e instanceof ValidationException) {
            return RestApi::error(response(), 422, $e->validator->getMessageBag());
        }

        // Some inappropriate condition / state occurred
        if ($e instanceof UnacceptableConditionException) {
            return RestApi::error(response(), 422, $e->getMessage());
        }

        // No authorization token / not logged in
        if ($e instanceof AuthenticationException) {
            return response()->json(['error' => 'Not authenticated.'], 401);
        }

        // User does not have the appropriate roles.
        if ($e instanceof AuthorizationException ||
            $e instanceof AccessDeniedHttpException) {
            // Handle the situation where the token was expired.. and return a Not Authenticated response instead
            if (!Auth::check() && request()->header('Authorization')) {
                return response()->json(['error' => 'Not authenticated.'], 401);
            }

            $message = $e->getMessage();
            if (empty($message)) {
                $message = 'Not permitted';
            }
            return response()->json(['error' => $message], 403);
        }

        /*
         * A HTTP exception is thrown when:
         * - The URL/endpoint cannot be found (status 404)
         * - The wrong HTTP verb was used (status 405)
         * - Something, something, something, bad.
         */
        if ($e instanceof HttpExceptionInterface) {
            $statusCode = (int)$e->getStatusCode();

            switch ($statusCode) {
                case 404:
                    return RestApi::error(response(), 404, 'Endpoint not found');
                case 405:
                    return RestApi::error(response(), 405, 'Method not allowed');
                default:
                    return RestApi::error(response(), $statusCode, 'Unknown status.');
            }
        }


        // Bad SQL statement, no biscuit!
        if ($e instanceof QueryException) {
            if (app()->isLocal() || app()->runningUnitTests()) {
                // For development return the full SQL statement
                $className = class_basename($e);
                $file = $e->getFile();
                $line = $e->getLine();
                $message = $e->getMessage();
                $error = "SQL Exception [$className] $file:$line - $message";
            } else {
                // Otherwise say where it happened and don't leak potentially harmful data
                $error = 'An unrecoverable database failure occurred.';
            }
        } else {
            $error = 'An unrecoverable server error occurred.';
        }

        return RestApi::error(response(), 500, $error);
    }
}
