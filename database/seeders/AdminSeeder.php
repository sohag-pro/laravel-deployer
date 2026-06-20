<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    /**
     * Create the single admin account from environment variables.
     *
     * Set ADMIN_EMAIL and ADMIN_PASSWORD in .env before seeding. When the
     * password is omitted a random one is generated and printed once — there
     * are no default credentials, by design, because this tool can deploy
     * code and overwrite databases.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD');

        if (empty($password)) {
            $password = Str::password(16);
            $this->command?->warn("Generated admin password for {$email}: {$password}");
            $this->command?->warn('Store it now — it will not be shown again.');
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => 'Admin', 'password' => Hash::make($password)],
        );
    }
}
