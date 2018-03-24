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
 * Submits a course for indexing
 * @package blocks-publishflow
 * @category blocks
 *
 */

require('../../config.php');
require_once($CFG->dirroot.'/mnet/lib.php');
require_once($CFG->dirroot.'/mnet/xmlrpc/client.php');
require_once($CFG->dirroot.'/blocks/publishflow/submitlib.php');
require_once($CFG->dirroot.'/blocks/publishflow/block_publishflow.php');

// This is a common step for any indexing procedure. Other processes may add their own step declarations.
define('STEP_COMPLETED', -1);
define('STEP_INITIAL', 0);

$id = required_param('id', PARAM_INT); // The block instance ID.
$fromcourse = required_param('fromcourse', PARAM_INT);
$action = required_param('what', PARAM_TEXT);
$step = optional_param('step', STEP_INITIAL, PARAM_INT);
$course = $DB->get_record('course', array('id' => "$fromcourse"));

require_login($course);

$url = new moodle_url('/blocks/publishflow/submit.php', array('id' => $id));
$PAGE->set_url($url);
$PAGE->navigation->add(format_string($course->shortname), new moodle_url('/course/view.php', array('id' => $course->id)));
$PAGE->navigation->add(get_string('indexing', 'block_publishflow'));
$PAGE->set_title(get_string('indexing', 'block_publishflow'));
$PAGE->set_heading(get_string('indexing', 'block_publishflow'));

echo $OUTPUT->header();

$result = 0;

// Runs the proper indexing procedure.
$instance = $DB->get_record('block_instances', array('id' => $id));
$theblock = block_instance('publishflow', $instance);

if (!isset($theblock->config->submitto)) {
    $theblock->config = new StdClass();
    $theblock->config->submitto = 'default';
}
$result = include($CFG->dirroot."/blocks/publishflow/submits/{$theblock->config->submitto}/submit_proc.php");

echo $OUTPUT->footer();
