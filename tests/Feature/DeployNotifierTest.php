<?php

namespace Tests\Feature;

use App\Support\DeployNotifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DeployNotifierTest extends TestCase
{
    public function test_it_posts_to_slack_when_a_webhook_is_configured(): void
    {
        config(['deployer.notify_slack_webhook' => 'https://hooks.slack.test/abc', 'deployer.notify_email' => null]);
        Http::fake();

        DeployNotifier::send(true, 'release 2024 live');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://hooks.slack.test/abc'
                && str_contains($request['text'], 'succeeded')
                && str_contains($request['text'], 'release 2024 live');
        });
    }

    public function test_it_does_not_call_slack_when_unset(): void
    {
        config(['deployer.notify_slack_webhook' => null, 'deployer.notify_email' => null]);
        Http::fake();

        DeployNotifier::send(false, 'boom');

        Http::assertNothingSent();
    }

    public function test_it_emails_when_an_address_is_configured(): void
    {
        config(['deployer.notify_slack_webhook' => null, 'deployer.notify_email' => 'ops@example.com']);

        DeployNotifier::send(false, 'boom');

        $messages = Mail::getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('failed', $messages[0]->getOriginalMessage()->getSubject());
    }

    public function test_it_is_a_noop_when_no_channel_is_configured(): void
    {
        config(['deployer.notify_slack_webhook' => null, 'deployer.notify_email' => null]);

        DeployNotifier::send(true, 'ok');

        $this->assertCount(0, Mail::getSymfonyTransport()->messages());
    }
}
