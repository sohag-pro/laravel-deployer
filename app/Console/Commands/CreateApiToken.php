<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateApiToken extends Command
{
    protected $signature = 'deployer:token {name : A label for the token} {--email= : Admin email (defaults to the first user)}';

    protected $description = 'Create a Sanctum API token for controlling deploys remotely';

    public function handle(): int
    {
        $email = $this->option('email');

        $user = $email
            ? User::where('email', $email)->first()
            : User::query()->orderBy('id')->first();

        if (! $user) {
            $this->error($email ? "No user found for {$email}." : 'No users exist. Seed an admin first.');

            return self::FAILURE;
        }

        $token = $user->createToken($this->argument('name'));

        $this->info("Token created for {$user->email}:");
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->comment('Store it now — it will not be shown again. Use it as: Authorization: Bearer <token>');

        return self::SUCCESS;
    }
}
