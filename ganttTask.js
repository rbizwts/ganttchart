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

/**
 * A method to instantiate valid task models from
 * raw data.
 */
 function TaskFactory() {

  /**
   * Build a new Task
   */
  this.build = function (id, name, code, level, start, duration, collapsed) {
    // Set at beginning of day
    var adjusted_start = computeStart(start);
    console.log("Let's build");
    var calculated_end = computeEndByDuration(adjusted_start, duration);
    console.log("Let's build calculated_end", calculated_end);
    return new Task(id, name, code, level, adjusted_start, calculated_end, duration, collapsed);
  };

}

function Task(id, name, code, level, start, end, duration, collapsed) {
  this.id = id;
  this.name = name;
  this.progress = 0;
  this.progressByWorklog = false;
  this.relevance = 0;
  this.type = "";
  this.typeId = "";
  this.description = "";
  this.code = code;
  this.level = level;
  this.status = "STATUS_UNDEFINED";
  this.depends = "";

  this.start = start;
  this.duration = duration;
  this.end = end;

  this.startIsMilestone = false;
  this.endIsMilestone = false;

  this.collapsed = collapsed;

  //permissions
  // by default all true, but must be inherited from parent
  this.canWrite = true;
  this.canAdd = true;
  this.canDelete = true;
  this.canAddIssue = true;

  this.rowElement; //row editor html element
  this.ganttElement; //gantt html element
  this.master;


  this.assigs = [];
}

Task.prototype.clone = function () {
  var ret = {};
  for (var key in this) {
    if (typeof(this[key]) != "function")
      if (typeof(this[key]) != "object" || Array.isArray(this[key]))
        ret[key] = this[key];
    }
    return ret;
  };

  Task.prototype.getAssigsString = function () {
    var ret = "";
    for (var i = 0; i < this.assigs.length; i++) {
      var ass = this.assigs[i];
      var res = this.master.getResource(ass.resourceId);
      if (res) {
        ret = ret + (ret == "" ? "" : ", ") + res.name;
      }
    }
    return ret;
  };

  Task.prototype.createAssignment = function (id, resourceId, roleId, effort) {
    var assig = new Assignment(id, resourceId, roleId, effort);
    this.assigs.push(assig);
    return assig;
  };


//<%---------- SET PERIOD ---------------------- --%>
Task.prototype.setPeriod = function (start, end ) {
  console.log("ganttTask setPeriod :this.name,new Date(start), new Date(end)",this.name,new Date(start), new Date(end));
  //var profilerSetPer = new Profiler("gt_setPeriodJS");

  if (start instanceof Date) {
    start = start.getTime();
  }

  if (end instanceof Date) {
    end = end.getTime();
  }

  var originalPeriod = {
    start:    this.start,
    end:      this.end,
    duration: this.duration
  };

  //console.log("ganttTask setPeriod originalPeriod: -->", originalPeriod);

  //compute legal start/end //todo mossa qui R&S 30/3/2016 perchè altrimenti il calcolo della durata, che è stato modificato sommando giorni, sbaglia
  start = computeStart(start);
  end=computeEnd(end);
  //console.log("set period:-->", originalPeriod);
  //console.log("ganttTask setPeriod : New start end  ", start, end);
  var newDuration = recomputeDuration(start, end);
  //console.log("ganttTask setPeriod : New duration:-->", newDuration);
  //if are equals do nothing and return true
  if ( start == originalPeriod.start && end == originalPeriod.end && newDuration == originalPeriod.duration) {
    //console.log("in if start, originalPeriod.start:==>", start, originalPeriod.start, this.dependency);
    return true;
  }

  if (newDuration == this.duration) { // is shift
    console.log("is shift");
    return this.moveTo(start, false,true ,end);
  }

  var wantedStartMillis = start;

  var children = this.getChildren();

  if(this.master.shrinkParent && children.length>0) {
    //console.log("in if");
    var chPeriod= this.getChildrenBoudaries();
    start = chPeriod.start;
    end = chPeriod.end;
  }


  //cannot start after end
  if (start > end) {
    console.log("in if");
    start = end;
  }

  //if there are dependencies compute the start date and eventually moveTo
  var startBySuperiors = this.computeStartBySuperiors(start);
  console.log("in if");
  console.log("setPeriod : computeStartBySuperiors:-->", startBySuperiors);
  if (startBySuperiors != start) {
    console.log("if lag is there then new start date");
    return this.moveTo(startBySuperiors, false,true, end);
  }

  var somethingChanged = false;

  if (this.start != start || this.start != wantedStartMillis) {
    this.start = start;
    somethingChanged = true;
  }

  //set end
  var wantedEndMillis = end;

  if (this.end != end || this.end != wantedEndMillis) {
    this.end = end;
    somethingChanged = true;
  }

  this.duration = recomputeDuration(this.start, this.end);

  //profilerSetPer.stop();

  //nothing changed exit
  if (!somethingChanged)
    return true;

  //cannot write exit
  if (!this.canWrite) {
    this.master.setErrorOnTransaction("\"" + this.name + "\"\n" + GanttMaster.messages["CANNOT_WRITE"], this);
    return false;
  }

  //external dependencies: exit with error
  if (this.hasExternalDep) {
    console.log("TASK_HAS_EXTERNAL_DEPS");
    this.master.setErrorOnTransaction("\"" + this.name + "\"\n" + GanttMaster.messages["TASK_HAS_EXTERNAL_DEPS"], this);
    return false;
  }

  var todoOk = true;

  //I'm restricting
  var deltaPeriod = originalPeriod.duration - this.duration;
  var restricting = deltaPeriod > 0;
  var enlarging = deltaPeriod < 0;
  var restrictingStart = restricting && (originalPeriod.start < this.start);
  var restrictingEnd = restricting && (originalPeriod.end > this.end);

  if (restricting) {
    //loops children to get boundaries
    var bs = Infinity;
    var be = 0;
    for (var i = 0; i < children.length; i++) {

      var ch = children[i];
      if (restrictingEnd) {
        be = Math.max(be, ch.end);
      } else {
        bs = Math.min(bs, ch.start);
      }
    }

    if (restrictingEnd) {
      this.end = Math.max(be, this.end);
    } else {
      this.start = Math.min(bs, this.start);
    }
    this.duration = recomputeDuration(this.start, this.end);
    if (this.master.shrinkParent ) {
      todoOk = updateTree(this);
    }

  } else {

    //check global boundaries
    if (this.start < this.master.minEditableDate || this.end > this.master.maxEditableDate) {
      this.master.setErrorOnTransaction("\"" + this.name + "\"\n" +GanttMaster.messages["CHANGE_OUT_OF_SCOPE"], this);
      todoOk = false;
    }

    //console.debug("set period: somethingChanged",this);
    if (todoOk ) {
      todoOk = updateTree(this);
    }
  }

  if (todoOk) {
    todoOk = this.propagateToInferiors(end,3);
  }
  return todoOk;
};

