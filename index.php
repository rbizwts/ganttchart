<?php
include("controller/list_tasks.php"); 
?>
<!DOCTYPE HTML>
<html>
<head>
  <meta http-equiv="X-UA-Compatible" content="IE=9; IE=8; IE=7; IE=EDGE"/>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
  <title>Teamwork</title>

  <link rel=stylesheet href="platform.css" type="text/css">
  <link rel=stylesheet href="libs/jquery/dateField/jquery.dateField.css" type="text/css">

  <link rel=stylesheet href="gantt.css" type="text/css">
  <link rel=stylesheet href="ganttPrint.css" type="text/css" media="print">

  <script src="http://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
  <script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>

  <script src="libs/jquery/jquery.livequery.1.1.1.min.js"></script>
  <script src="libs/jquery/jquery.timers.js"></script>

  <script src="libs/utilities.js"></script>
  <script src="libs/forms.js"></script>
  <script src="libs/date.js"></script>
  <script src="libs/dialogs.js"></script>
  <script src="libs/layout.js"></script>
  <script src="libs/i18nJs.js"></script>
  <script src="libs/jquery/dateField/jquery.dateField.js"></script>
  <script src="libs/jquery/JST/jquery.JST.js"></script>

  <script type="text/javascript" src="libs/jquery/svg/jquery.svg.min.js"></script>
  <script type="text/javascript" src="libs/jquery/svg/jquery.svgdom.1.8.js"></script>


  <script src="ganttUtilities.js"></script>
  <script src="ganttTask.js"></script>
  <script src="ganttDrawerSVG.js"></script>
  <script src="ganttZoom.js"></script>
  <script src="ganttGridEditor.js"></script>
  <script src="ganttMaster.js"></script>  
</head>
<body style="background-color: #fff;">

  <div id="workSpace" style="padding:0px; overflow-y:auto; overflow-x:hidden;border:1px solid #e5e5e5;position:relative;margin:0 5px"></div>

  <style>
    .tab {
      overflow: hidden;
      border: 1px solid #ccc;
      background-color: #f1f1f1;
    }

    /* Style the buttons inside the tab */
    .tab button {
      background-color: inherit;
      float: left;
      border: none;
      outline: none;
      cursor: pointer;
      padding: 10px 10px;
      transition: 0.3s;
      font-size: 17px;
    }
    .tab button:hover {
      background-color: #ddd;
    }

    /* Create an active/current tablink class */
    .tab button.active {
      background-color: #ccc;
    }

    /* Style the tab content */
    .tabcontent {
      display: none;
      padding: 6px 12px;
      border: 1px solid #ccc;
      border-top: none;
    }

    .resEdit {
      padding: 15px;
    }

    .resLine {
      width: 95%;
      padding: 3px;
      margin: 5px;
      border: 1px solid #d0d0d0;
    }

    body {
      overflow: hidden;
    }

    .ganttButtonBar h1{
      color: #000000;
      font-weight: bold;
      font-size: 28px;
      margin-left: 10px;
    }

  </style>

  <form id="gimmeBack" style="display:none;" action="../gimmeBack.jsp" method="post" target="_blank"><input type="hidden" name="prj" id="gimBaPrj"></form>

  <script type="text/javascript">
    var countSuccessors=1;;
    var ge;
    $(function() {
      setTimeout(() => {
        $('#addNew').text('Click here to add new task');
      }, 500);
  var canWrite=true; //this is the default for test purposes

  // here starts gantt initialization
  ge = new GanttMaster();
  ge.set100OnClose=true;

  ge.shrinkParent=true;

  ge.init($("#workSpace"));
  loadI18n(); //overwrite with localized ones

  //in order to force compute the best-fitting zoom level
  delete ge.gantt.zoom;

  var project=loadData();

  if (!project.canWrite)
    $(".ganttButtonBar button.requireWrite").attr("disabled","true");

  ge.loadProject(project);
  ge.checkpoint(); //empty the undo stack

});



    function getDemoProject(){
      console.log('<?php echo $tasks ?>');
      var tasks=JSON.parse(JSON.stringify(JSON.parse('<?php echo $tasks ?>')));
      var roles=JSON.parse('<?php echo $roles ?>');
      var resources=JSON.parse('<?php echo $resources ?>');
  //console.debug("getDemoProject")
  ret= {"tasks":[],
  "selectedRow": 2, "deletedTaskIds": [],
  "resources": [
  ],
  "roles":[
  ], "canWrite":    true, "canDelete":true, "canWriteOnParent": true, canAdd:true}

  for (var i = tasks.length - 1; i >= 0; i--) {
   tasks[i]['name']=tasks[i]['task_name'];
   tasks[i]['depends']=tasks[i]['id_task_dependency'];  
   tasks[i]['description']=tasks[i]['notes'];
   tasks[i]['duration']=parseInt(tasks[i]['duration']);
   tasks[i]['collapsed']=false;
   tasks[i]['canWrite']=true;
   tasks[i]['megaMilestone']=parseInt(tasks[i]['megaMilestone']);
   tasks[i]['startIsMilestone']=parseInt(tasks[i]['is_milestone']);
   tasks[i]['endIsMilestone']=parseInt(tasks[i]['is_milestone1']);
   tasks[i]['canAdd']=true;
   tasks[i]['canAddIssue']=true;
   tasks[i]['progressByWorklog']=false;
   tasks[i]['progress']=parseInt(tasks[i]['percentage_completed']);
   tasks[i]['status']=tasks[i]['status'];
   tasks[i]['type']="";
   tasks[i]['typeId']="";
   tasks[i]['assigs']=tasks[i]['task_responsable'];
   tasks[i]['level']=parseInt(tasks[i]['level']);
   tasks[i]['relevance ']=0;
   tasks[i]['start']=parseInt(tasks[i]['start_date']);
   tasks[i]['end']=parseInt(tasks[i]['ended_date']);
   tasks[i]['dependency']=tasks[i]['dependency'];
   tasks[i]['attchmentfile']=tasks[i]['attchmentfile'];
 }
 console.log(tasks,'tasks');
 ret['tasks']=tasks;
 ret['roles']=roles;
 ret['resources']=resources;
    //actualize data
    if (ret.tasks.length!=0) {var offset=new Date().getTime()-ret.tasks[0].start;}
    for (var i=0;i<ret.tasks.length;i++) {
      ret.tasks[i].start = ret.tasks[i].start;
    }

    return ret;
  }

  function loadGanttFromServer(taskId, callback) {

  //this is a simulation: load data from the local storage if you have already played with the demo or a textarea with starting demo data
  var ret=loadData();
  return ret;
}



function saveGanttOnServer() {
  var prj = ge.saveProject();
  console.log(prj);
  localStorage.setObject("teamworkGantDemo", prj);

  if (ge.deletedTaskIds.length>0) {
    if (!confirm("TASK_THAT_WILL_BE_REMOVED\n"+ge.deletedTaskIds.length)) {
      return;
    }
  }
  console.log(prj);
  $.ajax("controller/ganttSaveController.php", {
    dataType:"json",
    data: {deletedIds:prj.deletedTaskIds,tasks:JSON.stringify(prj.tasks)},
    type:"POST",
    success: function(response) {
     setTimeout(()=>{
      window.location.reload();
    },500);
     if (response.ok) {
     } else {
     }
   }
 });

}

function newProject(){
  clearGantt();
}


function clearGantt() {
  ge.reset();
}

//-------------------------------------------  Get project file as JSON (used for migrate project from gantt to Teamwork) ------------------------------------------------------
function getFile() {
  $("#gimBaPrj").val(JSON.stringify(ge.saveProject()));
  $("#gimmeBack").submit();
  $("#gimBaPrj").val("");

  /*  var uriContent = "data:text/html;charset=utf-8," + encodeURIComponent(JSON.stringify(prj));
  neww=window.open(uriContent,"dl");*/
}


