#!/usr/bin/env php
<?php
//
// Definition of bccsvhtmlimport class
//
// Created on: <17-May-2009 22:23:27 gb>
//
// SOFTWARE NAME: bccsvhtmlimport
// SOFTWARE RELEASE: 0.1
// COPYRIGHT NOTICE: Copyright (C) 2009 Brookins Consulting
// SOFTWARE LICENSE: GNU General Public License v2.0 (or later)
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0 or later of the GNU General
//   Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
//
//

/*! \file
*/

require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => ( "eZ Publish CSV html import script\n\n" .
                                                        "\n" .
                                                        "\n" .
                                                        "\n" .
                                                        "\n" .
                                                        "" ),
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true ) );

$script->startup();

$options = $script->getOptions( "[havepublishdate][image-container:][class:][creator:][storage-dir:]",
                                "[node][file]",
                                array( 'node' => 'parent node_id to upload object under',
                                       'file' => 'file to read CSV data from',
                                       'class' => 'class identifier to create objects',
                                       'creator' => 'user id of imported objects creator',
                                       'havepublishdate' => 'first field is publish date',
                                       'image-container' => 'parent node id for imported embedded images',
                                       'storage-dir' => 'path to directory which will be added to the path of CSV elements' ),
                                false,
                                array( 'user' => true ));
$script->initialize();

if ( count( $options['arguments'] ) < 2 )
{
    $cli->error( "Need a parent node to place object under and file to read data from" );
    $script->shutdown( 1 );
}

setlocale(LC_ALL, 'en_US.UTF-8');

$nodeID = $options['arguments'][0];
$inputFileName = $options['arguments'][1];
$createClass = $options['class'];
$creator = $options['creator'];
$havePublishDate = $options['havepublishdate'];
$imageContainerID = $options['image-container'];

if ( $options['storage-dir'] )
{
    $storageDir = $options['storage-dir'];
}
else
{
    $storageDir = '';
}

// $csvLineLength = 100000;
$csvLineLength = 100000000000000000000000000000000000000000;

$cli->output( "Preparing to import objects of class $createClass under node $nodeID from file $inputFileName" );

$node = eZContentObjectTreeNode::fetch( $nodeID );
if ( !$node )
{
    $cli->error( "No such node to import objects" );
    $script->shutdown( 1 );
}
// $parentObject = $node->attribute( 'object' );

$class = eZContentClass::fetchByIdentifier( $createClass );

if ( !$class )
{
    $cli->error( "No class with identifier $createClass" );
    $script->shutdown( 1 );
}

$fp = @fopen( $inputFileName, "r" );
if ( !$fp )
{
    $cli->error( "Can not open file $inputFileName for reading" );
    $script->shutdown( 1 );
}

$objectComplex = false;
$objectDataCount = 0;
$sectionID = 1;
$createDate = new eZDateTime();

