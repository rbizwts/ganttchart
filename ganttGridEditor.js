/*
 Copyright (c) 2012-2018 Open Lab
 Written by Roberto Bicchierai and Silvia Chelazzi http://roberto.open-lab.com
 Permission is hereby granted, free of charge, to any person obtaining
 a copy of this software and associated documentation files (the
 "Software"), to deal in the Software without restriction, including
 without limitation the rights to use, copy, modify, merge, publish,
 distribute, sublicense, and/or sell copies of the Software, and to
 permit persons to whom the Software is furnished to do so, subject to
 the following conditions:

 The above copyright notice and this permission notice shall be
 included in all copies or substantial portions of the Software.

 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
 function GridEditor(master) {
  this.master = master; // is the a GantEditor instance
  var editorTabel = $.JST.createFromTemplate({}, "TASKSEDITHEAD");
  if (!master.permissions.canSeeDep)
    editorTabel.find(".requireCanSeeDep").hide();

  this.gridified = $.gridify(editorTabel);
  this.element = this.gridified.find(".gdfTable").eq(1);

  this.minAllowedDate=new Date(new Date().getTime()-3600000*24*365*20).format();
  this.maxAllowedDate=new Date(new Date().getTime()+3600000*24*365*30).format();
}


GridEditor.prototype.fillEmptyLines = function () {
  //console.debug("fillEmptyLines")
  var factory = new TaskFactory();
  var master = this.master;

  //console.debug("GridEditor.fillEmptyLines");
  var rowsToAdd = master.minRowsInEditor - this.element.find(".taskEditRow").length;
  var empty=this.element.find(".emptyRow").length;
  rowsToAdd=Math.max(rowsToAdd,empty>5?0:5-empty);

  //fill with empty lines
  for (var i = 0; i < rowsToAdd; i++) {
    var emptyRow = $.JST.createFromTemplate({}, "TASKEMPTYROW");
    if (!master.permissions.canSeeDep)
      emptyRow.find(".requireCanSeeDep").hide();

    //click on empty row create a task and fill above
    emptyRow.click(function (ev) {
      //console.debug("emptyRow.click")
      var emptyRow = $(this);
      //add on the first empty row only
      if (!master.permissions.canAdd || emptyRow.prevAll(".emptyRow").length > 0)
        return;

      master.beginTransaction();
      var lastTask;
      var start = new Date().getTime();
      var level = 0;
      if (master.tasks[0]) {
        start = master.tasks[0].start;
        level = master.tasks[0].level + 1;
      }

      //fill all empty previouses
      var cnt=0;
      emptyRow.prevAll(".emptyRow").addBack().each(function () {
        cnt++;
        var ch = factory.build("tmp_fk" + new Date().getTime()+"_"+cnt, "", "", level, start, Date.workingPeriodResolution);
        console.log("add task"+ch);
        var task = master.addTask(ch);
        lastTask = ch;
      });
      master.endTransaction();
      if (lastTask.rowElement) {
        lastTask.rowElement.find("[name=name]").focus();//focus to "name" input
      }
    });
    this.element.append(emptyRow);
  }
};


GridEditor.prototype.addTask = function (task, row, hideIfParentCollapsed) {
  console.debug("GridEditor.addTask",task,row);
  //var prof = new Profiler("ganttGridEditor.addTask");

  //remove extisting row
  this.element.find("#tid_" + task.id).remove();

  var taskRow = $.JST.createFromTemplate(task, "TASKROW");

  if (!this.master.permissions.canSeeDep)
    taskRow.find(".requireCanSeeDep").hide();

  if (!this.master.permissions.canSeePopEdit)
    taskRow.find(".edit .teamworkIcon").hide();

  //save row element on task
  task.rowElement = taskRow;

  this.bindRowEvents(task, taskRow);

  if (typeof(row) != "number") {
    var emptyRow = this.element.find(".emptyRow:first"); //tries to fill an empty row
    if (emptyRow.length > 0)
      emptyRow.replaceWith(taskRow);
    else
      this.element.append(taskRow);
  } else {
    var tr = this.element.find("tr.taskEditRow").eq(row);
    if (tr.length > 0) {
      tr.before(taskRow);
    } else {
      this.element.append(taskRow);
    }

  }

  //[expand]
  if (hideIfParentCollapsed) {
    if (task.collapsed) taskRow.addClass('collapsed');
    var collapsedDescendant = this.master.getCollapsedDescendant();
    if (collapsedDescendant.indexOf(task) >= 0) taskRow.hide();
  }
  //prof.stop();
  return taskRow;
};

GridEditor.prototype.refreshExpandStatus = function (task) {
  //console.debug("refreshExpandStatus",task);
  if (!task) return;
  if (task.isParent()) {
    task.rowElement.addClass("isParent");
  } else {
    task.rowElement.removeClass("isParent");
  }


  var par = task.getParent();
  if (par && !par.rowElement.is("isParent")) {
    par.rowElement.addClass("isParent");
  }

};

GridEditor.prototype.refreshTaskRow = function (task) {
  //console.debug("refreshTaskRow")
  //var profiler = new Profiler("editorRefreshTaskRow");

  var canWrite=this.master.permissions.canWrite || task.canWrite;

  var row = task.rowElement;

  row.find(".taskRowIndex").html(task.getRow() + 1);
  row.find(".indentCell").css("padding-left", task.level * 10 + 18);
  row.find("[name=name]").val(task.name);
  row.find("[status]").attr("status", task.status);

  row.find("[name=duration]").val(durationToString(task.duration)).prop("readonly",!canWrite || task.isParent() && task.master.shrinkParent);
  row.find("[name=progress]").val(task.progress).prop("readonly",!canWrite || task.progressByWorklog==true);
  row.find("[name=startIsMilestone]").prop("checked", task.startIsMilestone);
  row.find("[name=megaMilestone]").prop("checked", task.megaMilestone);
  row.find("[name=start]").val(new Date(task.start).format()).updateOldValue().prop("readonly",!canWrite || task.depends || !(task.canWrite  || this.master.permissions.canWrite) ); // called on dates only because for other field is called on focus event
  row.find("[name=endIsMilestone]").prop("checked", task.endIsMilestone);
  row.find("[name=end]").val(new Date(task.end).format()).prop("readonly",!canWrite || task.isParent() && task.master.shrinkParent).updateOldValue();
  row.find("[name=depends]").val(task.depends);
  row.find(".taskAssigs").html(task.getAssigsString());

  //manage collapsed
  if (task.collapsed)
    row.addClass("collapsed");
  else
    row.removeClass("collapsed");


  //Enhancing the function to perform own operations
  this.master.element.trigger('gantt.task.afterupdate.event', task);
  //profiler.stop();
};

GridEditor.prototype.redraw = function () {
  //console.debug("GridEditor.prototype.redraw")
  //var prof = new Profiler("gantt.GridEditor.redraw");
  for (var i = 0; i < this.master.tasks.length; i++) {
    this.refreshTaskRow(this.master.tasks[i]);
  }
  // check if new empty rows are needed
  if (this.master.fillWithEmptyLines)
    this.fillEmptyLines();

  //prof.stop()

};

GridEditor.prototype.reset = function () {
  this.element.find("[taskid]").remove();
};


GridEditor.prototype.bindRowEvents = function (task, taskRow) {
  var self = this;
  //console.debug("bindRowEvents",this,this.master,this.master.permissions.canWrite, task.canWrite);

  //bind row selection
  taskRow.click(function (event) {
    var row = $(this);
    //console.debug("taskRow.click",row.attr("taskid"),event.target)
    //var isSel = row.hasClass("rowSelected");
    row.closest("table").find(".rowSelected").removeClass("rowSelected");
    row.addClass("rowSelected");

    //set current task
    self.master.currentTask = self.master.getTask(row.attr("taskId"));

    //move highlighter
    self.master.gantt.synchHighlight();

    //if offscreen scroll to element
    var top = row.position().top;
    if (top > self.element.parent().height()) {
      row.offsetParent().scrollTop(top - self.element.parent().height() + 100);
    } else if (top <= 40) {
      row.offsetParent().scrollTop(row.offsetParent().scrollTop() - 40 + top);
    }
  });


  if (this.master.permissions.canWrite || task.canWrite) {
    self.bindRowInputEvents(task, taskRow);

  } else { //cannot write: disable input
    taskRow.find("input").prop("readonly", true);
    taskRow.find("input:checkbox,select").prop("disabled", true);
  }

  if (!this.master.permissions.canSeeDep)
    taskRow.find("[name=depends]").attr("readonly", true);

  self.bindRowExpandEvents(task, taskRow);

  if (this.master.permissions.canSeePopEdit) {
    taskRow.find(".edit").click(function () {self.openFullEditor(task, false)});

    taskRow.dblclick(function (ev) { //open editor only if no text has been selected
      if (window.getSelection().toString().trim()=="")
        self.openFullEditor(task, $(ev.target).closest(".taskAssigs").size()>0)
    });
  }
  //prof.stop();
};


GridEditor.prototype.bindRowExpandEvents = function (task, taskRow) {
  var self = this;
  //expand collapse
  taskRow.find(".exp-controller").click(function () {
    var el = $(this);
    var taskId = el.closest("[taskid]").attr("taskid");
    var task = self.master.getTask(taskId);
    if (task.collapsed) {
      self.master.expand(task,false);
    } else {
      self.master.collapse(task,false);
    }
  });
};

GridEditor.prototype.bindRowInputEvents = function (task, taskRow) {
  var self = this;

  //bind dateField on dates
  taskRow.find(".date").each(function () {
    var el = $(this);
    el.click(function () {
      var inp = $(this);
      inp.dateField({
        inputField: el,
        minDate:self.minAllowedDate,
        maxDate:self.maxAllowedDate,
        callback:   function (d) {
          $(this).blur();
        }
      });
    });

    el.blur(function (date) {
      var inp = $(this);
      if (inp.isValueChanged()) {
        if (!Date.isValid(inp.val())) {
          alert(GanttMaster.messages["INVALID_DATE_FORMAT"]);
          inp.val(inp.getOldValue());

        } else {
          var row = inp.closest("tr");
          var taskId = row.attr("taskId");
          var task = self.master.getTask(taskId);

          var leavingField = inp.prop("name");
          var dates = resynchDates(inp, row.find("[name=start]"), row.find("[name=startIsMilestone]"), row.find("[name=duration]"), row.find("[name=end]"), row.find("[name=endIsMilestone]"));
          //console.debug("resynchDates",new Date(dates.start), new Date(dates.end),dates.duration)
          //update task from editor
          self.master.beginTransaction();
          self.master.changeTaskDates(task, dates.start, dates.end);
          self.master.endTransaction();
          inp.updateOldValue(); //in order to avoid multiple call if nothing changed
        }
      }
    });
  });


  //milestones checkbox
  taskRow.find(":checkbox").click(function () {
    var el = $(this);
    var row = el.closest("tr");
    var taskId = row.attr("taskId");

    var task = self.master.getTask(taskId);

    //update task from editor
    var field = el.prop("name");

    if (field == "startIsMilestone" || field == "endIsMilestone" || field=="megaMilestone") {

      let tasks=self.master.tasks;
      let taskIndex = tasks.indexOf(task, 0);
      let has_dependency=false;
      for (var i = (taskIndex+1); i < tasks.length; i++) {
        let dependency=tasks[i].dependency;
        if(dependency!=''){
          if(dependency!=undefined){
            dependency=dependency.split(',');
            let splitDep=dependency[0].split(':');
            if(splitDep[0]==(taskIndex+1))
            {
              has_dependency=true;
            }
          }
        }
      }
      if(has_dependency){
        alert("\""+task.name + "\"\n" + GanttMaster.messages.TASK_HAS_CONSTRAINTS);
      }
      else{
        if(task.level==0){
          alert("\""+task.name + "\"\n" + GanttMaster.messages.TASK_HAS_CONSTRAINTS);
        }
        else if(task.level==1)
        {
          let countChild=0;
          for (let index = taskIndex+1; index < tasks.length; index++) {
            const element = tasks[index];
            if(element.level==1){
              break;
            }
            countChild++;
          }

          if(countChild>0){
            alert("\""+task.name + "\"\n" + GanttMaster.messages.TASK_HAS_CONSTRAINTS);
          }else{
            task.duration=1;
            task.end=task.start+(1000 * 60 * 60 * 24);
            self.master.beginTransaction();
      //milestones
      task[field] = el.prop("checked");
      resynchDates(el, row.find("[name=start]"), row.find("[name=startIsMilestone]"), row.find("[name=duration]"), row.find("[name=end]"), row.find("[name=endIsMilestone]"));
      self.master.endTransaction();
    }

  }else{
    task.duration=1;
    task.end=task.start+(1000 * 60 * 60 * 24);

    let parent_index;
    let parent_task_id;
    let startArray = [];
    let endArray = [];

    for (let index = taskIndex; index > 0; index--) {
      const element = tasks[index];
      if (element.level == 1) {
        parent_index = index;
        parent_task_id = element.id;
        break;
      }
    }

    for (
      let index = parent_index + 1;
      index < tasks.length;
      index++
      ) {
      const element = tasks[index];
    if (element.level == 1) {
      break;
    }
    startArray.push(element.start);
    endArray.push(element.end);
  }

  let MinStart = Math.min(...startArray);
  let MaxEnd = Math.max(...endArray);
  let parenttask = ge.getTask(parent_task_id);
  let parentDuration=0;

  parenttask.start=MinStart;
  parenttask.end=MaxEnd;

  dates1 = getDatesBetween(
    new Date(parenttask.start),
    new Date(parenttask.end)
    );

  for (let index = 0; index < dates1.length; index++) {
    const element = dates1[index];
    let Holiday = isHoliday(new Date(element));
    if (!Holiday) {
      parentDuration++;
    }
  }
  parenttask.duration = parentDuration-1  ;
  self.master.beginTransaction();
      //milestones
      task[field] = el.prop("checked");
      resynchDates(el, row.find("[name=start]"), row.find("[name=startIsMilestone]"), row.find("[name=duration]"), row.find("[name=end]"), row.find("[name=endIsMilestone]"));
      self.master.endTransaction();
    }
  }
}
});


  //binding on blur for task update (date exluded as click on calendar blur and then focus, so will always return false, its called refreshing the task row)
  taskRow.find("input:text:not(.date)").focus(function () {
    $(this).updateOldValue();

  }).blur(function (event) {
      $('#addNew').text('Click here to add new task');
      console.log('Binding Blur');
    var el = $(this);
    var row = el.closest("tr");
    var taskId = row.attr("taskId");
    var task = self.master.getTask(taskId);
    //addding popup for deps
    //REMOVED BY NEHAL
    // var resourceEditor = $.JST.createFromTemplate(task, "RESOURCE_DEPENDENCY");
    // var ndo = createModalPopup(400, 500).append(resourceEditor);
    //update task from editor
    var field = el.prop("name");

    if (el.isValueChanged()) {
      //self.master.beginTransaction();

      if (field == "depends") {
        var oldDeps = task.depends;
        task.depends = el.val();
        console.log(task.depends);
        // update links
        //this function is for drawing dependancy on tasks
        //var linkOK = self.master.updateLinks(task);
        if (1) {
          //synchronize status from superiors states
          var sups = task.getSuperiors();

          var oneFailed=false;
          var oneUndefined=false;
          var oneActive=false;
          var oneSuspended=false;
          var oneWaiting=false;
         /* for (var i = 0; i < sups.length; i++) {
            oneFailed=oneFailed|| sups[i].from.status=="STATUS_FAILED";
            oneUndefined=oneUndefined|| sups[i].from.status=="STATUS_UNDEFINED";
            oneActive=oneActive|| sups[i].from.status=="STATUS_ACTIVE";
            oneSuspended=oneSuspended|| sups[i].from.status=="STATUS_SUSPENDED";
            oneWaiting=oneWaiting|| sups[i].from.status=="STATUS_WAITING";
          }

          if (oneFailed){
            task.changeStatus("STATUS_FAILED")
          } else if (oneUndefined){
            task.changeStatus("STATUS_UNDEFINED")
          } else if (oneActive){
            //task.changeStatus("STATUS_SUSPENDED")
            task.changeStatus("STATUS_WAITING")
          } else  if (oneSuspended){
            task.changeStatus("STATUS_SUSPENDED")
          } else  if (oneWaiting){
            task.changeStatus("STATUS_WAITING")
          } else {
            task.changeStatus("STATUS_ACTIVE")
          }*/
          console.log("Start redraws for task dpendancies");
          //self.master.changeTaskDeps(task); //dates recomputation from dependencies
        }
        
      } else if (field == "duration") {
        var dates = resynchDates(el, row.find("[name=start]"), row.find("[name=startIsMilestone]"), row.find("[name=duration]"), row.find("[name=end]"), row.find("[name=endIsMilestone]"));
        self.master.changeTaskDates(task, dates.start, dates.end);

      } else if (field == "name" && el.val() == "") { // remove unfilled task
        self.master.deleteCurrentTask(taskId);


      } else if (field == "progress" ) {
        task[field]=parseFloat(el.val())||0;
        el.val(task[field]);

      } else {
        task[field] = el.val();
      }
      console.log("End transaction");
      //self.master.endTransaction();

    } else if (field == "name" && el.val() == "") { // remove unfilled task even if not changed
      if (task.getRow()!=0) {
        self.master.deleteCurrentTask(taskId);

      }else {
        el.oneTime(1,"foc",function(){$(this).focus()}); //
        event.preventDefault();
        //return false;
      }

    }
  });

  //cursor key movement
  taskRow.find("input").keydown(function (event) {
    var theCell = $(this);
    var theTd = theCell.parent();
    var theRow = theTd.parent();
    var col = theTd.prevAll("td").length;

    var ret = true;
    if (!event.ctrlKey) {
      switch (event.keyCode) {
        case 13:
        if (theCell.is(":text"))
          theCell.blur();
        break;

        case 37: //left arrow
        if (!theCell.is(":text") || (!this.selectionEnd || this.selectionEnd == 0))
          theTd.prev().find("input").focus();
        break;
        case 39: //right arrow
        if (!theCell.is(":text") || (!this.selectionEnd || this.selectionEnd == this.value.length))
          theTd.next().find("input").focus();
        break;

        case 38: //up arrow
          //var prevRow = theRow.prev();
          var prevRow = theRow.prevAll(":visible:first");
          var td = prevRow.find("td").eq(col);
          var inp = td.find("input");

          if (inp.length > 0)
            inp.focus();
          break;
        case 40: //down arrow
          //var nextRow = theRow.next();
          var nextRow = theRow.nextAll(":visible:first");
          var td = nextRow.find("td").eq(col);
          var inp = td.find("input");
          if (inp.length > 0)
            inp.focus();
          else
            nextRow.click(); //create a new row
          break;
        case 36: //home
        break;
        case 35: //end
        break;

        case 9: //tab
        case 13: //enter
        break;
      }
    }
    return ret;

  }).focus(function () {
    $(this).closest("tr").click();
  });


  //change status
  taskRow.find(".taskStatus").click(function () {
    var el = $(this);
    var tr = el.closest("[taskid]");
    var taskId = tr.attr("taskid");
    var task = self.master.getTask(taskId);

    var changer = $.JST.createFromTemplate({}, "CHANGE_STATUS");
    changer.find("[status=" + task.status + "]").addClass("selected");
    changer.find(".taskStatus").click(function (e) {
      e.stopPropagation();
      var newStatus = $(this).attr("status");
      changer.remove();
      self.master.beginTransaction();
      task.changeStatus(newStatus);
      self.master.endTransaction();
      el.attr("status", task.status);
    });
    el.oneTime(3000, "hideChanger", function () {
      changer.remove();
    });
    el.after(changer);
  });

};

