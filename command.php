<?php
error_reporting(E_ALL);
// Uncomment the below if bad stuff seems to happen:
ini_set('display_errors',True);

require_once dirname(__FILE__). '/requester/soap_req.cfg';
require_once  dirname(__FILE__). '/requester/php-getid3/getid3/getid3.php';
require_once dirname(__FILE__) . '/requester/codelibs/getid3SupportsDbmCache.php';

$getID3 = new getID3;
global $getID3;

$command = $_POST['command'];

if( isset($_POST['command'] )  && !empty($_POST['command']) ) {

        echo json_encode( jarvisCommand($command), true);
}

// Functions 
function jarvisCommand($command){    
    try{
        $responses  = [];       
        $fp = stream_socket_client("tcp://localhost:1234", $errno, $errstr, 20);
        if(!$fp) throw new Exception( 'Telnet Failure - ' . $errstr, $errno );
        
        fwrite( $fp, "$command\nquit\n");
        
        while (!feof($fp)) {        
            array_push($responses, trim(str_replace('|', '', fgets($fp, 1024)))  );
        }
        fclose($fp);

        
        $payload            = $que = [];
        $getResponse        = array();         
        $responses          = array_filter( array_pop_n($responses, 2) ); // drop last three elems, and empty elems
        
        if( empty($responses) ) return 'No requests';
        
        $payload['command'] = $command; 

        switch($command){
            
            case 'request.on_air':
                
                $t              = $responses[0];            
                $getResponse    = array(); 
                $fp = stream_socket_client("tcp://localhost:1234", $errno, $errstr, 20);
                if(!$fp) throw new Exception( 'Telnet Failure - ' . $errstr, $errno );
                
                fwrite( $fp, "request.trace $t\nquit\n");
                 
                while(!feof($fp)){                           
                    array_push($getResponse, fgets( $fp, 1024) );
                }                             
                fclose($fp);
                
                $line = $getResponse[0];
                // [2020/11/23 17:30:25] Pushed ["/home/scott/Music/Gov't Mule - Revolution Come... Revolution Go (Deluxe Edition) (2017) [2488 FLAC]/10. Burning Point.flac";...].
                preg_match('/"([^"]+)"/', $line, $m); // get everything between the double quotes.

                $meta               = req_meta( $m[1] );
                $f = pathinfo($m[1]);

                extract( $meta['comments']);             
                
                $payload['response'] = array( 
                    sprintf("<div class='next_row %s'><span>%s</span> - <span>%s</span> - <span>%s</span></div>", $f['extension'], $artist[0],$title[0],$album[0]  )
                );
        

                $responses = $payload;
                
            break;

            case 'help':               
                array_shift($responses);
                $payload['response'] =  array_filter( array_pop_n($responses, 2));   
                $responses = $payload;
            break;
            
            // main.next 
            case strpos($command, '.next') > 0:
                
                $responses = array_filter( array_pop_n($responses, 1) );

                foreach($responses as $idx => $filename){

                    $fname = trim(str_replace('[ready]', '', $filename));
                    $fname = trim(str_replace('[playing]','', $fname));    

                    $meta  = req_meta($fname);            
                    
                    extract( $meta['comments'] );      
                    $payload['filename'] = $fname;
                    $filetype = pathinfo($fname);

                    $payload['response'][$idx] = sprintf("<div class='next_row %s'><span>%s</span> - <span>%s</span> - <span>%s</span></div>",$filetype['extension'], $artist[0], $title[0], $album[0]  ) ;
                }
                
                $responses = $payload;

            break;

            case 'requested.queue':
                $list = [];
                
                // echo "requested.queue:\n";
                array_pop($responses); // lose END element

                foreach($responses as $q){
                    $que = explode(' ', $q);
                                    
                    foreach($que as $t){
                        $trace = '';
                        $fp = stream_socket_client("tcp://localhost:1234", $errno, $errstr, 20);
                        if(!$fp) throw new Exception( 'Telnet Failure - ' . $errstr, $errno );
                        
                        // echo "sending: request.trace $t<br/>";
                        fwrite( $fp, "request.trace $t\nquit\n");
                         
                        while(!feof($fp)){                           
                            // echo fgets( $fp, 1024) . "<br/>";
                            $trace .= fgets($fp, 1024);
                        }   

                        $trace_arr = parseTrace($trace);
                    
                        /** Returns --
                         * Array
                            (
                                [18] => /home/scott/Music/1964 - BB King - Live At The Regal [MFSL 24k Gold UDCD 548 FLAC]/09 - You Done Lost Your Good Thing Now.flac
                                [4] => /home/scott/Music/1964 - BB King - Live At The Regal [MFSL 24k Gold UDCD 548 FLAC]/09 - You Done Lost Your Good Thing Now.flac
                                [2] => /home/scott/Music/80s-Rock-Pop-New-Wave-Metal/Various Artists/1980s Radio Hits/09 Robert Palmer - Addicted to Love.flac
                            )
                         */
                        
                        $list[$t] = $trace_arr;
                        fclose($fp);
                    }
                    
                    // iterate the list and get artist-title for display
                    foreach($list as $r ){
                        $rmeta =  req_meta($r);
                        
                        $payload['filename'] = $r;                       
                        $filetype = pathinfo($r);

                        extract($rmeta['comments']);
                        // printf("%s - %s <br/>", $artist[0], $title[0] );
                        array_push($getResponse, sprintf("<div title='file type %s' class='next_row %s'><span>%s</span> - <span>%s</span> - <span>%s</span></div>", $filetype['extension'], $filetype['extension'], $artist[0],$title[0],$album[0] ));

                        $payload['response'] = $getResponse;

                     }                   
                    $responses = $payload;               
                }               
                break;
            case 'skip':               
                
                $payload['response'] = array($responses[0]);
                $responses = $payload;

            break;

            default:
                $responses = array_filter( array_pop_n($responses, 1) );
            break;
        }
        
        
        return $responses;

        
    } catch (Exception $e) {
        return $e->getMessage(); 
    }
}
    
