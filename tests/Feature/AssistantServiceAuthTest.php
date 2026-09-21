<?php

namespace Chatsaas\LaravelWidget\Tests\Feature;

use Chatsaas\LaravelWidget\Tests\TestCase;
use Chatsaas\LaravelWidget\Tests\TestUser;
use Illuminate\Support\Facades\Gate;

class AssistantServiceAuthTest extends TestCase
{
    public function test_it_rejects_a_missing_or_wrong_service_key(): void
    {
        $this->postJson('/__test/protected', ['userId' => 1])
            ->assertStatus(401);

        $this->withHeaders(['Authorization' => 'Bearer wrong-key'])
            ->postJson('/__test/protected', ['userId' => 1])
            ->assertStatus(401);
    }

    public function test_it_rejects_an_unknown_user_id(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer test-service-key'])
            ->postJson('/__test/protected', ['userId' => 999])
            ->assertStatus(403);
    }

    public function test_it_denies_by_default_when_the_host_never_defines_the_gate(): void
    {
        $user = TestUser::create(['name' => 'Ada']);

        $this->withHeaders(['Authorization' => 'Bearer test-service-key'])
            ->postJson('/__test/protected', ['userId' => $user->id])
            ->assertStatus(403);
    }

    public function test_it_allows_and_impersonates_once_the_host_defines_the_gate(): void
    {
        Gate::define('use-chatsaas', fn () => true);

        $user = TestUser::create(['name' => 'Ada']);

        $this->withHeaders(['Authorization' => 'Bearer test-service-key'])
            ->postJson('/__test/protected', ['userId' => $user->id])
            ->assertStatus(200)
            ->assertJson(['userId' => $user->id]);
    }
}
