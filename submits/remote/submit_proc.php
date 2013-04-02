<?php

switch($step){
    case AUTHORING :{
        $select = " wwwroot LIKE '{$CFG->mainhostprefix}%' ";
        $mainhost = $DB->get_record_select('mnet_host', $select);
        $form->command = get_string('continue');
        $form->courseurl = $mainhost->wwwroot."/local/course/courseview.php?idnum=<%%NUMERIBASEID%%>";
        $form->editors = array();
        $form->authors = array();
        $form->contributors = array();
        $form->datecreated = '';
        $form->lastmodified = '';
        $form->cost = 0;
        $form->userestriction = 0;
        $form->termsofuse = '';
        $form->summary = '';
        $form->collection = '';
        $form->subcollection = '';
        $form->format = '';
        $form->dimensions = '';
        $form->technicalrequirements = '';
        $form->extrarequirements = '';
        $form->step = $step + 1;
    }
    break;
    case TEACHING :{
        // from form 1
        $form = data_submitted();
        $oldform = get_object_vars(clone($form));
        unset($oldform['step']);
        $form->command = get_string('finish', 'block_publishflow');

        // new fields
        $form->mainlevel = '';
        $form->sublevels = array();
        $form->domain = '';
        $form->targets = array();
        $form->disciplins = array();
        $form->pedagogictypes = array();
        $form->useenvironments = array();
        $form->activities = array();
        $form->step = $step + 1;
    }
    break;
    case COMPLETED :{
        $form = data_submitted();
        $form->step = $step + 1;
        $result = include($CFG->dirroot.'/blocks/publishflow/submits/remote/submit.controller.php');
    }
}

echo $OUTPUT->container_start();
if ($result != -1)
    include $CFG->dirroot.'/blocks/publishflow/submits/remote/submit_step'.$form->step.'.html';
echo $OUTPUT->container_end();

?>