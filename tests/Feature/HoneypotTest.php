<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class HoneypotTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_is_blocked_when_honeypot_field_is_filled(): void
    {
        $response = $this->from(route('contact'))->post(route('contact.send'), $this->withHoneypot([
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'subject' => 'Spam',
            'message' => 'Buy now',
        ], filled: true));

        $response->assertRedirect(route('contact'));
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_contact_form_is_blocked_when_submitted_too_quickly(): void
    {
        $response = $this->from(route('contact'))->post(route('contact.send'), [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'subject' => 'Spam',
            'message' => 'Buy now',
            config('honeypot.name_field') => '',
            config('honeypot.valid_from_field') => Crypt::encryptString((string) now()->timestamp),
        ]);

        $response->assertRedirect(route('contact'));
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_contact_form_succeeds_with_valid_honeypot(): void
    {
        $response = $this->from(route('contact'))->post(route('contact.send'), $this->withHoneypot([
            'name' => 'Ali',
            'email' => 'ali@example.com',
            'subject' => 'Hello',
            'message' => 'I have a question about delivery.',
        ]));

        $response->assertRedirect(route('contact'));
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('contact_messages', 1);
        $this->assertInstanceOf(ContactMessage::class, ContactMessage::first());
    }

    public function test_registration_is_blocked_when_honeypot_field_is_filled(): void
    {
        $response = $this->from(route('register'))->post('/register', $this->withHoneypot([
            'name' => 'Bot User',
            'email' => 'bot@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], filled: true));

        $response->assertRedirect(route('register'));
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_succeeds_with_valid_honeypot(): void
    {
        $response = $this->post('/register', $this->withHoneypot([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]));

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_login_is_blocked_when_honeypot_field_is_filled(): void
    {
        $user = User::factory()->create();

        $response = $this->from(route('login'))->post('/login', $this->withHoneypot([
            'email' => $user->email,
            'password' => 'password',
        ], filled: true));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_newsletter_is_blocked_when_honeypot_field_is_filled(): void
    {
        $response = $this->postJson(route('newsletter.subscribe'), $this->withHoneypot([
            'email' => 'bot@example.com',
        ], filled: true));

        $response->assertStatus(422);
        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }
}
