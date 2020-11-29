<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <!-- jquery from Google -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <!-- bootstrap styles -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <!-- fontawesome -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.13.0/css/all.css">
    <!-- style override -->
    <link rel="stylesheet" href="style.css">

    <title>Jarvis Admin</title>
</head>
<body>
    
    <div class="container-fluid mt-2 ml-3">
        <div class="row">
            <div class="col-md-5">
                <h2>The Butler Admin</h2>
            </div>
        </div>
        <div class="row ml-2 mt-2">
            <!-- <div class="col-md-3"> -->
                <div class="btn-group-sm" role="group" >
                    <button type="button" class="btn btn-default" id="request.on_air">Playing</button>
                    <button type="button" class="btn btn-default" id="main.next">Queue</button>
                    <button type="button" class="btn btn-default" id="skip">Skip Song</button>
                    <button type="button" class="btn btn-default" id="requested.queue">Requests</button>
                </div>
            <!-- </div> -->
        </div>
        <div class="row">
            <div class="col-md-8 ml-2">
                <div class="responseContainer"></div>
            </div>
        </div>
    </div>



<script>
    $(document).ready(function(){
        const urlParams = new URLSearchParams(window.location.search);
        const myParam   = urlParams.get('a');

        if(!myParam){
            console.log( myParam);
            $(".btn:nth-child(3)").attr('disabled', 'disabled');
        }

        $('.btn').on('click', function(e){
            
            $('.responseContainer').html('')
                        
            $(this).siblings().removeClass('btn-success').addClass('btn-default');
            $(this).removeClass('btn-default').addClass('btn-success');
            
            var command = $(this).attr('id');

            $.post('command.php', { command : command }, 
                function(returnedData){                   
                    
                    console.log( returnedData );
                    
                    var resp = JSON.parse(returnedData);
                

                    if( resp.length !== 0){
                        $.each(resp.response, function(i, row){
                           
                            $('.responseContainer').append(row)
                            
                        })
                    }else{
                        $('.responseContainer').append('<div>No Requests at this time. <a target="_blank" href="/requester/">Make One!</a></div>')
                    }
                }).fail(function(){
                    console.log("error");
            });
           
        });            

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