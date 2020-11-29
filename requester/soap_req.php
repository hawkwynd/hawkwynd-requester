<?php

error_reporting(E_STRICT);
ini_set('display_errors',True);

// Workaround for GetID3 Windows HelperApps bug:
define('GETID3_HELPERAPPSDIR', dirname(__FILE__).'/php-getid3/helperapps/');
    
require_once dirname(__FILE__).'/soap_req.cfg';
require_once  dirname(__FILE__).'/php-getid3/getid3/getid3.php';
require_once dirname(__FILE__) . '/codelibs/getid3SupportsDbmCache.php';

$getID3 = new getID3;
global $getID3;

try{
    $mysqli = new mysqli('127.0.0.1', 'root', 'scootre', 'keep');   
    if(!$mysqli)  throw new Exception( $mysqli->connect_error );
} catch (Exception $e){   
    die( $e->getMessage() );
}

//   request song url
//   soap_req.php?act=req&que=%2Fhome%2Fscott%2FMusic%2F10cc%2F10cc%2F03+Johnny%2C+Don%27t+Do+It.mp3

//  skip track request
//  soap_req.php?act=nxt
  
  $msg = '';

  if (isset($_REQUEST['act'])) {
    $act = $_REQUEST['act'];
  } else {
    $act = '';
  }
  if (isset($_REQUEST['que'])) {
    $que = $_REQUEST['que'];
  } else {
    $que = '';
  }
  if (isset($_REQUEST['gus'])) {
    $gus = $_REQUEST['gus'];
  } else {
    $gus = '';
  }
  if (isset($_REQUEST['vue'])) {
    $vue = $_REQUEST['vue'];
  } else {
    $vue = '';
  }
  if ($act != '' && $que != '') {
    if ($act == 'req') {
      $msg .= soap_req($que);
    } else {
      $msg .= '<b><u>ERROR:</u> The Command Given Is Unknown!</b><br>';
    }
  } else {
    if ($act == 'req') {
      $msg .= '<b><u>ERROR:</u> No File Given To Queue!</b><br>';
    } elseif ($act == 'nxt') {
      $msg .= soap_nxt($skipVia);
    } elseif ($act == 'reload') {
      $msg .= reload();
    } else {
      if ($act != '') {
        $msg .= '<b><u>ERROR:</u> The Command Given Is Unknown!</b><br>';
      }
    }
  }

echo isset($msg) ? $msg : ''; 

exit;


// The new way
// if($mSource == 'mysql') {

//     try{
//         $sql = "select * from library ORDER BY artist, album LIMIT 0, 2000";

//         if(! $result = $mysqli->query($sql)) throw new Exception( $mysqli->error );

//         if ($result->num_rows > 0) {
//             // output data of each row
//             while($row = $result->fetch_assoc()) {
//               printf(
//                 '<tr><td>%s</td><td>%s (%s)</td><td><a href="%s?act=req&amp;que=%s">Request</a></td></tr>',
//                 $row['artist'], $row['title'], $row['album'], basename(__FILE__), urlencode( $row['filenamepath'])
//             );
//               // echo "id: " . $row['id']. " Artist:" . $row["artist"]. " title:" . $row["title"]. " path" .$row['filenamepath'] ."<br>";
//             }
//           } else {
//             echo "0 results";
//           }

//     } catch (Exception $e){
//         die( $e->getMessage());
//     }
// }

  



function getDirContents($dir, &$results = array()) {

    $files = scandir($dir);
    foreach ($files as $key => $value) {
        $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
        if (!is_dir($path)) {
            $results[] = $path;
        } else if ($value != "." && $value != ".." && $value != ".Trash-1000" ) {
            getDirContents($path, $results);
            if(is_file($path)){
                $results[] = $path;
            } 
        }
    }

    return $results;
}


