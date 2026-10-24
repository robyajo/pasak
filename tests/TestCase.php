<?php

namespace Internal\Pasak\Tests;

use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Internal\Pasak\Http\Middleware\BlockIpAddress;
use Internal\Pasak\Http\Middleware\DetectSecurityThreats;
use Internal\Pasak\PasakServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [PasakServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('auth.providers.users.model', TestUser::class);
        $app['config']->set('pasak.user_model', TestUser::class);
        $app['config']->set('pasak.enabled', true);
        $app['config']->set('pasak.block_enforcement', true);
        $app['config']->set('pasak.routes.enabled', true);

        $kernel = $app->make(KernelContract::class);

        if ($kernel instanceof HttpKernel) {
            $kernel->pushMiddleware(BlockIpAddress::class);
            $kernel->pushMiddleware(DetectSecurityThreats::class);
        }
    }

    protected function defineRoutes($router): void
    {
        $router->post('/login', function () {
            return redirect('/');
        });
        $router->get('/login', function () {
            return response('login form', 200);
        });
        $router->get('/', function () {
            return response('home', 200);
        });
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('user');
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamps();
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function tearDown(): void
    {
        $base = base_path();
        $testbenchBase = dirname(__DIR__).'/vendor/orchestra/testbench-core/laravel';

        if (realpath($base) === realpath($testbenchBase)) {
            foreach (['.env', '.env.example'] as $envFile) {
                $path = $base.'/'.$envFile;

                if (File::exists($path)) {
                    File::put($path, "APP_NAME=Laravel\n");
                }
            }
        }

        parent::tearDown();
    }

    protected function createAdminUser(array $attributes = []): TestUser
    {
        return TestUser::create(
            array_merge(
                [
                    'name' => 'Admin User',
                    'email' => 'admin@example.com',
                    'password' => bcrypt('password'),
                    'role' => 'admin',
                ],
                $attributes,
            ),
        );
    }

    protected function createRegularUser(array $attributes = []): TestUser
    {
        return TestUser::create(
            array_merge(
                [
                    'name' => 'Regular User',
                    'email' => 'user@example.com',
                    'password' => bcrypt('password'),
                    'role' => 'user',
                ],
                $attributes,
            ),
        );
    }
}
