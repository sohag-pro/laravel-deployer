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
    protected $description = 'New Deployment';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {
        $tag                   = now()->format( 'Y-m-d__H_i_s' );
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
            mkdir( $base_dir, 0777, true );
        }

        // Check if version dir exists
        if ( !file_exists( $version_dir ) ) {
            mkdir( $version_dir, 0777, true );
        }

        // Check if serve dir exists
        if ( !file_exists( $serve_dir ) ) {
            mkdir( $serve_dir, 0777, true );
        }

        // Check if storage dir exists
        if ( !file_exists( $storage_dir ) ) {
            mkdir( $storage_dir, 0777, true );
        }

        // Clone into version dir
        exec( "git clone $git_remote_url $version_dir" );

        // Copy storage dir to version dir if storage directory is empty
        if ( count( scandir( $storage_dir ) ) == 2 ) {
            exec( "cp -r $version_dir/storage/. $storage_dir " );
        }

        // remove storage directory from version
        exec( "rm -rf $version_dir/storage" );

        // Link storage dir to version/storage
        exec( "ln -s $storage_dir $version_dir" );

        // is there is no .env, copy .env.example to .env in base dir
        if ( !file_exists( $base_dir . '/.env' ) ) {
            $run_one_time_commands = true;
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
            mkdir( $db_dir, 0777, true );
        }

        // Dump DB to db dir
        exec( "mysqldump -u $db_user -p$db_password -h $db_host -P $db_port $db_name > $db_dir/$db_name-$tag.sql" );

        // link .env to version/.env
        exec( "ln -s $base_dir/.env $version_dir/.env" );

        // run composer install in version directory
        exec( "cd $version_dir && $commands" );

        // Get after deploy commands
        $after_deploy_commands = explode( ',', config( 'deployer.after_deploy' ) );
        foreach ( $after_deploy_commands as $command ) {
            echo shell_exec( "cd $version_dir && $command" );
        }

        // look for file named afterDeploy.sh in version dir and run it
        if ( file_exists( "$version_dir/afterDeploy.sh" ) ) {
            echo shell_exec( "cd $version_dir && sh afterDeploy.sh" );
        }

        // Remove any previous link of server dir
        exec( "rm -rf $serve_dir/*" );

        // Link version dir to serve dir
        exec( "ln -s $version_dir/* $serve_dir" );

        // run one time commands
        if ( $run_one_time_commands ) {
            echo shell_exec( "cd $version_dir && $one_time_commands" );
        }

    }
}