Task.prototype.setPeriod2 = function (start, end,dep) {
  if (start instanceof Date) {
    start = start.getTime();
  }

  if (end instanceof Date) {
    end = end.getTime();
  }

  var originalPeriod = {
    start:    this.start,
    end:      this.end,
    duration: this.duration
  };

  console.log("ganttTask setPeriod2 originalPeriod: -->", originalPeriod);

  //compute legal start/end //todo mossa qui R&S 30/3/2016 perchè altrimenti il calcolo della durata, che è stato modificato sommando giorni, sbaglia
  start = computeStart(start);
  end=computeEnd(end);
  //console.log("set period:-->", originalPeriod);
  console.log("ganttTask setPeriod2 : New start end  ", start, end);
  var newDuration = recomputeDuration(start, end);
  console.log("ganttTask setPeriod2 : this.duration, New duration:-->", this.duration, newDuration);
  //if are equals do nothing and return true
  if ( start == originalPeriod.start && end == originalPeriod.end && newDuration == originalPeriod.duration) {
    return true;
  }
  
  if (newDuration == this.duration) { // is shift
    console.log("is shift");
    return this.moveToEndToEnd(start, false,true);
  }
  
  var wantedStartMillis = start;

  var children = this.getChildren();

  if(this.master.shrinkParent && children.length>0) {
    var chPeriod= this.getChildrenBoudaries();
    start = chPeriod.start;
    end = chPeriod.end;
  }


  //cannot start after end
  if (start > end) {
    alert('start>end');
    start = end;
  }

  //if there are dependencies compute the start date and eventually moveTo
  if(dep==1){
    start = this.computeStartBySuperiors(start, dep);
    var newDuration = recomputeDuration(start, end);
    this.duration=newDuration;
    return this.moveTo(start, false,true, end,dep);
  }else if(dep==2){
    end = this.computeEndBySuperiors(end, dep);
    var newDuration = recomputeDuration(start, end);
    this.duration=newDuration;
    return this.moveTo(start, false,true, end,dep);
  }else{
    var startBySuperiors = this.computeStartBySuperiors(start);
    if (startBySuperiors != start) {
      return this.moveTo(startBySuperiors, false,true, end,dep);
    }
  }
  
  var somethingChanged = false;

  if (this.start != start || this.start != wantedStartMillis) {
    this.start = start;
    somethingChanged = true;
  }

  //set end
  var wantedEndMillis = end;

  if (this.end != end || this.end != wantedEndMillis) {
    this.end = end;
    somethingChanged = true;
  }

  this.duration = recomputeDuration(this.start, this.end);

  //profilerSetPer.stop();

  //nothing changed exit
  if (!somethingChanged)
    return true;

  //cannot write exit
  if (!this.canWrite) {
    this.master.setErrorOnTransaction("\"" + this.name + "\"\n" + GanttMaster.messages["CANNOT_WRITE"], this);
    return false;
  }

  //external dependencies: exit with error
  if (this.hasExternalDep) {
    this.master.setErrorOnTransaction("\"" + this.name + "\"\n" + GanttMaster.messages["TASK_HAS_EXTERNAL_DEPS"], this);
    return false;
  }

  var todoOk = true;

  //I'm restricting
  var deltaPeriod = originalPeriod.duration - this.duration;
  var restricting = deltaPeriod > 0;
  var enlarging = deltaPeriod < 0;
  var restrictingStart = restricting && (originalPeriod.start < this.start);
  var restrictingEnd = restricting && (originalPeriod.end > this.end);

  if (restricting) {
    //loops children to get boundaries
    var bs = Infinity;
    var be = 0;
    for (var i = 0; i < children.length; i++) {

      var ch = children[i];
      if (restrictingEnd) {
        be = Math.max(be, ch.end);
      } else {
        bs = Math.min(bs, ch.start);
      }
    }

    if (restrictingEnd) {
      this.end = Math.max(be, this.end);
    } else {
      this.start = Math.min(bs, this.start);
    }
    this.duration = recomputeDuration(this.start, this.end);
    if (this.master.shrinkParent ) {
      todoOk = updateTree2(this);
    }

  } else {

    //check global boundaries
    if (this.start < this.master.minEditableDate || this.end > this.master.maxEditableDate) {
      this.master.setErrorOnTransaction("\"" + this.name + "\"\n" +GanttMaster.messages["CHANGE_OUT_OF_SCOPE"], this);
      todoOk = false;
    }

    //console.debug("set period: somethingChanged",this);
    if (todoOk ) {
      console.log("Todook");
      todoOk = updateTree(this);
    }
  }

  if (todoOk) {
    todoOk = this.propagateToInferiors(end,dep);
  }
  return todoOk;
};



//<%---------- MOVE TO ---------------------- --%>
Task.prototype.moveTo = function (start, ignoreMilestones, propagateToInferiors, end , dep_type = 3 ) {

  if (start instanceof Date) {
    start = start.getTime();
  }
  //added by NJ
  if (end instanceof Date) {
    end = end.getTime();
  }

  //added end by NJ
  //console.log("ganttTask : moveTo  start.getTime(), end.getTime()",this.name,start,end,ignoreMilestones);
  var originalPeriod = {
    start: this.start,
    end:   this.end
  };

  var wantedStartMillis = start;
  var wantedEndMillis = end;

  //set a legal start
  start = computeStart(start);
  end = computeEnd(end);

  //if depends, start is set to max end + lag of superior
  if(dep_type==3)
  {
    //console.log("ganttTask : moveTo before - computeStartBySuperiors:-->",new Date(start).getDate(),":",new Date(end).getDate(),this.duration);
    start = this.computeStartBySuperiors(start, dep_type);

    end = computeEndByDurationStartToEnd(start, this.duration, dep_type);
    //console.log("ganttTask : moveTo 3 after - computeStartBySuperiors:-->",new Date(start), new Date(end), this.duration);
    //console.log("ganttTask : moveTo 3 after - comparison this.start  ==   start:-->",new Date(this.start),":", new Date(start), this.duration);
    //this.start = start;
    //this.end = end;
  }
  else if(dep_type==1)
  {
    console.log("ganttTask : moveTo before - computeStartBySuperiors:-->",new Date(start),new Date(end),this.duration);
    start = this.computeStartBySuperiors(start, dep_type);

    end = computeEndByDurationStartToStart(start, this.duration, dep_type);
    console.log("ganttTask : moveTo 1 after - computeStartBySuperiors  & computeEndByDurationStartToStart:-->",new Date(start).getDate(), new Date(end).getDate(), this.name);
    //console.log("ganttTask : moveTo 1 after - comparison this.start  ==   start:-->",new Date(this.start), new Date(start), this.duration);

  }
  else if(dep_type==2)
  {
    //console.log("ganttTask : moveTo before - computeEndBySuperiors:-->",new Date(start),new Date(end),this.duration);
    end = this.computeEndBySuperiors(end, dep_type);

    start = computeStartByDurationEndToEnd(end, this.duration);
    console.log("ganttTask : moveTo 2 after - computeStartByDurationEndToEnd new start & end:-->",new Date(start), new Date(end), this.duration); 
    console.log("ganttTask : moveTo 2 after - comparison this.start  ==   start:-->",new Date(this.start), new Date(start), this.duration);
    //this.start = start2;
    //this.end = end;
    
  }
  //check milestones compatibility
  if (!this.checkMilestonesConstraints(start,end,ignoreMilestones))
    return false;

  console.log("ganttTask : moveTo in this.start != start condition, wantedEndMillis :-->",new Date(this.start), new Date(start), wantedEndMillis);
  console.log("ganttTask : moveTo in this.end != end condition, wantedEndMillis :-->",new Date(this.end), new Date(end), wantedEndMillis);
  if (this.start != start || this.start != wantedStartMillis || this.end != end || this.end != wantedEndMillis) {
    //in case of end is milestone it never changes!
    //if (!ignoreMilestones && this.endIsMilestone && end != this.end) {
    //  this.master.setErrorOnTransaction("\"" + this.name + "\"\n" + GanttMaster.messages["END_IS_MILESTONE"], this);
    //  return false;
    //}
    //if(dep_type==3){
      //console.log("ganttTask : moveTo in this.start != start condition, wantedStartMillis :-->",new Date(this.start), new Date(start), wantedStartMillis);
      console.log("ganttTask : moveTo in this.end != end condition, wantedEndMillis :-->",new Date(this.end), new Date(end), wantedEndMillis);


      this.start = start;
      this.end = end;
    //}
    /*else if(dep_type==2){

    }*/
    //profiler.stop();
    //console.log("this.start, this.end",new Date(this.start),new Date(this.end));
    console.log("ganttTask : moveTo final - computeStartByDurationEndToEnd new start & end:-->",this.name ,new Date(this.start).getDate()," : ", new Date(this.end).getDate(), this.duration);  
    //check global boundaries
    if (this.start < this.master.minEditableDate || this.end > this.master.maxEditableDate) {
      this.master.setErrorOnTransaction("\"" + this.name + "\"\n" +GanttMaster.messages["CHANGE_OUT_OF_SCOPE"], this);
      return false;
    }


    // bicch 22/4/2016: when you move a task with child riding on holidays, the children must be shifted into workingDays, not in milliseconds, otherwise the durations are changed
    // when moving children you MUST consider WORKING days,
    var panDeltaInWM = getDistanceInUnits(new Date(originalPeriod.start),new Date(this.start));
    console.log("ganttTask : moveTo considering panDeltaInWM:-->",panDeltaInWM);  
    //loops children to shift them
    var children = this.getChildren();
    for (var i = 0; i < children.length; i++) {
      console.log("ganttTask : MoveTo  move children panDeltaInWM:",panDeltaInWM);
      var ch = children[i];
     // alert(ch.name+" : "+ch.duration);
     
     var chStart=incrementDateByUnits(new Date(ch.start),panDeltaInWM);
     var chEnd=incrementDateByUnits(new Date(ch.end),panDeltaInWM);
     console.log("children23 start end",ch.name, new Date(ch.start),new Date(ch.end));
     if(dep_type==3) {
        //ch.moveTo(chStart,false,false,chEnd, dep_type);
        ch.moveToSimple(chStart,false,false);
      }
      else if(dep_type==2) {
        ch.moveTo(chStart,false,false,chEnd, dep_type);
      }
      else if(dep_type==1) {
        console.log("Move start to start childs",new Date(originalPeriod.start), new Date(this.start));
        ch.moveTo(chStart,false,false,ch.end, dep_type);
      }
    }

    if(dep_type==3) {
      if (!updateTree(this)) {
        console.log("update tree");
        return false;
      }
      else
      {
        console.log("update tree simple have returntrue");
      }
    }
    else if(dep_type==2)
    {
      if (!updateTree2(this)) {
        console.log("update tree2");
        return false;
      }
      else{
        console.log("update tree 2 have returntrue");
      }
    }
    else if(dep_type==1)
    {
      if (!updateTree3(this)) {
        console.log("update tree3");
        return false;
      }
      else
      {
        console.log("update tree 3 simple have returntrue");
      }
    }

    if (propagateToInferiors) {
      console.log("propagation to inferiors");
      this.propagateToInferiors(end,dep_type);
      var todoOk = true;
      var descendants = this.getDescendant();
      for (var i = 0; i < descendants.length; i++) {
        ch = descendants[i];
        if (!ch.propagateToInferiors(ch.end,dep_type))
          return false;
      }
    }
  }
  if(this.end != end || this.end != wantedStartMillis){
    console.log("compute parental tasks by end dates");
  }
  console.log("this.start, this.end",new Date(this.start),new Date(this.end));
  console.log("return true");
  return true;
};

