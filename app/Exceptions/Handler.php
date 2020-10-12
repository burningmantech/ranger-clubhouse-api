<?php

namespace App\Exceptions;

use Exception;

use App\Http\RestApi;
use App\Models\ErrorLog;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

use Illuminate\Support\Str;

use \Symfony\Component\Console\Exception\RuntimeException as CommandRuntimeException;

use Throwable;


class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Validation\ValidationException::class,
        \InvalidArgumentException::class,
        \Tymon\JWTAuth\Exceptions\TokenExpiredException::class,
        CommandRuntimeException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param Throwable $exception
     * @return void
     */

    public function report(Throwable $exception)
    {
        if (!$this->shouldReport($exception)) {
            return;
        }

        if ($exception instanceof \Symfony\Component\Process\Exception\ProcessSignaledException) {
            // May see this when an ECS instance is being shutdown -- don't report it.
            if ($exception->getSignal() == 9) {
                return;
            }
        }

        // Report the exception on the console if running in development
        if (app()->isLocal()) {
            parent::report($exception);
            return;
        }

        ErrorLog::recordException($exception, 'server-exception');
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  Throwable  $e
     * @return \Illuminate\Http\JsonResponse
     */
    public function render($request, Throwable $e)
    {
        /*
         * Handle JWT exceptions.
         */

        if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException) {
            return response()->json(['token_expired'], $e->getStatusCode());
        } elseif ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException) {
            return response()->json(['token_invalid'], $e->getStatusCode());
        }

        // Record not found
        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            $className = last(explode('\\', $e->getModel()));
            return response()->json([ 'error' => "$className was not found" ], 404);
        }

        // Required parameters not present and/or do not pass validation.
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return RestApi::error(response(), 422, $e->validator->getMessageBag());
        }

        // Parameters given to a method are not valid.
        if ($e instanceof \InvalidArgumentException) {
            return RestApi::error(response(), 422, $e->getMessage());
        }

        // No authorization token / not logged in
        if ($e instanceof \Illuminate\Auth\AuthenticationException) {
            return response()->json([ 'error' => 'Not authenticated.'], 401);
        }

        // User does not have the appropriate roles.
        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            $message = $e->getMessage();
            if ($message == '') {
                $message = 'Not permitted';
            }
            return response()->json([ 'error' => $message ], 403);
        }

        /*
         * A HTTP exception is thrown when:
         * - The URL/endpoint cannot be found (status 404)
         * - The wrong HTTP verb was used (status 405)
         * - Something, something, something, bad.
         */
        if ($this->isHttpException($e)) {
            $statusCode = (int) $e->getStatusCode();

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
        if ($e instanceof \Illuminate\Database\QueryException) {
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
