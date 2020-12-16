<?php
include("database/connection.php");
function getTasks() {
    global $conn;
    $fetch_task_query="SELECT tasks.id,tasks.attchmentfile,tasks.task_name,tasks.dependency,tasks.duration,tasks.attchmentfile,tasks.level,tasks.start_date,tasks.status,tasks.ended_date,tasks.task_responsable,tasks.has_dependency,tasks.notes,tasks.percentage_completed,dependency.id_task,dependency.id_task_dependency,tasks.is_milestone,tasks.is_milestone1,tasks.megaMilestone,dependency.id as did from tasks LEFT JOIN dependency ON tasks.id=dependency.id_task ORDER BY tasks.id";

    $task_result = mysqli_query($conn,$fetch_task_query);

    $rows = [];
    while ($tasks = mysqli_fetch_assoc($task_result)) {
    	$tasks['task_responsable'] = json_decode($tasks['task_responsable']);
    	$tasks['notes'] = str_replace("\r\n", "<br/>", $tasks['notes']);
    	$tasks['notes'] = str_replace("\n", "<br/>", $tasks['notes']);
        $tasks['attchmentfile'] = explode(',', $tasks['attchmentfile']);
        if (!isset($tasks['id_task_dependency'])) {
            $tasks['id_task_dependency']=0;
        }
        array_push($rows, $tasks);
    }
    return json_encode($rows);
}

function getRoles() {
    global $conn;
    $fetch_role_query="SELECT * from roles";

    $task_result = mysqli_query($conn,$fetch_role_query);

    $rows = [];
    while ($tasks = mysqli_fetch_assoc($task_result)) {
       array_push($rows, $tasks);
   }
   return json_encode($rows);
}

function getResources() {
    global $conn;
    $fetch_resources_query="SELECT * from resources";

    $task_result = mysqli_query($conn,$fetch_resources_query);

    $rows = [];
    while ($tasks = mysqli_fetch_assoc($task_result)) {
       array_push($rows, $tasks);
   }
   return json_encode($rows);
}
$tasks = getTasks();
$roles = getRoles();
$resources = getResources();
?>