Task.prototype.moveToEndToEnd = function (start, ignoreMilestones, propagateToInferiors) {
  //console.debug("moveTo ",this.name,new Date(start),this.duration,ignoreMilestones);
  //var profiler = new Profiler("gt_task_moveTo");
  console.log("ganttTask : moveToEndToEnd  ",this.name,new Date(start),this.duration,ignoreMilestones, new Date(end));
  //console.log("Dependancy type:-->",dep_type);
  if (start instanceof Date) {
    start = start.getTime();
  }

  var originalPeriod = {
    start: this.start,
    end:   this.end
  };

  var wantedStartMillis = start;

  //set a legal start
  start = computeStart(start);

  //if depends, start is set to max end + lag of superior
  //console.log("ganttTask : moveToEndToEnd before - computeStartBySuperiors:-->",new Date(start),new Date(end),this.duration);
  start = this.computeStartBySuperiors(start);

  var end = computeEndByDuration(start, this.duration);
  console.log("ganttTask : moveToEndToEnd after - computeStartBySuperiors:-->",new Date(start), new Date(end), this.duration);

  //check milestones compatibility
  if (!this.checkMilestonesConstraints(start,end,ignoreMilestones))
    return false;

  if (this.start != start || this.start != wantedStartMillis) {
    //in case of end is milestone it never changes!
    //if (!ignoreMilestones && this.endIsMilestone && end != this.end) {
    //  this.master.setErrorOnTransaction("\"" + this.name + "\"\n" + GanttMaster.messages["END_IS_MILESTONE"], this);
    //  return false;
    //}
    this.start = start;
    this.end = end;
    //profiler.stop();

    //check global boundaries
    if (this.start < this.master.minEditableDate || this.end > this.master.maxEditableDate) {
      this.master.setErrorOnTransaction("\"" + this.name + "\"\n" +GanttMaster.messages["CHANGE_OUT_OF_SCOPE"], this);
      return false;
    }


    // bicch 22/4/2016: quando si sposta un task con child a cavallo di holidays, i figli devono essere shiftati in workingDays, non in millisecondi, altrimenti si cambiano le durate
    // when moving children you MUST consider WORKING days,
    var panDeltaInWM = getDistanceInUnits(new Date(originalPeriod.start),new Date(this.start));
    
    //loops children to shift them
    var children = this.getChildren();
    console.log("moveEndToEnd getChildren",this,children);
    for (var i = 0; i < children.length; i++) {
      var ch = children[i];
      var chStart=incrementDateByUnits(new Date(ch.start),panDeltaInWM);
      ch.moveToEndToEnd(chStart,false,false);
    }

    if (!updateTree2(this)) {
      return false;
    }

    if (propagateToInferiors) {
      this.propagateToInferiors(end);
      var todoOk = true;
      var descendants = this.getDescendant();
      for (var i = 0; i < descendants.length; i++) {
        ch = descendants[i];
        if (!ch.propagateToInferiors(ch.end))
          return false;
      }
    }
  }
  return true;
};

Task.prototype.moveToSimple = function (start, ignoreMilestones, propagateToInferiors) {
  //console.debug("moveTo ",this.name,new Date(start),this.duration,ignoreMilestones);
  //var profiler = new Profiler("gt_task_moveTo");
  console.log("ganttTask : moveTo  ",this.name,new Date(start),this.duration,ignoreMilestones, new Date(end));
  //console.log("Dependancy type:-->",dep_type);
  if (start instanceof Date) {
    start = start.getTime();
  }

  var originalPeriod = {
    start: this.start,
    end:   this.end
  };

  var wantedStartMillis = start;

  //set a legal start
  start = computeStart(start);

  //if depends, start is set to max end + lag of superior
  console.log("ganttTask : moveTo before - computeStartBySuperiors:-->",new Date(start),new Date(end),this.duration);
  start = this.computeStartBySuperiors(start);

  var end = computeEndByDuration(start, this.duration);
  console.log("ganttTask : moveTo after - computeStartBySuperiors:-->",new Date(start), new Date(end), this.duration);

  //check milestones compatibility
  if (!this.checkMilestonesConstraints(start,end,ignoreMilestones))
    return false;

  if (this.start != start || this.start != wantedStartMillis) {
    //in case of end is milestone it never changes!
    //if (!ignoreMilestones && this.endIsMilestone && end != this.end) {
    //  this.master.setErrorOnTransaction("\"" + this.name + "\"\n" + GanttMaster.messages["END_IS_MILESTONE"], this);
    //  return false;
    //}
    this.start = start;
    this.end = end;
    //profiler.stop();

    //check global boundaries
    if (this.start < this.master.minEditableDate || this.end > this.master.maxEditableDate) {
      this.master.setErrorOnTransaction("\"" + this.name + "\"\n" +GanttMaster.messages["CHANGE_OUT_OF_SCOPE"], this);
      return false;
    }


    // bicch 22/4/2016: quando si sposta un task con child a cavallo di holidays, i figli devono essere shiftati in workingDays, non in millisecondi, altrimenti si cambiano le durate
    // when moving children you MUST consider WORKING days,
    var panDeltaInWM = getDistanceInUnits(new Date(originalPeriod.start),new Date(this.start));

    //loops children to shift them
    var children = this.getChildren();
    for (var i = 0; i < children.length; i++) {
      var ch = children[i];
      var chStart=incrementDateByUnits(new Date(ch.start),panDeltaInWM);
      ch.moveTo(chStart,false,false);
    }

    if (!updateTree(this)) {
      return false;
    }

    if (propagateToInferiors) {
      this.propagateToInferiors(end);
      var todoOk = true;
      var descendants = this.getDescendant();
      for (var i = 0; i < descendants.length; i++) {
        ch = descendants[i];
        if (!ch.propagateToInferiors(ch.end))
          return false;
      }
    }
  }

  return true;
};

Task.prototype.checkMilestonesConstraints = function (newStart,newEnd,ignoreMilestones) {

//if start is milestone cannot be move
if (!ignoreMilestones && (this.startIsMilestone && newStart != this.start  )) {
    //notify error
    this.master.setErrorOnTransaction("\"" + this.name + "\"\n" + GanttMaster.messages["START_IS_MILESTONE"], this);
    return false;
  } else if (!ignoreMilestones && (this.endIsMilestone && newEnd != this.end)) {
    //notify error
    this.master.setErrorOnTransaction("\"" + this.name + "\"\n" + GanttMaster.messages["END_IS_MILESTONE"], this);
    return false;
  } else if (this.hasExternalDep) {
    //notify error
    this.master.setErrorOnTransaction("\"" + this.name + "\"\n" + GanttMaster.messages["TASK_HAS_EXTERNAL_DEPS"], this);
    return false;
  }
  return true;
};

