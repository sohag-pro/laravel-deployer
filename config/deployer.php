<?php

return [
    'one_time_commands' => 'php artisan key:generate',
    'commands'          => 'composer2 install',
    'git_remote_url'    => env( 'GIT_REMOTE_URL' ),
    'serve_dir'         => env( 'SERVE_DIR', '/var/www/html/deployer-demo/public_html' ),
    'base_dir'          => env( 'BASE_DIR', '/var/www/html/deployer-demo' ),
    'version_dir'       => env( 'VERSION_DIR', '/version' ),
    'storage_dir'       => env( 'STORAGE_DIR', '/storage' ),
    'db_dir'            => env( 'DB_DIR', '/db' ),
    'db_name'           => env( 'DEPLOYER_DB_NAME' ),
    'db_user'           => env( 'DEPLOYER_DB_USER' ),
    'db_password'       => env( 'DEPLOYER_DB_PASSWORD' ),
    'db_host'           => env( 'DEPLOYER_DB_HOST', 'localhost' ),
    'db_port'           => env( 'DEPLOYER_DB_PORT', '3306' ),
];
