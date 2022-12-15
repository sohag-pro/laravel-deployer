<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Delpoy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deploy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $composer_command = 'composer install';
        $this->info('Deploying...');
        $tag = now()->format('YmdHis');
        $this->info('Deploying tag ' . $tag);
        $git_remote_url = 'https://github.com/sohag-pro/deployer.git';
        $base_dir = '/var/www/html/deployer-demo';
        $serve_dir = $base_dir . '/public_html';
        $version_dir = $base_dir . '/versions/' . $tag;
        // Should contain storage directory
        $storage_dir = $base_dir . '/storage';

        // Check if base dir exists
        if (!file_exists($base_dir)) {
            $this->info('Creating base dir');
            mkdir($base_dir, 0777, true);
        }

        // Check if version dir exists
        if (!file_exists($version_dir)) {
            $this->info('Creating version dir');
            mkdir($version_dir, 0777, true);
        }

        // Check if serve dir exists
        if (!file_exists($serve_dir)) {
            $this->info('Creating serve dir');
            mkdir($serve_dir, 0777, true);
        }

        // Check if storage dir exists
        if (!file_exists($storage_dir)) {
            $this->info('Creating storage dir');
            mkdir($storage_dir, 0777, true);
        }

        // Clone into version dir
        $this->info('Cloning into version dir');
        exec("git clone $git_remote_url $version_dir");

        // Copy storage dir to version dir if storage directory is empty
        if (count(scandir($storage_dir)) == 2) {
            $this->info('Copying storage dir to version dir');
            exec("cp -r $version_dir/storage/. $storage_dir ");
        }

        // remove storage directory from version
        $this->info('Removing storage directory from version');
        exec("rm -rf $version_dir/storage");

        // Link storage dir to version/storage
        $this->info('Linking storage dir to version/storage');
        exec("ln -s $storage_dir $version_dir");

        // is there is no .env, copy .env.example to .env in base dir
        if (!file_exists($base_dir . '/.env')) {
            $this->info('Copying .env.example to .env in base dir');
            exec("cp $version_dir/.env.example $base_dir/.env");
        }

        // link .env to version/.env
        $this->info('Linking .env to version/.env');
        exec("ln -s $base_dir/.env $version_dir/.env");

        // run composer install in version directory
        $this->info('Running composer install in version directory');
        exec("cd $version_dir && $composer_command");

        // Remove any previous link of server dir
        $this->info('Removing any previous link of server dir');
        exec("rm -rf $serve_dir");

        // Link version dir to serve dir
        $this->info('Linking version dir to serve dir');
        exec("ln -s $version_dir $serve_dir");

        $this->info('Deployed successfully');

        return Command::SUCCESS;
    }
}