function loadData() {
 var ret;

 ret=getDemoProject();
 if (!ret || !ret.tasks || ret.tasks.length == 0){
   /*ret.tasks=[
     {
      "process_status": "8",
      "id": "tmp_1",
      "id_task": "tmp_1",
      "name": "Test 1",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "megaMilestone":1,
      "description": "",
      "level": 1,
      "status": "STATUS_ACTIVE",
      "depends": "0",
      "id_task_dependency": "0",
      "start": 1604584203019,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": false,
      "canDelete": false,
      "canAddIssue": false,
      "assigs": [
        
      ],
      "has_dependency": 1,
      "rowElement": [
        "tr#tid_329.taskEditRow"
      ],
      "fBudget": 0,
      "iBudget": 0
    },
    {
      "process_status": "8",
      "id": "tmp_9",
      "id_task": "tmp_9",
      "name": "Test 9",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 1,
      "status": "STATUS_ACTIVE",
      "depends": "0",
      "id_task_dependency": "0",
      "start": 1604676192000,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": false,
      "canDelete": false,
      "canAddIssue": false,
      "assigs": [
        
      ],
      "has_dependency": 1,
      "rowElement": [
        "tr#tid_329.taskEditRow"
      ],
      "fBudget": 0,
      "iBudget": 0
    },{
      "process_status": "8",
      "id": "tmp_2",
      "id_task": "tmp_2",
      "name": "Test 2",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 1,
      "status": "STATUS_ACTIVE",
      "megaMilestone":1,
      "depends": "0",
      "id_task_dependency": "0",
      "start": 1604584203019,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": false,
      "canDelete": false,
      "canAddIssue": false,
      "assigs": [
        
      ],
      "has_dependency": 1,
      "rowElement": [
        "tr#tid_329.taskEditRow"
      ],
      "fBudget": 0,
      "iBudget": 0
    },{
      "process_status": "8",
      "id": "tmp_3",
      "id_task": "tmp_3",
      "name": "Test 3",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 1,
      "status": "STATUS_ACTIVE",
      "depends": "0",
      "id_task_dependency": "0",
      "start": 1604676192000,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": false,
      "canDelete": false,
      "canAddIssue": false,
      "assigs": [
        
      ],
      "has_dependency": 1,
      "rowElement": [
        "tr#tid_329.taskEditRow"
      ],
      "fBudget": 0,
      "iBudget": 0
    },{
      "process_status": "8",
      "id": "tmp_4",
      "id_task": "tmp_4",
      "name": "Test 4",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "megaMilestone":1,
      "description": "",
      "level": 1,
      "status": "STATUS_ACTIVE",
      "depends": "0",
      "id_task_dependency": "0",
      "start": 1604584203019,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": false,
      "canDelete": false,
      "canAddIssue": false,
      "assigs": [
        
      ],
      "has_dependency": 1,
      "rowElement": [
        "tr#tid_329.taskEditRow"
      ],
      "fBudget": 0,
      "iBudget": 0
    },{
      "process_status": "8",
      "id": "tmp_5",
      "id_task": "tmp_5",
      "name": "Test 5",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 1,
      "status": "STATUS_ACTIVE",
      "depends": "0",
      "id_task_dependency": "0",
      "start": 1604584203019,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": false,
      "canDelete": false,
      "canAddIssue": false,
      "assigs": [
        
      ],
      "has_dependency": 1,
      "rowElement": [
        "tr#tid_329.taskEditRow"
      ],
      "fBudget": 0,
      "iBudget": 0
    },{
      "process_status": "8",
      "id": "tmp_6",
      "id_task": "tmp_6",
      "name": "Test 6",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 1,
      "status": "STATUS_ACTIVE",
      "megaMilestone":1,
      "depends": "0",
      "id_task_dependency": "0",
      "start": 1604584203019,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": false,
      "canDelete": false,
      "canAddIssue": false,
      "assigs": [
        
      ],
      "has_dependency": 1,
      "rowElement": [
        "tr#tid_329.taskEditRow"
      ],
      "fBudget": 0,
      "iBudget": 0
    },{
      "process_status": "8",
      "id": "tmp_7",
      "id_task": "tmp_7",
      "name": "Test 7",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 1,
      "status": "STATUS_ACTIVE",
      "depends": "0",
      "id_task_dependency": "0",
      "start": 1604584203019,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": false,
      "canDelete": false,
      "canAddIssue": false,
      "assigs": [
        
      ],
      "has_dependency": 1,
      "rowElement": [
        "tr#tid_329.taskEditRow"
      ],
      "fBudget": 0,
      "iBudget": 0
    },{
      "process_status": "8",
      "id": "tmp_8",
      "id_task": "tmp_8",
      "name": "Test 8",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 1,
      "status": "STATUS_ACTIVE",
      "depends": "0",
      "id_task_dependency": "0",
      "start": 1604584203019,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      // "megaMilestone":true,
      "collapsed": false,
      "canWrite": true,
      "canAdd": false,
      "canDelete": false,
      "canAddIssue": false,
      "assigs": [
        
      ],
      "has_dependency": 1,
      "rowElement": [
        "tr#tid_329.taskEditRow"
      ],
      "fBudget": 0,
      "iBudget": 0
    }
    ];*/
    ret.tasks=[
    {
      "process_status": "8",
      "id": "milestone_370",
      "id_task": "milestone_370",
      "name": "Inicio de Etapa",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 0,
      "status": "STATUS_ACTIVE",
      "depends": "0",
      "id_task_dependency": "0",
      "start": 1607776978777,
      "duration": 1,
      "end": "",
      "startIsMilestone": true,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": false,
      "canDelete": false,
      "canAddIssue": false,
      "assigs": [],
      "has_dependency": "1",
      "rowElement": [
        "tr#tid_370.taskEditRow"
      ],
      "fBudget": 0,
      "iBudget": 0,
      "megaMilestone": 1
    },
    {
      "process_status": "8",
      "id": "370",
      "id_task": "370",
      "name": "test",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 0,
      "status": "STATUS_ACTIVE_STEP",
      "depends": "0",
      "id_task_dependency": "0",
      "start": "",
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [],
      "has_dependency": "1",
      "rowElement": [
        "tr#tid_370.taskEditRow"
      ],
      "fBudget": 0,
      "iBudget": 0,
      "origin": "step",
      "megaMilestone": false
    },
    {
      "process_status": "8",
      "id": "257",
      "name": "fase 1",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 1,
      "status": "STATUS_ACTIVE_PHASE",
      "depends": "0",
      "start": 1607776978777,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [],
      "has_dependency": 0,
      "fBudget": 0,
      "iBudget": 0,
      "origin": "phase",
      "megaMilestone": false
    },
    {
      "process_status": "8",
      "id": "353",
      "name": "actividad fase 1",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 2,
      "status": "STATUS_ACTIVE",
      "depends": "",
      "start": 1607776978777,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        }
      ],
      "has_dependency": 0,
      "checkpoints": {
        "checklists": [
          {
            "items": [
              {
                "id": "38",
                "name": "item",
                "finished": "0",
                "approved": "0",
                "rejected": null
              }
            ],
            "id": "36",
            "name": "checklist",
            "approved": "1",
            "justification": "0"
          }
        ],
        "approvements": {
          "error": 0,
          "approvements": [],
          "checkpoints": {
            "attachments": [],
            "checklists": [
              {
                "id": "36",
                "id_proceso": "459",
                "id_actividad": "353",
                "created_by": "863",
                "created_on": "2020-12-03 07:30:22",
                "name": "checklist",
                "finished": "0",
                "approved": "1",
                "approved_by": "0",
                "justification": "0",
                "published": "1"
              }
            ],
            "specifications": []
          }
        }
      },
      "fBudget": "0",
      "iBudget": "0",
      "origin": "activity",
      "megaMilestone": false
    },
    {
      "process_status": "8",
      "id": "378",
      "name": "actividad 2 fase 1",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 2,
      "status": "STATUS_ACTIVE",
      "depends": "4:0",
      "start": 1607863378777,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        }
      ],
      "has_dependency": true,
      "checkpoints": {
        "checklists": [
          {
            "items": [
              {
                "id": "39",
                "name": "item 1",
                "finished": "0",
                "approved": "0",
                "rejected": null
              }
            ],
            "id": "37",
            "name": "checklist 1",
            "approved": "1",
            "justification": "0"
          }
        ],
        "approvements": {
          "error": 0,
          "approvements": [],
          "checkpoints": {
            "attachments": [],
            "checklists": [
              {
                "id": "37",
                "id_proceso": "459",
                "id_actividad": "378",
                "created_by": "863",
                "created_on": "2020-12-04 05:42:01",
                "name": "checklist 1",
                "finished": "0",
                "approved": "1",
                "approved_by": "0",
                "justification": "0",
                "published": "1"
              }
            ],
            "specifications": []
          }
        }
      },
      "fBudget": "0",
      "iBudget": "0",
      "origin": "activity",
      "megaMilestone": false,
      "dependency": "4:0::4"
    },
    {
      "process_status": "8",
      "id": "267",
      "name": "fase 2",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 1,
      "status": "STATUS_ACTIVE_PHASE",
      "depends": "3",
      "start": 1607776978777,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [],
      "has_dependency": 0,
      "fBudget": 0,
      "iBudget": 0,
      "origin": "phase",
      "megaMilestone": false
    },
    {
      "process_status": "8",
      "id": "377",
      "name": "actividad fase 2",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 2,
      "status": "STATUS_ACTIVE",
      "depends": "4:0",
      "start": 1607863378777,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        }
      ],
      "has_dependency": true,
      "checkpoints": {
        "checklists": [],
        "approvements": {
          "error": 0,
          "approvements": [],
          "checkpoints": {
            "attachments": [],
            "checklists": [],
            "specifications": [
              {
                "id": "14",
                "id_proceso": "459",
                "id_actividad": "377",
                "created_by": "863",
                "created_on": "2020-12-02 08:35:09",
                "name": "test",
                "finished": "0",
                "approved_by": "0",
                "justification": "0",
                "published": "1",
                "approved": "",
                "warning": "",
                "rejected": ""
              }
            ]
          }
        },
        "ranges": [
          {
            "id": "14",
            "name": "test",
            "approved": "",
            "warning": "",
            "rejected": ""
          }
        ]
      },
      "fBudget": "0",
      "iBudget": "0",
      "origin": "activity",
      "megaMilestone": false,
      "dependency": "4:0::2"
    },
    {
      "process_status": "8",
      "id": "380",
      "name": "fin a fin 1",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 2,
      "status": "STATUS_ACTIVE",
      "depends": "",
      "start": 1607776978777,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        }
      ],
      "has_dependency": 0,
      "checkpoints": {
        "checklists": [
          {
            "items": [
              {
                "id": "37",
                "name": "sdfsdf",
                "finished": "0",
                "approved": "0",
                "rejected": null
              }
            ],
            "id": "34",
            "name": "sadasda",
            "approved": "1",
            "justification": "0"
          }
        ],
        "approvements": {
          "error": 0,
          "approvements": [],
          "checkpoints": {
            "attachments": [],
            "checklists": [
              {
                "id": "34",
                "id_proceso": "459",
                "id_actividad": "380",
                "created_by": "863",
                "created_on": "2020-12-02 08:35:23",
                "name": "sadasda",
                "finished": "0",
                "approved": "1",
                "approved_by": "0",
                "justification": "0",
                "published": "1"
              }
            ],
            "specifications": []
          }
        }
      },
      "fBudget": "0",
      "iBudget": "0",
      "origin": "activity",
      "megaMilestone": false
    },
    {
      "process_status": "8",
      "id": "381",
      "name": "fin a fin 2",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 2,
      "status": "STATUS_ACTIVE",
      "depends": "8:0",
      "start": 1607863378777,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        }
      ],
      "has_dependency": true,
      "checkpoints": {
        "checklists": [],
        "approvements": {
          "error": 0,
          "approvements": [],
          "checkpoints": {
            "attachments": [],
            "checklists": [],
            "specifications": []
          }
        }
      },
      "fBudget": "0",
      "iBudget": "0",
      "origin": "activity",
      "megaMilestone": false,
      "dependency": "8:0::2"
    },
    {
      "process_status": "8",
      "id": "389",
      "name": "otra diferente 2",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 2,
      "status": "STATUS_ACTIVE",
      "depends": "",
      "start": 1607776978777,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        }
      ],
      "has_dependency": 0,
      "checkpoints": {
        "checklists": [
          {
            "items": [
              {
                "id": "40",
                "name": "item nuevo",
                "finished": "0",
                "approved": "0",
                "rejected": null
              }
            ],
            "id": "38",
            "name": "esta es una vacia",
            "approved": "0",
            "justification": "0"
          }
        ],
        "approvements": {
          "error": 0,
          "approvements": [],
          "checkpoints": {
            "attachments": [],
            "checklists": [
              {
                "id": "38",
                "id_proceso": "459",
                "id_actividad": "389",
                "created_by": "863",
                "created_on": "2020-12-07 05:35:59",
                "name": "esta es una vacia",
                "finished": "0",
                "approved": "0",
                "approved_by": "0",
                "justification": "0",
                "published": "1"
              }
            ],
            "specifications": []
          }
        }
      },
      "fBudget": "0",
      "iBudget": "0",
      "origin": "activity",
      "megaMilestone": false
    },
    {
      "process_status": "8",
      "id": "268",
      "name": "fase 3",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 1,
      "status": "STATUS_ACTIVE_PHASE",
      "depends": "6",
      "start": 1607776978777,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [],
      "has_dependency": 0,
      "fBudget": 0,
      "iBudget": 0,
      "origin": "phase",
      "megaMilestone": false
    },
    {
      "process_status": "8",
      "id": "379",
      "name": "actividad fase 3",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 2,
      "status": "STATUS_ACTIVE",
      "depends": "5:0",
      "start": 1607863378777,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        }
      ],
      "has_dependency": true,
      "checkpoints": {
        "checklists": [],
        "approvements": {
          "error": 0,
          "approvements": [],
          "checkpoints": {
            "attachments": [],
            "checklists": [],
            "specifications": []
          }
        }
      },
      "fBudget": "0",
      "iBudget": "0",
      "origin": "activity",
      "megaMilestone": false,
      "dependency": "5:0::1"
    },
    {
      "process_status": "8",
      "id": "384",
      "name": "una actividad",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 2,
      "status": "STATUS_ACTIVE",
      "depends": "",
      "start": 1607776978777,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        }
      ],
      "has_dependency": 0,
      "checkpoints": {
        "checklists": [
          {
            "items": [
              {
                "id": "33",
                "name": "item 1",
                "finished": "0",
                "approved": "0",
                "rejected": null
              },
              {
                "id": "34",
                "name": "item 2",
                "finished": "0",
                "approved": "0",
                "rejected": null
              }
            ],
            "id": "35",
            "name": "test",
            "approved": "0",
            "justification": "0"
          }
        ],
        "approvements": {
          "error": 0,
          "approvements": [],
          "checkpoints": {
            "attachments": [],
            "checklists": [
              {
                "id": "35",
                "id_proceso": "459",
                "id_actividad": "384",
                "created_by": "863",
                "created_on": "2020-12-02 08:51:35",
                "name": "test",
                "finished": "0",
                "approved": "0",
                "approved_by": "0",
                "justification": "0",
                "published": "1"
              }
            ],
            "specifications": []
          }
        }
      },
      "fBudget": "0",
      "iBudget": "0",
      "origin": "activity",
      "megaMilestone": false
    },
    {
      "process_status": "8",
      "id": "387",
      "name": "actividad",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 2,
      "status": "STATUS_ACTIVE",
      "depends": "",
      "start": 1607776978777,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        }
      ],
      "has_dependency": 0,
      "checkpoints": {
        "checklists": [],
        "approvements": {
          "error": 0,
          "approvements": [],
          "checkpoints": {
            "attachments": [],
            "checklists": [],
            "specifications": []
          }
        }
      },
      "fBudget": "0",
      "iBudget": "0",
      "origin": "activity",
      "megaMilestone": false
    },
    {
      "process_status": "8",
      "id": "388",
      "name": "otra diferente",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 2,
      "status": "STATUS_ACTIVE",
      "depends": "",
      "start": 1607776978777,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        }
      ],
      "has_dependency": 0,
      "checkpoints": {
        "checklists": [],
        "approvements": {
          "error": 0,
          "approvements": [],
          "checkpoints": {
            "attachments": [],
            "checklists": [],
            "specifications": []
          }
        }
      },
      "fBudget": "0",
      "iBudget": "0",
      "origin": "activity",
      "megaMilestone": false
    },
    {
      "process_status": "8",
      "id": "milestone_371",
      "id_task": "milestone_371",
      "name": "Inicio de Etapa",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 0,
      "status": "STATUS_ACTIVE",
      "depends": "2",
      "id_task_dependency": "2",
      "start": 1608122578777,
      "duration": 1,
      "end": "",
      "startIsMilestone": true,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": false,
      "canDelete": false,
      "canAddIssue": false,
      "assigs": [],
      "has_dependency": "1",
      "rowElement": [
        "tr#tid_371.taskEditRow"
      ],
      "fBudget": 0,
      "iBudget": 0,
      "megaMilestone": 1
    },
    {
      "process_status": "8",
      "id": "371",
      "id_task": "371",
      "name": "otro",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 0,
      "status": "STATUS_ACTIVE_STEP",
      "depends": "2",
      "id_task_dependency": "2",
      "start": "",
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [],
      "has_dependency": "1",
      "rowElement": [
        "tr#tid_371.taskEditRow"
      ],
      "fBudget": 0,
      "iBudget": 0,
      "origin": "step",
      "megaMilestone": false
    },
    {
      "process_status": "8",
      "id": "271",
      "name": "fase 2.1",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 1,
      "status": "STATUS_ACTIVE_PHASE",
      "depends": "0",
      "start": 1607863378777,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [],
      "has_dependency": 0,
      "fBudget": 0,
      "iBudget": 0,
      "origin": "phase",
      "megaMilestone": false
    },
    {
      "process_status": "8",
      "id": "385",
      "name": "act 2.1",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 2,
      "status": "STATUS_ACTIVE",
      "depends": "",
      "start": 1607863378777,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        }
      ],
      "has_dependency": 0,
      "checkpoints": {
        "checklists": [],
        "approvements": {
          "error": 0,
          "approvements": [],
          "checkpoints": {
            "attachments": [],
            "checklists": [],
            "specifications": []
          }
        }
      },
      "fBudget": "0",
      "iBudget": "0",
      "origin": "activity",
      "megaMilestone": false
    },
    {
      "process_status": "8",
      "id": "386",
      "name": "act 2.2",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 2,
      "status": "STATUS_ACTIVE",
      "depends": "",
      "start": 1607863378777,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        }
      ],
      "has_dependency": 0,
      "checkpoints": {
        "checklists": [],
        "approvements": {
          "error": 0,
          "approvements": [],
          "checkpoints": {
            "attachments": [],
            "checklists": [],
            "specifications": []
          }
        }
      },
      "fBudget": "0",
      "iBudget": "0",
      "origin": "activity",
      "megaMilestone": false
    },
    {
      "process_status": "8",
      "id": "milestone_445",
      "id_task": "milestone_445",
      "name": "Inicio de Etapa",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 0,
      "status": "STATUS_ACTIVE",
      "depends": "17",
      "id_task_dependency": "17",
      "start": 1608036178777,
      "duration": 1,
      "end": "",
      "startIsMilestone": true,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": false,
      "canDelete": false,
      "canAddIssue": false,
      "assigs": [],
      "has_dependency": "1",
      "rowElement": [
        "tr#tid_445.taskEditRow"
      ],
      "fBudget": 0,
      "iBudget": 0,
      "megaMilestone": 1
    },
    {
      "process_status": "8",
      "id": "445",
      "id_task": "445",
      "name": "etapa 3",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 0,
      "status": "STATUS_ACTIVE_STEP",
      "depends": "17",
      "id_task_dependency": "17",
      "start": "",
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [],
      "has_dependency": "1",
      "rowElement": [
        "tr#tid_445.taskEditRow"
      ],
      "fBudget": 0,
      "iBudget": 0,
      "origin": "step",
      "megaMilestone": false
    },
    {
      "process_status": "8",
      "id": "276",
      "name": "fase 3.1",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 1,
      "status": "STATUS_ACTIVE_PHASE",
      "depends": "0",
      "start": 1607949778777,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [],
      "has_dependency": 0,
      "fBudget": 0,
      "iBudget": 0,
      "origin": "phase",
      "megaMilestone": false
    },
    {
      "process_status": "8",
      "id": "395",
      "name": "act 3.1",
      "progress": 0,
      "progressByWorklog": false,
      "relevance": 0,
      "type": "",
      "typeId": "",
      "description": "",
      "level": 2,
      "status": "STATUS_ACTIVE",
      "depends": "",
      "start": 1607949778777,
      "duration": 1,
      "end": "",
      "startIsMilestone": false,
      "endIsMilestone": false,
      "collapsed": false,
      "canWrite": true,
      "canAdd": true,
      "canDelete": true,
      "canAddIssue": true,
      "assigs": [
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "862",
          "resourceId": "862",
          "roleId": 1,
          "effort": 0
        },
        {
          "id": "0",
          "resourceId": "0",
          "roleId": 1,
          "effort": 0
        }
      ],
      "has_dependency": 0,
      "checkpoints": {
        "checklists": [],
        "approvements": {
          "error": 0,
          "approvements": [],
          "checkpoints": {
            "attachments": [],
            "checklists": [],
            "specifications": []
          }
        }
      },
      "fBudget": "0",
      "iBudget": "0",
      "origin": "activity",
      "megaMilestone": false
    }
  ]
 }


 setTimeout(() => {
  if(ret.tasks.length>0){
    console.log(ret.tasks);
    ret.tasks.forEach((element, i) => {
      console.log(`${i}`,element);
      //console.log(i,element);
      /*if($("#mega"+element.id).prop('checked')){
        $("#mega"+element.id).closest('td').next().find('[type=checkbox]').prop('disabled', true);
        $("#mega"+element.id).hide();
      }
      if($("#start"+element.id).prop('checked')){
        $("#start"+element.id).closest('td').prev().find('[type=checkbox]').prop('disabled', true);
      }*/
      /*if(element.megaMilestone)
      {
        //ge.updateLinks(task);
        ge.beginTransaction();
        //console.log(ret.tasks[i]);
        var parentTaskUpdate = ge.getTask(ret.tasks[i].id);
        var task = ge.getTask(ret.tasks[i+1].id);
        console.log("parentTaskUpdate",parentTaskUpdate);
        console.log("task",task);
        //task.start=parentTaskUpdate.start;
        console.log(i);
        task.depends=i+1+":1";
        //console.log(ret.tasks[i+1]);
        console.log("task",task);
        //console.log(task);
        ge.updateLinks(task);
        ge.updateLinks(parentTaskUpdate);
        ge.changeTaskDeps(task,3);
        ge.endTransaction();
        task.start=parentTaskUpdate.start;
      }*/
    });
    //ge.updateLinks(task);
    //ge.updateLinks(parentTaskUpdate);
    //ge.changeTaskDeps(task,dep);
  }

  /*$(".MegaMilestoneClicked").click(function(){
    if($(this).prop("checked") == true){
      if(confirm("Once mega milestone is set then it can not be revise or delete that activity")){
      //$("#addNew").click();
      var factory = new TaskFactory();
      var ch = factory.build("tmp_fk" + new Date().getTime(), "Inicio de Etapa", "", 1, 1604584203019, Date.workingPeriodResolution);
      //console.log("on line 591");
      //console.log(ch);
      //console.log(self);
      //console.log(this);
      ge.beginTransaction();
      console.log(typeof($(this).closest('tr').find(".taskRowIndex").text()));
      
      var integer = parseInt($(this).closest('tr').find(".taskRowIndex").text(), 10);
      let task=ge.addTask(ch,integer);
      
      //ge.moveUpCurrentTask();

      setTimeout(() => {
        $(this).closest('td').next().find('[type=checkbox]').prop('disabled', true);
          let parentId=$(this).closest('td').prev().find('[type=text]').attr('id');
          console.log(parentId,"parentId");
          setTimeout(() => {
            //Dependency
            //ge.beginTransaction();
            let delay = 0;
            if (delay == "") {
              delay = 0;
            }
            let parent;
            console.log("get task",ge.getTask($(this).closest('td').prev().find('[type=text]').attr('id')));
            var parentTaskUpdate = ge.getTask(parentId);
            parentTaskUpdate.startIsMilestone=0;

            console.log($(this).closest('td').prev().find('[type=text]').attr('id'));
            parentTaskUpdate.megaMilestone=1;
            for (let index = 0; index < ret.tasks.length; index++) {
              const tsk = ret.tasks[index];
              console.log(tsk,"tsk",task.id,parentId);
              if(tsk.id==parentId){
                parentId=parseInt($(this).closest('tr').find(".taskRowIndex").text(), 10);;
                console.log(parentId,"is in tsk.id === parentId",$(this).closest('tr').find(".taskRowIndex").text());
              }
            }
            let depends = parentId+":0,";
              if (/[,]/.test(depends)) {
                let dependsArray = depends.split(",");
                let newdependency;
                if (dependsArray.length > 1) {
                  console.log("dependsArray.length > 1");
                  let newDepends = [];
                  console.log(dependsArray.length,"dependsArray.length");
                  for (let index = 0; index < dependsArray.length; index++) {
                    const element = dependsArray[index];

                    if (index == dependsArray.length - 1) {
                      console.log("index == dependsArray.length - 1");
                      if(delay>0)
                      {
                        newDepends.push(element + ":" + delay);
                        console.log(element, delay,"element  + delay");
                      }
                    } else {
                      console.log(element, "newDepends.push(element)");
                      newDepends.push(element);
                    }
                  }
                  console.log(newDepends, "newDepends");
                  newDepends = newDepends.join(",");
                  task.depends = newDepends;
                  dependsArray = task.depends.split(",");

                  parent = dependsArray[dependsArray.length - 1];
                  newdependency =
                  dependsArray[dependsArray.length - 1] +
                  "::" +
                  3
                } else {
                  let newDepends = [];
                  for (let index = 0; index < dependsArray.length; index++) {
                    const element = dependsArray[index];
                    if (index == 0) {
                      if(delay>0)
                        newDepends.push(element + ":" + delay);
                    } else {
                      newDepends.push(element);
                    }
                  }
                  newDepends = newDepends.join(",");
                  task.depends = newDepends;
                  dependsArray = task.depends.split(",");
                  parent = dependsArray[0];
                  newdependency = dependsArray[0] + "::" + 3;
                }
                let dependency=[];
                if(task.dependency){  
                  let old_dependency = task.dependency;
                  dependency = old_dependency.split(",");
                }
                  dependency.push(newdependency);
                  listOfdependency = dependency.join(",");
                  task.dependency = listOfdependency;
                } else {
                  if(delay>0)
                    task.depends = task.depends + ":" + delay;

                  parent = task.depends;
                  task.dependency = task.depends + "::" + 3;
                }
                //Update Time
                if (/[:]/.test(parent)) {
                  let parentArray = parent.split(":");
                  parent = parentArray[0];
                }

            let tasks = ge.tasks;
            let parent_task;

            for (let index = 0; index < tasks.length; index++) {
              if (index == parent - 1) {
                console.log(tasks[index],"tasks[index]");
                parent_task = tasks[index];
              }
            }
            var one_day = 1000 * 60 * 60 * 24;
            let dep = 3;
            let duration_ms = task.end - task.start;
            let parent_task_id;
            let taskDuration = 0;
            let parentDuration = 0;
            if (dep == 3) {
              task.start = task.start;
              task.end = task.start + duration_ms;
              task.start = computeStart(task.start);
              task.end = computeEnd(task.end);
              taskDuration = getDurationInUnits(
                computeStartDate(task.start),
                computeEndDate(task.end)
                );
              task.status = "STATUS_ACTIVE";
              // console.log("before assigning task duration",task,dep);
            }
            console.log(task,"task at end");
            ge.updateLinks(task);
            ge.updateLinks(parentTaskUpdate);
            ge.changeTaskDeps(task,dep);
            ge.endTransaction();
        }, 1000);
        
      }, 700);
      //saveGanttOnServer();
      //ge.endTransaction();
    }else{
      $(this).click();
    }
      //ge.updateLinks(task);
    }else{
      $(this).closest('td').next().find('[type=checkbox]').prop('disabled', false);
    }
  });*/
 }, 1000);
 return ret;
}


