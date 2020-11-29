<?php
  $name = 'user_cookie';
  $value = 'Johny Dawkins';
  setcookie($name, $value, time() + (86400 * 80), '/');
  // 86400 = 1 day
?>
<html>
  <body>
    <?php
      if (!isset($_COOKIE[$name])) {    
        echo "Cookie called '" . $name . "' has not been set!";
      } else { 
        echo "Cookie '" . $name  . "' has been set!<br>";    
        echo "Value in cookie is: " . $_COOKIE[$name];
      }
    ?>
  </body>
</html>