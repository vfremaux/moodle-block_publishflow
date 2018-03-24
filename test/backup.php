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
 * @package block_publishflow
 * @category blocks
 * @author Valery Fremaux (valery.fremaux@gmail.com)
 * @author Wafa Adham (admin@adham.ps)
 * @copyright 2008 onwards Valery Fremaux (http://www.myLearningFactory.com)
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/blocks/publishflow/backup/util/includes/backup_includes.php');
require_once('backup/backup_automation.class.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$courseid = required_param('id', PARAM_INT);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('coursemisconf');
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

echo '<div style="text-align:center;">';
echo $OUTPUT->single_button(new moodle_url('/course/view.php', array('id' => $courseid)), "Backup Complete");
echo '</div>';

echo $OUTPUT->footer();
