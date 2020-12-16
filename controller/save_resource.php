<?php
require_once('../database/connection.php');
global $conn;
$name=$_POST['name'];
$query2 = "INSERT INTO resources(name) VALUES ('$name')";

$results = mysqli_query($conn,$query2);
if ($results == true) 
{
	echo "true";
}
else
{
	echo "false";
}
?>