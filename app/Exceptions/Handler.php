<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        // A database error's message holds the whole SQL with its values
        // (password hashes, names, emails, birth dates). Log the database's
        // own reason, with any quoted value blanked, and where it happened.
        $this->reportable(function (\Illuminate\Database\QueryException $e) {
            \Illuminate\Support\Facades\Log::error('Database error', [
                'reason' => \App\Support\DbError::reason($e),
                'at' => basename($e->getFile()) . ':' . $e->getLine(),
                'url' => request()?->path(),
            ]);
        })->stop();
    }
}
