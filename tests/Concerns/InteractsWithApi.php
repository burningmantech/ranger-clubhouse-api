<?php

namespace Tests\Concerns;

use App\Models\Role;
use Illuminate\Testing\TestResponse;

trait InteractsWithApi
{
    /**
     * Sign in a freshly created user and grant them the admin role.
     */
    public function signInAsAdmin(): static
    {
        $this->signInUser();
        $this->addRole(Role::ADMIN);

        return $this;
    }

    /**
     * Sign in a freshly created user and grant them the given role(s).
     *
     * @param  int|array<int>  $role
     */
    public function signInWithRole(int|array $role): static
    {
        $this->signInUser();
        $this->addRole(is_array($role) ? $role : [$role]);

        return $this;
    }

    /**
     * Assert the standard ApiController::success() envelope: { <key>: { ...expected } }.
     *
     * @param  array<string, mixed>  $expected
     */
    public function assertResourceResponse(TestResponse $response, string $key, array $expected): void
    {
        $response->assertStatus(200);
        $response->assertJson([$key => $expected]);
    }
}