function getMeta( $meta ){
    $out['fileformat']      = $meta['fileformat'];
    $out['filename']        = $meta['filename'];
    $out['filenamepath']    = $meta['filenamepath'];
    $out['fileformat']      = $meta['fileformat'];
    $out['filesize']        = $meta['filesize'];
    $out['playtime_seconds'] = $meta['playtime_seconds'];
    
    foreach($meta['comments'] as $key => $row ){
        $out[$key] = $row[0];
    }

    unset($out['comment']);
    
    return $out;


}
/**
 * Issue request to Jarvis via telnet
 * @var string
 * @return string
 */

function soap_req($reqFile) {
    
    try{
        global $ctlPort;
        $resp = '';
        $fp = stream_socket_client("tcp://localhost:$ctlPort", $errno, $errstr, 20);
        if(!$fp) throw new Exception( 'Telnet failure ' . $errstr ($errno));
        
        // send command to Jarvis!
        fwrite($fp, "requested.push ".str_replace("\\'","'",str_replace('&amp;','&', urldecode($reqFile)))."\nquit\n");
          
        while (!feof($fp)) {
            $resp .= fgets($fp, 1024);
        }
        
        fclose($fp);
        // return("Jarvis has acknowleged \"".$reqFile."\" to queue as $resp</b><br>");       
        return($resp);       

    } catch( Exception $e){
        return ( $e->getMessage() );
    }
}

/**
 * Skip command via telnet to Jarvis
 * @return string
 */
  function soap_nxt($skipVia, $response='') {
      try{
          global $ctlPort;
          $fp = stream_socket_client("tcp://localhost:$ctlPort", $errno, $errstr, 20);
          
          if(!$fp) throw new Exception( 'Telnet Failure - ' . $errstr );
          
          fwrite( $fp, "skip\nquit\n") ;
          
          while (!feof($fp)) { $response .= fgets($fp, 1024); }
          
          fclose($fp);
          return( $response );
        
      } catch (Exception $e) {
          return $e->getMessage(); 
      }
  }

  /**
   * Reload main playlist
   * @return string
   */
  function reload($response='') {
      try{
          global $ctlPort;
          $fp = stream_socket_client("tcp://localhost:$ctlPort", $errno, $errstr, 20);
          if(!$fp) throw new Exception('Telnet Failure ', $errstr );

          fwrite($fp, "main.reload\nquit\n");

          while (!feof($fp)) {
            $response .= fgets($fp, 1024);
          }
          fclose($fp);
          return($response);
      } catch (Exception $e){
        return $e->getMessage(); 
      }
    
  }

  function stl($aString) {
    return(strtolower($aString));
  }

  
// for requesting metadata QUICKLY...
function req_meta( $fname ) {
    
    try{
        global $getID3;
        global $useMeta;
        
        if (!$useMeta) return('');  
    
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

// Function to get # of processors, and load, if both are available
// RETURNS:
//   string with status/info if we can get it
//   FALSE if we can't get info/status

function cpu_stat() {
  if (!file_exists('/bin/grep'))
    return(false);
  if (!is_readable('/bin/grep'))
    return(false);
  if (!file_exists('/proc/cpuinfo'))
    return(false);
  if (!is_readable('/proc/cpuinfo'))
    return(false);
  if (!file_exists('/proc/loadavg'))
    return(false);
  if (!is_readable('/proc/loadavg'))
    return(false);
  $dum = explode('.',`hostname`);
  $a = trim($dum[0]);
  $dun = explode("\n",rtrim(`/bin/grep 'processor' /proc/cpuinfo`));
  $b = strval(count($dun));
  $tmp = explode(" ",file_get_contents('/proc/loadavg'));
  $c = strval($tmp[0]);
  $d = strval($tmp[1]);
  $e = strval($tmp[2]);
  if ($b != 1)
    return("On Linux-compatible host &quot;$a&quot;<br>with $b processors, <b style=\"font-style: normal;\">loads: $c $d $e</b>");
  return("On Linux-compatible host &quot;$a&quot;<br>with $b processor, <b style=\"font-style: normal;\">loads: $c $d $e</b>");
}
?>

</table>
</body>
</html>
<?php
  // My work here is done!
  die();
