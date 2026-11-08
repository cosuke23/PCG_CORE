<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RegistrationLogTest extends TestCase
{
    public function test_database_errors_are_logged_without_the_sql_values()
    {
        Log::spy();
        $e = new QueryException('mysql', 'insert into users (password, email) values (?, ?)', ['$2y$10$secrethash', 'person@example.test'],
            new \PDOException('SQLSTATE[22001]: String data, right truncated'));
        $e->errorInfo = ['22001', 1406, "Data too long for column 'sub_unit' at row 1"];

        app(\Illuminate\Contracts\Debug\ExceptionHandler::class)->report($e);

        Log::shouldHaveReceived('error')->withArgs(function ($msg, $ctx) {
            $all = $msg . json_encode($ctx);

            return str_contains($all, "Data too long for column 'sub_unit'")
                && !str_contains($all, 'secrethash') && !str_contains($all, 'person@example.test');
        })->once();
    }
}
