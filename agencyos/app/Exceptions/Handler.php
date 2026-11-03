<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password', 'password', 'password_confirmation',
        'api_key', 'db_password', 'mail_password', 'setup_token',
    ];

    protected function shouldReturnJson($request, Throwable $exception): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}
