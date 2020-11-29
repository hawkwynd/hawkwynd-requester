<?php
// Jarvis Requester version 1.0
error_reporting(E_ALL);
ini_set('display_errors',True);
try{
    $cookiename = "jarviskey";
    $mysqli = new mysqli('127.0.0.1', 'root', 'scootre', 'keep');   
    if(!$mysqli)  throw new Exception( $mysqli->connect_error );
} catch(Exception $e){
    die( $e->getMessage() );
}

// if we have param k, use it, else check for a cookie and use it, else null.
if(isset($_GET['k']) && !empty($_GET['k']) ) {
    setcookie($cookiename, $_GET['k'], time() + 31556926 , '/' ); // 1 year      
    // now reload the page, so we can access the cookie we just stored
    header('location: /requester/');
}


// values and vars validation for user
try{

    $key    = isset($_COOKIE[$cookiename]) ? $_COOKIE[$cookiename] : null;;
    if($key === null ) throw new Exception( ' No KEY FOUND!' );
    // lookup the user by cookie key 
    $sql = "SELECT * FROM users WHERE secret = '$key'";
    if(!$result = $mysqli->query($sql)) throw new Exception( $mysqli->error );
    if($result->num_rows > 0){       
        $record     = $result->fetch_assoc();
        $id         = $record['user_id'];
        $visitor    = $record['user_name'];       
        $ts         = date('Y-m-d H:i a');
    
    }else{
        // they gots no key, they gets the hose...  
        header( 'location: http://stream.hawkwynd.com ');
    
    }
        
        // $laston     = "SELECT DATE_FORMAT(last_accessed,'%a, %b %D, %Y %r') last_accessed FROM users WHERE user_id IN($id)";
        // if(!$result = $mysqli->query($laston ) ) throw new Exception( $mysqli->error);
        // while($row = $result->fetch_assoc()) {
        //     $laston = $row['last_accessed'];
        // }
        // setcookie('last_on', $laston, time(), 31556926, '/');
        // setcookie('jarvis_user', $visitor, time() + 31556926, '/');
        // // update the last_accessed column
        // $update     = "update users set last_accessed=now() where user_id in($id)";
        // $u          = $mysqli->query($update); // update last_accessed
        // if(!$mysqli->affected_rows) throw new Exception( 'Update user failed!');

} catch(Exception $key){
    die($key->getMessage());
}

// OK, we have a valid user let's proceed
$a      = isset($_POST['artist']) ? $_POST['artist'] : null;
$t      = isset($_POST['title']) ? $_POST['title'] : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requester</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <link rel="stylesheet" href="../style.css">
    <!-- fontawesome -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.13.0/css/all.css">
</head>
<body>

<div class="overlay"></div>
<div class="container-fluid">

    <div class="mb-2 header col-sm-4">
        <h2>Hawkwynd's Butler</h2>
            <div> 
            <?php printf('<h2>Welcome %s! <span></span></h2>', $visitor );
            ?>
    </div>

    
            <form method="post" id="requesterform" >
            <div class="form-group">
                <!-- <label for="artist">Artist</label> -->
                <input placeholder="Artist" type="search" name="artist" class="form-control" id="artist">               
            </div>
            <div class="form-group">
                <!-- <label for="title">Title</label> -->
                <input placeholder="Title or Album or Genre" type="search" name="title" class="form-control" id="title">               
            </div>
            <div class="form-group">
                <label for="flac_flag">FLAC only</label>
                <input type="checkbox" name="flac_only" id="flac_flag">
            </div>
            <button type="submit" class="btn btn-success">Ask Jarvis</button>
            <button type="reset" class="btn btn-warning reset">Start over</button>
            </form> 
        </div>   
    

    <?php
    // Lets build a query, shall we? 
    try{
        $artist = isset($_POST['artist']) ? $mysqli->real_escape_string($_POST['artist']): null;
        $title  = isset($_POST['title']) ? $mysqli->real_escape_string($_POST['title']): null;
        $flac_flag = isset($_POST['flac_only']) ? $_POST['flac_only'] : null;
        // print_r($_POST);

        if( !$artist && !$title ){
            exit;
        }
    } catch (Exception $e){   
        die( $e->getMessage() );
    }


    try{
        
        switch(true){
            case strlen($artist) == 0:
                $limit = 500;
                $sql = "SELECT artist, title, album, genre, filenamepath FROM library WHERE title LIKE '%$title%' OR album LIKE '%$title%' OR genre LIKE '%$title%'";
            break;
            case strlen($title) == 0:
                $limit = 500;
                $sql = "SELECT artist, title, album, genre, filenamepath FROM library WHERE artist LIKE '%$artist%'";
            break;
            case strlen($artist) > 0 && strlen($title) > 0:
                $limit = 25;
                $sql = "SELECT artist, title, album, genre, filenamepath FROM library WHERE artist LIKE '%$artist%' AND title LIKE '%$title%'";
            break;

        }
        // restrict flac only
        if($flac_flag) $sql .= " AND fileformat NOT IN('mp3')";

        $sql .= " ORDER BY artist, album limit $limit";  

        if(!$result = $mysqli->query($sql)) throw new Exception( $mysqli->error );

        echo "<div class='mt-2 results'>";

        if ($result->num_rows > 0) {
            print '<div class="stats">Your search: '.  $result->num_rows . " matches found.</div>";
            print '<table class="table table-striped">';
            print '<thead class="thead-dark"><tr><th scope="col">Artist</th><th scope="col">Title</th><th scope="col">Album</th><th scope="col"></th></tr></thead>';
            print '<tbody>';
            
            while($row = $result->fetch_assoc()) {
               
                printf('<tr><td>%s</td><td>%s</td><td>%s</td><td><span data-path="act=req&amp;que=%s" class="request_link">Request</span></td></tr>', 
                $row['artist'], $row['title'],$row['album'],  urlencode( $row['filenamepath'])  
                );        

            
            }
            echo "</tbody></table>";

        } else {
                echo "<div class='mt-4 ml-4 mb-5 fail'>Yeah, I got nothing. Try another.</div>";
        }

    } catch (Exception $e){
        die( $e->getMessage());
    }

    ?>
</div><!-- container-fluid -->

<footer class="page-footer font-small blue mb-2 mt-2">
<div class="footer-copyright text-center">&copy; <?php echo date('Y') ." Hawkwynd Radio - all rights reserved.";?></div>
</footer>

<script>
$(document).ready(function(){

    $('.reset').on('click', function(){
        console.log('clear the deck!');
        
        $('.results').empty(); // flush results container
        
        // clear input fields 
        $(this).closest('form').find("input[type=text]").val("");
        $(':input').val('');

    });
 
    $(document).on("click", ".request_link", function(){
        
        var options = ['Got it!','Great choice!','Good one!','Yeah Baby!', 'Nice one!', 'Love it!'];
        var approved = options[Math.floor(Math.random() * options.length)];


        var path = $(this).attr('data-path');

        $(this).removeClass('request_link').text(approved).addClass('approved').closest('tr').addClass('selected');

        $.get("/requester/soap_req.php?"+path , function(data){
            console.log(data);
        });       


    });

    // Add remove loading class on body element based on Ajax request status
$(document).on({
    ajaxStart: function(){
        $("body").addClass("loading"); 
    },
    ajaxStop: function(){ 
        $("body").removeClass("loading"); 
    }    
});
});

</script>

</body>
</html>