//<%---------- PROPAGATE TO INFERIORS ---------------------- --%>
Task.prototype.propagateToInferiors = function (end,start) {
  //console.debug("propagateToInferiors "+this.name)
  //and now propagate to inferiors
  var todoOk = true;
  var infs = this.getInferiors();
  console.log(infs);
  //alert("infs"+infs);

  if (infs && infs.length > 0) {
    for (var i = 0; i < infs.length; i++) {
      var link = infs[i];
      if (!link.to.canWrite) {
        this.master.setErrorOnTransaction(GanttMaster.messages["CANNOT_WRITE"] + "\n\"" + link.to.name + "\"", link.to);
        break;
      }
      
      let dep;
      if(link.to.dependency!=undefined){
        let dependency=link.to.dependency.split(',');
        for (let index = 0; index < dependency.length; index++) {
          const element = dependency[index];
          dep=element.split('::');
            //console.log(":::::");
          }
      }
        //var endOfficial=computeEndByDuration(end, link.to.duration);
      //console.log("ganttTask.js : propagateToInferiors", dep[1],"--:--", endOfficial);
      //var endOfficial=computeEndByDuration(end, link.to.duration);
      //todoOk = link.to.moveTo(end, false,true); //this is not the right date but  checks start
      if(dep[1]==3)
      {
        var endOfficial=computeEndByDuration(end, link.to.duration);
        console.log("ganttTask.js : propagateToInferiors start to end",new Date(end).getDate(),"--:--", link.to.duration,"--:--",new Date(endOfficial).getDate());
        todoOk = link.to.moveTo(end, false,true,endOfficial,3); //this is not the right date but  checks start
      }
      else if(dep[1]==2)
      {


        var startOfficial = computeStartByDurationEndToEnd(end, link.to.duration);
        console.log("ganttTask.js : propagateToInferiors end to end",new Date(startOfficial));
        todoOk = link.to.moveTo(startOfficial, false,true, end, 2); //this is not the right date but  checks start

      }
      else if(dep[1]==1)
      {
        var endOfficial=computeEndByDuration(end, link.to.duration);
        console.log("ganttTask.js : propagateToInferiors start to start",new Date(endOfficial));
        todoOk = link.to.moveTo(end, false,true,endOfficial,1); //this is not the right date but  checks start
      }
      //alert("propagation completed");

      if (!todoOk)
        break;
    }
  }
  return todoOk;
};


//<%---------- COMPUTE START BY SUPERIORS ---------------------- --%>
/*Task.prototype.computeStartBySuperiors = function (proposedStart) {
  //if depends -> start is set to max end + lag of superior
  console.log("Actual func proposedStart:-->", proposedStart);
  var supEnd=proposedStart;
  var sups = this.getSuperiors();
  if (sups && sups.length > 0) {
    supEnd=0;
    for (var i = 0; i < sups.length; i++) {
      var link = sups[i];
      console.log("link.lag" ,link.lag);
      supEnd = Math.max(supEnd, incrementDateByUnits(new Date(link.from.end), link.lag));
    }
    supEnd+=1;
  }
  return computeStart(supEnd);
};*/
Task.prototype.computeStartBySuperiors = function (proposedStart, dep_type = 3) {
  //console.log("ganttTask computeStartBySuperiors : proposedStart:-->", new Date(proposedStart));
  
  //if depends -> start is set to max end + lag of superior
  //console.log("this:-->",this);
  var supEnd=proposedStart;
  var sups = this.getSuperiors();
  //console.log("ganttTask computeStartBySuperiors : sups:-->",sups);
  if (sups && sups.length > 0) {
    supEnd=0;
    for (var i = 0; i < sups.length; i++) {
      var link = sups[i];
      console.log("ganttTask computeStartBySuperiors : ",link);
      if(link.to.dependency!=undefined)
      {
        let dependency=link.to.dependency.split(',');
        let dep;
        for (let index = 0; index < dependency.length; index++) {
          const element = dependency[index];
          dep=element.split('::');
          //console.log(":::::");
        }
        //console.log("ganttTask computeStartBySuperiors : dep[1]-->"+dep[1]);
        //console.log("ganttTask computeStartBySuperiors : link.lag::--->",link.lag);
        //console.log(link);
        if(dep[1]==1){
          supEnd = Math.max(supEnd, incrementDateByUnits(new Date(link.from.start), link.lag));
        }else if(dep[1]==2){
          console.log("ganttTask computeStartBySuperiors: end to end : link.from.end",link.from.end);
          supEnd = Math.max(supEnd, incrementDateByUnits(new Date(link.from.end), 0));
        }
        else if(dep[1]==3){
          //console.log("ganttTask computeStartBySuperiors: start to end : link.from.end",link.from.end);
          supEnd = Math.max(supEnd, incrementDateByUnits(new Date(link.from.end), link.lag));
        }
      }else{
        console.log("ganttTask : computeStartBySuperiors deps else");

        supEnd = Math.max(supEnd, incrementDateByUnits(new Date(link.from.end), link.lag));
      }
    }
    supEnd+=1;
  }
  console.log("SupEnd:-->",new Date(supEnd));
  return computeStart(supEnd);
};

Task.prototype.computeEndBySuperiors = function (proposedEnd) {
  //console.log("ganttTask computeEndBySuperiors : proposedEnd:-->", new Date(proposedEnd));
  
  //if depends -> start is set to max end + lag of superior
  //console.log("this:-->",this);
  var supEnd=proposedEnd;
  var sups = this.getSuperiors();
  //console.log("ganttTask computeStartBySuperiors : sups:-->",sups);
  if (sups && sups.length > 0) {
    supEnd=0;
    for (var i = 0; i < sups.length; i++) {
      var link = sups[i];
      //console.log("ganttTask computeEndBySuperiors : ",link);
      if(link.to.dependency!=undefined)
      {
        let dependency=link.to.dependency.split(',');
        let dep;
        for (let index = 0; index < dependency.length; index++) {
          const element = dependency[index];
          dep=element.split('::');
          //console.log(":::::");
        }
        //console.log("ganttTask computeEndBySuperiors : dep[1]-->"+dep[1]);
        //console.log("ganttTask computeEndBySuperiors : link.lag::--->",link.lag);
        // console.log(link);
        
        //console.log("ganttTask computeEndBySuperiors: end to end : link.from.end",link.from.end);
        supEnd = Math.max(supEnd, decrementDateByUnits(new Date(link.from.end), link.lag));
      }else{
        console.log("ganttTask : computeEndBySuperiors deps else");

        supEnd = Math.max(supEnd, incrementDateByUnits(new Date(link.from.end), link.lag));
      }
    }
    //supEnd+=1;
  }
  console.log("SupEnd:-->",new Date(supEnd));
  return computeEnd(supEnd);
};

