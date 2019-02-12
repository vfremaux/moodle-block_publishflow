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
require_once($CFG->dirroot.'/blocks/publishflow/block_publishflow.php');
require_once($CFG->dirroot.'/blocks/publishflow/lib.php');
require_once($CFG->dirroot.'/blocks/publishflow/backup/backup_automation.class.php');
require_once($CFG->dirroot.'/backup/util/xml/parser/progressive_parser.class.php');
require_once($CFG->dirroot.'/backup/util/helper/restore_moodlexml_parser_processor.class.php');

/**
 * Facade access to retrofit function.
 * @param mixed $courseorid a course ID or a course record
 * @param string $where the wwwroot identity of a remote mnet_host
 * @param int $fromcourse
 */
function block_publishflow_retrofit_course($courseorid, $whereroot, $fromcourse = null) {
    global $DB;

    if (!is_object($courseorid)) {
        $course = $DB->get_record('course', array('id' => $courseorid));
    } else {
        $course = $courseorid;
    }

    if (!$course) {
        throw new moodle_exception('Course does not exist');
    }

    if (!$whereroot) {
        throw new moodle_exception('No target to retrofit to');
    }

    // First backup it.
    backup_automation::run_publishflow_coursebackup($course->id);
    return block_publishflow_retrofit($course, $whereroot, $fromcourse);
}

function block_publishflow_get_factories() {
    return \block_publishflow::get_factories();
}