<?php

namespace App\Exceptions;

use Exception;

use App\Http\RestApi;
use App\Models\ErrorLog;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Validation\ValidationException::class,
        Tymon\JWTAuth\Exceptions\TokenExpiredException::class,
        \InvalidArgumentException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $e
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e)
    {
        if (app()->isLocal()) {
            // For debugging purposes.
            error_log("Exception [".get_class($e)."]: ". $e->getMessage() ." file ".$e->getFile().":".$e->getLine());
        }

        /*
         * Handle JWT exceptions.
         */

        if ($e instanceof Tymon\JWTAuth\Exceptions\TokenExpiredException) {
    		return response()->json(['token_expired'], $e->getStatusCode());
    	} else if ($e instanceof Tymon\JWTAuth\Exceptions\TokenInvalidException) {
    		return response()->json(['token_invalid'], $e->getStatusCode());
    	}

        // Record not found
        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            $className = last(explode('\\', $e->getModel()));
            return response()->json([ 'error' => "$className was not found" ], 400);
        }

        // Required parameters not present and/or do not pass validation.
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return RestApi::error(response(), 422, $e->errors());
        }

        // Parameters given to a method are not valid.
        if ($e instanceof \InvalidArgumentException) {
            return RestApi::error(response(), 422, $e->getMessage());
        }

        // No authorization token / not logged in
        if ($e instanceOf \Illuminate\Auth\AuthenticationException) {
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

        if ($this->isHttpException($e)) {
            $statusCode = $e->getStatusCode();

            switch ($e->getStatusCode()) {
                case '404':
                   return RestApi::error(response(), 404, 'Endpoint not found');
                case '405':
                    return RestApi::error(response(), 405, 'Method not allowed');
                default:
                    return RestApi::error(response(), $statusCode, 'Unknown status.');

            }
        }

        if (!app()->isLocal()) {
            // Fatal server error.. record what happened.
            try {
                $log = new ErrorLog([
                    'error_type'    => 'server-exception',
                    'ip'            => $request->ip(),
                    'user_agent'    => $request->userAgent(),
                    'url'           => $request->fullUrl(),
                    'data'          => [
                        'exception' => [
                            'class'   => class_basename($e),
                            'message' => $e->getMessage(),
                            'file'    => $e->getFile(),
                            'line'    => $e->getLine(),
                            'backtrace'  => $e->getTrace(),
                        ],
                        'method'     => $request->method(),
                        'parameters' => $request->all(),
                    ]
                ]);

                if (Auth::check()) {
                    $log->person_id = Auth::user()->id;
                }

                $log->save();
            } catch (\Exception $e) {
                // ignore exception.
            }
        }

        $className = class_basename($e);
        $file = $e->getFile();
        $line = $e->getLine();
        $message = $e->getMessage();

        // Bad SQL statement, no biscuit!
        if ($e instanceof \Illuminate\Database\QueryException) {
            if (app()->isLocal()) {
                // For development return the full SQL statement
                return RestApi::error(response(), 500, "SQL Exception $file:$line - $message");
            } else {
                // Otherwise say where it happened and don't leak potentially harmful data
                return RestApi::error(response(), 500, "An unrecoverable database failure occured at $file:$line");
            }
        }

        return RestApi::error(response(), 500, "An unrecoverable server error occured. Exception $className at $file:$line - ".$e->getMessage());
    }
}
