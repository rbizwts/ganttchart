<?php
require_once('../database/connection.php');
global $conn;
$name=$_POST['name'];
$id=$_POST['id'];
$query2 = "UPDATE resources SET name='$name' WHERE id =".$id;
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