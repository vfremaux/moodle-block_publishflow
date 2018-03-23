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
 * Utility helper for automated backups run through cron.
 *
 * @package    core
 * @subpackage backup
 * @copyright  2010 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * This class is an abstract class with methods that can be called to aid the
 * running of automated backups over cron.
 */
abstract class backup_automation {

    /** automated backups are active and ready to run */
    const STATE_OK = 0;
    /** automated backups are disabled and will not be run */
    const STATE_DISABLED = 1;
    /** automated backups are all ready running! */
    const STATE_RUNNING = 2;

    /** Course automated backup completed successfully */
    const BACKUP_STATUS_OK = 1;
    /** Course automated backup errored */
    const BACKUP_STATUS_ERROR = 0;
    /** Course automated backup never finished */
    const BACKUP_STATUS_UNFINISHED = 2;
    /** Course automated backup was skipped */
    const BACKUP_STATUS_SKIPPED = 3;

    /** Run if required by the schedule set in config. Default. **/
    const RUN_ON_SCHEDULE = 0;
    /** Run immediately. **/
    const RUN_IMMEDIATELY = 1;
    const AUTO_BACKUP_DISABLED = 0;
    const AUTO_BACKUP_ENABLED = 1;
    const AUTO_BACKUP_MANUAL = 2;
    const MODE_MANUAL = 110;

    /**
     * Runs the automated backups if required
     *
     * @global moodle_database $DB
     */
    public static function run_publishflow_coursebackup($courseid) {
        global $DB, $USER;

        $fs = get_file_storage();

        $status = true;
        $emailpending = false;
        $now = time();
        $rundirective = self::RUN_ON_SCHEDULE;

        echo '<pre>';
        mtrace(get_string('backupstatecheck', 'block_publishflow'));

        $state = self::get_automated_backup_state($rundirective);

        if ($state === self::STATE_RUNNING) {
            echo '<div class="pf-backup-step">RUNNING</div>';
            if ($rundirective == self::RUN_IMMEDIATELY) {
                mtrace(get_string('backupinprogress', 'block_publishflow'));
            } else {
                mtrace(get_string('backupautomatedrunning', 'block_publishflow'));
            }
            echo '</pre>';
            return false;
        }
        self::set_state_running();

        $admin = get_admin();
        if (!$admin) {
            mtrace(get_string('backuperrornoadmin', 'block_publishflow'));
            return false;
        }

        mtrace(get_string('backupcheckingcourse', 'block_publishflow'));

        // This could take a while!
        @set_time_limit(0);
        raise_memory_limit(MEMORY_EXTRA);

        $course = $DB->get_record('course', array('id' => $courseid));

        /*
         * Skip backup of unavailable courses that have remained unmodified in a month
         * Check log if there were any modifications to the course content
         */
        $sqlwhere = "
            course=:courseid AND
            time>:time AND ". $DB->sql_like('action', ':action', false, true, true);
        $params = array('courseid' => $course->id, 'time' => $now - 31 * DAYSECS, 'action' => '%view%');
        $logexists = $DB->record_exists_select('log', $sqlwhere, $params);

        // Now we backup every non-skipped course.
        mtrace(get_string('backupcourse', 'block_publishflow', $course->fullname));

        $usercontext = context_user::instance($USER->id);
        $admincontext = context_user::instance($admin->id);
        $coursecontext = context_course::instance($courseid);

        // Ensure the user_tohub filearea is empty.
        $fs->delete_area_files($usercontext->id, 'user', 'tohub', 0);

        // Only make the backup if laststatus isn't 2-UNFINISHED (uncontrolled error).

        if (self::launch_automated_backup($course, $admin->id)) {

            // Get result in user_tohub filearea and move it to publishflow backup area.

            $generatedfiles = $fs->get_area_files($admincontext->id, 'user', 'tohub', 0, 'timecreated DESC', false);

            if (!empty($generatedfiles)) {
                $backupfile = array_pop($generatedfiles);
                $newfilerec = new Stdclass;
                $newfilerec->contextid = $coursecontext->id;
                $newfilerec->component = 'backup';
                $newfilerec->filearea = 'publishflow';
                $newfilerec->itemid = 0;
                $newfilerec->filepath = $backupfile->get_filepath();
                $newfilerec->filename = $backupfile->get_filename();

                mtrace(get_string('backupstore', 'block_publishflow'));

                // Purge eventual old file.
                if ($oldfile = $fs->get_file($newfilerec->contextid,
                                             $newfilerec->component,
                                             $newfilerec->filearea,
                                             $newfilerec->itemid,
                                             $newfilerec->filepath,
                                             $newfilerec->filename)) {
                    $oldfile->delete();
                }

                // Move to new publishflow location.
                $fs->create_file_from_storedfile($newfilerec, $backupfile);
                // Purge old backup location.
                $backupfile->delete();
            } else {
                mtrace(get_string('backuprelocfailure', 'block_publishflow'));
            }
        } else {
            mtrace(get_string('backupfailure', 'block_publishflow'));
        }

        // Everything is finished stop backup_auto_running.
        self::set_state_running(false);

        echo '<div class="pf-backup-step" style="color:#009922;font-weight:bold;">Course backup complete.</div>';

        return true;
    }

