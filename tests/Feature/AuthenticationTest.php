<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_home_and_protected_admin(): void
    {
        $this->get('/')->assertOk()->assertSee('Cubiertas');
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_active_staff_can_login_and_inactive_staff_cannot(): void
    {
        $user = User::factory()->create();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->get('/admin')->assertOk();
        $user->forceFill(['active' => false])->save();
        $this->get('/admin')->assertForbidden();
        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    }
}
