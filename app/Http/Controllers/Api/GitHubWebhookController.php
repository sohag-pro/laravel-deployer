<?php

namespace App\Http\Controllers\Api;

use App\Console\Commands\Deploy as DeployCommand;
use App\Http\Controllers\Controller;
use App\Support\DeployLauncher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives GitHub push webhooks and triggers a deploy. The request is
 * authenticated by verifying the HMAC signature against a shared secret, so
 * no Sanctum token is needed (and must not be required) for this endpoint.
 */
class GitHubWebhookController extends Controller
{
    public function handle(Request $request, DeployLauncher $launcher): JsonResponse
    {
        $secret = (string) config('deployer.github_webhook_secret');

        if ($secret === '') {
            return response()->json(['message' => 'Webhook is not configured.'], 404);
        }

        if (! $this->signatureIsValid($request, $secret)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event = $request->header('X-GitHub-Event');

        if ($event === 'ping') {
            return response()->json(['message' => 'pong']);
        }

        if ($event !== 'push') {
            return response()->json(['message' => 'Ignored event.'], 200);
        }

        // Extract the pushed branch from refs/heads/<branch>.
        $ref = (string) $request->input('ref');
        $branch = str_starts_with($ref, 'refs/heads/') ? substr($ref, strlen('refs/heads/')) : null;

        $only = (string) config('deployer.webhook_branch');
        if ($only !== '' && $branch !== $only) {
            return response()->json(['message' => "Ignored branch: {$branch}."], 200);
        }

        if ($branch !== null && ! DeployCommand::isValidRef($branch)) {
            return response()->json(['message' => 'Invalid branch reference.'], 422);
        }

        if (! $launcher->trigger($branch ?: null)) {
            return response()->json(['message' => 'A deploy is already in progress.'], 409);
        }

        return response()->json(['message' => 'Deploy started.', 'branch' => $branch], 202);
    }

    protected function signatureIsValid(Request $request, string $secret): bool
    {
        $signature = (string) $request->header('X-Hub-Signature-256');
        if ($signature === '') {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
