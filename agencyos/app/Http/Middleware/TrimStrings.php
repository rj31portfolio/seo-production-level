<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

class TrimStrings extends Middleware
{
    protected $except = [
        'current_password', 'password', 'password_confirmation',
        'db_password', 'mail_password', 'api_key', 'setup_token',
    ];
}
