<?php
    $con = mysqli_connect("localhost","root","","malvar_bat_cafe");

    if(mysqli_connect_errno()){
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
    }
?>