<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use App\Http\RestApi;

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
//        \Illuminate\Validation\ValidationException::class,
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
        error_log("Exception [".get_class($e)."]: ". $e->getMessage() ." file ".$e->getFile().":".$e->getLine());

        /*
         * Handle JWT exceptions.
         */

        if ($e instanceof Tymon\JWTAuth\Exceptions\TokenExpiredException) {
    		return response()->json(['token_expired'], $e->getStatusCode());
    	} else if ($e instanceof Tymon\JWTAuth\Exceptions\TokenInvalidException) {
    		return response()->json(['token_invalid'], $e->getStatusCode());
    	}

        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            $className = last(explode('\\', $e->getModel()));
            return response()->json([ 'error' => "$className was not found" ], 400);
        }

        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return RestApi::error(response(), 422, $e->errors());
        }

        if ($e instanceof \InvalidArgumentException) {
            return RestApi::error(response(), 422, $e->getMessage());
        }

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

        return RestApi::error(response(), 500, "Server Exception [".class_basename($e)."]: " .$e->getMessage()." file ".$e->getFile().":".$e->getLine());

        return parent::render($request, $e);
    }
}
