<?php
error_reporting(E_STRICT);
// Uncomment the below if bad stuff seems to happen:
ini_set('display_errors',True);

// ob_implicit_flush(true);
// ob_start();
// header( 'Content-type: text/html; charset=utf-8' );


try{

    $mysqli = new mysqli('127.0.0.1', 'root', 'scootre', 'keep');   
    if(!$mysqli)  throw new Exception( $mysqli->connect_error );
} catch (Exception $e){   
    die( $e->getMessage() );
}

require_once('requester/php-getid3/getid3/getid3.php');

$getID3     = new getID3;
$dirname    = "/home/scott/Music";   
$dir        = opendir($dirname) or die('cannot open directory!');
$payload    = getDirContents( $dirname );
        


        echo "<pre>";
        // flush();
        // ob_flush();

        printf('total songs in keep %d ', count($payload));
        
        // flush();
        // ob_flush();

        $c = 3; 
        $x = 0;
        
        
        foreach( $payload as $song ){

            // $x++;
            // if( $x > $c ) break;
            
            echo "Checking $song ...";

            $exists = checkDb( $song );

            if( $exists === 0 ){

                $meta   = req_meta( $song );                
                $parsed = getMeta( $meta );
                
                if( !(array_key_exists('artist', $parsed ) || !(array_key_exists('title', $parsed)) )) continue;
                if ( !isset($parsed['artist']) || $parsed['artist'] == "" || is_null($parsed['artist']) ) continue;
                if ( !isset($parsed['title']) || $parsed['title'] == "" || is_null($parsed['title']) ) continue;
                               
                echo intoDB( $parsed ) . PHP_EOL . PHP_EOL;

            }else{
                
                echo $song . " : " . $exists === 1 ? ' exists. No Change.' : $exists . PHP_EOL;
               
            }

        }            
        // ob_end_flush(); 
        echo "</pre>";

      closedir($dir);


function getDirContents($dir, &$results = array()) {
    $files = scandir($dir);

    foreach ($files as $key => $value) {
        $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
        if (!is_dir($path)) {
            $results[] = $path;
        } else if ($value != "." && $value != ".." && $value != ".Trash-1000") {
            getDirContents($path, $results);
            if(is_file($path)){
                $results[] = $path;
            } 
        }
    }

    return $results;
}

function getMeta( $meta ){
    
    unset($meta['comments']['comment']);

     foreach($meta['comments'] as $key => $comment ){
         $out[$key] = $comment[0];
     }
     
    //  unset($meta['id3v2'], $meta['tags'],$meta['comments_html']);
    //  print_r( $meta );

     $out['filename']       = $meta['filename'];
     $out['filepath']       = $meta['filepath'];
     $out['filenamepath']   = $meta['filenamepath'];
     $out['filesize']       = $meta['filesize'];
     $out['fileformat']     = substr($meta['fileformat'], 0, 4); // flac, mp3 only.
     $out['genre']          = isset( $meta['comments_html']['genre']) ? $meta['comments_html']['genre'][0] : null;

    //  unset($out['comments']);

    return $out;
}


function checkDb( $filename ){
    global $mysqli;
    $sql = "Select id from library where filenamepath = '" . $mysqli->real_escape_string($filename). "'";
    
    if(!$result = $mysqli->query($sql)) die( $mysqli->error );

    return $result->num_rows;
}

function intoDB( $meta ){
    global $mysqli;

    try{
        // print_r($meta);

        $stmt = $mysqli->prepare("INSERT INTO library ( artist, title, album, genre, filenamepath,`filename`, fileformat ) VALUES 
        (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE filenamepath = ? , `filename` = ? , album = ?, genre= ?, artist = ? , title = ?");
        
        if(!$stmt) throw new Exception( $mysqli->error );

        if(!$stmt->bind_param("sssssssssssss" , 
            $meta['artist'], $meta['title'], $meta['album'], $meta['genre'], $meta['filenamepath'], $meta['filename'],
            $meta['fileformat'], $meta['filenamepath'], $meta['filename'] , $meta['album'], $meta['genre'], $meta['artist'], $meta['title']) ) throw new Exception( $stmt->error );

        if(!$stmt->execute()) throw new Exception( $stmt->error . ": " . json_encode($meta) );
        
        $insertID = $stmt->insert_id;
        $stmt->close();
        
        if( $insertID > 0 ) {
            $success = sprintf('%s - %s on %s successful insertId %d', $meta['artist'], $meta['title'], $meta['album'], $insertID );
        }else{
            $success = sprintf('%s updated.', $meta['genre']);
        }
        
        return $success;
        


    } catch (Exception $e){       
        die( $e->getMessage() );
    }



}

// for requesting metadata QUICKLY...
function req_meta($fname) {
	        
    global $getID3;
    // Optional: copies data from all subarrays of [tags] into [comments] so
    // metadata is all available in one location for all tag formats
    // meta information is always available under [tags] even if this is not called
        
	$ThisFileInfo = $getID3->analyze($fname);
    getid3_lib::CopyTagsToComments($ThisFileInfo);
    
    return($ThisFileInfo);

}