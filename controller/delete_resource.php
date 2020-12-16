<?php
require_once('../database/connection.php');
global $conn;
$id=$_POST['id'];
$delete_query="DELETE FROM resources WHERE id='$id'";
$results = mysqli_query($conn,$delete_query);
if ($results == true) 
{
	echo "true";
}
else
{
	echo "false";
}
?>