function updateTree(task) {
  console.log("ganttTask : updateTree this task name, start, end: -->", new Date(task.start).getTime()," : ",new Date(task.end).getTime(), task.name);
  var error;

  //try to enlarge parent
  var p = task.getParent();
  console.log("parent task:--",p);
  //no parent:exit
  if (!p)
    return true;

  console.log("ganttTask: updateTree:-->",p);
  console.log("ganttTask : updateTree parents name, start, end: -->", new Date(p.start).getTime()," : ",new Date(p.end).getTime(), task.name);

  var newStart;
  var newEnd;

  //id shrink start and end are computed on children boundaries
  if (task.master.shrinkParent) {
    console.log("ganttTask updateTree task.master.shrinkParent:-->",task.master.shrinkParent);
    var chPeriod= p.getChildrenBoudaries();
    console.log("ganttTask updateTree chPeriod:-->",new Date(chPeriod.start).getDate()," : ",new Date(chPeriod.end).getDate());
    newStart = chPeriod.start;
    newEnd = chPeriod.end;
  } else {
    console.log("ganttTask updateTree not task.master.shrinkParent:-->",task.master.shrinkParent);
    newStart = p.start;
    newEnd = p.end;

    if (p.start > task.start) {
      console.log("ganttTask updateTree : p.start > task.start:-->",p.start, task.start);
      newStart = task.start;
    }
    if (p.end < task.end) {
      console.log("ganttTask updateTree : p.end < task.end:-->",p.end, task.end);
      newEnd = task.end;
    }
  }

  console.log("ganttTask updateTree out of if: p.name, task.name, task.depends, p.start!=newStart:-->", task.depends,"--:--", new Date(p.start).getDate()," : ",new Date(newStart).getDate(), p.name, task.name);
  if (p.start!=newStart) {
    console.log("ganttTask updateTree : p.name task.name p.start!=newStart:-->",p.name, task.name ,new Date(p.start),new Date(newStart));
    if (p.startIsMilestone) {
      task.master.setErrorOnTransaction("\"" + p.name, task.name + "\"\n" + GanttMaster.messages["START_IS_MILESTONE"], task);
      return false;
    } else if (p.depends) {
      console.log(p.depends," ganttTask updateTree : parents name, p.depends:-->", p.name, p.depends);
      task.master.setErrorOnTransaction("\" update tree simple: " + p.name+" : "+ task.name+ "\"\n" + GanttMaster.messages["TASK_HAS_CONSTRAINTS"], task);
      return false;
    }
  }
  if (p.end!=newEnd) {
    console.log("ganttTask updateTree : p.end!=newEnd:-->",p.end, newEnd);
    if (p.endIsMilestone) {
      task.master.setErrorOnTransaction("\"" + p.name + "\"\n" + GanttMaster.messages["END_IS_MILESTONE"], task);
      return false;
    }
  }


  //propagate updates if needed
  if (newStart != p.start || newEnd != p.end) {
    console.log("newStart != p.start || newEnd != p.end:-->",newStart,p.start, newEnd, p.end);
    //can write?
    if (!p.canWrite) {
      task.master.setErrorOnTransaction(GanttMaster.messages["CANNOT_WRITE"] + "\n" + p.name, task);
      return false;
    }

    //has external deps ?
    if (p.hasExternalDep) {
      task.master.setErrorOnTransaction(GanttMaster.messages["TASK_HAS_EXTERNAL_DEPS"] + "\n\"" + p.name + "\"", task);
      return false;
    }
    console.log("ganttTask : updateTree");
    return p.setPeriod(newStart, newEnd);
  }

  return true;
}

function updateTree2(task) {
  console.log("ganttTask : updateTree2 name start, end: -->",task.name, new Date(task.start),new Date(task.end));
  var error;

  //try to enlarge parent
  var p = task.getParent();
  console.log("ganttTask: updateTree2:-->",p);
  //no parent:exit
  if (!p)
    return true;

  var newStart;
  var newEnd;

  //id shrink start and end are computed on children boundaries
  if (task.master.shrinkParent) {
    console.log("ganttTask updateTree2 task.master.shrinkParent:-->",task.master.shrinkParent);
    var chPeriod= p.getChildrenBoudaries();
    console.log("ppppp",p);
    console.log("ganttTask updateTree2 chPeriod:-->",chPeriod.name, new Date(chPeriod.start),new Date(chPeriod.end));
    newStart = chPeriod.start;
    newEnd = chPeriod.end;
  } else {
    console.log("ganttTask updateTree2 not task.master.shrinkParent:-->",task.master.shrinkParent);
    newStart = p.start;
    newEnd = p.end;

    if (p.start > task.start) {
      console.log("ganttTask updateTree2 : p.start > task.start:-->",p.start, task.start);
      newStart = task.start;
    }
    if (p.end < task.end) {
      console.log("ganttTask updateTree2 : p.end < task.end:-->",p.end, task.end);
      newEnd = task.end;
    }
  }
  console.log("ganttTask updateTree2 out of if: p.name, task.name, task.depends, task.start, p.start!=newStart:-->",p.name, task.name, task.depends, "Current task",new Date(task.start).getDate()," : ",new Date(task.start).getMonth()," :::: parent task", new Date(p.start).getDate()," : ",new Date(p.start).getMonth()," :::: new task ",new Date(newStart).getDate()," : ",new Date(newStart).getMonth());
  console.log("ganttTask updateTree2 out of if :-->end<--: : p.name, task.name, task.depends, task.end, p.start!=newEnd:-->",p.name, task.name, task.depends, "Current task",new Date(task.end).getDate()," : ",new Date(task.end).getMonth()," :::: parent task", new Date(p.end).getDate()," : ",new Date(p.end).getMonth()," :::: new task ",new Date(newEnd).getDate()," : ",new Date(newEnd).getMonth());
  if (p.start!=newStart) {
    console.log("ganttTask updateTree2 : p.end!=newEnd:-->",p.end, newEnd);
    if (p.endIsMilestone) {
      task.master.setErrorOnTransaction("\"" + p.name + "\"\n" + GanttMaster.messages["END_IS_MILESTONE"], task);
      return false;
    }
    
  }
  if (p.end!=newEnd) {
    console.log("ganttTask updateTree2 : p.start!=newStart:-->",p.start, newStart);
    if (p.startIsMilestone) {
      task.master.setErrorOnTransaction("\"" + p.name + "\"\n" + GanttMaster.messages["START_IS_MILESTONE"], task);
      return false;
    } else if (p.depends) {
      console.log("ganttTask updateTree2 : p.depends:-->",p.depends);
      //task.master.setErrorOnTransaction("\"" + p.name + "\"\n" + GanttMaster.messages["TASK_HAS_CONSTRAINTS"], task);
      return true;
    }  
  }


  //propagate updates if needed
  if (newStart != p.start || newEnd != p.end) {
    console.log("newStart != p.start || newEnd != p.end:-->",newStart,p.start, newEnd, p.end);
    //can write?
    if (!p.canWrite) {
      task.master.setErrorOnTransaction(GanttMaster.messages["CANNOT_WRITE"] + "\n" + p.name, task);
      return false;
    }

    //has external deps ?
    if (p.hasExternalDep) {
      task.master.setErrorOnTransaction(GanttMaster.messages["TASK_HAS_EXTERNAL_DEPS"] + "\n\"" + p.name + "\"", task);
      return false;
    }
    console.log("ganttTask : updateTree2 rask.name:-->",p.name, new Date(newStart),new Date(newEnd));
    return p.setPeriod2(newStart, newEnd);
  }
  return true;
}

function updateTree3(task) {
  console.log("ganttTask : updateTree3 start, end: -->",new Date(task.start),new Date(task.end));
  var error;

  //try to enlarge parent
  var p = task.getParent();
  console.log("ganttTask: updateTree3:-->",p);
  //no parent:exit
  if (!p)
    return true;

  var newStart;
  var newEnd;

  //id shrink start and end are computed on children boundaries
  if (task.master.shrinkParent) {
    console.log("ganttTask updateTree3 task.master.shrinkParent:-->",task.master.shrinkParent);
    var chPeriod= p.getChildrenBoudaries();
    console.log("ganttTask updateTree3 chPeriod:-->",chPeriod);
    newStart = chPeriod.start;
    newEnd = chPeriod.end;
  } else {
    console.log("ganttTask updateTree3 not task.master.shrinkParent:-->",task.master.shrinkParent);
    newStart = p.start;
    newEnd = p.end;

    if (p.start > task.start) {
      console.log("ganttTask updateTree3 : p.start > task.start:-->",p.start, task.start);
      newStart = task.start;
    }
    if (p.end < task.end) {
      console.log("ganttTask updateTree3 : p.end < task.end:-->",p.end, task.end);
      newEnd = task.end;
    }
  }

  if (p.start!=newStart) {
    console.log("ganttTask updateTree3 : p.start!=newStart:-->",p.start, newStart);
    if (p.startIsMilestone) {
      task.master.setErrorOnTransaction("\"" + p.name + "\"\n" + GanttMaster.messages["START_IS_MILESTONE"], task);
      return false;
    } else if (p.depends) {
      console.log("ganttTask updateTree3 : p.depends:-->",p.depends);
      task.master.setErrorOnTransaction("\" tree3" + p.name+" : "+ task.name + "\"\n" + GanttMaster.messages["TASK_HAS_CONSTRAINTS"], task);
      return false;
    }
  }
  if (p.end!=newEnd) {
    console.log("ganttTask updateTree3 : p.end!=newEnd:-->",p.end, newEnd);
    if (p.endIsMilestone) {
      task.master.setErrorOnTransaction("\"" + p.name + "\"\n" + GanttMaster.messages["END_IS_MILESTONE"], task);
      return false;
    }
  }


  //propagate updates if needed
  if (newStart != p.start || newEnd != p.end) {
    console.log("newStart != p.start || newEnd != p.end:-->",newStart,p.start, newEnd, p.end);
    //can write?
    if (!p.canWrite) {
      task.master.setErrorOnTransaction(GanttMaster.messages["CANNOT_WRITE"] + "\n" + p.name, task);
      return false;
    }

    //has external deps ?
    if (p.hasExternalDep) {
      console.log("external deps found");
      task.master.setErrorOnTransaction(GanttMaster.messages["TASK_HAS_EXTERNAL_DEPS"] + "\n\"" + p.name + "\"", task);
      return false;
    }
    console.log("ganttTask : updateTree2:-->",new Date(newStart),new Date(newEnd));
    return p.setPeriod(newStart, newEnd);
  }
  return true;
}

