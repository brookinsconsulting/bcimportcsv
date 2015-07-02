#!/usr/bin/env php
<?php

// Script for finding and handling content_objects that are not completely created
// That may occur under some circustanses when using a database without transations enabled
//
// 2007.10.09, jonny.bergkvist@hit.no

// $doUpdate, true or false. Set to false for at dry test-run

require 'autoload.php';

$doUpdate = false;

include_once( 'kernel/common/template.php' );
include_once( "lib/ezutils/classes/ezhttptool.php" );
include_once( 'lib/ezutils/classes/ezcli.php' );
include_once( 'kernel/classes/ezscript.php' );
include_once( 'lib/ezdb/classes/ezdb.php' );

$cli = eZCLI::instance();
$script =& eZScript::instance();
$script = eZScript::instance( array( 'debug-message' => '',
                                      'use-session' => true,
                                      'use-modules' => true,
                                      'use-extensions' => true ) );

$script->startup();

$siteaccess = 'stdl';
$script->setUseSiteAccess( $siteaccess );
$cli->notice( "Using siteaccess $siteaccess for cronjob" );

$script->initialize();

$db =& eZDB::instance();

set_time_limit( 0 );

$arrayResult1 = $db->arrayQuery( "SELECT id, contentclass_id, current_version FROM ezcontentobject" );
echo "First checking for content objects that has no contentobject_attributes at all...\n";

$i = 0;
foreach( $arrayResult1 as $item) {
        //check if object has no attributes of any version stored
	print_r("SELECT contentobject_id FROM ezcontentobject_attribute WHERE contentobject_id = " . $item['id']. "\n");
        $hasAttribute = $db->arrayQuery( "SELECT contentobject_id FROM ezcontentobject_attribute WHERE contentobject_id = " . $item['id'] );
        if ( empty( $hasAttribute ) ) {
                if ( $doUpdate ) {
                        echo "Corrupt object, no attributes: " . $item['id'] . ". Deleting corrupt object with no attributes...\n";
                        $db->query( "DELETE FROM ezcontentobject WHERE ezcontentobject.id = " . $item['id'] );
                        $db->query( "DELETE FROM ezcontentobject_name WHERE ezcontentobject_name.contentobject_id = " . $item['id'] );
                        if ( $item['contentclass_id'] == 4 ) {
                                $db->query( "DELETE FROM ezuser WHERE ezuser.contentobject_id = " . $item['id'] );
                        }
                }
           else {
               echo "Corrupt object, no attributes: " . $item['id'] . ", current_version:" . $item['current_version'] . "\n";        
           }
        $i++;
        }
}

echo "Total corrupt objects with no attributes: " . $i . "\n\n";

$arrayResult2 = $db->arrayQuery( "SELECT id, current_version FROM ezcontentobject" );
print_r("SELECT id, current_version FROM ezcontentobject\n");
echo "Then checking for content objects that has contentobject_attributes, but not of the current_version...\n";

$i = 0;
foreach( $arrayResult2 as $item) {
        //check if current_version has content attributes
        $hasAttribute = $db->arrayQuery( "SELECT contentobject_id FROM ezcontentobject_attribute WHERE contentobject_id = " . $item['id'] . " AND version = " . $item['current_version'] );

        if ( empty( $hasAttribute ) ) {
                if ( $doUpdate ) {
                        $previousCurrentVersion = $item['current_version'] - 1;
                                echo "Corrupt object: " . $item['id'] . ", current_version: " . $item['current_version'] . ". Setting back to version: " . $previousCurrentVersion . "\n";
                        $db->query( "UPDATE ezcontentobject SET current_version = " . $previousCurrentVersion . " WHERE id = " . $item['id'] );
                }
                else {
                        echo "Corrupt object: " . $item['id'] . ", current_version: " . $item['current_version'] . "\n";
                }
        $i++;
        }
}

echo "Total objects with wrong current_version: " . $i . "\n";

$script->shutdown();

?>