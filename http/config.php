<?php
 date_default_timezone_set('Asia/Ho_Chi_Minh');
 $databaseserver = getenv('DB_HOST') ?: "localhost"; //the address of your server, usually localhost, change it if you use a distant mysql database
 $databasename = getenv('DB_NAME') ?: "evo"; //the name of your database on the mysql server
 $databaseuser = getenv('DB_USER') ?: "root"; //the name of the database-user
 $databasepass = getenv('DB_PASS') ?: "password"; // the password to your database
 
 $wwwroot = "/var/www/html/http/";
 $leaguename = getenv('LEAGUE_NAME') ?: "localhost:8080"; // no www. prefix!
 $directory = "http://".$leaguename; //the full URL (including www.)
?>