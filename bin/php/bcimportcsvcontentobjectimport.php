#!/usr/bin/env php
<?php
/**
 * File containing the bcimportcsvcontentobjectimport.php command line script
 *
 * @copyright Copyright (C) 1999 - 2016 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) 2013 - 2016 Think Creative. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or later)
 * @version 0.1.1
 * @package bcimportcsv
 */

/**
 * Add a starting timing point tracking script execution time
 */
$srcStartTime = microtime( true );

/**
 * Require eZ Publish autload system
 */
require 'autoload.php';

/**
 * Disable memory, time limit and enable line ending detection
 */

set_time_limit( 0 );

ini_set( "memory_limit", -1 );

ini_set( "auto_detect_line_endings", 1 );

/** Script startup and initialization **/

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => ( "bcimportcsvcontentobjectimport.php is a solution to import CSV content object content for eZ Publish Legacy" ),
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true ) );

$script->startup();

$options = $script->getOptions( "[scriptid:][script-verbose;][script-verbose-level;][havepublishdate][image-container:][class-identifier:][creator:][storage-dir:]",
                                "[parent-node][file]",
                                array( 'script-verbose' => 'Use this parameter to display verbose script output without disabling script iteration counting of images created or removed. Example: ' . "'--script-verbose'" . ' is an optional parameter which defaults to false',
                                       'script-verbose-level' => 'Use only with ' . "'--script-verbose'" . ' parameter to see more of execution internals. Example: ' . "'--script-verbose-level=3'" . ' is an optional parameter which defaults to 1 and works till 5',
                                       'node' => 'parent node_id to upload object under',
                                       'file' => 'file to read CSV data from',
                                       'class_identifier' => 'class identifier to create objects',
                                       'creator' => 'user id of imported objects creator',
                                       'havepublishdate' => 'first field is publish date',
                                       'image-container' => 'parent node id for imported embedded images',
                                       'storage-dir' => 'path to directory which will be added to the path of CSV elements' ),
                                false,
                                array( 'user' => true ) );
$script->initialize();

if ( count( $options['arguments'] ) < 2 )
{
    $cli->error( "Need a parent node to place object under and file to read data from" );
    $script->shutdown( 1 );
}

setlocale( LC_ALL, 'en_US.UTF-8' );

$scheduledScript = false;

/** Test for required script arguments **/

$parentNodeID = $options['arguments'][0];

$inputFileName = $options['arguments'][1];

$verbose = isset( $options['script-verbose'] ) ? true : false;

$scriptVerboseLevel = isset( $options['script-verbose-level'] ) ? $options['script-verbose-level'] : 1;

$troubleshoot = ( isset( $options['script-verbose-level'] ) && $options['script-verbose-level'] > 0 ) ? true : false;

$createClassIdentifier = $options['class-identifier'];
$creatorUserID = $options['creator'];
$havePublishDate = $options['havepublishdate'];
$imageContainerID = $options['image-container'];

$sectionID = 1;
$baseImageUrl = 'http://www.diariodelhuila.com';
$baseImageImportPath = "/tmp/imgtmp/";

$xmlBlockAttributeContentClassAttributeIdentifiers = array( 'title', 'short_title', 'tags', 'enable_comments', 'image', 'intro', 'body' );
$csvImportDelimiter = ',';
$csvImportDelimiterQuoteString = '"';

/** Login script to run as provided user. This may require specific user role policy permissions to see past content tree permissions, sections and other limitations **/

$currentUser = eZUser::currentUser();
$currentUser->logoutCurrent();
$user = eZUser::fetch( $creatorUserID );
$user->loginCurrent();

/** Fetch current user information **/

$currentUser = eZUser::currentUser();
$currentUserID = $currentUser->attribute( 'contentobject_id' );
$currentUserEmail = $currentUser->attribute( 'email' );
$currentUserName = eZContentObject::fetch( $currentUserID )->attribute( 'name' );

/** Fetch ezscriptmonitoer scriptid parameter **/

if ( isset( $options['scriptid'] ) and
     in_array( 'ezscriptmonitor', eZExtension::activeExtensions() ) and
     class_exists( 'eZScheduledScript' ) )
{
    $scriptID = $options['scriptid'];
    $scheduledScript = eZScheduledScript::fetch( $scriptID );
}

if ( isset( $options['storage-dir'] ) && $options['storage-dir'] != '' )
{
    $storageDir = $options['storage-dir'];
}
else
{
    $storageDir = '';
}

/** Display of execution time **/