Task.prototype.getChildrenBoudaries = function () {
  var newStart = Infinity;
  var newEnd = -Infinity;
  var children = this.getChildren();
  for (var i = 0; i < children.length; i++) {
    var ch = children[i];
    newStart = Math.min(newStart, ch.start);
    newEnd = Math.max(newEnd, ch.end);
  }
  return({start:newStart,end:newEnd})
}

//<%---------- CHANGE STATUS ---------------------- --%>
Task.prototype.changeStatus = function (newStatus,forceStatusCheck) {
  //console.debug("changeStatus: "+this.name+" from "+this.status+" -> "+newStatus);

  var cone = this.getDescendant();

  function propagateStatus(task, newStatus, manuallyChanged, propagateFromParent, propagateFromChildren) {
    //console.debug("propagateStatus",task.name, task.status,newStatus, manuallyChanged, propagateFromParent, propagateFromChildren);
    var oldStatus = task.status;

    //no changes exit
    if (newStatus == oldStatus && !forceStatusCheck) {
      return true;
    }

    var todoOk = true;
    task.status = newStatus;


    //xxxx -> STATUS_DONE            may activate dependent tasks, both suspended and undefined. Will set to done all children.
    //STATUS_FAILED -> STATUS_DONE   do nothing if not forced by hand
    if (newStatus == "STATUS_DONE") {

      // cannot close task if open issues
      if (task.master.permissions.cannotCloseTaskIfIssueOpen && task.openIssues > 0) {
        task.master.setErrorOnTransaction(GanttMaster.messages["CANNOT_CLOSE_TASK_IF_OPEN_ISSUE"] + " \"" + task.name + "\"");
        return false;
      }


      if ((manuallyChanged || oldStatus != "STATUS_FAILED")) { //cannot set failed task as closed for cascade - only if changed manually

        //can be closed only if superiors are already done
        var sups = task.getSuperiors();
        for (var i = 0; i < sups.length; i++) {
          if (sups[i].from.status != "STATUS_DONE" && cone.indexOf(sups[i].from)<0) { // è un errore se un predecessore è non chiuso ed è fuori dal cono
            if (manuallyChanged || propagateFromParent)  //genere un errore bloccante se è cambiato a mano o se il cambiamento arriva dal parent ed ho una dipendenza fuori dal cono (altrimenti avrei un attivo figlio di un chiuso
              task.master.setErrorOnTransaction(GanttMaster.messages["GANTT_ERROR_DEPENDS_ON_OPEN_TASK"] + "\n\"" + sups[i].from.name + "\" -> \"" + task.name + "\"");
            todoOk = false;
            break;
          }
        }

        if (todoOk) {
          // set progress to 100% if needed by settings
          if (task.master.set100OnClose && !task.progressByWorklog ){
            task.progress=100;
          }

          //set children as done
          propagateStatusToChildren(task,newStatus,false);

          //set inferiors as active
          propagateStatusToInferiors( task.getInferiors(), "STATUS_ACTIVE");
        }
      } else { // una propagazione tenta di chiudere un task fallito
        todoOk = false;
      }


      //  STATUS_UNDEFINED -> STATUS_ACTIVE       all children become active, if they have no dependencies.
      //  STATUS_SUSPENDED -> STATUS_ACTIVE       sets to active all children and their descendants that have no inhibiting dependencies.
      //  STATUS_WAITING -> STATUS_ACTIVE         sets to active all children and their descendants that have no inhibiting dependencies.
      //  STATUS_DONE -> STATUS_ACTIVE            all those that have dependencies must be set to suspended.
      //  STATUS_FAILED -> STATUS_ACTIVE          nothing happens: child statuses must be reset by hand.
    } else if (newStatus == "STATUS_ACTIVE") {

      if (manuallyChanged || (oldStatus != "STATUS_FAILED" && oldStatus != "STATUS_SUSPENDED")) { //cannot set failed or suspended task as active for cascade - only if changed manually

        //can be active only if superiors are already done, not only on this task, but also on ancestors superiors
        var sups = task.getSuperiors();

        for (var i = 0; i < sups.length; i++) {
          if (sups[i].from.status != "STATUS_DONE") {
            if (manuallyChanged || propagateFromChildren)
              task.master.setErrorOnTransaction(GanttMaster.messages["GANTT_ERROR_DEPENDS_ON_OPEN_TASK"] + "\n\"" + sups[i].from.name + "\" -> \"" + task.name + "\"");
            todoOk = false;
            break;
          }
        }

        // check if parent is already active
        if (todoOk) {
          var par = task.getParent();
          if (par && par.status != "STATUS_ACTIVE") {
            // todoOk = propagateStatus(par, "STATUS_ACTIVE", false, false, true); //todo abbiamo deciso di non far propagare lo status verso l'alto
            todoOk = false;
          }
        }


        if (todoOk) {
          if (oldStatus == "STATUS_UNDEFINED" || oldStatus == "STATUS_SUSPENDED" || oldStatus == "STATUS_WAITING" ) {
            //set children as active
            propagateStatusToChildren(task,newStatus,true);
          }

          //set inferiors as suspended
          //propagateStatusToInferiors( task.getInferiors(), "STATUS_SUSPENDED");
          propagateStatusToInferiors( task.getInferiors(), "STATUS_WAITING");
        }
      } else {
        todoOk = false;
      }

      // xxxx -> STATUS_WAITING       all active children and their active descendants become waiting. when not failed or forced
    } else if (newStatus == "STATUS_WAITING" ) {
      if (manuallyChanged || oldStatus != "STATUS_FAILED") { //cannot set failed task as waiting for cascade - only if changed manually

        //check if parent if not active
        var par = task.getParent();
        if (par && (par.status != "STATUS_ACTIVE" && par.status != "STATUS_SUSPENDED" && par.status != "STATUS_WAITING")) {
          todoOk = false;
        }


        if (todoOk) {
          //set children as STATUS_WAITING
          propagateStatusToChildren(task, "STATUS_WAITING", true);

          //set inferiors as STATUS_WAITING
          propagateStatusToInferiors( task.getInferiors(), "STATUS_WAITING");
        }
      } else {
        todoOk = false;
      }

      // xxxx -> STATUS_SUSPENDED       all active children and their active descendants become suspended. when not failed or forced
    } else if (newStatus == "STATUS_SUSPENDED" ) {
      if (manuallyChanged || oldStatus != "STATUS_FAILED") { //cannot set failed task as closed for cascade - only if changed manually

        //check if parent if not active
        var par = task.getParent();
        if (par && (par.status != "STATUS_ACTIVE" && par.status != "STATUS_SUSPENDED" && par.status != "STATUS_WAITING")) {
          todoOk = false;
        }


        if (todoOk) {
          //set children as STATUS_SUSPENDED
          propagateStatusToChildren(task, "STATUS_SUSPENDED", true);

          //set inferiors as STATUS_SUSPENDED
          propagateStatusToInferiors( task.getInferiors(), "STATUS_SUSPENDED");
        }
      } else {
        todoOk = false;
      }

      // xxxx -> STATUS_FAILED children and dependent failed
      // xxxx -> STATUS_UNDEFINED  children and dependant become undefined.
    } else if (newStatus == "STATUS_FAILED" || newStatus == "STATUS_UNDEFINED") {

      //set children as failed or undefined
      propagateStatusToChildren(task,newStatus,false);

      //set inferiors as failed
      propagateStatusToInferiors( task.getInferiors(), newStatus);
    }
    if (!todoOk) {
      task.status = oldStatus;
      //console.debug("status rolled back: "+task.name + " to " + oldStatus);
    }

    return todoOk;
  }

  /**
   * A helper method to traverse an array of 'inferior' tasks
   * and signal a status change.
   */
   function propagateStatusToInferiors( infs, status) {
     for (var i = 0; i < infs.length; i++) {
       propagateStatus(infs[i].to, status, false, false, false);
     }
   }

  /**
   * A helper method to loop children and propagate new status
   */
   function propagateStatusToChildren(task, newStatus, skipClosedTasks) {
     var chds = task.getChildren();
     for (var i = 0; i < chds.length; i++)
       if (!(skipClosedTasks && chds[i].status == "STATUS_DONE") )
         propagateStatus(chds[i], newStatus, false, true, false);
     }


     var manuallyChanged=true;

     var oldStatus = this.status;
  //first call
  if (propagateStatus(this, newStatus, manuallyChanged, false, false)) {
    return true;
  } else {
    this.status = oldStatus;
    return false;
  }
};