function pwrap($arr){

    echo "<pre>", print_r($arr), "</pre>";

}
function array_pop_n(array $arr, $n) {
    return array_splice($arr, 0, -$n);
}

function req_meta( $fname ) {
    
    try{
        global $getID3;
      
        if(!$ThisFileInfo = $getID3->analyze($fname)) throw new Exception('No FileInfo found!');
        
        // Optional: copies data from all subarrays of [tags] into [comments] so
        // metadata is all available in one location for all tag formats
        // metainformation is always available under [tags] even if this is not called   
        getid3_lib::CopyTagsToComments($ThisFileInfo);
            
        return($ThisFileInfo);

    } catch( Exception $e){
        return $e->getMessage();
    }

}


function parseTrace( $trace ){
    
    // [2020/11/22 15:32:05] Pushed ["/home/scott/Music/80s-Rock-Pop-New-Wave-Metal/Meat Puppets/Up on the Sun/07 Buckethead.flac";...].
    // [2020/11/22 15:32:05] "/home/scott/Music/80s-Rock-Pop-New-Wave-Metal/Meat Puppets/Up on the Sun/07 Buckethead.flac" entered the secondary queue : position #3.
    // [2020/11/22 15:37:29] #2 in secondary queue
    // [2020/11/22 15:41:11] #1 in secondary queue
    // [2020/11/22 15:44:08] Entering the primary queue.
    // [2020/11/22 15:44:21] Currently on air.

    $array = explode("\n", $trace);
    array_pop($array);
    $firstLine = $array[0];
    // [2020/11/22 15:32:05] Pushed ["/home/scott/Music/80s-Rock-Pop-New-Wave-Metal/Meat Puppets/Up on the Sun/07 Buckethead.flac";...].
    preg_match('/"([^"]+)"/', $array[0], $m);

    // Array
    // (
    //  [0] => "/home/scott/Music/1964 - BB King - Live At The Regal [MFSL 24k Gold UDCD 548 FLAC]/09 - You Done Lost Your Good Thing Now.flac"
    //  [1] => /home/scott/Music/1964 - BB King - Live At The Regal [MFSL 24k Gold UDCD 548 FLAC]/09 - You Done Lost Your Good Thing Now.flac
    // )

        return $m[1];
}
?>
