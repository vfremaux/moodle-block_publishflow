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
 * Manage backup files
 * @package   block_publishflow
 * @category  blocks
 * @copyright 2008 Valery Fremaux <valery.fremaux@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require('../../config.php');
require_once(dirname(__FILE__) . '/pffilesedit_form.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/repository/lib.php');

$courseid = required_param('id', PARAM_INT);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('coursemisconf');
}

// Current context.
$context = context_course::instance($course->id);
$systemcontext = context_system::instance();

// File parameters.
$component = 'backup';
$filearea = 'publishflow';
$returnurl = optional_param('returnurl', new moodle_url('/course/view.php', array('id' => $course->id)), PARAM_URL);

$url = new moodle_url('/blocks/publishflow/pffilesedit.php', array('id' => $course->id));

// Security.

require_course_login($course, false);
require_capability('block/publishflow:managepublishedfiles', $context);

$PAGE->set_url($url, array('id' => $courseid, 'returnurl' => $returnurl));
$PAGE->set_context($context);
$PAGE->set_title(get_string('managefiles', 'backup'));
$PAGE->set_heading(get_string('managefiles', 'backup'));
$PAGE->navbar->add(get_string('pluginname', 'block_publishflow'));
$PAGE->navbar->add(get_string('managepublishedfiles','block_publishflow'));
$PAGE->set_pagelayout('admin');
$browser = get_file_browser();

$data = new stdClass();
$options = array('subdirs' => 0, 'maxfiles' => -1, 'accepted_types' => '*', 'return_types' => FILE_INTERNAL);
file_prepare_standard_filemanager($data, 'files', $options, $context, $component, $filearea, 0);
$params = array('data' => $data,
                'courseid' => $courseid,
                'contextid' => $context->id,
                'filearea' => $filearea,
                'component' => $component,
                'returnurl' => $returnurl);
$form = new pf_files_edit_form(null, $params);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

$data = $form->get_data();
if ($data) {
    $formdata = file_postupdate_standard_filemanager($data, 'files', $options, $context, $component, $filearea, 0);
    redirect($returnurl);
}

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('publishflowbackups', 'block_publishflow'));
echo get_string('publishflowbackupsadvice', 'block_publishflow');

echo $OUTPUT->container_start();
$form->display();
echo $OUTPUT->container_end();

<<<<<<< HEAD
require_once('../../config.php');
require_once(dirname(__FILE__) . '/pffilesedit_form.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/repository/lib.php');

$courseid = required_param('id', PARAM_INT);

// current context
$contextid =  get_context_instance(CONTEXT_COURSE, $courseid)->id;//required_param('contextid', PARAM_INT);
$currentcontext = CONTEXT_COURSE;//required_param('currentcontext', PARAM_INT);
// file parameters
$component  = 'backup';//optional_param('component', null, PARAM_COMPONENT);
$filearea   = 'publishflow';//optional_param('filearea', null, PARAM_AREA);
$returnurl  = optional_param('returnurl', $CFG->wwwroot."/course/view.php?id=".$courseid, PARAM_URL);

list($context, $course, $cm) = get_context_info_array($contextid);
$filecontext = get_context_instance_by_id($contextid);

$url = new moodle_url('/blocks/publishflow/pffilesedit.php', array('currentcontext' => $currentcontext, 'contextid'=>$contextid, 'component'=>$component, 'filearea'=>$filearea));

require_login($course, false, $cm);
//require_capability('moodle/restore:uploadfile', $context);
$systemcontext = get_context_instance(CONTEXT_SYSTEM);
require_capability('block/publishflow:managepublishedfiles',$systemcontext);

$PAGE->set_url($url,array('id' => $courseid,'returnurl' => $returnurl));
$PAGE->set_context($context);
$PAGE->set_title(get_string('managefiles', 'backup'));
$PAGE->set_heading(get_string('managefiles', 'backup'));
$PAGE->set_pagelayout('admin');
$browser = get_file_browser();

$data = new stdClass();
$options = array('subdirs' => 0, 'maxfiles' => -1, 'accepted_types' => '*', 'return_types' => FILE_INTERNAL);
file_prepare_standard_filemanager($data, 'files', $options, $filecontext, $component, $filearea, 0);
$form = new pf_files_edit_form(null, array('data' => $data, 'courseid' => $courseid, 'contextid' => $contextid, 'currentcontext' => $currentcontext, 'filearea' => $filearea, 'component'=>$component, 'returnurl' => $returnurl));

if ($form->is_cancelled()) {
    redirect($returnurl);
}

$data = $form->get_data();
if ($data) {
    $formdata = file_postupdate_standard_filemanager($data, 'files', $options, $filecontext, $component, $filearea, 0);
    redirect($returnurl);
}

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('publishflowbackups', 'block_publishflow'));
echo get_string('publishflowbackupsadvice', 'block_publishflow');

echo $OUTPUT->container_start();
$form->display();
echo $OUTPUT->container_end();

=======
>>>>>>> MOODLE_34_STABLE
echo $OUTPUT->footer();
