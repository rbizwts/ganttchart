<?php
$servername = "localhost";
$username = "root";
$password = "";
global $conn;
// Create connection
// $conn = new mysqli($servername, $username, $password);
$conn = mysqli_connect($servername,$username,$password,'wtshub_gantt_chart');

?>