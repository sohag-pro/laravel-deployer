<?php

namespace App\Http\Controllers\Api;

use App\Console\Commands\Deploy as DeployCommand;
use App\Http\Controllers\Controller;
use App\Support\DeployLauncher;
use App\Support\DeployState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeployApiController extends Controller
{
    /**
     * Trigger a deploy. Optional `ref` (branch/tag/commit). Returns 202 on
     * launch, 409 if one is already running, 422 for an invalid ref.
     */
    public function deploy(Request $request, DeployLauncher $launcher): JsonResponse
    {
        $ref = trim((string) $request->input('ref', ''));

        if ($ref !== '' && ! DeployCommand::isValidRef($ref)) {
            return response()->json(['message' => 'Invalid branch, tag or commit reference.'], 422);
        }

        if (! $launcher->trigger($ref)) {
            return response()->json(['message' => 'A deploy is already in progress.'], 409);
        }

        return response()->json([
            'message' => 'Deploy started.',
            'status' => DeployState::status(),
        ], 202);
    }

    /**
     * Current deploy status and whether one is running.
     */
    public function status(): JsonResponse
    {
        $status = DeployState::status();

        return response()->json([
            'status' => $status['status'],
            'message' => $status['message'],
            'running' => DeployState::running(),
            'started_at' => $status['started_at'],
            'finished_at' => $status['finished_at'],
        ]);
    }
}
