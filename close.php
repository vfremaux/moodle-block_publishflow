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
 * @package    block_publishflow
 * @category   blocks
 * @copyright  2008 valery fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 *
 * Implements a result page for driving the training session closing
 * transaction.
 */
require('../../config.php');
require_once($CFG->dirroot.'/blocks/publishflow/lib.php');

$fromcourse = required_param('fromcourse', PARAM_INT);
$action = required_param('what', PARAM_TEXT);
$step = optional_param('step', COURSE_CLOSE_CHOOSE_MODE, PARAM_TEXT);

$params = array('fromcourse' => $fromcourse, 'what' => $action, 'step' => $step);
$url = new moodle_url('/blocks/publishflow/close.php', $params);

// Check we can do this.

if (!$course = $DB->get_record('course', array('id' => "$fromcourse"))) {
    print_error('coursemisconf');
}

$context = context_course::instance($course->id);

// Security.

require_course_login($course, false);
require_capability('block/publishflow:manage', $context);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('deployment', 'block_publishflow'));
$PAGE->set_heading(get_string('deployment', 'block_publishflow'));
$PAGE->navbar->add(get_string('pluginname', 'block_publishflow'));
$PAGE->navbar->add(get_string('closing', 'block_publishflow'));

echo $OUTPUT->header();

// Start triggering the remote deployment.

$strdoclose = get_string('doclose', 'block_publishflow');

echo $OUTPUT->box_start('publishpanel');

switch ($step) {
     case COURSE_CLOSE_CHOOSE_MODE: {
        // Prints a choice with helpers.
        print_string('closehelper', 'block_publishflow');

        echo $OUTPUT->heading(get_string('closepublic', 'block_publishflow'));
        print_string('closepublichelper', 'block_publishflow');
        echo "<p align=\"right\"><a href=\"{$url}&amp;step=".COURSE_CLOSE_EXECUTE."&amp;mode=".COURSE_CLOSE_PUBLIC."\">$strdoclose</a></p>";

        echo $OUTPUT->heading(get_string('closeprotected', 'block_publishflow'));
        print_string('closeprotectedhelper', 'block_publishflow');
        echo "<p align=\"right\"><a href=\"{$url}&amp;step=".COURSE_CLOSE_EXECUTE."&amp;mode=".COURSE_CLOSE_PROTECTED."\">$strdoclose</a></p>";

        echo $OUTPUT->heading(get_string('closeprivate', 'block_publishflow'));
        print_string('closeprivatehelper', 'block_publishflow');
        echo "<p align=\"right\"><a href=\"{$url}&amp;step=".COURSE_CLOSE_EXECUTE."&amp;mode=".COURSE_CLOSE_PRIVATE."\">$strdoclose</a></p>";

        echo '<p align="center"><center>';
        $opts['id'] = $course->id;
        echo $OUTPUT->single_button(new moodle_url('/course/view.php', $opts), get_string('cancel'), 'get');
        echo '</center></p>';
        break;
    }

     case COURSE_CLOSE_EXECUTE: {
        $mode = required_param('mode', PARAM_INT);
        publishflow_course_close($course, $mode);
        echo $OUTPUT->box(get_string('courseclosed', 'block_publishflow'), 'center');
        echo " <a href=\"/course/view.php?id={$course->id}\">".get_string('backtocourse', 'block_publishflow').'</a>';
        echo '</center>';
        break;
    }
}

echo $OUTPUT->box_end();

echo $OUTPUT->footer();
