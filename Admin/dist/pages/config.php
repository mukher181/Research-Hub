<?php
      
            define("HOSTNAME", "localhost");
            define("USERNAME", "root");
            define("PASSWORD", "");
            define("DATABASE", "research");

            $conn = mysqli_connect(HOSTNAME, USERNAME, PASSWORD, DATABASE);
        
        
            if (!$conn) {
                die("Connection failed: " . mysqli_connect_error());
            }

?> 