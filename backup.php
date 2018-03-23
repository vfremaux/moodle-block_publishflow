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
 * This script is a local rewritten strategy for making quick backup
 * in the publisheflow file areas
 * @package block_publishflow
 * @author Valery Fremaux (valery.fremaux@gmail.com);
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot.'/blocks/publishflow/backup/backup_automation.class.php');
require_once($CFG->dirroot.'/backup/util/xml/parser/progressive_parser.class.php');
require_once($CFG->dirroot.'/backup/util/helper/restore_moodlexml_parser_processor.class.php');

$courseid = required_param('id', PARAM_INT);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('coursemisconf');
}

$url = new moodle_url('/blocks/publishflow/backup.php', array('id' => $course->id));

$full = get_string('backupforpublishing', 'block_publishflow');

$coursecontext = context_course::instance($courseid);
$PAGE->set_context($coursecontext);
$PAGE->set_url($url);

require_login();
require_capability('block/publishflow:managepublishedfiles', $coursecontext);

$PAGE->set_title($full);
$PAGE->set_heading($full);
$PAGE->set_cacheable(false);

echo $OUTPUT->header();
echo $OUTPUT->heading($full);

backup_automation::run_publishflow_coursebackup($courseid);
backup_automation::remove_excess_publishflow_backups($course);

echo '<div style="text-align:center;">';
$buttonurl = new moodle_url('/course/view.php', array('id' => $courseid));
echo $OUTPUT->continue_button($buttonurl);
echo '</div>';

echo $OUTPUT->footer();