while ( ( $objectData = fgetcsv( $fp, $csvLineLength, '|', '"' ) ) !== FALSE )
{
    $cli->output( 'Content Object Index: '. $objectDataCount );

    if( $objectDataCount != 0 ) //  && $objectDataCount < 10 )
    {
        $contentObject = $class->instantiate( $creator );
        $contentObject->setName( $objectData[1], 1 );
        $contentObject->Name = $objectData[1];
        $contentObject->setAttribute( 'section_id', $sectionID );
        $contentObject->ClassName = 'Article';
        $contentObject->ClassIdentifier = 'article';
	$contentObject->setCurrentLanguage( $contentObject->defaultLanguage( ) );
        $contentObject->store();

        $nodeAssignment = eZNodeAssignment::create( array(
                            'contentobject_id' => $contentObject->attribute( 'id' ),
                            'contentobject_version' => $contentObject->attribute( 'current_version' ),
                            'parent_node' => $nodeID,
                            'is_main' => 1
                         )
        );
        $nodeAssignment->store();

        $version = $contentObject->version( 1 );
        $version->setAttribute( 'status', eZContentObjectVersion::STATUS_DRAFT );
        $version->store();

        $attributes = $contentObject->attribute( 'contentobject_attributes' );
        $contentObjectID = $contentObject->attribute( 'id' );

        // $cli->output( $cli->output( $objectData ) );

        while ( list( $key, $attribute ) = each( $attributes ) )
        {
        if( $havePublishDate ) {
            $dataKey = $key+1;
        } else {
            $dataKey = $key;
        }
        $dataString = $objectData[$dataKey];

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
		if( $attribute->ContentClassAttributeIdentifier != 'tags' ) {
		  $dataString = null;
		}
                break;
            }
            case 'ezxmltext':
            {
		if( $attribute->ContentClassAttributeIdentifier == 'caption' ) {
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
  		    // $cli->output( print_r( $matches ) );

                    if ( $matches_count > 0 )
                    {
                        $toReplace = array();
                        $replacements = array();
			$objectComplex = true;

			$cli->output( "Matches Count: " . $matches_count );

                        // $imagenr = 0;
                        foreach ( $matches[2] as $key => $match )
                        {
				$toReplace[] = $matches[0][$key];
				$imageURL = trim( str_replace(chr(32), '%20', str_replace(' ', '%20', str_replace('\"', '', $matches[2][$key] ) ) ) );
                                if ( substr($imageURL, 0, 1) == '/') {
					$imageURL = 'http://www.diariodelhuila.com' . $imageURL;
        	                }
                                $cli->output("Image link: " . $imageURL);

                            	$imageTempURL = 'http://optics.kulgun.net/Blue-Sky/red-sunset-casey1.jpg';
				$imageTempFileName = 'def.jpg';

				$imageFileName = basename( $imageURL );
				$cli->output( 'Image File Name: '.$imageFileName );

				$imagePath = "/tmp/imgtmp/" . $imageTempFileName;
                                // $imagePath = "/tmp/imgtmp" . $imagenr++ . ".jpg";

                                if ( !copy($imageTempURL, $imagePath ) )
                                {
                                    $cli->output("Error copying image from remote server");
                                    $replacements[] = '';
                                }else {
                                    $imageClass = eZContentClass::fetchByIdentifier( 'image' );
                                    $imageObject = $imageClass->instantiate( $creator );
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
                                    $cli->output("Image attributes:" . $cli->output( $imageAttributes, true ) );
                                
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
	     		    $cli->output( print_r( $toReplace ) );
			    // $cli->output( print_r( $replacements ) );
			    // $cli->output( $dataString ); echo "\n";
			    unset( $toReplace ); unset( $replacements ); unset( $imageAttributes ); unset( $imageNodeAssignment );
                        }
                    }

                    $parser = new eZSimplifiedXMLInputParser( $contentObjectID, false, 0 );
                    $document = $parser->process( $dataString );
                    // $dataString = eZXMLTextType::domString( $document );
		    // $cli->output( print_r( $dataString ) );

		    // get links
		    $links = $document->getElementsByTagName( 'link' );
		    if( is_numeric( $links->length ) && $links->length > 0 && is_object( $links ) ) {
  		        // $cli->output( print_r( $links ) );
			$li = 0;
                        // for each link
	                for( $li = 0; $li < $links->length; $li++ )
                        { 
			   $linkNode = $links->item( $li );
			   $url_id = $linkNode->getAttribute( 'url_id' );

			   $cli->output( 'Link Item Count: '. $li );
                           $cli->output( 'Link Item ID: '. $url_id );

			   if( is_numeric( $url_id ) ) {
                               // create link between url (link) and object
                               $eZURLObjectLink = eZURLObjectLink::create( $url_id,
                                                  $contentObject->attribute('id'),
                                                  $contentObject->attribute('current_version') );
		               $cli->output( print_r( $eZURLObjectLink ) );
		               // $cli->output( print_r( $url_id ) );

        	               $eZURLObjectLink->store();
                           }
	                }
                    }
                }break;
                default:
            }

            if ( $attribute->ContentClassAttributeIdentifier == 'author' )
            {
                $author1 = new eZAuthor();
                $author1->addAuthor( 14, 'Adminstrator User', 'info@example.com');
	        $attribute->setContent( $author1 );
	    } elseif( $attribute->ContentClassAttributeIdentifier == 'intro' or 
		     $attribute->ContentClassAttributeIdentifier == 'body' ) {
		$attribute->fromString( $dataString );
	    } else {
	      if(
	        $attribute->ContentClassAttributeIdentifier == 'title' 
	        or $attribute->ContentClassAttributeIdentifier == 'short_title'	
		or $attribute->ContentClassAttributeIdentifier == 'tags'
		or $attribute->ContentClassAttributeIdentifier == 'enable_comments'
	        or $attribute->ContentClassAttributeIdentifier == 'image'
	        or $attribute->ContentClassAttributeIdentifier == 'intro'
    		or $attribute->ContentClassAttributeIdentifier == 'body'
	      ) {
           	$attribute->fromString( $dataString );
	      } /* else {
	        // $attribute->setContent( $dataString );
        	// $cli->output($attribute);
	      } */
	    }
            $attribute->store();
	    unset( $attribute );
        } // end of second while loop

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
                $createDate->setMDYHMS( $dateParts[2], $dateParts[1], $dateParts[0], $timeParts[0], $timeParts[1], $timeParts[2]);
                $contentObject->setAttribute( 'published', $createDate->timeStamp() );
                $contentObject->setAttribute( 'modified', $createDate->timeStamp() );
            }

            $contentObject->store();
            // eZContentCacheManager::clearContentCache( $contentObject->attribute( 'id' ) );

            $nodeAssignment = eZContentObjectTreeNode::fetchByContentObjectID( $contentObject->attribute( 'id' ) );
            if( count( $nodeAssignment ) > 0 && $objectComplex == true )
            {
        	 // $cli->output( 'Content Object Name: '. $nodeAssignment[0]->getName() ."\n");
        	 // $cli->output( 'Content Object NodeID: '. $nodeAssignment[0]->NodeID ."\n");
        	 $cli->output( 'Content Object URL: '. $nodeAssignment[0]->urlAlias( ) );
		 // $cli->output( 'Content Object Name2: ' . $contentObject->Name ."\n");
	         // $cli->output( $nodeAssignment[0] );
                 // $cli->output( $contentObject );
            }
        } // test $operationResult != false

        unset( $contentObject );
        unset( $attributes );
        unset( $nodeAssignment );
        unset( $version );
        unset( $objectData );
        unset( $operationResult );
        } // end first record exclusion if
        $objectDataCount = $objectDataCount +1;
} // end first while loop

fclose( $fp );

unset( $fp );
unset( $node );
unset( $class );
unset( $foo );	

$script->shutdown();

?>