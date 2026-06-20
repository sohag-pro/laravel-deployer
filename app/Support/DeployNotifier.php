<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends deploy success/failure notifications to a Slack webhook and/or an
 * email address. Both are optional; a missing channel is silently skipped,
 * and a delivery failure is logged but never fails the deploy.
 */
class DeployNotifier
{
    public static function send(bool $ok, string $message): void
    {
        $status = $ok ? 'succeeded' : 'failed';
        $appName = (string) config('app.name', 'Laravel Deployer');
        $text = "[{$appName}] Deploy {$status}: {$message}";

        self::slack($text);
        self::email("Deploy {$status}", $text);
    }

    protected static function slack(string $text): void
    {
        $webhook = (string) config('deployer.notify_slack_webhook');
        if ($webhook === '') {
            return;
        }

        try {
            Http::timeout(10)->post($webhook, ['text' => $text]);
        } catch (Throwable $e) {
            Log::warning('Deploy Slack notification failed', ['exception' => $e]);
        }
    }

    protected static function email(string $subject, string $body): void
    {
        $to = (string) config('deployer.notify_email');
        if ($to === '') {
            return;
        }

        try {
            Mail::raw($body, function ($mail) use ($to, $subject) {
                $mail->to($to)->subject($subject);
            });
        } catch (Throwable $e) {
            Log::warning('Deploy email notification failed', ['exception' => $e]);
        }
    }
}
