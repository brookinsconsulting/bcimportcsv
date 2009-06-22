#!/usr/bin/php
<?
// Settings
$file = 'extension/bcimportcsv/var/export.csv';

// Database connect settings
$database="joomla1";
$table="joomla1_content";
$user="root";
$key="publish";

// Connect to database
mysql_connect("localhost", $user, $key);
mysql_select_db( $database );

$fields="c.created, c.title, NULL, '14', c.introtext, c.fulltext, NULL, NULL, NULL, NULL, NULL, s.name section";
$q = "SELECT $fields FROM $table as c, huila_sections as s WHERE c.sectionid = s.id ORDER BY c.id";

// Run query
// print_r($q); die();
$result = mysql_query( $q );
$out = '';

// Get all fields names in table "name_list" in database "tutorial".
$fields = mysql_list_fields($database,$table);

// Count the table fields and put the value into $columns.
$columns = mysql_num_fields( $fields );
$columns = 11; // 5;

/* print_r($columns);
die(); */

// Put the name of all fields to $out.
/*
for ($i = 0; $i < $columns; $i++) {
    $l = mysql_field_name($fields, $i);
    // $out .= '"'.$l.'",';
    $out .= '"'.$l.'",';
}
$out .="n"; 
*/

$out = '"created"|"title"|"short_title"|"author"|"introtext"|"fulltext"|"comments"|"image"|"caption"|"publish_date"|"unpublish_date"|"tags"'."\n";

// print_r(strlen($out)); die();
// print_r( mysql_fetch_array($result) ); die();

// Add all values in the table to $out.
while ($l = mysql_fetch_array($result)) {
    for ($i = 0; $i <= $columns; $i++) {
	$s = $l["$i"];

        $s = str_replace( '|', '', $s );
        $s = str_replace( '"', '\"', $s );
        $s = str_replace( "\n", '', $s );
        $s = str_replace( chr(13), '', $s );
        $s = str_replace( chr(10), '', $s );
        $s = str_replace( "\r", '', $s );

	$s = html_entity_decode( $s , ENT_COMPAT, 'UTF-8' );
	// $s = html_entity_decode( $s );
	// $s = htmlspecialchars_decode( $s );

	$pattern = '/{[^>]*}/is';
	 preg_match_all( $pattern, $s, $matches );
	if( count( $matches[0] ) > 0 ) {
            // print_r( $matches[0] );
	    $replacements = array( '' );
	    foreach( $matches[0] as $m ) {
	      $s = str_replace( $m, '', $s );
              // print_r( $m );
	    }
            // print_r( $s ); die();
        }
	// $s = str_replace( "{mosimage}", '', $s );

	/*
	$expanded = iconv("UTF-8", "UTF-32", $s);
        $s = unpack("L*", $expanded);

	$t='';
	foreach($s as $key => $value) {
                  $one_character = pack("L", $value);
                  $t .= iconv("UTF-32", "UTF-8", $one_character);
        }
	$s = $t;
	*/
	// print_r( $s );
	// die( print_r( unpack("L*", $expanded) ) );
        // $s = str_replace( "{mosimage}", '', $s );

	/*
	$s = str_replace( "<U+0093>", '', $s );
	$s = str_replace( "<U+0094>", '', $s );
	$s = str_replace( "<U+0092>", '', $s );
	$s = str_replace( '/[\x00-\x19'.'0x0093'.']/u', '', $s );
	$s = str_replace( '/[\x00-\x19'.unicode_to_utf8('0x0094').']/u', '', $s );
	*/

	$s = utf8_encode( $s );
    	// print_r($s); echo "\n\n";

	if( true ) // $s != '' && $s != null )
	{
             // $st ='"'."$i - ".$s.'"';
	     // $st ='"'.$s.'"';
	     // $st ='|'.$s.'|';
	     // $st ='|'.$s.'|';
	     $st ='"'.$s.'"';

             $out .= $st;

	     if( $i == $columns ) // 5 )
	     {
             	$out .= "\n";
	     } else {
		$out .= "|";
	     }
	}
    }
    // $out .="n";
    $out .='';
    // print_r( $out );die();
}

// Open file export.csv.
$f = fopen ($file,'w');

// Put all values from $out to export.csv.
if( true ) // $out != '' && $out != null )
{
	fputs($f, $out);
}

fclose($f);

unset($f);
unset($out);

print_r("Review output file: $file\n\n");

// header('Content-type: application/csv');
// header('Content-Disposition: attachment; filename="'.$file.'"');
// readfile( $file );
unset($file);

?>