function saveInLocalStorage() {
  var prj = ge.saveProject();
  if (localStorage) {
    localStorage.setObject("teamworkGantDemo", prj);
  }
}


//-------------------------------------------  Open a black popup for managing resources. This is only an axample of implementation (usually resources come from server) ------------------------------------------------------
function editResources(){

//make resource editor
var resourceEditor = $.JST.createFromTemplate({}, "RESOURCE_EDITOR");
var resTbl=resourceEditor.find("#resourcesTable");

for (var i=0;i<ge.resources.length;i++){
  var res=ge.resources[i];
  resTbl.append($.JST.createFromTemplate(res, "RESOURCE_ROW"))
}


//bind add resource
resourceEditor.find("#addResource").click(function(){
  console.log('For add Resource');
  resTbl.append($.JST.createFromTemplate({id:"new",name:"resource"}, "RESOURCE_ROW"))
});

//bind save event
resourceEditor.find("#resSaveButton").click(function(){
  console.log('Save Resource');
  var newRes=[];
  //find for deleted res
  for (var i=0;i<ge.resources.length;i++){
    var res=ge.resources[i];
    var row = resourceEditor.find("[resId="+res.id+"]");
    if (row.length>0){
      //if still there save it
      var name = row.find("input[name]").val();
      if (name && name!="")
        res.name=name;
      newRes.push(res);
    } else {
      //remove assignments
      for (var j=0;j<ge.tasks.length;j++){
        var task=ge.tasks[j];
        var newAss=[];
        for (var k=0;k<task.assigs.length;k++){
          var ass=task.assigs[k];
          if (ass.resourceId!=res.id)
            newAss.push(ass);
        }
        task.assigs=newAss;
      }
    }
  }

  if (newRes.length > 0) {
    for (var i = newRes.length - 1; i >= 0; i--) {
      $.ajax("controller/edit_resource.php", {
        dataType:"json",
        data: {id:newRes[i].id,name:newRes[i].name},
        type:"POST",
        success: function(response) {
          if (response.ok) {
          } else {
          }
        }
      });
    }
  }
  //loop on new rows
  var cnt=0
  resourceEditor.find("[resId=new]").each(function(){
    cnt++;
    var row = $(this);
    var name = row.find("input[name]").val();

    if (name && name!="")
    {
      $.ajax("controller/save_resource.php", {
        dataType:"json",
        data: {name:name},
        type:"POST",
        success: function(response) {
          if (response.ok) {
          } else {
          }
        }
      });

      newRes.push (new Resource("tmp_"+new Date().getTime()+"_"+cnt,name));
    }
  });

  ge.resources=newRes;

  closeBlackPopup();
  ge.redraw();
});


var ndo = createModalPopup(400, 500).append(resourceEditor);
}