GridEditor.prototype.openFullEditor = function (task, editOnlyAssig) {
  var self = this;

  if (!self.master.permissions.canSeePopEdit)
    return;

  var taskRow=task.rowElement;

  //task editor in popup
  var taskId = taskRow.attr("taskId");

  //make task editor
  var taskEditor = $.JST.createFromTemplate(task, "TASK_EDITOR");

  //hide task data if editing assig only
  if (editOnlyAssig) {
    taskEditor.find(".taskData").hide();
    taskEditor.find(".assigsTableWrapper").height(455);
    taskEditor.prepend("<h1>\""+task.name+"\"</h1>");
  }

  //got to extended editor
  if (task.isNew()|| !self.master.permissions.canSeeFullEdit){
    taskEditor.find("#taskFullEditor").remove();
  } else {
    taskEditor.bind("openFullEditor.gantt",function () {
      window.location.href=contextPath+"/applications/teamwork/task/taskEditor.jsp?CM=ED&OBJID="+task.id;
    });
  }


  taskEditor.find("#name").val(task.name);
  taskEditor.find("#description").val(task.description);
  taskEditor.find("#progress").val(task.progress ? parseFloat(task.progress) : 0).prop("readonly",task.progressByWorklog==true);
  taskEditor.find("#progressByWorklog").prop("checked",task.progressByWorklog);
  taskEditor.find("#status").val(task.status);
  taskEditor.find("#type").val(task.typeId);
  taskEditor.find("#type_txt").val(task.type);
  taskEditor.find("#relevance").val(task.relevance);
  //cvc_redraw(taskEditor.find(".cvcComponent"));
  taskEditor.find("#load_files").html("");
  if(Array.isArray(task.attchmentfile)){
    for (var i = 0; i < task.attchmentfile.length; i++) {
      taskEditor.find("#load_files").append('<a id="attchment_link" href="http://wtshub.com/ts/ganttchart/v10/jQueryGantt-master/uploads/'+task.attchmentfile[i]+'" target="_blank">'+task.attchmentfile[i]+'</a><br/>');
    }
  }

  if (task.startIsMilestone)
    taskEditor.find("#startIsMilestone").prop("checked", true);
  if (task.megaMilestone)
    taskEditor.find("#megaMilestone").prop("checked", true);
  if (task.endIsMilestone)
    taskEditor.find("#endIsMilestone").prop("checked", true);

  setTimeout(() => {
    if($("#startIsMilestone").prop("checked") == true){
      taskEditor.find("#megaMilestone").prop('disabled', true);
    }
    if($("#megaMilestone").prop("checked") == true){
      taskEditor.find("#startIsMilestone").prop('disabled', true);
      taskEditor.find("#megaMilestone").prop('disabled', true);
    }
  }, 500);
    
  taskEditor.find("#duration").val(durationToString(task.duration));
  var startDate = taskEditor.find("#start");
  startDate.val(new Date(task.start).format());
  //start is readonly in case of deps
  if (task.depends || !(this.master.permissions.canWrite ||task.canWrite)) {
    startDate.attr("readonly", "true");
  } else {
    startDate.removeAttr("readonly");
  }

  taskEditor.find("#end").val(new Date(task.end).format());

  //make assignments table
  var assigsTable = taskEditor.find("#assigsTable");
  assigsTable.find("[assId]").remove();

  // loop on assignments
  for (var i = 0; i < task.assigs.length; i++) {
    var assig = task.assigs[i];
    var assigRow = $.JST.createFromTemplate({task: task, assig: assig}, "ASSIGNMENT_ROW");
    assigsTable.append(assigRow);
  }
   //for successors
   var dependsTable = taskEditor.find("#dependsTable");
   dependsTable.find("[depId]").remove();

   if(task.dependency && task.dependency!='')
   {
     let dep=task.dependency.split(',');
     for (let index = 0; index <= dep.length; index++) {
       const element = dep[index];
       let splitDep;
       let count=[];
       if(element!=undefined)
       {
         dep_data=element.split('::');
         let tasks=this.master.tasks;
         for (let index = 0; index < tasks.length; index++) {
           const element1 = tasks[index];
           splitDep=dep_data[0].split(':');
           taskIndex = tasks.indexOf(task, 0);
           if((splitDep[0])-1==index){
             count=splitDep;
             dep_data[4]=splitDep[0];
             dep_data[0]=element1['name'];
           }
         }

         if(dep_data[1]==1){
           dep_data[1]='START TO START';
         }else if(dep_data[1]==2){
           dep_data[1]='END TO END';
         }
         else{
           dep_data[1]='START TO END';
         }

         if(count.length>1){
           dep_data[2]=count[1];
         }else{
           dep_data[2]=0;
         }

         var depRow = $.JST.createFromTemplate({id:dep_data[4],name: dep_data[0],delay:dep_data[2],task:dep_data[1]}, "DEPENDENCY_ROW");
         dependsTable.append(depRow);
       }
     }
   }
   else{
     var depRow = $.JST.createFromTemplate({id:'temp_1',name:'',delay:0,task:''}, "DEPENDENCY_ROW");
     dependsTable.append(depRow);
   }

   taskEditor.find("#addDep1").click(function () {
     var dependsTable = taskEditor.find("#dependsTable");
     if(countSuccessors==0)
     {
       var dependsRow = $.JST.createFromTemplate({id: "temp_1",delay:0,name:'',task:''}, "DEPENDENCY_ROW");
       dependsTable.append(dependsRow);
       $("#bwinPopupd").scrollTop(10000);
       countSuccessors=1;
     }else{
       alert('Each task have only one successor.');
     }
   });

   var dependsTable1 = taskEditor.find("#dependsTable1");
   dependsTable1.find("[depId1]").remove();

   let IndexOfPredecessors = this.master.tasks.indexOf(task, 0);

   let tasks1=this.master.tasks;
   for (let index = 0; index < tasks1.length; index++) {
     const element1 = tasks1[index];     
     let childDep=element1.dependency;
     let childDepSplit;
     if(childDep!=undefined)
     {
        childDepSplit=childDep.split(',');
     }
     
     if(childDepSplit!=undefined)
     {
       let splitDep=childDepSplit[0].split('::');
       let depType=splitDep[1];
       let splitDelay=splitDep[0].split(':');
       let Delay=splitDelay[1];
       let Dep=splitDelay[0];
       let TaskName;
       let Id;
       if(Dep==(IndexOfPredecessors+1)){
         TaskName=element1['name'];
         Id=element1['id'];
         if(depType==1){
           depType='START TO START';
         }else if(depType==2){
           depType='END TO END';
         }
         else{
           depType='STAR TO END';
         }

         if(Delay==undefined)
         {
           Delay=0;
         }
         var depRow1 = $.JST.createFromTemplate({parentId:(IndexOfPredecessors+1),id:Id,name:TaskName,delay:Delay,task:depType}, "DEPENDENCY_ROW1");
         dependsTable1.append(depRow1);
       }
     }
   }

   taskEditor.find(":input").updateOldValue();

   if (!(self.master.permissions.canWrite || task.canWrite)) {
     taskEditor.find("input,textarea").prop("readOnly", true);
     taskEditor.find("input:checkbox,select").prop("disabled", true);
     taskEditor.find("#saveButton").remove();
     taskEditor.find(".button").addClass("disabled");

   } else {

    //bind dateField on dates, duration
    taskEditor.find("#start,#end,#duration").click(function () {
      var input = $(this);
      if (input.is("[entrytype=DATE]")) {
        input.dateField({
          inputField: input,
          minDate:self.minAllowedDate,
          maxDate:self.maxAllowedDate,
          callback:   function (d) {$(this).blur();}
        });
      }
    }).blur(function () {
      var inp = $(this);
      if (inp.validateField()) {
        resynchDates(inp, taskEditor.find("[name=start]"), taskEditor.find("[name=startIsMilestone]"), taskEditor.find("[name=duration]"), taskEditor.find("[name=end]"), taskEditor.find("[name=endIsMilestone]"));
        //workload computation
        if (typeof(workloadDatesChanged)=="function")
          workloadDatesChanged();
      }
    });

    taskEditor.find("#startIsMilestone,#endIsMilestone").click(function () {
      var inp = $(this);
      resynchDates(inp, taskEditor.find("[name=start]"), taskEditor.find("[name=startIsMilestone]"), taskEditor.find("[name=duration]"), taskEditor.find("[name=end]"), taskEditor.find("[name=endIsMilestone]"));
    });

    //bind add assignment
    var cnt=0;
    taskEditor.find("#addAssig").click(function () {
      cnt++;
      var assigsTable = taskEditor.find("#assigsTable");
      var assigRow = $.JST.createFromTemplate({task: task, assig: {id: "tmp_" + new Date().getTime()+"_"+cnt}}, "ASSIGNMENT_ROW");
      assigsTable.append(assigRow);
      $("#bwinPopupd").scrollTop(10000);
    }).click();

    var count=0;
    taskEditor.find("#addDep").click(function () {
      count++;
      var dependsTable = taskEditor.find("#dependsTable1");
      var dependsRow = $.JST.createFromTemplate({parentId:(IndexOfPredecessors+1),id: "tmp_" + new Date().getTime()+"_"+count,delay:0,name:'',task:''}, "DEPENDENCY_ROW1");
      dependsTable.append(dependsRow);
      $("#bwinPopupd").scrollTop(10000);
    });

    //save task
    let that=this;
    taskEditor.bind("saveFullEditor.gantt",function () {  
      self.master.beginTransaction();
      var task = self.master.getTask(taskId); // get task again because in case of rollback old task is lost
       console.log(task,'tasks Edittor');

      task.name = taskEditor.find("#name").val();
      task.description = taskEditor.find("#description").val();
      task.progress = parseFloat(taskEditor.find("#progress").val());
      //task.duration = parseInt(taskEditor.find("#duration").val()); //bicch rimosso perchè devono essere ricalcolata dalla start end, altrimenti sbaglia
      task.startIsMilestone = taskEditor.find("#startIsMilestone").is(":checked");
      task.endIsMilestone = taskEditor.find("#endIsMilestone").is(":checked");
      task.megaMilestone = taskEditor.find("#megaMilestone").is(":checked");

      task.type = taskEditor.find("#type_txt").val();
      task.typeId = taskEditor.find("#type").val();
      task.relevance = taskEditor.find("#relevance").val();
      task.progressByWorklog= taskEditor.find("#progressByWorklog").is(":checked");

      let parentTask=task.getParent();
      let taskchilds=task.getChildren();

      if(taskchilds.length>0){
        let progress=0;
        for (var i = 0; i < taskchilds.length; i++) {
          progress=progress+taskchilds[i].progress;
        }
        task.progress=progress/taskchilds.length;
      }

      if(parentTask!=undefined){
        let mainParent=parentTask.getParent();
        let mainchilds=parentTask.getChildren();

        if(mainchilds.length>0){
          let progress=0;
          for (var i = 0; i < mainchilds.length; i++) {
            progress=progress+mainchilds[i].progress;
          }
          parentTask.progress=progress/mainchilds.length;
        }

        if(mainParent!=undefined){
          let Parentt=mainParent.getParent();
          let childs=mainParent.getChildren();
          if(childs.length>0){
            let progress=0;
            for (var i = 0; i < childs.length; i++) {
              progress=progress+childs[i].progress;
            }
            mainParent.progress=progress/childs.length;
          }
          if(Parentt!=undefined){
            let children=mainParent.getChildren();
            if(children.length>0){
              let progress=0;
              for (var i = 0; i < children.length; i++) {
                progress=progress+children[i].progress;
              }
              Parentt.progress=progress/children.length;
            }
          }
        }
      }

      //set assignments
      var cnt=0;
      taskEditor.find("tr[assId]").each(function () {
        var trAss = $(this);
        var assId = trAss.attr("assId");
        var resId = trAss.find("[name=resourceId]").val();
        var resName = trAss.find("[name=resourceId_txt]").val(); // from smartcombo text input part
        var roleId = trAss.find("[name=roleId]").val();
        var effort = millisFromString(trAss.find("[name=effort]").val(),true);

        //check if the selected resource exists in ganttMaster.resources
        var res= self.master.getOrCreateResource(resId,resName);

        //if resource is not found nor created
        if (!res)
          return;

        //check if an existing assig has been deleted and re-created with the same values
        var found = false;
        for (var i = 0; i < task.assigs.length; i++) {
          var ass = task.assigs[i];

          if (assId == ass.id) {
            ass.effort = effort;
            ass.roleId = roleId;
            ass.resourceId = res.id;
            ass.touched = true;
            found = true;
            break;

          } else if (roleId == ass.roleId && res.id == ass.resourceId) {
            ass.effort = effort;
            ass.touched = true;
            found = true;
            break;

          }
        }

        if (!found && resId && roleId) { //insert
          cnt++;
          var ass = task.createAssignment("tmp_" + new Date().getTime()+"_"+cnt, resId, roleId, effort);
          ass.touched = true;
        }

      });

      //remove untouched assigs
      task.assigs = task.assigs.filter(function (ass) {
        var ret = ass.touched;
        delete ass.touched;
        return ret;
      });

      //change dates
      task.setPeriod(Date.parseString(taskEditor.find("#start").val()).getTime(), Date.parseString(taskEditor.find("#end").val()).getTime() + (3600000 * 22));

      //change status
      task.changeStatus(taskEditor.find("#status").val());

      if(task.startIsMilestone==true)
      {
        let tasks=self.master.tasks;
        let taskIndex;
        taskIndex = tasks.indexOf(task, 0);

        if(task.level==0){
          task.master.setErrorOnTransaction("\""+task.name + "\"\n" + GanttMaster.messages.TASK_HAS_CONSTRAINTS);
        }
        else if(task.level==1)
        {
          let countChild=0;
          for (let index = taskIndex+1; index < tasks.length; index++) {
            const element = tasks[index];
            if(element.level==1){
              break;
            }
            countChild++;
          }

          if(countChild>0){
            task.master.setErrorOnTransaction("\""+task.name + "\"\n" + GanttMaster.messages.TASK_HAS_CONSTRAINTS);
          }else{
            task.duration=1;
            task.end=task.start+(1000 * 60 * 60 * 24);
          }
        }else{
          task.duration=1;
          task.end=task.start+(1000 * 60 * 60 * 24);

          let parent_index;
          let parent_task_id;
          let startArray = [];
          let endArray = [];

          for (let index = taskIndex; index > 0; index--) {
            const element = tasks[index];
            if (element.level == 1) {
              parent_index = index;
              parent_task_id = element.id;
              break;
            }
          }

          for (
            let index = parent_index + 1;
            index < tasks.length;
            index++
            ) {
            const element = tasks[index];
          if (element.level == 1) {
            break;
          }
          startArray.push(element.start);
          endArray.push(element.end);
        }

        let MinStart = Math.min(...startArray);
        let MaxEnd = Math.max(...endArray);
        let parenttask = ge.getTask(parent_task_id);
        let parentDuration=0;

        parenttask.start=MinStart;
        parenttask.end=MaxEnd;

        dates1 = getDatesBetween(
          new Date(parenttask.start),
          new Date(parenttask.end)
          );

        for (let index = 0; index < dates1.length; index++) {
          const element = dates1[index];
          let Holiday = isHoliday(new Date(element));
          if (!Holiday) {
            parentDuration++;
          }
        }
        parenttask.duration = parentDuration-1  ;
      }
    }

    let newDep=[];
    var depId;
    var newdepends=[];
    taskEditor.find("tr[depId]").each(function () {
      var dpAss = $(this);
      var parentId = dpAss.attr("depId");
      var name = dpAss.find("[name=taskName]").val();
      depId = dpAss.find("[name=dependency]").val();
      var delay_dep = dpAss.find("[name=delay]").val();
      if(name!='select'){
        if (delay_dep == "") {
          delay_dep = 0;
        }
        if(delay_dep==0){
          newDep.push(name+'::'+depId);
          newdepends.push(name);
        }else{
          newDep.push(name+':'+delay_dep+'::'+depId);
          newdepends.push(name+':'+delay_dep);
        }
      }
    });
   
    if(name!='select'){
      task.depends=newdepends.join(',');
      task.dependency=newDep.join(',');
      self.master.updateLinks(task);
      self.master.changeTaskDeps(task,depId);
    }
    let checkChild=false;
    taskEditor.find("tr[depId1]").each(function () {
      var dpAss1 = $(this);
      var OldId=dpAss1.attr("depId1");
      var parentId = dpAss1.attr("parentId");
      var name = dpAss1.find("[name=taskName]").val();
      var dep = dpAss1.find("[name=dependency]").val();
      var delay = dpAss1.find("[name=delay]").val();
      let tasks=self.master.tasks;
      let getLastId=tasks[tasks.length-1];
      for (var i = 0; i < getLastId.id; i++) {
        if(tasks[i]!='' && tasks[i]!=undefined)
        {
          let splitDep=tasks[i].depends;
          if(splitDep!='')
          {
            splitDep=splitDep.split(',');
            splitDep=splitDep[0].split(':');
            splitDep=splitDep[0];
            if(taskEditor.find("#megaMilestone").is(":checked")){
              if(splitDep==task.id){
                checkChild = true;
              }
            }
            if(splitDep==parentId)
            {
              tasks[i].depends='';
              tasks[i].dependency='';
              self.master.updateLinks(tasks[i]);
              self.master.changeTaskDeps(tasks[i],dep);
            }
          }else{
            checkChild=true;
          }
        }
      }
      setTimeout(function(){
        for (var i = 0; i < getLastId.id; i++) {
          if(name==(i+1)){
            if(delay==0){
              tasks[i].depends=parentId;
              tasks[i].dependency=parentId+'::'+dep;
            }else{
              tasks[i].depends=parentId+':'+delay;
              tasks[i].dependency=parentId+':'+delay+'::'+dep;
            }
            self.master.updateLinks(tasks[i]);
            self.master.changeTaskDeps(tasks[i],dep);
          }
        }
      },500);
    });
    if(!checkChild && taskEditor.find("#megaMilestone").is(":checked")){
      var factory = new TaskFactory();
      var ch = factory.build("tmp_fk" + new Date().getTime(), "new Task", "", 2, 1604584203019, Date.workingPeriodResolution);
      console.log("Adding task...");
      that.master.addTask(ch);
     
      self.master.tasks[self.master.tasks.length-1].depends=task.id;
      self.master.tasks[self.master.tasks.length-1].dependency=task.id+'::' + 3;
    }
    var form_data = new FormData();
    if($('#attchmentfile').val() != ""){
      task.attchmentfile = $('#attchmentfile').prop('files'); 
      for (var i = task.attchmentfile.length - 1; i >= 0; i--) {
        form_data.append('file[]',task.attchmentfile[i]);
      }        
    } else {
      form_data.append('file', "");
    }
    form_data.append('id', task.id);
    $.ajax("controller/FileSaveController.php", {
      dataType:"json",
      contentType: false,
      processData: false,
      data: form_data,
      type:"POST",
      success: function(response) {
        if (response.ok) {
        } else {
        }
      }
    });
    if(!checkChild && taskEditor.find("#megaMilestone").is(":checked")){
      setTimeout(() => {
        var prj = ge.saveProject();
        localStorage.setObject("teamworkGantDemo", prj);
        console.log(prj,'prj');
          $.ajax("controller/ganttSaveController.php", {
            dataType:"json",
            data: {deletedIds:[],tasks:JSON.stringify(prj.tasks)},
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
      }, 300);
     
      }
    setTimeout(function(){
      if (self.master.endTransaction()) {
        taskEditor.find(":input").updateOldValue();
        closeBlackPopup();
      }
    },700);
  });
}

taskEditor.attr("alertonchange","true");
  var ndo = createModalPopup(800, 450).append(taskEditor);//.append("<div style='height:800px; background-color:red;'></div>")

  //workload computation
  if (typeof(workloadDatesChanged)=="function")
    workloadDatesChanged();



};
