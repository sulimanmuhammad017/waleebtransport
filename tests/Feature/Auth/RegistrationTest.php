<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/register')
                ->has('passwordRules'),
            );
    }

    public function test_new_users_can_register(): void
    {
        Event::fake([Registered::class]);

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        Event::assertDispatched(Registered::class);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_requires_name_email_and_password(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), []);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $response->assertSessionHasErrors(['name', 'email', 'password']);
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'taken@example.com',
        ]);

        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'taken@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $response->assertSessionHasErrors([
            'email' => __('validation.unique', ['attribute' => 'email']),
        ]);
    }

    public function test_registration_rejects_an_unconfirmed_password(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'different-password',
        ]);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $response->assertSessionHasErrors('password');
    }
}
