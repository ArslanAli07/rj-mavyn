<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/account/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/account/profile')
            ->put('/account/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/account/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
    }

    public function test_email_changes_clear_the_previous_verification(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->put('/account/profile', ['name' => $user->name, 'email' => 'new@example.com'])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_unchanged_email_preserves_verification(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $verifiedAt = $user->email_verified_at->toDateTimeString();
        $this->actingAs($user)->put('/account/profile', ['name' => 'Updated name', 'email' => $user->email])->assertSessionHasNoErrors();
        $this->assertSame($verifiedAt, $user->fresh()->email_verified_at->toDateTimeString());
    }
}