function initializeHistoryManagement(){

  //si chiede al server se c'è della hisory per la root
  $.getJSON(contextPath+"/applications/teamwork/task/taskAjaxController.jsp", {CM: "GETGANTTHISTPOINTS", OBJID:10236}, function (response) {

    //se c'è
    if (response.ok == true && response.historyPoints && response.historyPoints.length>0) {

      //si crea il bottone sulla bottoniera
      var histBtn = $("<button>").addClass("button textual icon lreq30 lreqLabel").attr("title", "SHOW_HISTORY").append("<span class=\"teamworkIcon\">&#x60;</span>");

      //al click
      histBtn .click(function () {
        var el = $(this);
        var ganttButtons = $(".ganttButtonBar .buttons");

        //è gi�  in modalit�  history?
        if (!ge.element.is(".historyOn")) {
          ge.element.addClass("historyOn");
          ganttButtons.find(".requireCanWrite").hide();

          //si carica la history server side
          if (false) return;
          showSavingMessage();
          $.getJSON(contextPath + "/applications/teamwork/task/taskAjaxController.jsp", {CM: "GETGANTTHISTPOINTS", OBJID: ge.tasks[0].id}, function (response) {
            jsonResponseHandling(response);
            hideSavingMessage();
            if (response.ok == true) {
              var dh = response.historyPoints;
              //ge.historyPoints=response.historyPoints;
              if (dh && dh.length > 0) {
                //si crea il div per lo slider
                var sliderDiv = $("<div>").prop("id", "slider").addClass("lreq30 lreqHide").css({"display":"inline-block","width":"500px"});
                ganttButtons.append(sliderDiv);

                var minVal = 0;
                var maxVal = dh.length-1 ;

                $("#slider").show().mbSlider({
                  rangeColor : '#2f97c6',
                  minVal     : minVal,
                  maxVal     : maxVal,
                  startAt    : maxVal,
                  showVal    : false,
                  grid       :1,
                  formatValue: function (val) {
                    return new Date(dh[val]).format();
                  },
                  onSlideLoad: function (obj) {
                    this.onStop(obj);

                  },
                  onStart    : function (obj) {},
                  onStop     : function (obj) {
                    var val = $(obj).mbgetVal();
                    showSavingMessage();
                    $.getJSON(contextPath + "/applications/teamwork/task/taskAjaxController.jsp", {CM: "GETGANTTHISTORYAT", OBJID: ge.tasks[0].id, millis:dh[val]}, function (response) {
                      jsonResponseHandling(response);
                      hideSavingMessage();
                      if (response.ok ) {
                        ge.baselines=response.baselines;
                        ge.showBaselines=true;
                        ge.baselineMillis=dh[val];
                        ge.redraw();
                      }
                    })

                  },
                  onSlide    : function (obj) {
                    clearTimeout(obj.renderHistory);
                    var self = this;
                    obj.renderHistory = setTimeout(function(){
                      self.onStop(obj);
                    }, 200)

                  }
                });
              }
            }
          });


          // quando si spenge
        } else {
          //si cancella lo slider
          $("#slider").remove();
          ge.element.removeClass("historyOn");
          if (ge.permissions.canWrite)
            ganttButtons.find(".requireCanWrite").show();

          ge.showBaselines=false;
          ge.baselineMillis=undefined;
          ge.redraw();
        }

      });
      $("#saveGanttButton").before(histBtn);
    }
  })
}

