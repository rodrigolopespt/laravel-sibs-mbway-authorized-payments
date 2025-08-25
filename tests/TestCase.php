<?php

namespace Rodrigolopespt\SibsMbwayAP\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Rodrigolopespt\SibsMbwayAP\SibsMbwayAPServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Rodrigolopespt\\SibsMbwayAP\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            SibsMbwayAPServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $this->loadMigrations($app);
    }

    /**
     * Load package migrations for testing
     */
    protected function loadMigrations($app): void
    {
        $migrationFiles = [
            'create_sibs_authorized_payments_table.php.stub',
            'create_sibs_charges_table.php.stub',
            'create_sibs_transactions_table.php.stub',
        ];

        foreach ($migrationFiles as $file) {
            $migrationPath = __DIR__.'/../database/migrations/'.$file;

            if (file_exists($migrationPath)) {
                $migration = include $migrationPath;
                $migration->up();
            }
        }
    }
}
