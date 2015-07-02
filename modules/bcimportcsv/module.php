<?php
/**
 * File containing the importcsv module configuration file, module.php
 *
 * @copyright Copyright (C) 1999 - 2016 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) 2013 - 2016 Think Creative. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or later)
 * @version 0.1.1
 * @package bcimportcsv
*/

// Define module name
$Module = array( 'name' => 'Import CSV',
                 'variable_params' => false );

// Define module view and parameters
$ViewList = array();

// Define 'upload' module view parameters
$ViewList['upload'] = array( 'script' => 'upload.php',
                             'name' => 'upload',
                             'functions' => array( 'upload' ),
                             'default_navigation_part' => 'ezbcimportcsvnavigationpart',
                             'post_actions' => array( 'Download', 'Generate' ),
                             'params' => array( 'NodeID' ),
                             'unordered_params' => array() );

// Define 'download' module view parameters
$ViewList['download'] = array( 'script' => 'download.php',
                               'name' => 'download',
                               'functions' => array( 'download' ),
                               'default_navigation_part' => 'ezbcimportcsvnavigationpart',
                               'post_actions' => array( 'Download', 'Generate' ),
                               'params' => array( 'NodeID' ),
                               'unordered_params' => array() );

// Define function parameters
$FunctionList = array();

// Define function 'upload' parameters
$FunctionList['upload'] = array();

// Define function 'download' parameters
$FunctionList['download'] = array();

?>