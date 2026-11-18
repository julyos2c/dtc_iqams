<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccountCredentialsSender;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountCredentialsEmailTest extends TestCase
{
    public function test_email_is_rendered_with_recipient_credentials_and_login_link(): void
    {
        config(['mail.default' => 'array']);
        $user = new User(['name' => 'Test Person', 'username' => 'TEST-123', 'email' => 'recipient@example.test']);
        $transport = Mail::mailer('array')->getSymfonyTransport();
        $transport->flush();

        $this->assertTrue(app(AccountCredentialsSender::class)->send($user, 'Temporary@123'));

        $this->assertCount(1, $transport->messages());
        $message = $transport->messages()->first()->getOriginalMessage();
        $this->assertSame('recipient@example.test', $message->getTo()[0]->getAddress());
        $this->assertSame('Your IQAMS account', $message->getSubject());
        $body = $message->getHtmlBody();
        $this->assertStringContainsString('TEST-123', $body);
        $this->assertStringContainsString('Temporary@123', $body);
        $this->assertStringContainsString(route('login'), $body);
        $this->assertStringContainsString('change your password', $body);
    }

    public function test_log_mailer_does_not_write_credentials(): void
    {
        config(['mail.default' => 'log']);
        Notification::fake();
        $user = new User(['name' => 'Test Person', 'username' => 'TEST-123', 'email' => 'recipient@example.test']);

        $this->assertFalse(app(AccountCredentialsSender::class)->send($user, 'Temporary@123'));
        Notification::assertNothingSent();
    }

    public function test_delivery_exception_is_reported_as_failure(): void
    {
        config(['mail.default' => 'array']);
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('transport failed'));
        $user = new User(['name' => 'Test Person', 'username' => 'TEST-123', 'email' => 'recipient@example.test']);

        $this->assertFalse(app(AccountCredentialsSender::class)->send($user, 'Temporary@123'));
    }
}
