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

/**
 * Implements a result page for driving the training session closing
 * transaction.
 * @package blocks-publishflow
 * @category blocks
 *
 */

require('../../config.php');
require_once($CFG->dirroot.'/blocks/publishflow/lib.php');
require_once($CFG->dirroot.'/blocks/publishflow/mailtemplatelib.php');

$fromcourse = required_param('fromcourse', PARAM_INT);
$action = required_param('what', PARAM_TEXT);
$step = optional_param('step', COURSE_OPEN_CHOOSE_OPTIONS, PARAM_INT);

$url = new moodle_url('/blocks/publishflow/open.php', array('fromcourse' => $fromcourse, 'what' => $action, 'step' => $step));

$course = $DB->get_record('course', array('id' => "$fromcourse"));
$context = context_course::instance($course->id);

require_course_login($course, false);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('opening', 'block_publishflow'));
$PAGE->set_heading(get_string('opening', 'block_publishflow'));
$PAGE->navbar->add(get_string('pluginname', 'block_publishflow'));
$PAGE->navbar->add(get_string('opening', 'block_publishflow'));

echo $OUTPUT->header();

$strdoopen = get_string('doopen', 'block_publishflow');
$url = new moodle_url('/blocks/publishflow/open.php', array('what' => 'open', 'fromcourse' => $course->id));
$context = context_course::instance($course->id);

switch ($step) {
     case COURSE_OPEN_CHOOSE_OPTIONS: {
        // prints a choice with helpers
        echo $OUTPUT->heading(get_string('notification', 'block_publishflow'));
        print_string('opennotifyhelper', 'block_publishflow');
        echo "<p align=\"right\"><a href=\"{$url}&amp;step=".COURSE_OPEN_EXECUTE."&amp;notify=1\">$strdoopen</a></p>";

        echo $OUTPUT->heading(get_string('withoutnotification', 'block_publishflow'));
        print_string('openwithoutnotifyhelper', 'block_publishflow');
        echo "<p align=\"right\"><a href=\"{$url}&amp;step=".COURSE_OPEN_EXECUTE."&amp;notify=0\">$strdoopen</a></p>";

        echo '<p align="center"><center>';
        $opts['id'] = $course->id;
        echo $OUTPUT->single_button(new moodle_url($CFG->wwwroot.'/course/view.php', $opts), get_string('cancel'), 'get');
        echo '</center></p>';

         break;
    }

     case COURSE_OPEN_EXECUTE: {
        $notify = required_param('notify', PARAM_INT);
        publishflow_session_open($course, $notify);
        echo $OUTPUT->box(get_string('courseopen', 'block_publishflow'), 'center');
        $courseurl = new moodle_url('/course/view.php', array('id' => $course->id));
        echo ' <p align="center"><a href="'.$courseurl.'">'.get_string('backtocourse', 'block_publishflow').'</a></p>';
        echo '</center>';
        break;
    }
}
echo $OUTPUT->footer();
