<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

/**
 * Unachieved. Not functional
 * the prurpose would be to fetch an adequate publication identifier from a remote source
 * in which the course colume has been referenced. Then publishing might send the complete
 * content to that remote source.
 *
 * This needs more work and integration opportunities. 
 */

switch($step) {
    case AUTHORING : {
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
    case TEACHING : {
        // From form 1.
        $form = data_submitted();
        $oldform = get_object_vars(clone($form));
        unset($oldform['step']);
        $form->command = get_string('finish', 'block_publishflow');

        // New fields.
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
    case STEP_COMPLETED :{
        $form = data_submitted();
        $form->step = $step + 1;
        $result = include($CFG->dirroot.'/blocks/publishflow/submits/remote/submit.controller.php');
    }
}

echo $OUTPUT->container_start();
if ($result != -1) {
    include($CFG->dirroot.'/blocks/publishflow/submits/remote/submit_step'.$form->step.'.html');
}
echo $OUTPUT->container_end();