    /**
     * Gets the results from the last automated backup that was run based upon
     * the statuses of the courses that were looked at.
     *
     * @global moodle_database $DB
     * @return array
     */
    public static function get_backup_status_array() {
        global $DB;

        $result = array(
            self::BACKUP_STATUS_ERROR => 0,
            self::BACKUP_STATUS_OK => 0,
            self::BACKUP_STATUS_UNFINISHED => 0,
            self::BACKUP_STATUS_SKIPPED => 0,
        );

        $sql = '
            SELECT DISTINCT
                bc.laststatus,
                COUNT(bc.courseid) statuscount
            FROM
                {backup_courses} bc
            GROUP BY
                bc.laststatus
        ';
        $statuses = $DB->get_records_sql($sql);

        foreach ($statuses as $status) {
            if (empty($status->statuscount)) {
                $status->statuscount = 0;
            }
            $result[(int)$status->laststatus] += $status->statuscount;
        }

        return $result;
    }

    /**
     * Works out the next time the automated backup should be run.
     *
     * @param mixed $timezone
     * @param int $now
     * @return int
     */

    /**
     * Launches a automated backup routine for the given course
     *
     * @param stdClass $course
     * @param int $userid
     * @return bool
     */
    public static function launch_automated_backup($course, $userid) {
        global $CFG;

        $config = get_config('backup');
        $bc = new backup_controller(backup::TYPE_1COURSE,
                                    $course->id,
                                    backup::FORMAT_MOODLE,
                                    backup::INTERACTIVE_NO,
                                    backup::MODE_HUB,
                                    $userid);

        try {
            $settings = array(
                'role_assignments' => 0,
                'user_files' => 0,
                'activities' => 1,
                'blocks' => 1,
                'filters' => 1,
                'comments' => 0,
                'completion_information' => 0,
                'logs' => 0,
                'histories' => 0
            );
            foreach ($settings as $setting => $configsetting) {
                if ($bc->get_plan()->setting_exists($setting)) {
                    $bc->get_plan()->get_setting($setting)->set_value($configsetting);
                }
            }

            // Set the default filename.
            $format = $bc->get_format();
            $type = $bc->get_type();
            $id = $bc->get_id();
            $users = $bc->get_plan()->get_setting('users')->get_value();
            $anonymised = $bc->get_plan()->get_setting('anonymize')->get_value();
            $filename = backup_plan_dbops::get_default_backup_filename($format, $type, $id, $users, $anonymised);
            $bc->get_plan()->get_setting('filename')->set_value($filename);

            $bc->set_status(backup::STATUS_AWAITING);

            $outcome = $bc->execute_plan();
            $results = $bc->get_results();
            $file = $results['backup_destination'];

            // Move file to a more accessible location.
            $dir = $CFG->tempdir.'/backup';

            if (!file_exists($dir) || !is_dir($dir) || !is_writable($dir)) {
                $dir = null;
            }

            if (!empty($dir)) {
                $filename = backup_plan_dbops::get_default_backup_filename($format, $type, $course->id, $users, $anonymised, true);
                $outcome = $file->copy_content_to($dir.'/'.$filename);
            }
        } catch (backup_exception $e) {
            $bc->log('backup_auto_failed_on_course', backup::LOG_WARNING, $course->shortname);
            $bc->destroy();
            unset($bc);
            return false;
        }

        $bc->destroy();
        unset($bc);

        return true;
    }

