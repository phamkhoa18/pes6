<?php
 date_default_timezone_set('Asia/Ho_Chi_Minh');
 $databaseserver = getenv('DB_HOST') ?: "localhost"; //the address of your server, usually localhost, change it if you use a distant mysql database
 $databasename = getenv('DB_NAME') ?: "evo"; //the name of your database on the mysql server
 $databaseuser = getenv('DB_USER') ?: "root"; //the name of the database-user
 $databasepass = getenv('DB_PASS') ?: "password"; // the password to your database
 
 $wwwroot = "/var/www/html/http/";
 $leaguename = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (getenv('LEAGUE_NAME') ?: "localhost"); 
 $directory = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $leaguename; 
?>