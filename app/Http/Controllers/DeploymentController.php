<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;

class DeploymentController extends Controller {
    /**
     * @return mixed
     */
    public function index() {
        $serve_dir   = config( 'deployer.serve_dir' );
        $base_dir    = config( 'deployer.base_dir' );
        $version_dir = $base_dir . config( 'deployer.version_dir' );
        $db_dir      = $base_dir . config( 'deployer.db_dir' );
        $live        = null;

        // check if exisit serve directory
        if ( file_exists( $serve_dir ) ) {
            // get the link directory with serve directory
            $live = @explode( '/', readlink( "$serve_dir/app" ) );
            $live = $live[count( $live ) - 2] ?? null;
        }

        // Check if base dir exists
        if ( !file_exists( $base_dir ) ) {
            mkdir( $base_dir, 0777, true );
        }

        // Check if version dir exists
        if ( !file_exists( $version_dir ) ) {
            mkdir( $version_dir, 0777, true );
        }

        // Check if db dir exists
        if ( !file_exists( $db_dir ) ) {
            mkdir( $db_dir, 0777, true );
        }

        $db_files = array_diff( scandir( $db_dir ), ['.', '..'] );
        // make array with file name and created date time
        $db_files = array_map( function ( $file ) use ( $db_dir ) {
            return [
                'name'       => $file,
                'created_at' => date( 'Y-m-d H:i:s', filectime( $db_dir . '/' . $file ) ),
            ];
        }, $db_files );

        // Sort db files by created date time
        usort( $db_files, function ( $a, $b ) {
            return $a['created_at'] <=> $b['created_at'];
        } );

        // Reverse the order
        $db_files = array_reverse( $db_files );

        // Read folders in version dir
        $folders = array_diff( scandir( $version_dir ), ['.', '..'] );

        // remove files from the array
        $folders = array_filter( $folders, function ( $folder ) use ( $version_dir ) {
            return is_dir( $version_dir . '/' . $folder );
        } );

        // Make an array with folder anme and created date time
        $folders = array_map( function ( $folder ) use ( $version_dir ) {
            return [
                'name'       => $folder,
                'created_at' => date( 'Y-m-d H:i:s', filectime( $version_dir . '/' . $folder ) ),
            ];
        }, $folders );

        // Sort folders by created date time
        usort( $folders, function ( $a, $b ) {
            return $a['created_at'] <=> $b['created_at'];
        } );

        // Reverse the order
        $folders = array_reverse( $folders );

        return view( 'home', compact( 'folders', 'live', 'db_files' ) );
    }

    /**
     * @param $folder
     */
    public function download( $folder ) {
        $base_dir    = config( 'deployer.base_dir' );
        $version_dir = $base_dir . config( 'deployer.version_dir' );
        $zip_file    = $version_dir . '/' . $folder . '.zip';

        // Check if zip file exists
        if ( !file_exists( $zip_file ) ) {
            $this->zip( $folder );
        }

        // Download zip file
        return response()->download( $zip_file );
    }

    /**
     * @param $folder
     */
    public function restore( $folder ) {
        $serve_dir   = config( 'deployer.serve_dir' );
        $base_dir    = config( 'deployer.base_dir' );
        $version_dir = $base_dir . config( 'deployer.version_dir' ) . '/' . $folder;

        // Check if folder exists
        if ( !file_exists( $version_dir ) ) {
            return back()->with( 'error', 'Folder not found' );
        }

        // Remove any previous link of server dir
        exec( "rm -rf $serve_dir/*" );

        // Link version dir to serve dir
        exec( "ln -s $version_dir/* $serve_dir" );

        return redirect()->back()->with( 'success', 'Restored successfully' );
    }

    /**
     * @param $file
     */
    public function downloadDb( $file ) {
        $base_dir = config( 'deployer.base_dir' );
        $db_dir   = $base_dir . config( 'deployer.db_dir' );

        // Check if file exists
        if ( !file_exists( $db_dir . '/' . $file ) ) {
            return back()->with( 'error', 'File not found' );
        }

        // Download file
        return response()->download( $db_dir . '/' . $file );
    }

    /**
     * @param $file
     */
    public function restoreDb( $file ) {
        $base_dir    = config( 'deployer.base_dir' );
        $db_dir      = $base_dir . config( 'deployer.db_dir' );
        $db_name     = config( 'deployer.db_name' );
        $db_user     = config( 'deployer.db_user' );
        $db_password = config( 'deployer.db_password' );
        $db_host     = config( 'deployer.db_host' );
        $db_port     = config( 'deployer.db_port' );

        // Check if file exists
        if ( !file_exists( $db_dir . '/' . $file ) ) {
            return back()->with( 'error', 'File not found' );
        }

        // Restore database
        exec( "mysql -u $db_user -p$db_password -h $db_host -P $db_port $db_name < $db_dir/$file" );

        return redirect()->back()->with( 'success', 'Restored successfully' );
    }

    public function deploy() {
        Artisan::call( 'deploy' );

        return redirect()->back()->with( 'success', 'Deployed successfully' );
    }

    /**
     * @param $folder
     */
    protected function zip( $folder ) {
        $base_dir    = config( 'deployer.base_dir' );
        $version_dir = $base_dir . config( 'deployer.version_dir' );
        $zip_file    = $version_dir . '/' . $folder . '.zip';

        // Create zip file
        $zip = new \ZipArchive ();
        $zip->open( $zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );

        // Create recursive directory iterator
        $files = new \RecursiveIteratorIterator (
            new \RecursiveDirectoryIterator ( $version_dir . '/' . $folder ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ( $files as $name => $file ) {
            // Skip directories (they would be added automatically)
            if ( !$file->isDir() ) {
                // Get real and relative path for current file
                $filePath     = $file->getRealPath();
                $relativePath = substr( $filePath, strlen( $version_dir . '/' . $folder ) + 1 );

                // Add current file to archive
                $zip->addFile( $filePath, $relativePath );
            }
        }

        // Zip archive will be created only after closing object
        $zip->close();
    }
}
