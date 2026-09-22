<?php

namespace Chatsaas\LaravelWidget\Tests\Feature;

use Chatsaas\LaravelWidget\Tests\TestCase;
use Chatsaas\LaravelWidget\Tests\TestUser;

class AllowByDefaultTest extends TestCase
{
    public function test_it_still_denies_when_allow_by_default_is_left_false(): void
    {
        $user = TestUser::create(['name' => 'Ada']);

        $this->withHeaders(['Authorization' => 'Bearer test-service-key'])
            ->postJson('/__test/protected', ['userId' => $user->id])
            ->assertStatus(403);
    }

    public function test_it_allows_every_user_once_allow_by_default_is_opted_into(): void
    {
        config(['chatsaas.allow_by_default' => true]);

        $user = TestUser::create(['name' => 'Ada']);

        $this->withHeaders(['Authorization' => 'Bearer test-service-key'])
            ->postJson('/__test/protected', ['userId' => $user->id])
            ->assertStatus(200)
            ->assertJson(['userId' => $user->id]);
    }

    public function test_a_host_defined_gate_still_takes_priority_over_allow_by_default(): void
    {
        config(['chatsaas.allow_by_default' => true]);
        \Illuminate\Support\Facades\Gate::define('use-chatsaas', fn () => false);

        $user = TestUser::create(['name' => 'Ada']);

        $this->withHeaders(['Authorization' => 'Bearer test-service-key'])
            ->postJson('/__test/protected', ['userId' => $user->id])
            ->assertStatus(403);
    }
}