function showBaselineInfo (event,element){
  //alert(element.attr("data-label"));
  $(element).showBalloon(event, $(element).attr("data-label"));
  ge.splitter.secondBox.one("scroll",function(){
    $(element).hideBalloon();
  })
}
</script>

<div id="gantEditorTemplates" style="display:none;">
  <div class="__template__" type="GANTBUTTONS"><!--
    <div class="ganttButtonBar noprint">
      <div class="buttons">
        <a href="#"><img src="res/twGanttLogo.png" alt="Twproject" align="absmiddle" style="max-width: 136px; padding-right: 15px"></a>

        <button onclick="$('#workSpace').trigger('undo.gantt');return false;" class="button textual icon requireCanWrite" title="undo"><span class="teamworkIcon">&#39;</span></button>
        <button onclick="$('#workSpace').trigger('redo.gantt');return false;" class="button textual icon requireCanWrite" title="redo"><span class="teamworkIcon">&middot;</span></button>
        <span class="ganttButtonSeparator requireCanWrite requireCanAdd"></span>
        <button onclick="$('#workSpace').trigger('addAboveCurrentTask.gantt');return false;" class="button textual icon requireCanWrite requireCanAdd" title="insert above"><span class="teamworkIcon">l</span></button>
        <button onclick="$('#workSpace').trigger('addBelowCurrentTask.gantt');return false;" class="button textual icon requireCanWrite requireCanAdd" title="insert below"><span class="teamworkIcon">X</span></button>
        <span class="ganttButtonSeparator requireCanWrite requireCanInOutdent"></span>
        <button onclick="$('#workSpace').trigger('outdentCurrentTask.gantt');return false;" class="button textual icon requireCanWrite requireCanInOutdent" title="un-indent task"><span class="teamworkIcon">.</span></button>
        <button onclick="$('#workSpace').trigger('indentCurrentTask.gantt');return false;" class="button textual icon requireCanWrite requireCanInOutdent" title="indent task"><span class="teamworkIcon">:</span></button>
        <span class="ganttButtonSeparator requireCanWrite requireCanMoveUpDown"></span>
        <button onclick="$('#workSpace').trigger('moveUpCurrentTask.gantt');return false;" class="button textual icon requireCanWrite requireCanMoveUpDown" title="move up"><span class="teamworkIcon">k</span></button>
        <button onclick="$('#workSpace').trigger('moveDownCurrentTask.gantt');return false;" class="button textual icon requireCanWrite requireCanMoveUpDown" title="move down"><span class="teamworkIcon">j</span></button>
        <span class="ganttButtonSeparator requireCanWrite requireCanDelete"></span>
        <button onclick="$('#workSpace').trigger('deleteFocused.gantt');return false;" class="button textual icon delete requireCanWrite" title="Elimina"><span class="teamworkIcon">&cent;</span></button>
        <span class="ganttButtonSeparator"></span>
        <button onclick="$('#workSpace').trigger('expandAll.gantt');return false;" class="button textual icon " title="EXPAND_ALL"><span class="teamworkIcon">6</span></button>
        <button onclick="$('#workSpace').trigger('collapseAll.gantt'); return false;" class="button textual icon " title="COLLAPSE_ALL"><span class="teamworkIcon">5</span></button>

      <span class="ganttButtonSeparator"></span>
        <button onclick="$('#workSpace').trigger('zoomMinus.gantt'); return false;" class="button textual icon " title="zoom out"><span class="teamworkIcon">)</span></button>
        <button onclick="$('#workSpace').trigger('zoomPlus.gantt');return false;" class="button textual icon " title="zoom in"><span class="teamworkIcon">(</span></button>
      <span class="ganttButtonSeparator"></span>
        <button onclick="$('#workSpace').trigger('print.gantt');return false;" class="button textual icon " title="Print"><span class="teamworkIcon">p</span></button>
      <span class="ganttButtonSeparator"></span>
        <button onclick="ge.gantt.showCriticalPath=!ge.gantt.showCriticalPath; ge.redraw();return false;" class="button textual icon requireCanSeeCriticalPath" title="CRITICAL_PATH"><span class="teamworkIcon">&pound;</span></button>
      <span class="ganttButtonSeparator requireCanSeeCriticalPath"></span>
        <button onclick="ge.splitter.resize(.1);return false;" class="button textual icon" ><span class="teamworkIcon">F</span></button>
        <button onclick="ge.splitter.resize(50);return false;" class="button textual icon" ><span class="teamworkIcon">O</span></button>
        <button onclick="ge.splitter.resize(100);return false;" class="button textual icon"><span class="teamworkIcon">R</span></button>
        <span class="ganttButtonSeparator"></span>
        <button onclick="$('#workSpace').trigger('fullScreen.gantt');return false;" class="button textual icon" title="FULLSCREEN" id="fullscrbtn"><span class="teamworkIcon">@</span></button>
        <button onclick="ge.element.toggleClass('colorByStatus' );return false;" class="button textual icon"><span class="teamworkIcon">&sect;</span></button>

      <button onclick="editResources();" class="button textual requireWrite" title="edit resources"><span class="teamworkIcon">M</span></button>
        &nbsp; &nbsp; &nbsp; &nbsp;
      <button onclick="saveGanttOnServer();" class="button first big requireWrite" title="Save">Save</button>
      <button onclick='newProject();' class='button requireWrite newproject'><em>clear project</em></button>
      <button class="button login" title="login/enroll" onclick="loginEnroll($(this));" style="display:none;">login/enroll</button>
      <button class="button opt collab" title="Start with Twproject" onclick="collaborate($(this));" style="display:none;"><em>collaborate</em></button>
      </div></div>
    --></div>

  <div class="__template__" type="TASKSEDITHEAD"><!--
    <table class="gdfTable" cellspacing="0" cellpadding="0">
      <thead>
      <tr style="height:40px">
        <th class="gdfColHeader" style="width:35px; border-right: none"></th>
        <th class="gdfColHeader" style="width:25px;"></th>
        <th style="display:none;" class="gdfColHeader gdfResizable" style="width:100px;">code/short name</th>
        <th class="gdfColHeader gdfResizable" style="width:300px;">name</th>
        
        <th class="gdfColHeader gdfResizable"  align="center" style="width:17px;" title="Start date is a milestone."><span class="teamworkIcon" style="font-size: 8px;">^</span></th>
        <th class="gdfColHeader gdfResizable" style="width:80px;">start</th>
        <th style="display:none;" class="gdfColHeader"  align="center" style="width:17px;" title="End date is a milestone."><span class="teamworkIcon" style="font-size: 8px;">^</span></th>
        <th class="gdfColHeader gdfResizable" style="width:80px;">End</th>
        <th class="gdfColHeader gdfResizable" style="width:50px;">dur.</th>
        <th class="gdfColHeader gdfResizable" style="width:20px;">%</th>
        <th style="display:none;" class="gdfColHeader gdfResizable requireCanSeeDep" style="width:50px;">depe.</th>
        <th class="gdfColHeader gdfResizable" style="width:1000px; text-align: left; padding-left: 10px;">assignees</th>
      </tr>
      </thead>
    </table>
  --></div>

  <div class="__template__" type="TASKROW"><!--
    <tr id="tid_(#=obj.id#)" taskId="(#=obj.id#)" class="taskEditRow (#=obj.isParent()?'isParent':''#) (#=obj.collapsed?'collapsed':''#)" level="(#=level#)">
      <th class="gdfCell edit" align="right" style="cursor:pointer;"><span class="taskRowIndex">(#=obj.id#)</span> <span class="teamworkIcon" style="font-size:12px;" >e</span></th>
      <td class="gdfCell noClip" align="center"><div class="taskStatus cvcColorSquare" status="(#=obj.status#)"></div></td>
      <td style="display:none;" class="gdfCell"><input type="text" name="code" value="(#=obj.code?obj.code:''#)" placeholder="code/short name"></td>
      <td class="gdfCell indentCell" style="padding-left:(#=obj.level*10+18#)px;">
        <div class="exp-controller" align="center"></div>
        <input type="text" name="name" value="(#=obj.name#)" class="newTask(#=obj.name#)" id="(#=obj.id#)" placeholder="name">
      </td>
      
      <td class="gdfCell" align="center"><input type="checkbox" id="start(#=obj.id#)" name="startIsMilestone" class="StartMilestoneClicked"></td>
      <td class="gdfCell"><input type="text" name="start"  value="" class="date"></td>
      <td style="display:none;" class="gdfCell" align="center"><input type="checkbox" name="endIsMilestone"></td>
      <td class="gdfCell"><input type="text" name="end" value="" class="date"></td>
      <td class="gdfCell"><input type="text" name="duration" autocomplete="off" value="(#=obj.duration#)"></td>
      <td class="gdfCell"><input type="text" name="progress" class="validated" entrytype="PERCENTILE" autocomplete="off" value="(#=obj.progress?obj.progress:''#)" (#=obj.progressByWorklog?"readOnly":""#)></td>
      <td style="display:none;" class="gdfCell requireCanSeeDep"><input type="text" name="depends" autocomplete="off" value="(#=obj.depends#)" (#=obj.hasExternalDep?"readonly":""#)></td>
      <td class="gdfCell taskAssigs">(#=obj.getAssigsString()#)</td>
    </tr>
  --></div>

  <div class="__template__" type="TASKEMPTYROW"><!--
    <tr class="taskEditRow emptyRow" >
      <th class="gdfCell" align="right"></th>
      <td class="gdfCell noClip" align="center"></td>
      <td class="gdfCell" id="addNew" style="text-decoration: underline;
    color: #477155;cursor:pointer;"></td>
      <td class="gdfCell"></td>
      <td class="gdfCell"></td>
      <td class="gdfCell"></td>
      <td class="gdfCell"></td>
      <td class="gdfCell"></td>
      <td class="gdfCell"></td>
      <td class="gdfCell"></td>
      <td class="gdfCell requireCanSeeDep"></td>
      <td class="gdfCell"></td>
    </tr>
  --></div>

  <div class="__template__" type="TASKBAR"><!--
    <div class="taskBox taskBoxDiv" taskId="(#=obj.id#)" >
      <div class="layout (#=obj.hasExternalDep?'extDep':''#)">
        <div class="taskStatus" status="(#=obj.status#)"></div>
        <div class="taskProgress" style="width:(#=obj.progress>100?100:obj.progress#)%; background-color:(#=obj.progress>100?'red':'rgb(153,255,51);'#);"></div>
        <div class="milestone (#=obj.startIsMilestone?'active':''#)" ></div>

        <div class="taskLabel"></div>
        <div class="milestone end (#=obj.endIsMilestone?'active':''#)" ></div>
      </div>
    </div>
  --></div>


  <div class="__template__" type="CHANGE_STATUS"><!--
      <div class="taskStatusBox">
      <div class="taskStatus cvcColorSquare" status="STATUS_ACTIVE" title="Active"></div>
      <div class="taskStatus cvcColorSquare" status="STATUS_DONE" title="Completed"></div>
      <div class="taskStatus cvcColorSquare" status="STATUS_FAILED" title="Failed"></div>
      <div class="taskStatus cvcColorSquare" status="STATUS_SUSPENDED" title="Suspended"></div>
      <div class="taskStatus cvcColorSquare" status="STATUS_UNDEFINED" title="Undefined"></div>
      </div>
    --></div>




  <div class="__template__" type="TASK_EDITOR"><!--
    <div class="ganttTaskEditor">
      <h2 class="taskData">Task editor</h2>
      <table  cellspacing="1" cellpadding="5" width="100%" class="taskData table" border="0">
            <tr>
          <td  style="display:none;" width="200" style="height: 80px"  valign="top">
            <label for="code">code/short name</label><br>
            <input type="text" name="code" id="code" value="" size=15 class="formElements" autocomplete='off' maxlength=255 style='width:100%' oldvalue="1">
          </td>
          <td colspan="3" valign="top"><label for="name" class="required">name</label><br><input type="text" name="name" id="name"class="formElements" autocomplete='off' maxlength=255 style='width:100%' value="" required="true" oldvalue="1"></td>
            </tr>


        <tr class="dateRow">
          <td nowrap="">
            <div style="position:relative">
              <label for="start">start</label>&nbsp;&nbsp;&nbsp;&nbsp;
              <div><input type="checkbox" id="megaMilestone" name="megaMilestone" value="yes"> &nbsp;<label for="megaMilestone">is megaMilestone</label>&nbsp;</div>
              <div><input type="checkbox" id="startIsMilestone" name="startIsMilestone" value="yes"> &nbsp;<label for="startIsMilestone">is Milestone</label>&nbsp;<div>
              <br><input type="text" name="start" id="start" size="8" class="formElements dateField validated date" autocomplete="off" maxlength="255" value="" oldvalue="1" entrytype="DATE">
              <span title="calendar" id="starts_inputDate" class="teamworkIcon openCalendar" onclick="$(this).dateField({inputField:$(this).prevAll(':input:first'),isSearchField:false});">m</span>          </div>
          </td>
          <td nowrap="">
            <label for="end">End</label>&nbsp;&nbsp;&nbsp;&nbsp;
            
            <br><input type="text" name="end" id="end" size="8" class="formElements dateField validated date" autocomplete="off" maxlength="255" value="" oldvalue="1" entrytype="DATE">
            <span title="calendar" id="ends_inputDate" class="teamworkIcon openCalendar" onclick="$(this).dateField({inputField:$(this).prevAll(':input:first'),isSearchField:false});">m</span>
          </td>
          <td nowrap="" >
            <label for="duration" class=" ">Days</label><br>
            <input type="text" name="duration" id="duration" size="4" class="formElements validated durationdays" title="Duration is in working days." autocomplete="off" maxlength="255" value="" oldvalue="1" entrytype="DURATIONDAYS">&nbsp;
          </td>
        </tr>

        <tr>
          <td  colspan="2">
            <label for="status" class=" ">status</label><br>
            <select id="status" name="status" class="taskStatus" status="(#=obj.status#)"  onchange="$(this).attr('STATUS',$(this).val());">
              <option value="STATUS_ACTIVE" class="taskStatus" status="STATUS_ACTIVE" >active</option>
              <option value="STATUS_SUSPENDED" class="taskStatus" status="STATUS_SUSPENDED" >suspended</option>
              <option value="STATUS_DONE" class="taskStatus" status="STATUS_DONE" >completed</option>
              <option value="STATUS_FAILED" class="taskStatus" status="STATUS_FAILED" >failed</option>
              <option value="STATUS_UNDEFINED" class="taskStatus" status="STATUS_UNDEFINED" >undefined</option>
            </select>
          </td>

          <td valign="top" nowrap>
            <label>progress</label><br>
            <input type="text" name="progress" id="progress" size="7" class="formElements validated percentile" autocomplete="off" maxlength="255" value="" oldvalue="1" entrytype="PERCENTILE">
          </td>
        </tr>

            </tr>
            <tr>
              <td colspan="4">
                <label for="description">Description</label><br>
                <textarea rows="3" cols="30" id="description" name="description" class="formElements" style="width:100%"></textarea>
              </td>

            </tr>
             <tr>
          <td>
              <label for="attachment">Attachment</label><br>
              <input type="file" 
              class="formElements"
              name="attchmentfile[]"
              id="attchmentfile"
              accept="image/png,.pdf,image/jpeg, image/gif,.doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" multiple/>
            </td>
            <td colspan="4">
              <span id="load_files">
              </span>
            </td>
          </tr>
          </table>

      <h2>Assignments</h2>
    <table  cellspacing="1" cellpadding="0" width="100%" id="assigsTable">
      <tr>
        <th style="width:100px;">name</th>
        <th style="width:70px;">Role</th>
        <th style="width:30px;">est.wklg.</th>
        <th style="width:30px;" id="addAssig"><span class="teamworkIcon" style="cursor: pointer">+</span></th>
      </tr>
    </table>

     <h4>Dependency</h4>
     <div class="tab">
      <button class="tablinks active" onclick="changeTab(event, 'Successors')">Successors</button>
      <button class="tablinks" onclick="changeTab(event, 'Predecessors')">Predecessors</button>
    </div>
  
  <div id="Successors" class="tabcontent" style="display:block;">
       <table  cellspacing="1" cellpadding="0" width="100%" id="dependsTable">
        <tr>
          <th style="width:100px;">Name</th>
          <th style="width:70px;">Delay</th>
          <th style="width:70px;">Dependency</th>
          <th style="width:30px;" id="addDep1"><span class="teamworkIcon" style="cursor: pointer">+</span></th>
        </tr>
      </table>
  </div>

  <div id="Predecessors" class="tabcontent">
      <table  cellspacing="1" cellpadding="0" width="100%" id="dependsTable1">
        <tr>
          <th style="width:100px;">Name</th>
          <th style="width:70px;">Delay</th>
          <th style="width:70px;">Dependency</th>
          <th style="width:30px;" id="addDep"><span class="teamworkIcon" style="cursor: pointer">+</span></th>
        </tr>
      </table>
  </div>
    <div style="text-align: right; padding-top: 20px">
      <span id="saveButton" class="button first" onClick="$(this).trigger('saveFullEditor.gantt');">Save</span>
    </div>

    </div>
  --></div>



  <div class="__template__" type="ASSIGNMENT_ROW"><!--
    <tr taskId="(#=obj.task.id#)" assId="(#=obj.assig.id#)" class="assigEditRow" >
      <td ><select name="resourceId"  class="formElements" (#=obj.assig.id.indexOf("tmp_")==0?"":"disabled"#) ></select></td>
      <td ><select type="select" name="roleId"  class="formElements"></select></td>
      <td ><input type="text" name="effort" value="(#=getMillisInHoursMinutes(obj.assig.effort)#)" size="5" class="formElements"></td>
      <td align="center"><span class="teamworkIcon delAssig del" style="cursor: pointer">d</span></td>
    </tr>
  --></div>

  <div class="__template__" type="DEPENDENCY_ROW">
        <!--
            <tr depId="(#=obj.id#)" class="assigEditRow">
              <td style="text-align: center;">
                <select type="select" name="taskName"  class="formElements"></select>
              </td>
              <td style="text-align: center;">
                <input type="number" name="delay" value="(#=obj.delay#)" size="5" class="formElements">
              </td>
              <td style="text-align: center;">
                <select type="select" name="dependency"  class="formElements"></select>
              </td>
              <td align="center"><span class="teamworkIcon delDep1 del" style="cursor: pointer">d</span></td>
            </tr>
          -->
        </div>

        <div class="__template__" type="DEPENDENCY_ROW1">
        <!--
            <tr depId1="(#=obj.id#)" parentId="(#=obj.parentId#)" class="assigEditRow">
              <td style="text-align: center;">
                <select type="select" name="taskName"  class="formElements"></select>
              </td>
              <td style="text-align: center;">
                <input type="number" name="delay" value="(#=obj.delay#)" size="5" class="formElements">
              </td>
              <td style="text-align: center;">
                <select type="select" name="dependency"  class="formElements"></select>
              </td>
               <td align="center"><span class="teamworkIcon delDep del" style="cursor: pointer">d</span></td>
            </tr>
          -->
        </div>

        <div class="__template__" type="NO_DEPENDENCY_ROW">
        <!--
            <tr class="assigEditRow">
              <td style="text-align: center;">(#=obj.name#)</td>
              <td style="text-align: center;">
              </td>
              <td style="text-align: center;">
              </td>
            </tr>
          -->
        </div>

  <div class="__template__" type="RESOURCE_EDITOR"><!--
    <div class="resourceEditor" style="padding: 5px;">

      <h2>Project team</h2>
      <table  cellspacing="1" cellpadding="0" width="100%" id="resourcesTable">
        <tr>
          <th style="width:100px;">name</th>
          <th style="width:30px;" id="addResource"><span class="teamworkIcon" style="cursor: pointer">+</span></th>
        </tr>
      </table>

      <div style="text-align: right; padding-top: 20px"><button id="resSaveButton" class="button big">Save</button></div>
    </div>
  --></div>



  <div class="__template__" type="RESOURCE_ROW"><!--
    <tr resId="(#=obj.id#)" class="resRow" >
      <td ><input type="text" name="name" value="(#=obj.name#)" style="width:100%;" class="formElements"></td>
      <td align="center"><span class="teamworkIcon delRes del" style="cursor: pointer">d</span></td>
    </tr>
  --></div>

  
  <div class="__template__" type="RESOURCE_DEPENDENCY">
  <!--
    <div class="resourceEditor" style="padding: 5px;">
      <h2>Dependency</h2>
      <br>
      <h4>Type</h4>
      <select id="dependency" name="dependency" class="taskStatus" onchange="$('#dependency').val($(this).val());">
        <option value="3" class="taskStatus">Start to End</option>
        <option value="1" class="taskStatus">Start to Start</option>
        <option value="2" class="taskStatus">End to End</option>
      </select>
      <h4>Delay</h4>
      <input type="number" name="delay" id="delay" pattern="^[0-9]*$"></input>
      <div style="text-align: right; padding-top: 20px">
      <span id="saveDependency" class="button first" onClick="saveDependency('(#=obj.id#)')">Save</span>
    </div>
  </div>
-->
</div>


</div>
<script type="text/javascript">

  const getDatesBetween = (startDate, endDate) => {
    const dates = [];
    let currentDate = new Date(
      startDate.getFullYear(),
      startDate.getMonth(),
      startDate.getDate()
      );

    while (currentDate <= endDate) {
      dates.push(currentDate);

      currentDate = new Date(
        currentDate.getFullYear(),
        currentDate.getMonth(),
        currentDate.getDate() + 1
        );
    }
    return dates;
  };

  function changeTab(evt, tabName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tabcontent");
    for (i = 0; i < tabcontent.length; i++) {
      tabcontent[i].style.display = "none";
    }
    tablinks = document.getElementsByClassName("tablinks");
    for (i = 0; i < tablinks.length; i++) {
      tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";
  }

  function deleteResource(id='')
  {
    $.ajax("controller/delete_resource.php", {
      dataType:"json",
      data: {id:id},
      type:"POST",
      success: function(response) {
        if (response.ok) {
        } else {
        }
      }
    });
    setTimeout(()=>{
      //window.location.reload();
    },300);
  }

  $.JST.loadDecorator("RESOURCE_ROW", function(resTr, res){
    resTr.find(".delRes").click(function(){$(this).closest("tr").remove()});
  });

  $.JST.loadDecorator("TASK_EDITOR", function(resTr, res){
    resTr.find("#startIsMilestone").click(function(){
    if($(this).prop("checked") == true){
        $(this).closest('div').prev().find('[type=checkbox]').prop('disabled', true);
    }else{
      $(this).closest('div').prev().find('[type=checkbox]').prop('disabled', false);
    }
  });

  resTr.find("#megaMilestone").click(function(){
    if($(this).prop("checked") == true){
        $(this).closest('div').next().find('[type=checkbox]').prop('disabled', true);
        $(this).hide();
    }else{
      $(this).closest('div').next().find('[type=checkbox]').prop('disabled', false); 
    }
  });
  });

  
  $.JST.loadDecorator("ASSIGNMENT_ROW", function(assigTr, taskAssig){
    var resEl = assigTr.find("[name=resourceId]");
    var opt = $("<option>");
    resEl.append(opt);
    for(var i=0; i< taskAssig.task.master.resources.length;i++){
      var res = taskAssig.task.master.resources[i];
      opt = $("<option>");
      opt.val(res.id).html(res.name);
      if(taskAssig.assig.resourceId == res.id)
        opt.attr("selected", "true");
      resEl.append(opt);
    }
    var roleEl = assigTr.find("[name=roleId]");
    for(var i=0; i< taskAssig.task.master.roles.length;i++){
      var role = taskAssig.task.master.roles[i];
      var optr = $("<option>");
      optr.val(role.id).html(role.name);
      if(taskAssig.assig.roleId == role.id)
        optr.attr("selected", "true");
      roleEl.append(optr);
    }

    if(taskAssig.task.master.permissions.canWrite && taskAssig.task.canWrite){
      assigTr.find(".delAssig").click(function(){
        var tr = $(this).closest("[assId]").fadeOut(200, function(){$(this).remove()});
      });
    }

  });

  $.JST.loadDecorator("DEPENDENCY_ROW", function(depTr, depAssig){
    console.log(depTr, depAssig,'depTr, depAssig');
    var depEl = depTr.find("[name=dependency]");
    let dep=[{id:"3",name: "START TO END"},{id:"1",name: "START TO START"},{id:"2",name: "END TO END"}];
    let allTasks = ge.tasks;
    var taskEl = depTr.find("[name=taskName]");

    for(var i=0; i< allTasks.length;i++){
      var options = $("<option>");
      if(depAssig.id=='temp_1'){
        if(i==0)
        {
          options.val('select').html('Select');
          options.attr("selected", "true");
          taskEl.append(options);
        }else{
          options.val(allTasks[i].id).html(allTasks[i].name);
          if(depAssig.name== allTasks[i].name)
            options.attr("selected", "true");
          taskEl.append(options);
        }
      }else{
        options.val(allTasks[i].id).html(allTasks[i].name);
        if(depAssig.name== allTasks[i].name)
          options.attr("selected", "true");
        taskEl.append(options);
      }
    }

    for(var i=0; i< dep.length;i++){
      var optr = $("<option>");
      optr.val(dep[i].id).html(dep[i].name);
      if(depAssig.task== dep[i].name)
        optr.attr("selected", "true");
      depEl.append(optr);
    }

    depTr.find(".delDep1").click(function(){
      countSuccessors=0;
      var tr = $(this).closest("[depId]").fadeOut(200, function(){
        for (var i = 0; i < allTasks.length; i++) {
          if(i==($(this).attr('depId')-1)){
            allTasks[i].depends='';
            allTasks[i].dependency='';
            ge.updateLinks(allTasks[i]);
          }
        }
        $(this).remove()
      });
    });
  });

  $.JST.loadDecorator("DEPENDENCY_ROW1", function(depTr, depAssig){
    var depEl = depTr.find("[name=dependency]");
    let dep=[{id:"3",name: "START TO END"},{id:"1",name: "START TO START"},{id:"2",name: "END TO END"}];
    for(var i=0; i< dep.length;i++){
      var optr = $("<option>");
      optr.val(dep[i].id).html(dep[i].name);
      if(depAssig.task== dep[i].name)
        optr.attr("selected", "true");
      depEl.append(optr);
    }

    let allTasks = ge.tasks;
    var taskEl = depTr.find("[name=taskName]");
    for(var i=0; i< allTasks.length;i++){
      var options = $("<option>");
      if(i==0 && allTasks[i].id!=depAssig['id'])
      {
        options.val('select').html('Select');
        taskEl.append(options);
      }
      if(allTasks[i].id!=depAssig['parentId'] && allTasks[i].level!=0)
      {
       options.val(allTasks[i].id).html(allTasks[i].name);
       if(depAssig.name== allTasks[i].name)
        options.attr("selected", "true");
      taskEl.append(options);
    }
  }

  depTr.find(".delDep").click(function(){
    var tr = $(this).closest("[depId1]").fadeOut(200, function(){
      console.log($(this).attr('depId1'),'testtest');
      for (var i = 0; i < allTasks.length; i++) {
        if(i==($(this).attr('depId1')-1)){
          allTasks[i].depends='';
          allTasks[i].dependency='';
          ge.updateLinks(allTasks[i]);
        }
      }
      $(this).remove()
    });
  });
});


  function loadI18n(){
    GanttMaster.messages = {
      "CANNOT_WRITE":"No permission to change the following task:",
      "CHANGE_OUT_OF_SCOPE":"Project update not possible as you lack rights for updating a parent project.",
      "START_IS_MILESTONE":"Start date is a milestone.",
      "END_IS_MILESTONE":"End date is a milestone.",
      "TASK_HAS_CONSTRAINTS":"Task has constraints.",
      "GANTT_ERROR_DEPENDS_ON_OPEN_TASK":"Error: there is a dependency on an open task.",
      "GANTT_ERROR_DESCENDANT_OF_CLOSED_TASK":"Error: due to a descendant of a closed task.",
      "TASK_HAS_EXTERNAL_DEPS":"This task has external dependencies.",
      "GANNT_ERROR_LOADING_DATA_TASK_REMOVED":"GANNT_ERROR_LOADING_DATA_TASK_REMOVED",
      "CIRCULAR_REFERENCE":"Circular reference.",
      "CANNOT_DEPENDS_ON_ANCESTORS":"Cannot depend on ancestors.",
      "INVALID_DATE_FORMAT":"The data inserted are invalid for the field format.",
      "GANTT_ERROR_LOADING_DATA_TASK_REMOVED":"An error has occurred while loading the data. A task has been trashed.",
      "CANNOT_CLOSE_TASK_IF_OPEN_ISSUE":"Cannot close a task with open issues",
      "TASK_MOVE_INCONSISTENT_LEVEL":"You cannot exchange tasks of different depth.",
      "CANNOT_MOVE_TASK":"CANNOT_MOVE_TASK",
      "PLEASE_SAVE_PROJECT":"PLEASE_SAVE_PROJECT",
      "GANTT_SEMESTER":"Semester",
      "GANTT_SEMESTER_SHORT":"s.",
      "GANTT_QUARTER":"Quarter",
      "GANTT_QUARTER_SHORT":"q.",
      "GANTT_WEEK":"Week",
      "GANTT_WEEK_SHORT":"w."
    };
  }



  function createNewResource(el) {
    var row = el.closest("tr[taskid]");
    var name = row.find("[name=resourceId_txt]").val();
    var url = contextPath + "/applications/teamwork/resource/resourceNew.jsp?CM=ADD&name=" + encodeURI(name);

    openBlackPopup(url, 700, 320, function (response) {
      //fillare lo smart combo
      if (response && response.resId && response.resName) {
        //fillare lo smart combo e chiudere l'editor
        row.find("[name=resourceId]").val(response.resId);
        row.find("[name=resourceId_txt]").val(response.resName).focus().blur();
      }

    });
  }

  function saveDependency(taskId) {
    ge.beginTransaction();
    let delay = $("#delay").val();
    console.log(delay);
    if (delay == "") {
      delay = 0;
    }
    let parent;
    var task = ge.getTask(taskId);
    let depends = task.depends;
    if (/[,]/.test(depends)) {
      let dependsArray = depends.split(",");
      let newdependency;
      if (dependsArray.length > 1) {
        let newDepends = [];
        for (let index = 0; index < dependsArray.length; index++) {
          const element = dependsArray[index];

          if (index == dependsArray.length - 1) {
            if(delay>0)
              newDepends.push(element + ":" + delay);
          } else {
            newDepends.push(element);
          }
        }
        newDepends = newDepends.join(",");
        task.depends = newDepends;
        dependsArray = task.depends.split(",");

        parent = dependsArray[dependsArray.length - 1];
        newdependency =
        dependsArray[dependsArray.length - 1] +
        "::" +
        $("#dependency").val();
      } else {
        let newDepends = [];
        for (let index = 0; index < dependsArray.length; index++) {
          const element = dependsArray[index];
          if (index == 0) {
            if(delay>0)
              newDepends.push(element + ":" + delay);
          } else {
            newDepends.push(element);
          }
        }
        newDepends = newDepends.join(",");
        task.depends = newDepends;
        dependsArray = task.depends.split(",");
        parent = dependsArray[0];
        newdependency = dependsArray[0] + "::" + $("#dependency").val();
      }
      let dependency;
      if(task.dependency){  
        let old_dependency = task.dependency;
        dependency = old_dependency.split(",");
      }
      dependency.push(newdependency);
      listOfdependency = dependency.join(",");
      task.dependency = listOfdependency;
    } else {
      if(delay>0)
        task.depends = task.depends + ":" + delay;

      parent = task.depends;
      task.dependency = task.depends + "::" + $("#dependency").val();
    }

        //Update Time
        if (/[:]/.test(parent)) {
          let parentArray = parent.split(":");
          parent = parentArray[0];
        }

        let tasks = ge.tasks;
        let parent_task;

        for (let index = 0; index < tasks.length; index++) {
          if (index == parent - 1) {
            parent_task = tasks[index];
          }
        }
        var one_day = 1000 * 60 * 60 * 24;
        let dep = $("#dependency").val();
        let duration_ms = task.end - task.start;
        let parent_task_id;
        let taskDuration = 0;
        let parentDuration = 0;
        console.log("delay:-->",delay);
        if (dep == 1) {
          taskDuration = getDurationInUnits(
            computeStartDate(task.start),
            computeEndDate(task.end)
            );
          console.log("Start to start:-->",taskDuration);
          let taskIndex;
          task.status = "STATUS_ACTIVE";
          console.log("before assigning task duration");
        } else if (dep == 2) {
          taskDuration = getDurationInUnits(
            computeStartDate(task.start),
            computeEndDate(task.end)
            );
          if (task.level > 1) {
            console.log("Level 1");
            taskIndex = tasks.indexOf(task, 0);
            let parent_index;
            for (let index = taskIndex; index > 0; index--) {
              const element = tasks[index];
              if (element.level == 1) {
                parent_index = index;
                parent_task_id = element.id;
                break;
              }
            }
            let countChild = 0;
            let startArray = [];
            let endArray = [];
            for (let index = parent_index + 1; index < tasks.length; index++) {
              const element = tasks[index];
              if (element.level == 1) {
                break;
              }
              startArray.push(element.start);
              endArray.push(element.end);
              countChild++;
            }
            let parenttask = ge.getTask(parent_task_id);
            let MinStart = Math.min(...startArray);
            let MaxEnd = Math.max(...endArray);

            if (countChild >= 2) {
              parenttask.start = MinStart;
              parenttask.end = MaxEnd;
            } else {
              parenttask.end = task.end;
              parenttask.start = task.start;
            }

            parentDuration = getDurationInUnits(
              computeStartDate(parenttask.start),
              computeEndDate(parenttask.end)
              );
            parenttask.duration = parentDuration;
          } else {
            console.log("Other levels");
          }
          console.log("end to end duration:-->",taskDuration);
          task.status = "STATUS_ACTIVE";
        } else {
          console.log("start to start add delay",delay);
          dep = 3;
          task.start = task.start;
          task.end = task.start + duration_ms;
          task.start = computeStart(task.start);
          task.end = computeEnd(task.end);
          taskDuration = getDurationInUnits(
            computeStartDate(task.start),
            computeEndDate(task.end)
            );
        }
        ge.updateLinks(task);
        ge.changeTaskDeps(task,dep);
        ge.endTransaction();
        closeBlackPopup();
      }
    </script>

  </body>
  </html>