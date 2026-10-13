<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Coupon;
use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponNewsletterRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_is_valid_through_its_expiry_date(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $coupon = Coupon::create(['code' => 'TODAY', 'type' => 'fixed', 'amount' => 10, 'starts_at' => today(), 'expires_at' => today(), 'is_active' => true]);
        $this->assertTrue($coupon->is_valid);
        $this->travelTo(now()->endOfDay());
        $this->assertTrue($coupon->is_valid);
        $this->travelTo(now()->addDay()->startOfDay());
        $this->assertFalse($coupon->is_valid);
    }

    public function test_future_coupon_start_date_remains_unavailable(): void
    {
        $coupon = Coupon::create(['code' => 'FUTURE', 'type' => 'fixed', 'amount' => 10, 'starts_at' => today()->addDay(), 'is_active' => true]);
        $this->assertFalse($coupon->is_valid);
    }

    private function export(): string
    {
        $admin = Admin::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);

        return $this->actingAs($admin, 'admin')->get(route('admin.newsletter.export'))->assertOk()->getContent();
    }

    private function rows(string $csv): array
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $csv);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, escape: '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    public function test_newsletter_csv_round_trips_quotes_commas_and_newlines(): void
    {
        $name = "Customer \"VIP\",\nSecond line";
        NewsletterSubscriber::create(['email' => 'customer@example.com', 'name' => $name, 'is_active' => true, 'subscribed_at' => now()]);
        NewsletterSubscriber::create(['email' => 'inactive@example.com', 'is_active' => false]);
        $rows = $this->rows($this->export());
        $this->assertCount(2, $rows);
        $this->assertSame(['Email', 'Name', 'Subscribed At'], $rows[0]);
        $this->assertCount(3, $rows[1]);
        $this->assertSame($name, $rows[1][1]);
    }

    public function test_newsletter_csv_neutralizes_spreadsheet_formulas(): void
    {
        $names = ['=1+1', '+1+1', '-1+1', '@SUM(1,1)', "\t=1+1"];
        foreach ($names as $index => $name) {
            NewsletterSubscriber::create(['email' => "customer{$index}@example.com", 'name' => $name, 'is_active' => true]);
        }
        $rows = array_slice($this->rows($this->export()), 1);
        foreach ($rows as $index => $row) {
            $this->assertSame("'".$names[$index], $row[1]);
        }
    }

    public function test_newsletter_validates_name_before_persistence(): void
    {
        $this->postJson(route('newsletter.subscribe'), $this->withHoneypot(['email' => 'customer@example.com', 'name' => ['invalid']]))->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }
}
