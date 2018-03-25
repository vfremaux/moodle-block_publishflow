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
 * Implements a cross plugin API.
 * @package block_publishflow
 * @category blocks
 * @author Valery Fremaux (valery.fremaux@gmail.com)
 * @author Wafa Adham (admin@adham.ps)
 * @copyright 2008 onwards Valery Fremaux (http://www.myLearningFactory.com)
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot.'/blocks/publishflow/backup/lib.php');
require_once($CFG->dirroot.'/blocks/publishflow/backup/backup_automation.class.php');
require_once($CFG->dirroot.'/backup/util/xml/parser/progressive_parser.class.php');
require_once($CFG->dirroot.'/backup/util/helper/restore_moodlexml_parser_processor.class.php');

function block_publishflow_retrofit_course($courseid) {
    global $DB;

    $course = $DB->get_record('course', array('id' => $courseid));

    if (!$course) {
        throw new moodle_exception('Invalid course ID');
    }

    // First backup it.
    backup_automation::run_publishflow_coursebackup($course->id);
    return block_publishflow_retrofit($course, $where, $fromcourse);
}