    /**
     * Gets the state of the automated backup system.
     *
     * @global moodle_database $DB
     * @return int One of self::STATE_*
     */
    public static function get_automated_backup_state($rundirective = self::RUN_ON_SCHEDULE) {
        global $DB;

        $config = get_config('backup');
        $active = (int)$config->backup_auto_active;
        if (!empty($config->backup_auto_running)) {
            /*
             * Detect if the backup_auto_running semaphore is a valid one
             * by looking for recent activity in the backup_controllers table
             * for backups of type backup::MODE_AUTOMATED
             */
            $timetosee = 60 * 90; // Time to consider in order to clean the semaphore.
            $params = array('purpose' => self::MODE_MANUAL, 'timetolook' => (time() - $timetosee));
            if ($DB->record_exists_select('backup_controllers',
                "operation = 'backup' AND type = 'course' AND purpose = :purpose AND timemodified > :timetolook", $params)) {
                return self::STATE_RUNNING; // Recent activity found, still running.
            } else {
                // No recent activity found, let's clean the semaphore.
                echo '<div>No backup activity found in last '.((int)$timetosee / 60).' minutes. Cleaning running status</div>';
                self::set_state_running(false);
            }
        }
        return self::STATE_OK;
    }

    /**
     * Sets the state of the automated backup system.
     *
     * @param bool $running
     * @return bool
     */
    public static function set_state_running($running = true) {
        if ($running === true) {
            if (self::get_automated_backup_state() === self::STATE_RUNNING) {
                throw new backup_exception('backup_automated_already_running');
            }
            set_config('backup_auto_running', '1', 'backup');
        } else {
            unset_config('backup_auto_running', 'backup');
        }
        return true;
    }

    /**
     * Removes excess backups from the external system and the local file system.
     *
     * The number of backups keep comes from $config->backup_auto_keep
     *
     * @param stdClass $course
     * @return bool
     */
    public static function remove_excess_publishflow_backups($course) {
        $config = get_config('backup');
        $keep = 1;
        $storage = $config->backup_auto_storage;
        $dir = $config->backup_auto_destination;

        $backupword = str_replace(' ', '_', core_text::strtolower(get_string('backupfilename')));
        $backupword = trim(clean_filename($backupword), '_');

        if (!file_exists($dir) || !is_dir($dir) || !is_writable($dir)) {
            $dir = null;
        }

        // Clean up excess backups in the course backup filearea.
        if ($storage == 0 || $storage == 2) {
            $fs = get_file_storage();
            $context = context_course::instance($course->id);
            $component = 'backup';
            $filearea = 'publishflow';
            $itemid = 0;
            $files = array();
            // Store all the matching files into timemodified => stored_file array.
            foreach ($fs->get_area_files($context->id, $component, $filearea, $itemid) as $file) {
                if (strpos($file->get_filename(), $backupword) !== 0) {
                    continue;
                }
                $files[$file->get_timemodified()] = $file;
            }
            if (count($files) <= $keep) {
                /*
                 * There are less matching files than the desired number to keep
                 * do there is nothing to clean up.
                 */
                return 0;
            }
            // Sort by keys descending (newer to older filemodified).
            krsort($files);
            $remove = array_splice($files, $keep);
            foreach ($remove as $file) {
                $file->delete();
            }
        }

        // Clean up excess backups in the specified external directory.
        if (!empty($dir) && ($storage == 1 || $storage == 2)) {
            /*
             * Calculate backup filename regex, ignoring the date/time/info parts that can be
             * variable, depending of languages, formats and automated backup settings
             */
            $filename = $backupword.'-'.backup::FORMAT_MOODLE.'-'.backup::TYPE_1COURSE.'-'.$course->id.'-';
            $regex = '#^'.preg_quote($filename, '#').'.*\.mbz$#';

            // Store all the matching files into fullpath => timemodified array.
            $files = array();
            foreach (scandir($dir) as $file) {
                if (preg_match($regex, $file, $matches)) {
                    $files[$file] = filemtime($dir . '/' . $file);
                }
            }
            if (count($files) <= $keep) {
                /*
                 * There are less matching files than the desired number to keep
                 * do there is nothing to clean up.
                 */
                return 0;
            }
            // Sort by values descending (newer to older filemodified).
            arsort($files);
            $remove = array_splice($files, $keep);
            foreach (array_keys($remove) as $file) {
                unlink($dir . '/' . $file);
            }
        }

        return true;
    }
}
