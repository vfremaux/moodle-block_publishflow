<?php
/**
 * 
 * 
 * 
 * 
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/blocks/publishflow/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot.'/blocks/publishflow/backup/backup_automation.class.php');

$course_id = required_param('id', PARAM_INT);

if (!$course = $DB->get_record('course', array('id' => $course_id))) {
    print_error('coursemisconf');
}

$full = "Course backup - ".$course->fullname;

$system_context = context_course::instance($course_id);
$PAGE->set_context($system_context); 
$PAGE->set_title($full);

$PAGE->set_heading($full);
/* SCANMSG: may be additional work required for $navigation variable */
$PAGE->set_focuscontrol('');
$PAGE->set_cacheable(false);
$PAGE->set_button('');

$PAGE->set_url('/blocks/publishflow/backup.php');

print $OUTPUT->header();

print $OUTPUT->footer();
