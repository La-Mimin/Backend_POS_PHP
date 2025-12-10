<?php

$servername = "localhost";
$username = "root";
$password = "Root1234!";
$dbname = "pos_db";

// connect to database
$conn = new mysqli($servername, $username, $password, $dbname);

// check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>