Task.prototype.synchronizeStatus = function () {
  //console.debug("synchronizeStatus",this.name);
  var oldS = this.status;
  this.status = this.getParent()?this.getParent().status:"STATUS_UNDEFINED"; // di default si invalida lo stato mettendo quello del padre, in modo che inde/outd siano consistenti
  return this.changeStatus(oldS,true);
};

Task.prototype.isLocallyBlockedByDependencies = function () {
  var sups = this.getSuperiors();
  var blocked = false;
  for (var i = 0; i < sups.length; i++) {
    if (sups[i].from.status != "STATUS_DONE") {
      blocked = true;
      break;
    }
  }
  return blocked;
};

//<%---------- TASK STRUCTURE ---------------------- --%>
Task.prototype.getRow = function () {
  ret = -1;
  if (this.master)
    ret = this.master.tasks.indexOf(this);
  return ret;
};


Task.prototype.getParents = function () {
  var ret;
  if (this.master) {
    var topLevel = this.level;
    var pos = this.getRow();
    ret = [];
    for (var i = pos; i >= 0; i--) {
      var par = this.master.tasks[i];
      if (topLevel > par.level) {
        topLevel = par.level;
        ret.push(par);
      }
    }
  }
  return ret;
};


Task.prototype.getParent = function () {
  var ret;
  if (this.master) {
    for (var i = this.getRow(); i >= 0; i--) {
      var par = this.master.tasks[i];
      if (this.level > par.level) {
        ret = par;
        break;
      }
    }
  }
  return ret;
};


Task.prototype.isParent = function () {
  var ret = false;
  if (this.master) {
    var pos = this.getRow();
    if (pos < this.master.tasks.length - 1)
      ret = this.master.tasks[pos + 1].level > this.level;
  }
  return ret;
};


Task.prototype.getChildren = function () {
  var ret = [];
  if (this.master) {
    var pos = this.getRow();
    for (var i = pos + 1; i < this.master.tasks.length; i++) {
      var ch = this.master.tasks[i];
      if (ch.level == this.level + 1)
        ret.push(ch);
      else if (ch.level <= this.level) // exit loop if parent or brother
        break;
    }
  }
  return ret;
};


Task.prototype.getDescendant = function () {
  var ret = [];
  if (this.master) {
    var pos = this.getRow();
    for (var i = pos + 1; i < this.master.tasks.length; i++) {
      var ch = this.master.tasks[i];
      if (ch.level > this.level)
        ret.push(ch);
      else
        break;
    }
  }
  return ret;
};


Task.prototype.getSuperiors = function () {
  //console.log("ganttTask getsupirior");
  //console.log(this.master);
  var ret = [];
  var task = this;
  if (this.master) {    
    //console.log("This msater = true");
    //console.log("this.master.links:-->",this.master.links);
    ret = this.master.links.filter(function (link) {
      //console.log("ganttTask getSuperiors: this.master",this.master);
      //console.log("ganttTask getSuperiors: ret",ret);
      return link.to == task;
    });
  }
  return ret;
};

Task.prototype.getSuperiorTasks = function () {
  var ret = [];
  var sups = this.getSuperiors();
  for (var i = 0; i < sups.length; i++)
    ret.push(sups[i].from);
  return ret;
};


Task.prototype.getInferiors = function () {
  var ret = [];
  var task = this;
  if (this.master) {
    ret = this.master.links.filter(function (link) {
      return link.from == task;
    });
  }
  return ret;
};

Task.prototype.getInferiorTasks = function () {
  var ret = [];
  var infs = this.getInferiors();
  for (var i = 0; i < infs.length; i++)
    ret.push(infs[i].to);
  return ret;
};

Task.prototype.deleteTask = function () {
  //console.debug("deleteTask",this.name,this.master.deletedTaskIds)
  //if is the current one remove it
  if (this.master.currentTask && this.master.currentTask.id==this.id)
    delete this.master.currentTask;

  //delete both dom elements if exists
  if (this.rowElement)
    this.rowElement.remove();
  if (this.ganttElement)
    this.ganttElement.remove();

  //remove children
  var chd = this.getChildren();
  for (var i = 0; i < chd.length; i++) {
    //add removed child in list
    chd[i].deleteTask();
  }

  if (!this.isNew())
    this.master.deletedTaskIds.push(this.id);


  //remove from in-memory collection
  this.master.tasks.splice(this.getRow(), 1);

  //remove from links
  var task = this;
  this.master.links = this.master.links.filter(function (link) {
    return link.from != task && link.to != task;
  });
};


Task.prototype.isNew = function () {
  return (this.id + "").indexOf("tmp_") == 0;
};

Task.prototype.isDependent = function (t) {
  //console.debug("isDependent",this.name, t.name)
  var task = this;
  var dep = this.master.links.filter(function (link) {
    return link.from == task;
  });

  // is t a direct dependency?
  for (var i = 0; i < dep.length; i++) {
    if (dep[i].to == t)
      return true;
  }
  // is t an indirect dependency
  for (var i = 0; i < dep.length; i++) {
    if (dep[i].to.isDependent(t)) {
      return true;
    }
  }
  return false;
};

Task.prototype.setLatest = function (maxCost) {
  this.latestStart = maxCost - this.criticalCost;
  this.latestFinish = this.latestStart + this.duration;
};


//<%------------------------------------------  INDENT/OUTDENT --------------------------------%>
Task.prototype.indent = function () {
  console.debug("ganttTask indent:", this);
  //a row above must exist
  var row = this.getRow();

  //no row no party
  if (row <= 0)
    return false;

  var ret = false;
  var taskAbove = this.master.tasks[row - 1];
  var newLev = this.level + 1;
  if (newLev <= taskAbove.level + 1) {
    ret = true;

    //trick to get parents after indent
    this.level++;
    var futureParents = this.getParents();
    this.level--;

    var oldLevel = this.level;
    for (var i = row; i < this.master.tasks.length; i++) {
      var desc = this.master.tasks[i];
      if (desc.level > oldLevel || desc == this) {
        desc.level++;
        //remove links from this and descendant to my parents
        this.master.links = this.master.links.filter(function (link) {
          var linkToParent = false;
          if (link.to == desc)
            linkToParent = futureParents.indexOf(link.from) >= 0;
          else if (link.from == desc)
            linkToParent = futureParents.indexOf(link.to) >= 0;
          return !linkToParent;
        });
        //remove links from this and descendants to predecessors of parents in order to avoid loop
        var predecessorsOfFutureParents=[];
        for (var j=0;j<futureParents.length;j++)
          predecessorsOfFutureParents=predecessorsOfFutureParents.concat(futureParents[j].getSuperiorTasks());

        this.master.links = this.master.links.filter(function (link) {
          var linkToParent = false;
          if (link.from == desc)
            linkToParent = predecessorsOfFutureParents.indexOf(link.to) >= 0;
          return !linkToParent;
        });


      } else
      break;
    }

    var parent = this.getParent();
    // set start date to parent' start if no deps
    if (parent && !this.depends) {
      console.log("ganttTask indent: parent",parent);
      var new_end = computeEndByDuration(parent.start, this.duration);
      this.master.changeTaskDates(this, parent.start, new_end);
    }


    //recompute depends string
    this.master.updateDependsStrings();
    //enlarge parent using a fake set period
    updateTree(this);
    //force status check starting from parent
    this.getParent().synchronizeStatus();
  }
  return ret;
};


