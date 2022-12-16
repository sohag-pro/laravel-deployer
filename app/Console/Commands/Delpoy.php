<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Delpoy extends Command {
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
    public function handle() {
        $this->info( 'Deploying...' );
        $tag = now()->format( 'YmdHis' );
        $this->info( 'Deploying tag ' . $tag );
        $run_one_time_commands = false;
        $one_time_commands     = config( 'deployer.one_time_commands' );
        $commands              = config( 'deployer.commands' );
        $git_remote_url        = config( 'deployer.git_remote_url' );
        $base_dir              = config( 'deployer.base_dir' );
        $serve_dir             = config( 'deployer.serve_dir' );
        $version_dir           = $base_dir . config( 'deployer.version_dir' ) . '/' . $tag;
        $storage_dir           = $base_dir . config( 'deployer.storage_dir' );
        $db_dir                = $base_dir . config( 'deployer.db_dir' );
        $db_name               = config( 'deployer.db_name' );
        $db_user               = config( 'deployer.db_user' );
        $db_password           = config( 'deployer.db_password' );
        $db_host               = config( 'deployer.db_host' );
        $db_port               = config( 'deployer.db_port' );

        // Check if base dir exists
        if ( !file_exists( $base_dir ) ) {
            $this->info( 'Creating base dir' );
            mkdir( $base_dir, 0777, true );
        }

        // Check if version dir exists
        if ( !file_exists( $version_dir ) ) {
            $this->info( 'Creating version dir' );
            mkdir( $version_dir, 0777, true );
        }

        // Check if serve dir exists
        if ( !file_exists( $serve_dir ) ) {
            $this->info( 'Creating serve dir' );
            mkdir( $serve_dir, 0777, true );
        }

        // Check if storage dir exists
        if ( !file_exists( $storage_dir ) ) {
            $this->info( 'Creating storage dir' );
            mkdir( $storage_dir, 0777, true );
        }

        // Clone into version dir
        $this->info( 'Cloning into version dir' );
        exec( "git clone $git_remote_url $version_dir" );

        // Copy storage dir to version dir if storage directory is empty
        if ( count( scandir( $storage_dir ) ) == 2 ) {
            $this->info( 'Copying storage dir to version dir' );
            exec( "cp -r $version_dir/storage/. $storage_dir " );
        }

        // remove storage directory from version
        $this->info( 'Removing storage directory from version' );
        exec( "rm -rf $version_dir/storage" );

        // Link storage dir to version/storage
        $this->info( 'Linking storage dir to version/storage' );
        exec( "ln -s $storage_dir $version_dir" );

        // is there is no .env, copy .env.example to .env in base dir
        if ( !file_exists( $base_dir . '/.env' ) ) {
            $run_one_time_commands = true;
            $this->info( 'Copying .env.example to .env in base dir' );
            exec( "cp $version_dir/.env.example $base_dir/.env" );

            $path = "$base_dir/.env";
            if ( file_exists( $path ) ) {
                file_put_contents(
                    $path,
                    str_replace(
                        'APP_KEY=',
                        'APP_KEY=' . config( 'app.key' ),
                        file_get_contents( $path )
                    )
                );
            }
        }

        // Create db dir if not exsis
        if ( !file_exists( $db_dir ) ) {
            $this->info( 'Creating db dir' );
            mkdir( $db_dir, 0777, true );
        }

        // Dump DB to db dir
        $this->info( 'Dumping DB to db dir' );
        exec( "mysqldump -u $db_user -p$db_password -h $db_host -P $db_port $db_name > $db_dir/$db_name-$tag.sql" );

        // link .env to version/.env
        $this->info( 'Linking .env to version/.env' );
        exec( "ln -s $base_dir/.env $version_dir/.env" );

        // run composer install in version directory
        $this->info( 'Running composer install in version directory' );
        exec( "cd $version_dir && $commands" );

        // Remove any previous link of server dir
        $this->info( 'Removing any previous link of server dir' );
        exec( "rm -rf $serve_dir" );

        // Link version dir to serve dir
        $this->info( 'Linking version dir to serve dir' );
        exec( "ln -s $version_dir $serve_dir" );

        // run one time commands
        if ( $run_one_time_commands ) {
            $this->info( 'Running one time commands' );
            echo shell_exec( "cd $version_dir && $one_time_commands" );
        }

        $this->info( 'Deployed successfully' );
    }
}
