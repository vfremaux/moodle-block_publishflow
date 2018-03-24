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

require_once($CFG->dirroot.'/mnet/xmlrpc/client.php');
require_once($CFG->dirroot.'/mnet/lib.php');

if ($action == 'submit') {

    /*
     * call the proper controller agains current submission procedure...
     * must setup an idnumber variable !
     */
    include($CFG->dirroot."/blocks/publishflow/submits/{$theblock->config->submitto}/submit.controller.php");

    if ($step == STEP_COMPLETED) {
        $DB->set_field('course', 'idnumber', $idnumber, array('id' => $fromcourse));
        echo '<br/>';
        echo $OUTPUT->box_start();
        echo get_string('indexingof', 'block_publishflow');
        echo ' "'.$COURSE->fullname.'" ';
        echo get_string('completed', 'block_publishflow', $idnumber);
        echo $OUTPUT->box_end();
        echo $OUTPUT->continue_button(new moodle_url('/course/view.php', array('id' => $COURSE->id)));
    }
}
