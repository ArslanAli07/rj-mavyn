<?php

namespace Tests\Feature;

use App\Mail\ForgotPasswordMail;
use App\Mail\WelcomeMail;
use App\Models\NotificationOutbox;
use App\Models\User;
use App\Services\NotificationOutboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use RuntimeException;
use Tests\TestCase;

class AccountNotificationOutboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_records_welcome_without_inline_mail(): void
    {
        Mail::fake();
        $this->post('/register', $this->withHoneypot(['name' => 'Buyer', 'email' => 'buyer@example.test', 'password' => 'password', 'password_confirmation' => 'password']))->assertSessionHasNoErrors();
        Mail::assertNothingSent();
        $entry = NotificationOutbox::sole();
        $this->assertSame('account_welcome', $entry->notification_type);
        app(NotificationOutboxService::class)->deliver($entry->id);
        app(NotificationOutboxService::class)->deliver($entry->id);
        Mail::assertSent(WelcomeMail::class, 1);
    }

    public function test_reset_transport_failure_is_retained_and_retried_without_logging_secrets(): void
    {
        $user = User::factory()->create();
        $this->post('/forgot-password', $this->withHoneypot(['email' => $user->email]))->assertSessionHasNoErrors();
        $entry = NotificationOutbox::sole();
        $token = Crypt::decryptString($entry->payload['token']);
        $this->assertNotSame($token, $entry->payload['token']);
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Private SMTP credential'));
        app(NotificationOutboxService::class)->deliver($entry->id);
        $this->assertNull($entry->fresh()->sent_at);
        $this->assertSame(RuntimeException::class, $entry->fresh()->last_error);
        $this->travel(61)->seconds();
        Mail::swap(new MailManager($this->app));
        Mail::fake();
        app(NotificationOutboxService::class)->deliver($entry->id);
        Mail::assertSent(ForgotPasswordMail::class, fn ($mail) => $mail->token === $token);
        $this->assertNotNull($entry->fresh()->sent_at);
    }

    public function test_expired_or_replaced_reset_tokens_are_discarded(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        app(NotificationOutboxService::class)->recordAccount($user, $token);
        $first = NotificationOutbox::sole();
        Password::broker()->createToken($user);
        app(NotificationOutboxService::class)->deliver($first->id);
        $this->assertNotNull($first->fresh()->discarded_at);
        $currentToken = Password::broker()->createToken($user);
        app(NotificationOutboxService::class)->recordAccount($user, $currentToken);
        $second = NotificationOutbox::latest('id')->first();
        $this->travel(61)->minutes();
        app(NotificationOutboxService::class)->deliver($second->id);
        $this->assertNotNull($second->fresh()->discarded_at);
        Mail::assertNothingSent();
    }

    public function test_password_reset_does_not_wait_for_a_failed_welcome(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        app(NotificationOutboxService::class)->recordAccount($user);
        app(NotificationOutboxService::class)->recordAccount($user, Password::broker()->createToken($user));
        app(NotificationOutboxService::class)->deliver(NotificationOutbox::latest('id')->first()->id);
        Mail::assertSent(ForgotPasswordMail::class, 1);
        Mail::assertNotSent(WelcomeMail::class);
    }
}
