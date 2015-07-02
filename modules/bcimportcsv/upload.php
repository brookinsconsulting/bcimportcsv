<?php
/**
 * File containing the bcimportcsv/upload module view.
 *
 * @copyright Copyright (C) 1999 - 2016 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) 2013 - 2016 Think Creative. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or later)
 * @version 0.1.1
 * @package bcimportcsv
 */

/**
 * Disable memory and time limit
 */
set_time_limit( 0 );
ini_set( "memory_limit", -1 );

/**
 * Default module parameters
 */
$module = $Params["Module"];
$nodeID = $Params['NodeID'];

/**
* Default variable values
*/
$limit = 9999999999;
$randomLimit = 99999999999999;
$offset = 0;
$classID = 56;
$phpExec = 'php';
$uploadFileIdentifier = 'importcsvfile';
$path = array();
$uploadStorageDirectory = 'var/bcimportcsv';

/**
* Default database instance
*/
$db = eZDB::instance();

/** Parse HTTP POST variables **/
$http = eZHTTPTool::instance();
$importClassIdentifier = $http->postVariable( 'class_identifier' );

/** Get current user information **/
$currentUser = eZUser::currentUser();
$currentUserID = $currentUser->attribute( 'contentobject_id' );
$currentUserEmail = $currentUser->attribute( 'email' );

/** Access system variables **/
$sys = eZSys::instance();

/** Init template behaviors **/
$tpl = eZTemplate::factory();

/** Access ini variables **/
$ini = eZINI::instance();
$iniBcimportcsv = eZINI::instance( 'bcimportcsv.ini' );

/** Report file variables **/
$storageDirectory = eZSys::cacheDirectory();
$contentTreeContentCsvReportName = 'bcimportcsv';
$contentTreeContentCsvReportFileName = $contentTreeContentCsvReportName;
$contentTreeContentCsvReportFileNameWithExtension = $contentTreeContentCsvReportName . '.csv';
$contentTreeContentCsvReportFileNameWithExtensionFullPath = $storageDirectory . '/' . $contentTreeContentCsvReportFileNameWithExtension;

/** Default variables **/
$siteNodeUrlHostname = $ini->variable( 'SiteSettings', 'SiteURL' );
$adminSiteAccessName = $iniBcimportcsv->variable( 'BCImportCsvSettings', 'AdminSiteAccessName' );
$currentSiteAccessName = eZSiteAccess::current();

/** Uploaded File **/
$file = eZHTTPFile::fetch( $uploadFileIdentifier );

if ( $file instanceof eZHTTPFile )
{
    $currentTimestamp = time();
    $randomInteger = mt_rand( 1, $randomLimit );
    $temporaryOriginalFileName = $file->attribute( 'original_filename' );
    $temporaryFileName = $file->attribute( 'filename' );
    $temporaryUniqueFileName = $uploadStorageDirectory . '/' . $currentTimestamp . '_' . $randomInteger . '_' . $temporaryOriginalFileName;

    // var_dump( $file );
    // var_dump( $_FILES ); die();
    // $tmpFile = $_FILES['importcsvfile']['tmp_name'];
    // var_dump( $file->attributes() ); die();

    if( !is_dir( $uploadStorageDirectory ) )
    {
        mkdir( $uploadStorageDirectory );
    }

    $fh = fopen( $temporaryUniqueFileName, 'w' ) or die( "Can not open temporary file file: $temporaryUniqueFileName" );
    fwrite( $fh, file_get_contents( $temporaryFileName ) );
    fclose( $fh );

    /** Schedule import task **/
    //p --class-identifier=$importClassIdentifier --creator=$currentUserID $nodeID ./var/bcimportcsv/67_0001.csv
    //p --class-identifier=$importClassIdentifier --creator=$currentUserID $nodeID $temporaryUniqueFileName
    $script = eZScheduledScript::create(
        'bcimportcsvcontentobjectimport.php',
        "extension/bcimportcsv/bin/php/bcimportcsvcontentobjectimport.php --class-identifier=$importClassIdentifier --creator=$currentUserID $nodeID $temporaryUniqueFileName"
    );
    $script->store();

    /** Fetch scheduled task id **/
    $result = $db->arrayQuery( "SELECT id FROM `ezscheduled_script` WHERE user_id = $currentUserID ORDER BY id DESC" );
    $scriptID = $result[0]['id'];
}

$tpl->setVariable( 'script_id', $scriptID );
$tpl->setVariable( 'email', $currentUserEmail );

$path[] = array( 'url' => false,
                 'text' => 'BC Import CSV / Upload' );

$Result['content'] = $tpl->fetch( "design:bcimportcsv/upload.tpl" );
$Result['path'] = $path;

?>