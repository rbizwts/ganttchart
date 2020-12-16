<?php
require_once('../database/connection.php');
$target_dir = "../uploads/";
//error_reporting(-1);
$data=json_decode($_POST['tasks']);
echo "<pre>";
//print_r($data);
//echo "<pre>";
//exit();*/
if (count($data)==0) {
	$query="TRUNCATE TABLE tasks";
	$query1="TRUNCATE TABLE dependency";
	$result = mysqli_query($conn,$query);
	$result1 = mysqli_query($conn,$query1);
	if ($result==true) {
		//echo json_encode("true");
	}
	else
	{
		//echo json_encode("false");
	}
}
else{
	$errors = [];
	/*$query="TRUNCATE TABLE tasks";
	$query1="TRUNCATE TABLE dependency";
	$result = mysqli_query($conn,$query);
	$result1 = mysqli_query($conn,$query1);*/
	foreach ($data as $key => $value) {
		$name = $value->name;
		//echo $value->depends;
		if(isset($value->depends) && $value->depends!= "")
		{
			//echo "isset";

		}
		$isDepended = isset($value->depends) && $value->depends ? 1 : 0;
		$startDate = $value->start;
		$endDate = $value->end;
		$percentage = $value->progress;
		$assigns =json_encode($value->assigs);
		$des = $value->description;
		$level = $value->level;
		$status = $value->status;
		if(isset($value->duration)){
			$duration = $value->duration;
		}else{
			$duration=null;
		}
		if(isset($value->dependency)){
			$dependency = $value->dependency;
		}else{
			$dependency=null;
		}
		//$attchmentfile = $value->attchmentfile;

		if (isset($value->startIsMilestone)) {
			//echo $value->name;
			//exit("startIsMilestone");
			if($value->startIsMilestone)
				$startIsMilestone = 1;
			else
				$startIsMilestone = 0;
		}
		else
		{
			$startIsMilestone=0;
		}

		if (isset($value->megaMilestone)) {
			$megaMilestone = $value->megaMilestone;
		}
		else
		{
			$megaMilestone=0;
		}
		if (!isset($value->status)) {
			$value->status='STATUS_ACTIVE';
		}

		if (isset($value->endIsMilestone)) {
			//exit("endIsMilestone");
			if($value->startIsMilestone)
				$endIsMilestone = 1;
			else
				$endIsMilestone = 0;
		}
		else
		{
			$endIsMilestone=0;
		}
		
		if (isset($_POST['deletedIds'])) {
			echo "delete ids";
			$deletedIds=$_POST['deletedIds'];
			$deletedIds=implode(',',$deletedIds);
			echo $deletedIds;
			$delete_query="DELETE FROM tasks WHERE id IN ($deletedIds)";
			$delete_query_dependency="DELETE FROM dependency WHERE id_task IN ($deletedIds)";
			$result1 = mysqli_query($conn,$delete_query);
			$result2 = mysqli_query($conn,$delete_query_dependency);
			if ($result1 == true) 
			{
				array_push($errors, "true");
				//echo json_encode("true");
			}
			else
			{	
				array_push($errors, "false");
				// echo json_encode("false");
			}
		}

		if (empty($name) || empty($startDate) || empty($endDate)) 
		{
			echo "Error";
			array_push($errors, "Error");
		}
		else
		{
			//for newly added task
			//echo $value->id."in if===";
			$result = substr($value->id, 0, 4);
			echo $result."<br>";
			if ($result=='tmp_') {
				echo $value->id."add";
				if($dependency=='' || $dependency==null || $dependency=='undefined'){
					$depends='';	
				}
				$query2 = "INSERT INTO tasks(id_task, task_name, task_responsable,start_date, ended_date, percentage_completed, notes, has_dependency,is_milestone,megaMilestone,is_milestone1,level,status,duration,dependency) VALUES (NULL,'$name','$assigns','$startDate','$endDate','$percentage','$des','$isDepended',$startIsMilestone,'$megaMilestone',$endIsMilestone,'$level','$status','$duration','$dependency')";
				//echo $query2;
				
				$results = mysqli_query($conn,$query2);
				if ($results == true) 
				{
					$last_id = mysqli_insert_id($conn);
					if ($isDepended==1) {
						$depends = $value->depends;
						//$query_dependency = "INSERT INTO dependency(id_task, id_task_dependency) VALUES ('$last_id','$depends')";
						//$results_dependency = mysqli_query($conn,$query_dependency);
					}
					array_push($errors, "true");
				} 
				else 
				{
					array_push($errors, "false");
				}
			}
			else
			{
				//for the existing task
				echo "update".$value->id;
				$id=$value->id;
				//For Edit
				$query2 = "UPDATE tasks SET task_name='$name',task_responsable='$assigns',is_milestone='$startIsMilestone',start_date='$startDate',ended_date='$endDate',percentage_completed='$percentage',notes='$des',has_dependency='$isDepended',is_milestone='$startIsMilestone',is_milestone1='$endIsMilestone',megaMilestone='$megaMilestone',level='$level',status='$status',duration='$duration',dependency='$dependency' WHERE id =".$value->id;
				//echo $query2;
				$results = mysqli_query($conn,$query2);
				if ($results == true) 
				{
					if ($isDepended==1) {
						$depends = $value->depends;
						if($dependency=='' || $dependency==null || $dependency=='undefined'){
							$depends='';	
						}
						$findDependency="SELECT * FROM dependency where id_task='$id' LIMIT 1";
						$result_dependency = mysqli_query($conn,$findDependency);
						if (mysqli_num_rows($result_dependency)) {
							//$update_dependency = "UPDATE dependency SET id_task_dependency='$depends' WHERE id_task =".$id;
							//echo $update_dependency;
							//$result = mysqli_query($conn,$update_dependency);
						}
						else{
							$insert_dependency = "INSERT INTO dependency(id_task, id_task_dependency) VALUES ('$id','$depends')";
							$result = mysqli_query($conn,$insert_dependency);
						}
					}
					// echo json_encode("true");
					array_push($errors, "true");
				} 
				else {
					// echo json_encode("false");
					array_push($errors, "false");
				}
			}
		}
	}	
	echo json_encode($errors);
	echo "</pre>";
} 	
?>