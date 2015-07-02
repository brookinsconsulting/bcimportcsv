<?php
/**
 * File containing the bcimportcsv/download module view.
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
$offset = 0;
$classID = 56;
$phpExec = 'php';

/**
* Default database instance
*/
$db = eZDB::instance();

/** Parse HTTP POST variables **/
$http = eZHTTPTool::instance();

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

/** Schedule import task **/
$script = eZScheduledScript::create(
    'userexport.php',
    "extension/site/cronjobs/exportusernames.php --php-exec=$phpExec --user-class-id=$classID -s $adminSiteAccessName --topNodeID=$nodeID --offset=$offset --limit=$limit --email=$currentUserEmail"
);

$script->store();

/** Fetch scheduled task id **/
$result = $db->arrayQuery( "SELECT id FROM `ezscheduled_script` WHERE user_id = $currentUserID ORDER BY id DESC" );
$scriptID = $result[0]['id'];

/** Redirect user to scheduled task status for review of execution **/
$module->redirectTo( "/$adminSiteAccessName/scriptmonitor/view/$scriptID" );
// header("Location: /$adminSiteAccessName/scriptmonitor/view/$scriptID");

?>