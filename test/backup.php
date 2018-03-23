<?php

require_once('../../config.php');
require_once($CFG->dirroot.'/blocks/publishflow/backup/util/includes/backup_includes.php');
require_once('backup/backup_automation.class.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$courseid = required_param('id', PARAM_INT);

if (!$course = $DB->get_record('course', array('id' => $course_id))) {
    print_error("invalid course");
}

$full = "Course backup - ".$course->fullname;

$context = context_course::instance($courseid);
$PAGE->set_context($context); 
$PAGE->set_title($full);

$PAGE->set_heading($full);
$PAGE->set_url(new moodle_url('/blocks/publishflow/backup.php'));

echo $OUTPUT->header();
echo $OUTPUT->heading("Publishflow - Course Backup");

echo "<div class='pf-backup-step'>Performing course backup .... Please wait</div>";

echo "<div class='pf-backup-step'>Testing Automated Backup</div>";
backup_automation::run_publishflow_coursebackup($courseid);
backup_automation::remove_excess_publishflow_backups($course);


echo '<div style='text-align:center;'>';
echo $OUTPUT->single_button(new moodle_url('/course/view.php', array('id' => $courseid)), "Backup Complete");
echo '</div>';

echo $OUTPUT->footer();
