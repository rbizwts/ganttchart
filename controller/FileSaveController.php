<?php
require_once('../database/connection.php');
$target_dir = "../uploads/";
$id=$_POST['id'];

if(isset($_FILES["file"])){
	$filename = "";
	$fileQuery = "";
	$files=[];
	for ($i=0; $i <count($_FILES["file"]['name']) ; $i++) { 
		$imageFileType = strtolower(pathinfo($_FILES["file"]["name"][$i],PATHINFO_EXTENSION));
		$filename = (microtime(true) * 1000). "." .  $imageFileType;
		$target_file = $target_dir . $filename;
		move_uploaded_file($_FILES["file"]["tmp_name"][$i], $target_file);
		array_push($files,$filename);
	}
	$get_query="SELECT * from tasks";
	$result = mysqli_query($conn,$get_query);
	while ($task = mysqli_fetch_assoc($result)) {
		if ($task['id']==$id) {
			$temp_files=explode(',',$task['attchmentfile']);
			for ($i=0; $i <count($temp_files) ; $i++) { 
				array_push($files,$temp_files[$i]);
			}
		}
	}
	$file_names=implode(',',$files);
	$update_file = "UPDATE tasks SET attchmentfile='$file_names' WHERE id =".$id;
	$results = mysqli_query($conn,$update_file);
}
echo json_encode("true");

?>