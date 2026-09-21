<?php

namespace Chatsaas\LaravelWidget\Tests;

use Chatsaas\LaravelWidget\ChatsaasServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [ChatsaasServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('chatsaas.identity_secret', 'test-identity-secret');
        $app['config']->set('chatsaas.service_key', 'test-service-key');
        $app['config']->set('chatsaas.user_model', TestUser::class);
        $app['config']->set('database.default', 'testing');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Route::middleware(['chatsaas.service'])
            ->post('/__test/protected', fn () => response()->json(['userId' => auth()->id()]));
    }
}