function executionTimeDisplay( $srcStartTime, $cli )
{
    /** Add a stoping timing point tracking and calculating total script execution time **/

    $srcStopTime = microtime( true );
    $startTimeCalc = $srcStartTime;
    $stopTimeCalc = $srcStopTime;
    $executionTime = round( $srcStopTime - $srcStartTime, 2 );

    /** Alert the user to how long the script execution took place **/

    $cli->output( "This script execution completed in " . $executionTime . " seconds" . ".\n" );
}

// $csvLineLength = 100000;
$csvLineLength = 100000000000000000000000000000000000000000;

if( $verbose && $scriptVerboseLevel >= 1 )
{
    $cli->output( "Preparing to import objects of class $createClassIdentifier under node $parentNodeID from file $inputFileName" );
}

$node = eZContentObjectTreeNode::fetch( $parentNodeID );
if ( !$node )
{
    $cli->error( "No such node to import objects" );
    $script->shutdown( 1 );
}
// $parentObject = $node->attribute( 'object' );

$class = eZContentClass::fetchByIdentifier( $createClassIdentifier );
$classIdentifierText = $class->attribute( 'identifier' );
$className = $class->attribute( 'name' );

if ( !$class )
{
    $cli->error( "No class with identifier $createClassIdentifier" );
    $script->shutdown( 1 );
}

$fp = @fopen( $inputFileName, "r" );
$fpCsvFileSize = filesize( $inputFileName );
$fpCsvString = fread( $fp, $fpCsvFileSize );

if ( !$fp )
{
    $cli->error( "Can not open file $inputFileName for reading" );
    $script->shutdown( 1 );
}

$objectComplex = false;
$createDate = new eZDateTime();

$csvArray = str_getcsv( $fpCsvString, "\n" );
$csvArrayCount = count( $csvArray ) - 1;
$progressPercentage = ( 100 / $csvArrayCount );
$objectDataCount = 0;

rewind( $fp );

