<?php
require_once('../database/connection.php');
$today_date=$millitime = round(microtime(true) * 1000);
global $conn;
$fetch_query="SELECT * FROM tasks";
$task_result = mysqli_query($conn,$fetch_query);
while ($tasks = mysqli_fetch_assoc($task_result)) {
	if ($tasks['percentage_completed']==0 && $tasks['start_date'] < $today_date) {

		$query2 = "UPDATE tasks SET status='STATUS_SUSPENDED' WHERE id =".$tasks['id'];
        echo "TASK".$tasks['name']."STATUS_SUSPENDED";
		$results = mysqli_query($conn,$query2);
		if ($results==true) {
			echo "true/n";
		}
		else
		{
			echo "false/n";
		}
	}
	else if ($tasks['percentage_completed'] !=0 && $tasks['percentage_completed'] < 100 && $tasks['ended_date'] < $today_date) {

		$query2 = "UPDATE tasks SET status='STATUS_FAILED' WHERE id =".$tasks['id'];
        echo "TASK".$tasks['name']."STATUS_FAILED";
		$results = mysqli_query($conn,$query2);
		if ($results==true) {
			echo "true/n";
		}
		else
		{
			echo "false/n";
		}
	}
	else if ($tasks['percentage_completed'] == 100 && $tasks['ended_date'] < $today_date) {
		$query2 = "UPDATE tasks SET status='STATUS_DONE' WHERE id =".$tasks['id'];
        echo "TASK".$tasks['name']."STATUS_DONE";
		$results = mysqli_query($conn,$query2);
		if ($results==true) {
			echo "true/n";
		}
		else
		{
			echo "false/n";
		}
	}
	else if ($tasks['percentage_completed'] == 0 && $tasks['start_date'] > $today_date) {
		$query2 = "UPDATE tasks SET status='STATUS_WAITING' WHERE id =".$tasks['id'];
        echo "TASK".$tasks['name']."STATUS_WAITING";
		$results = mysqli_query($conn,$query2);
		if ($results==true) {
			echo "true/n";
		}
		else
		{
			echo "false/n";
		}
	}
	else if($tasks['percentage_completed'] > 0 && $tasks['start_date'] < $today_date && $tasks['ended_date'] > $today_date){
	    $query2 = "UPDATE tasks SET status='STATUS_ACTIVE' WHERE id =".$tasks['id'];
        echo "TASK".$tasks['name']."STATUS_ACTIVE";
		$results = mysqli_query($conn,$query2);
		if ($results==true) {
			echo "true/n";
		}
		else
		{
			echo "false/n";
		}
	}
}