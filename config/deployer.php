<?php

return [
    'one_time_commands' => 'php artisan key:generate',
    'commands' => 'composer install && php artisan optimize:clear',
    'after_deploy' => env('AFTER_DEPLOY_COMMANDS', 'php artisan config:cache,php artisan route:cache,php artisan view:cache'),
    'git_remote_url' => env('GIT_REMOTE_URL'),
    'serve_dir' => env('SERVE_DIR', '/var/www/html/deployer-demo/public_html'),
    'base_dir' => env('BASE_DIR', '/var/www/html/deployer-demo'),
    'version_dir' => env('VERSION_DIR', '/version'),
    'storage_dir' => env('STORAGE_DIR', '/storage'),
    'db_dir' => env('DB_DIR', '/db'),
    'db_name' => env('DEPLOYER_DB_NAME'),
    'db_user' => env('DEPLOYER_DB_USER'),
    'db_password' => env('DEPLOYER_DB_PASSWORD'),
    'db_host' => env('DEPLOYER_DB_HOST', 'localhost'),
    'db_port' => env('DEPLOYER_DB_PORT', '3306'),

    // Retention: how many releases and DB dumps to keep. Older entries are
    // pruned after a successful deploy. Set to 0 to keep everything.
    'keep_releases' => (int) env('KEEP_RELEASES', 5),
    'keep_db_dumps' => (int) env('KEEP_DB_DUMPS', 10),

    // Gzip database dumps (recommended). Dumps are written as .sql.gz and
    // transparently decompressed on restore.
    'gzip_dumps' => env('GZIP_DUMPS', true),

    // PHP CLI binary used to launch the background deploy process.
    'php_binary' => env('DEPLOYER_PHP_BINARY', 'php'),

    // A deploy still marked "running" after this many seconds is treated as
    // stale, so a crashed deploy never blocks the dashboard permanently.
    'deploy_timeout' => (int) env('DEPLOYER_DEPLOY_TIMEOUT', 1800),

    // Post-deploy health check. If set, the new release is probed after it
    // goes live; on failure the previous release is restored automatically.
    'health_url' => env('DEPLOYER_HEALTH_URL'),
    'health_retries' => (int) env('DEPLOYER_HEALTH_RETRIES', 5),
    'health_delay' => (int) env('DEPLOYER_HEALTH_DELAY', 3),
];
