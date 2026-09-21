<?php

namespace Chatsaas\LaravelWidget\Tests\Feature;

use Chatsaas\LaravelWidget\Tests\TestCase;

class InstallCommandTest extends TestCase
{
    public function test_it_writes_secrets_to_env_and_is_idempotent_on_a_second_run(): void
    {
        $envPath = base_path('.env');
        file_put_contents($envPath, "APP_NAME=Testbench\n");

        $this->artisan('chatsaas:install', ['--user-model' => 'App\\Models\\User'])
            ->assertExitCode(0);

        $firstRun = file_get_contents($envPath);
        $this->assertSame(1, substr_count($firstRun, 'CHATSAAS_IDENTITY_SECRET='));
        $this->assertSame(1, substr_count($firstRun, 'CHATSAAS_SERVICE_KEY='));

        $this->artisan('chatsaas:install', ['--user-model' => 'App\\Models\\User'])
            ->assertExitCode(0);

        $secondRun = file_get_contents($envPath);
        $this->assertSame(1, substr_count($secondRun, 'CHATSAAS_IDENTITY_SECRET='));
        $this->assertSame(1, substr_count($secondRun, 'CHATSAAS_SERVICE_KEY='));

        // Idempotent: the secret VALUES generated on the first run must survive the second.
        preg_match('/CHATSAAS_IDENTITY_SECRET=(\S+)/', $firstRun, $first);
        preg_match('/CHATSAAS_IDENTITY_SECRET=(\S+)/', $secondRun, $second);
        $this->assertSame($first[1], $second[1]);

        unlink($envPath);
    }
}
