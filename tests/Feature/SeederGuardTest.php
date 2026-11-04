<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/** db:seed must never put sample data into a production or non-dev database. */
class SeederGuardTest extends TestCase
{
    public function test_seeder_refuses_outside_a_dev_or_test_database()
    {
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Refusing to seed');
        (new DatabaseSeeder())->run();
    }

    public function test_seeder_refuses_a_local_database_not_named_dev()
    {
        $this->app['env'] = 'local';
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->expectException(\RuntimeException::class);
        (new DatabaseSeeder())->run();
    }
}
