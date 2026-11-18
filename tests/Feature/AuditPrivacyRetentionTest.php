<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuditPrivacyRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_draft_notice_and_login_link(): void
    {
        $this->get(route('privacy-notice'))->assertOk()->assertSee('Draft for institutional review')
            ->assertSee('not an approved institutional privacy policy')->assertSee('IP addresses')
            ->assertDontSee('audit_logs')->assertDontSee('database credentials');
        $this->get(route('login'))->assertOk()->assertSee('Privacy Notice (draft)');
    }

    public function test_disposal_is_disabled_even_if_enabled_in_configuration(): void
    {
        $this->assertFalse(config('audit_retention.enabled'));
        config(['audit_retention.enabled' => true, 'audit_retention.days' => 30]);
        AuditLog::create(['action' => 'test.existing', 'created_at' => now()->subDays(40)]);
        $this->artisan('audit:retention')->assertExitCode(1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'test.existing']);
    }

    public function test_dry_run_uses_an_exclusive_boundary_without_mutating_history(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-09 12:00:00', 'UTC'));
        try {
            config(['audit_retention.days' => 30]);
            AuditLog::create(['action' => 'older', 'created_at' => now()->subDays(30)->subSecond()]);
            AuditLog::create(['action' => 'boundary', 'created_at' => now()->subDays(30)]);
            AuditLog::create(['action' => 'newer', 'created_at' => now()]);
            $this->artisan('audit:retention --dry-run')->expectsOutput('Age-based candidates: 1')->assertExitCode(0);
            $this->assertDatabaseCount('audit_logs', 3);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dry_run_requires_valid_days_and_handles_empty_table(): void
    {
        config(['audit_retention.days' => null]);
        $this->artisan('audit:retention --dry-run')->assertExitCode(1);
        config(['audit_retention.days' => 'invalid']);
        $this->artisan('audit:retention --dry-run')->assertExitCode(1);
        config(['audit_retention.days' => 30]);
        $this->artisan('audit:retention --dry-run')->expectsOutput('Age-based candidates: 0')->assertExitCode(0);
    }

    public function test_forwarded_ip_is_used_only_for_an_explicitly_trusted_proxy(): void
    {
        $middleware = new TrustProxies;
        $request = fn () => Request::create('http://localhost/', 'GET', server: [
            'REMOTE_ADDR' => '192.0.2.10', 'HTTP_X_FORWARDED_FOR' => '198.51.100.20',
        ]);
        try {
            TrustProxies::flushState();
            $middleware->handle($request(), fn ($r) => $this->assertSame('192.0.2.10', $r->ip()));
            TrustProxies::at(['192.0.2.10']);
            TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_FOR);
            $middleware->handle($request(), fn ($r) => $this->assertSame('198.51.100.20', $r->ip()));
        } finally {
            TrustProxies::flushState();
            Request::setTrustedProxies([], 0);
        }
    }
}