Task.prototype.outdent = function () {
  //console.debug("outdent", this);

  //a level must be >1 -> cannot escape from root
  if (this.level <= 1)
    return false;

  var ret = false;
  var oldLevel = this.level;

  ret = true;
  var row = this.getRow();
  for (var i = row; i < this.master.tasks.length; i++) {
    var desc = this.master.tasks[i];
    if (desc.level > oldLevel || desc == this) {
      desc.level--;
    } else
    break;
  }

  var task = this;
  var chds = this.getChildren();
  //remove links from me to my new children
  this.master.links = this.master.links.filter(function (link) {
    var linkExist = (link.to == task && chds.indexOf(link.from) >= 0 || link.from == task && chds.indexOf(link.to) >= 0);
    return !linkExist;
  });


  //enlarge me if inherited children are larger
  for (var i = 0; i < chds.length; i++) {
    //remove links from me to my new children
    chds[i].setPeriod(chds[i].start + 1, chds[i].end + 1);
  }

  //recompute depends string
  this.master.updateDependsStrings();

  //enlarge parent using a fake set period
  this.setPeriod(this.start + 1, this.end + 1);

  //force status check
  this.synchronizeStatus();
  return ret;
};


//<%------------------------------------------  MOVE UP / MOVE DOWN --------------------------------%>
Task.prototype.moveUp = function () {
  //console.debug("moveUp", this);
  var ret = false;

  //a row above must exist
  var row = this.getRow();

  //no row no party
  if (row <= 0)
    return false;

  //find new row
  var newRow;
  for (newRow = row - 1; newRow >= 0; newRow--) {
    if (this.master.tasks[newRow].level <= this.level)
      break;
  }

  //is a parent or a brother
  if (this.master.tasks[newRow].level == this.level) {
    ret = true;
    //compute descendant
    var descNumber = 0;
    for (var i = row + 1; i < this.master.tasks.length; i++) {
      var desc = this.master.tasks[i];
      if (desc.level > this.level) {
        descNumber++;
      } else {
        break;
      }
    }
    //move in memory
    var blockToMove = this.master.tasks.splice(row, descNumber + 1);
    var top = this.master.tasks.splice(0, newRow);
    this.master.tasks = [].concat(top, blockToMove, this.master.tasks);
    //move on dom
    var rows = this.master.editor.element.find("tr[taskid]");
    var domBlockToMove = rows.slice(row, row + descNumber + 1);
    rows.eq(newRow).before(domBlockToMove);

    //recompute depends string
    this.master.updateDependsStrings();
  } else {
    this.master.setErrorOnTransaction(GanttMaster.messages["TASK_MOVE_INCONSISTENT_LEVEL"], this);
    ret = false;
  }
  return ret;
};


Task.prototype.moveDown = function () {
  //console.debug("moveDown", this);

  //a row below must exist, and cannot move root task
  var row = this.getRow();
  if (row >= this.master.tasks.length - 1 || row == 0)
    return false;

  var ret = false;

  //find nearest brother
  var newRow;
  for (newRow = row + 1; newRow < this.master.tasks.length; newRow++) {
    if (this.master.tasks[newRow].level <= this.level)
      break;
  }

  //is brother
  if (this.master.tasks[newRow] && this.master.tasks[newRow].level == this.level) {
    ret = true;
    //find last desc
    for (newRow = newRow + 1; newRow < this.master.tasks.length; newRow++) {
      if (this.master.tasks[newRow].level <= this.level)
        break;
    }

    //compute descendant
    var descNumber = 0;
    for (var i = row + 1; i < this.master.tasks.length; i++) {
      var desc = this.master.tasks[i];
      if (desc.level > this.level) {
        descNumber++;
      } else {
        break;
      }
    }

    //move in memory
    var blockToMove = this.master.tasks.splice(row, descNumber + 1);
    var top = this.master.tasks.splice(0, newRow - descNumber - 1);
    this.master.tasks = [].concat(top, blockToMove, this.master.tasks);


    //move on dom
    var rows = this.master.editor.element.find("tr[taskid]");
    var aft = rows.eq(newRow - 1);
    var domBlockToMove = rows.slice(row, row + descNumber + 1);
    aft.after(domBlockToMove);

    //recompute depends string
    this.master.updateDependsStrings();
  }

  return ret;
};


Task.prototype.canStatusBeChangedTo=function(newStatus) {
  //lo stato corrente è sempre ok
  if (newStatus==this.status)
    return true;

  var parent=this.getParent();

  //---------------------------------------------------------------------- STATUS_DONE ----------------------------------------------------------------
  // un task può essere STATUS_DONE se di root
  // se il suo padre non è fallito o undefined
  // se non ha previouses aperti
  if ("STATUS_DONE"==newStatus) {
    if (!parent )
      return true;

    if ("STATUS_FAILED"==parent.status || "STATUS_UNDEFINED"==parent.status)
      return false;

    var sups=this.getSuperiorTasks();
    for (var i=0;i<sups.length;i++) {
      if ("STATUS_DONE"!=sups[i].status) { // è un errore se un predecessore non è chiuso
        return false;
      }
    }
    return true;


    //---------------------------------------------------------------------- STATUS_ACTIVE ----------------------------------------------------------------
    //un task può essere STATUS_ACTIVE se l'eventuale padre è active e se tutti i predecessori sono in STATUS_DONE
  } else if ("STATUS_ACTIVE"==newStatus) {
    if (!parent )
      return true;

    if (!"STATUS_ACTIVE"==parent.status)
      return false;

    var sups=this.getSuperiorTasks();
    for (var i=0;i<sups.length;i++) {
      if ("STATUS_DONE"!=sups[i].status) { // è un errore se un predecessore non è chiuso
        return false;
      }
    }
    return true;


    //---------------------------------------------------------------------- STATUS_WAITING ----------------------------------------------------------------
    //un task può essere STATUS_WAITING solo se ha il padre o un predecessore STATUS_WAITING || un predecessore active STATUS_ACTIVE
  } else if ("STATUS_WAITING"==newStatus) {
    //un task può essere STATUS_WAITING solo se ha il padre
    if (!parent )
      return false;

    if ("STATUS_FAILED"==parent.status || "STATUS_UNDEFINED"==parent.status)
      return false;


    if ("STATUS_WAITING"==parent.status)
      return true;

    var sups=this.getSuperiorTasks();
    for (var i=0;i<sups.length;i++) {
      if ("STATUS_WAITING"==sups[i].status || "STATUS_ACTIVE"==sups[i].status) {
        return true;
      }
    }
    return false;


    //---------------------------------------------------------------------- STATUS_SUSPENDED ----------------------------------------------------------------
    //un task può essere is STATUS_SUSPENDED (a mano) se di root
    // se il parent è STATUS_ACTIVE o STATUS_SUSPENDED e se non
  } else if ("STATUS_SUSPENDED"==newStatus) {
    if (!parent )
      return true;

    if ("STATUS_UNDEFINED"==parent.status || "STATUS_FAILED"==parent.status)
      return false;

    return true;

    //---------------------------------------------------------------------- STATUS_FAILED ----------------------------------------------------------------
  } else if ("STATUS_FAILED"==newStatus) {
    //può essere in STATUS_FAILED sempre
    return true;

    //---------------------------------------------------------------------- STATUS_UNDEFINED ----------------------------------------------------------------
  } else if ("STATUS_UNDEFINED"==newStatus) {
    //può essere in STATUS_UNDEFINED sempre
    return true;
  }

  return false;
};


//<%------------------------------------------------------------------------  LINKS OBJECT ---------------------------------------------------------------%>
function Link(taskFrom, taskTo, lagInWorkingDays) {
  this.from = taskFrom;
  this.to = taskTo;
  this.lag = lagInWorkingDays;
}


//<%------------------------------------------------------------------------  ASSIGNMENT ---------------------------------------------------------------%>
function Assignment(id, resourceId, roleId, effort) {
  this.id = id;
  this.resourceId = resourceId;
  this.roleId = roleId;
  this.effort = effort;
}


//<%------------------------------------------------------------------------  RESOURCE ---------------------------------------------------------------%>
function Resource(id, name) {
  this.id = id;
  this.name = name;
}


//<%------------------------------------------------------------------------  ROLE ---------------------------------------------------------------%>
function Role(id, name) {
  this.id = id;
  this.name = name;
}




