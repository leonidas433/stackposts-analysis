<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function databaseReady(): bool
{
    $driver = (string) (getenv('DB_CONNECTION') ?: 'mysql');
    if ($driver !== 'mysql') {
        return false;
    }

    $host = (string) (getenv('DB_HOST') ?: '127.0.0.1');
    $port = (string) (getenv('DB_PORT') ?: '3306');
    $database = (string) (getenv('DB_DATABASE') ?: '');
    $username = (string) (getenv('DB_USERNAME') ?: '');
    $password = (string) (getenv('DB_PASSWORD') ?: '');

    try {
        new \PDO("mysql:host={$host};port={$port};dbname={$database}", $username, $password, [
            \PDO::ATTR_TIMEOUT => 1,
        ]);

        return true;
    } catch (\Throwable) {
        return false;
    }
}