while ( ( $objectData = fgetcsv( $fp, $csvLineLength, $csvImportDelimiter, $csvImportDelimiterQuoteString ) ) !== FALSE )
{
    if( $verbose && $scriptVerboseLevel >= 1 )
    {
        $cli->output( 'Content import index: '. $objectDataCount );
    }

    if( $objectDataCount != 0 )
    {
        $contentObject = $class->instantiate( $creatorUserID );
        $contentObject->setAttribute( 'section_id', $sectionID );
        $contentObject->ClassName = $className;
        $contentObject->ClassIdentifier = $classIdentifierText;
        $contentObject->setCurrentLanguage( $contentObject->defaultLanguage() );
        $contentObject->store();

        $nodeAssignment = eZNodeAssignment::create( array(
            'contentobject_id' => $contentObject->attribute( 'id' ),
            'contentobject_version' => $contentObject->attribute( 'current_version' ),
            'parent_node' => $parentNodeID,
            'is_main' => 1
            )
        );
        $nodeAssignment->store();

        $version = $contentObject->version( 1 );
        $version->setAttribute( 'modified', eZDateTime::currentTimeStamp() );
        $version->setAttribute( 'status', eZContentObjectVersion::STATUS_DRAFT );
        $version->store();

        $attributes = $contentObject->attribute( 'contentobject_attributes' );
        $contentObjectID = $contentObject->attribute( 'id' );

        while ( list( $key, $attribute ) = each( $attributes ) )
        {
            if( $havePublishDate )
            {
                $dataKey = $key + 1;
            }
            else
            {
                $dataKey = $key;
            }
            $dataString = $objectData[ $dataKey ];

            switch ( $datatypeString = $attribute->attribute( 'data_type_string' ) )
            {
                case 'ezimage':
                case 'ezbinaryfile':
                case 'ezmedia':
                {
                    // $dataString = eZDir::path( array( $storageDir, $dataString ) );
                    $dataString = null;
                    break;
                }
                case 'ezdatetime':
                case 'ezboolean':
                case 'ezkeyword':
                {
                    $dataString = null;
                    if( $attribute->ContentClassAttributeIdentifier != 'tags' )
                    {
                      $dataString = null;
                    }
                    break;
                }
                case 'ezxmltext':
                {
                    if( $attribute->ContentClassAttributeIdentifier == 'caption' )
                    {
                        $dataString = null;
                        break;
                    }
                    // Filter for images, process, store and link
                    if ( is_numeric( $imageContainerID ) )
                    {
                        $matches = array();
                        $pattern = '/<img\b[^>]*\bsrc=(\\\\["\'])?((?(1)(?:(?!\1).)*|[^\s>]*))(?(1)\1)[^>]*>/si';
                        preg_match_all( $pattern, $dataString, $matches );
                        $matches_count = count( $matches[2] );

                        if( $verbose && $scriptVerboseLevel >= 2 )
                        {
                            $cli->output( print_r( $matches ) );
                        }

                        if ( $matches_count > 0 )
                        {
                            $toReplace = array();
                            $replacements = array();
                            $objectComplex = true;

                            if( $verbose && $scriptVerboseLevel >= 1 )
                            {
                                $cli->output( "Matches Count: " . $matches_count );
                            }

                            // $imagenr = 0;
                            foreach ( $matches[2] as $key => $match )
                            {
                                $toReplace[] = $matches[0][$key];
                                $imageURL = trim( str_replace(chr(32), '%20', str_replace(' ', '%20', str_replace('\"', '', $matches[2][$key] ) ) ) );

                                if ( substr($imageURL, 0, 1) == '/')
                                {
                                    $imageURL = $baseImageUrl . $imageURL;
                                }

                                if( $verbose && $scriptVerboseLevel >= 1 )
                                {
                                    $cli->output( "Image link: " . $imageURL );
                                }

                                $imageTempURL = 'http://optics.kulgun.net/Blue-Sky/red-sunset-casey1.jpg';
                                $imageTempFileName = 'def.jpg';

                                $imageFileName = basename( $imageURL );

                                if( $verbose && $scriptVerboseLevel >= 1 )
                                {
                                    $cli->output( 'Image File Name: '.$imageFileName );
                                }

                                $imagePath = $baseImageImportPath . $imageTempFileName;
                                // $imagePath = "/tmp/imgtmp" . $imagenr++ . ".jpg";

                                if ( !copy( $imageTempURL, $imagePath ) )
                                {
                                    $cli->output( "Error copying image from remote server: $imageTempURL" );
                                    $replacements[] = '';
                                }
                                else
                                {
                                    $imageClass = eZContentClass::fetchByIdentifier( 'image' );
                                    $imageObject = $imageClass->instantiate( $creatorUserID );
                                    $imageObject->store();

                                    $imageObjectID = $imageObject->attribute( 'id' );

                                    $imageNodeAssignment = eZNodeAssignment::create( array(
                                                                                     'contentobject_id' => $imageObject->attribute( 'id' ),
                                                                                     'contentobject_version' => $imageObject->attribute( 'current_version' ),
                                                                                     'parent_node' => $imageContainerID,
                                                                                     'is_main' => 1
                                                                                 )
                                                                             );
                                    $imageNodeAssignment->store();

                                    $imageVersion = $imageObject->version( 1 );
                                    $imageVersion->setAttribute( 'modified', $createDate );
                                    $imageVersion->setAttribute( 'status', eZContentObjectVersion::STATUS_DRAFT );
                                    $imageVersion->store();

                                    $imageAttributes = $imageObject->attribute( 'contentobject_attributes' );

                                    if( $verbose && $scriptVerboseLevel >= 1 )
                                    {
                                        $cli->output( "Image attributes: " . $cli->output( $imageAttributes, true ) );
                                    }

                                    $imageAttributes[0]->fromString( $imageTempFileName );
                                    $imageAttributes[0]->store();

                                    $imageAttributes[2]->fromString( $imagePath );
                                    $imageAttributes[2]->store();

                                    $operationResult = eZOperationHandler::execute( 'content', 'publish',
                                                       array( 'object_id' => $imageObjectID, 'version' => 1 ) );

                                    $replacements[] = '<embed href="ezobject://' . $imageObject->attribute( 'id' ) . '" size="original" />';
                                }
                            }

                            $dataString = str_replace( $toReplace, $replacements, $dataString );

                            if( $verbose && $scriptVerboseLevel >= 2 )
                            {
                                $cli->output( print_r( $toReplace ) );
                            }

                            if( $verbose && $scriptVerboseLevel >= 3 )
                            {
                                $cli->output( print_r( $replacements ) );
                                $cli->output( $dataString ); echo "\n";
                            }

                            unset( $toReplace ); unset( $replacements ); unset( $imageAttributes ); unset( $imageNodeAssignment );
                            }
                        }

                        $parser = new eZSimplifiedXMLInputParser( $contentObjectID, false, 0 );
                        $document = $parser->process( $dataString );
                        $dataString = eZXMLTextType::domString( $document );

                        if( $verbose && $scriptVerboseLevel >= 3 )
                        {
                            $cli->output( print_r( $dataString ) );
                        }

                        // Fetch links
                        $links = $document->getElementsByTagName( 'link' );
                        if( is_numeric( $links->length ) && $links->length > 0 && is_object( $links ) )
                        {
                            $li = 0;

                            if( $verbose && $scriptVerboseLevel >= 4 )
                            {
                               $cli->output( print_r( $links ) );
                            }

                            // For each link
                            for( $li = 0; $li < $links->length; $li++ )
                            {
                               $linkNode = $links->item( $li );
                               $url_id = $linkNode->getAttribute( 'url_id' );

                                if( $verbose && $scriptVerboseLevel >= 3 )
                                {
                                    $cli->output( 'Link Item Count: '. $li );
                                    $cli->output( 'Link Item ID: '. $url_id );
                                }

                                if( is_numeric( $url_id ) )
                                {
                                    // Create link between url (link) and object
                                    $eZURLObjectLink = eZURLObjectLink::create( $url_id,
                                    $contentObject->attribute('id'),
                                    $contentObject->attribute('current_version') );

                                    if( $verbose && $scriptVerboseLevel >= 5 )
                                    {
                                        $cli->output( print_r( $eZURLObjectLink ) );
                                        // $cli->output( print_r( $url_id ) );
                                    }

                                    $eZURLObjectLink->store();
                               }
                            }
                        }
                    }
                    break;
                    default:
                }

                if ( $attribute->ContentClassAttributeIdentifier == 'author' )
                {
                    $author = new eZAuthor();
                    $author->addAuthor( $creatorUserID, $currentUserName, $currentUserEmail );
                    $attribute->setContent( $author );
                } elseif( $attribute->ContentClassAttributeIdentifier == 'intro' or
                          $attribute->ContentClassAttributeIdentifier == 'body' )
                {
                    $attribute->fromString( $dataString );
                }
                else
                {
                    if( in_array( $attribute->ContentClassAttributeIdentifier, $xmlBlockAttributeContentClassAttributeIdentifiers ) )
                    {
                        $attribute->fromString( $dataString );
                    }
                    else
                    {
                        $attribute->fromString( $dataString );

                        if( $verbose && $scriptVerboseLevel >= 4 )
                        {
                            $cli->output( "Content: " . $dataString );
                        }
                    }
                    /*
                    else
                    {
                        $attribute->fromString( $dataString );
                        $attribute->setContent( $dataString );
                        $cli->output( "Content: " . $dataString );
                    }
                    */
                }

                $attribute->store();

                unset( $attribute );
            } // end of second while loop

            $contentObject->store();

            $operationResult = eZOperationHandler::execute( 'content', 'publish',
                               array( 'object_id' => $contentObjectID, 'version' => 1 ) );

            if( $operationResult != false )
            {
                if( $havePublishDate )
                {
                    // Format: 2007-05-14 12:05:01
                    $publishDate = $objectData[0];

                    // Set creation date.
                    $publishDateParts = explode( ' ', $publishDate );
                    $dateParts = explode( '-', $publishDateParts[0] );
                    $timeParts = explode( ':', $publishDateParts[1] );
                    $createDate->setMDYHMS( $dateParts[2], $dateParts[1], $dateParts[0], $timeParts[0], $timeParts[1], $timeParts[2] );
                    $contentObject->setAttribute( 'published', $createDate->timeStamp() );
                    $contentObject->setAttribute( 'modified', $createDate->timeStamp() );
                }
                else
                {
                    $contentObject->setAttribute( 'published', $createDate->timeStamp() );
                    $contentObject->setAttribute( 'modified', $createDate->timeStamp() );
                }

                $contentObject->store();
                // eZContentCacheManager::clearContentCache( $contentObject->attribute( 'id' ) );

                $nodeAssignment = eZContentObjectTreeNode::fetchByContentObjectID( $contentObject->attribute( 'id' ) );

                if( count( $nodeAssignment ) > 0 )
                {
                    if( $verbose && $scriptVerboseLevel >= 4 )
                    {
                        $cli->output( 'Content Object Name: '. $nodeAssignment[0]->getName() ."\n" );
                        $cli->output( 'Content Object parentNodeID: '. $nodeAssignment[0]->NodeID ."\n" );
                    }
                    elseif( $verbose && $scriptVerboseLevel >= 2 )
                    {
                        $cli->output( 'Content Object URL: '. $nodeAssignment[0]->urlAlias( ) );
                    }
                }
            } // test $operationResult != false

            unset( $contentObject );
            unset( $attributes );
            unset( $nodeAssignment );
            unset( $version );
            unset( $objectData );
            unset( $operationResult );
        } // end first record exclusion if

        if ( $scheduledScript )
        {
            $cli->output( 'Progress: ' . round( $progressPercentage, 2 ) . '%' );
            $scheduledScript->updateProgress( $progressPercentage );
        }

        $objectDataCount = $objectDataCount +1;
        $progressPercentage = $progressPercentage * $objectDataCount;
} // end first while loop

fclose( $fp );

unset( $fp );
unset( $node );
unset( $class );
unset( $foo );

/** Call for display of execution time **/

executionTimeDisplay( $srcStartTime, $cli );

$script->shutdown();

?>