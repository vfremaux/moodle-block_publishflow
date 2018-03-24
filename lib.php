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
 * minimalistic edit form
 *
 * @package    block_publishflow
 * @category   blocks
 * @author     Valery Fremaux (valery.fremaux@edunao.com)
 * @copyright  2008 Valery Fremaux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

if (!defined('RPC_SUCCESS')) {
    define('RPC_TEST', 100);
    define('RPC_SUCCESS', 200);
    define('RPC_FAILURE', 500);
    define('RPC_FAILURE_USER', 501);
    define('RPC_FAILURE_CONFIG', 502);
    define('RPC_FAILURE_DATA', 503);
    define('RPC_FAILURE_CAPABILITY', 510);
    define('MNET_FAILURE', 511);
    define('RPC_FAILURE_RECORD', 520);
    define('RPC_FAILURE_RUN', 521);
}

// Fakes a debug library if missing.
if (!function_exists('debug_trace')) {
    function debug_trace($str) {
        assert(1);
    }
}

define('COURSE_CLOSE_CHOOSE_MODE', 0);
define('COURSE_CLOSE_EXECUTE', 1);

define('COURSE_CLOSE_PUBLIC', 0);
define('COURSE_CLOSE_PROTECTED', 1);
define('COURSE_CLOSE_PRIVATE', 2);

define('COURSE_OPEN_CHOOSE_OPTIONS', 0);
define('COURSE_OPEN_EXECUTE', 1);

/**
 * This function is not implemented in this plugin, but is needed to mark
 * the vf documentation custom volume availability.
 */
function block_publishflow_supports_feature($feature) {
    static $supports;

    $config = get_config('block_publishflow');

    if (!isset($supports)) {
        $supports = array(
            'pro' => array(
                'restore' => array('postscripting'),
            ),
            'community' => array(),
        );
        $prefer = array();
    }

    // Check existance of the 'pro' dir in plugin.
    if (is_dir(__DIR__.'/pro')) {
        if ($feature == 'emulate/community') {
            return 'pro';
        }
        if (empty($config->emulatecommunity)) {
            $versionkey = 'pro';
        } else {
            $versionkey = 'community';
        }
    } else {
        $versionkey = 'community';
    }

    list($feat, $subfeat) = explode('/', $feature);

    if (!array_key_exists($feat, $supports[$versionkey])) {
        return false;
    }

    if (!in_array($subfeat, $supports[$versionkey][$feat])) {
        return false;
    }

    if (array_key_exists($feat, $supports['community'])) {
        if (in_array($subfeat, $supports['community'][$feat])) {
            // If community exists, default path points community code.
            if (isset($prefer[$feat][$subfeat])) {
                // Configuration tells which location to prefer if explicit.
                $versionkey = $prefer[$feat][$subfeat];
            } else {
                $versionkey = 'community';
            }
        }
    }

    return $versionkey;
}

/**
 * opens a training session. Opening changes the course category, if setup in
 * publishflow site configuration, and may send notification to enrolled users
 * @param object $course the course information
 * @param boolean $notify
 */
function publishflow_session_open($course, $notify) {
    global $CFG, $SITE, $DB, $COURSE;

    $config = get_config('block_publishflow');

    // Reopening is allowed.
    if ($course->category == @$config->deploycategory || $course->category == @$config->closedcategory) {
        $course->category = @$config->runningcategory;
    }

    $course->visible = 1;
    $course->guest = 0;
    $course->startdate = time();
    $DB->update_record('course', $course);
    $context = context_course::instance($course->id);

    // Revalidate disabled people.
    $role = $DB->get_record('role', array('shortname' => 'student'));
    $disabledrole = $DB->get_record('role', array('shortname' => 'disabledstudent'));
    if ($pts = get_users_from_role_on_context($disabledrole, $context)) {
        foreach ($pts as $pt) {
            role_unassign($disabledrole->id, $pt->userid, null, $context->id);
            role_assign($role->id, $pt->userid, null, $context->id);
        }
    }

    $notify = required_param('notify', PARAM_INT);
    if ($notify) {
        // Send notification to all enrolled members.
        $fields = 'u.id, '.get_all_user_name_fields(true, 'u').', email, emailstop, mnethostid, mailformat';
        if ($users = get_users_by_capability($context, 'moodle/course:view', $fields)) {
            $infomap = array('SITE_NAME' => $SITE->shortname,
                             'MENTOR' => fullname($USER),
                             'DATE' => userdate(time()),
                             'COURSE' => $course->fullname,
                             'URL' => $CFG->wwwroot."/course/view.php?id={$course->id}");
            $rawtemplate = publishflow_compile_mail_template('open_course_session', $infomap, 'local');
            $htmltemplate = publishflow_compile_mail_template('open_course_session_html', $infomap, 'local');
            $subject = get_string('sessionopening', 'block_publishflow', $SITE->shortname.':'.format_string($COURSE->shortname));

            foreach ($users as $user) {
                email_to_user($user, $USER, $subject, $rawtemplate, $htmltemplate);
            }
        }
    }
}

/**
 * closes a training session. Closing changes the course category, if setup in
 * publishflow site configuration. It will downgrade enrolled students to a "disablestudent"
 * role, and switches off accessibility of the closed volume depending on publis, protected or private closure.
 * @param object $course the course information
 * @param boolean $mode the course closure mode (COURSE_CLOSE_PUBLIC, COURSE_CLOSE_PROTECTED or COURSE_CLOSE_PRIVATE)
 * @param boolean $rpccall tells the function wether to perform direct error output (not RPC) or return error messages (RPC mode)
 */
function publishflow_course_close($course, $mode, $rpccall = false) {
    global $DB;

    $config = get_config('block_publishflow');

    if (!$course) {
        if (!$rpccall) {
            print_error('coursemisconf');
        } else {
            return "Cannot close null course";
        }
    }
    if (empty($config->closedcategory)) {
        if (!$rpccall) {
            error("Publish flow is not properly configured for closing courses");
        } else {
            return("Publish flow is not properly configured for closing courses");
        }
    }

    $context = context_course::instance($course->id);
    if ($course->category == @$config->runningcategory || $course->category == @$config->deploycategory) {
        $course->category = $config->closedcategory;
    }

    switch ($mode) {
        case COURSE_CLOSE_PUBLIC: {
            // Open course for guests.
            $course->guest = 1;
            $course->visible = 1;
            $course->enrollable = 0;
            // Get all students and reassign them as disabledstudents.
            $role = $DB->get_record('role', array('shortname' => 'student'));
            $disabledrole = $DB->get_record('role', array('shortname' => 'disabledstudent'));
            if ($pts = get_users_from_role_on_context($role, $context)) {
                foreach ($pts as $pt) {
                    role_unassign($role->id, $pt->userid, null, $context->id);
                    role_assign($disabledrole->id, $pt->userid, null, $context->id);
                }
            }
            break;
        }

        case COURSE_CLOSE_PROTECTED: {
            $course->guest = 0;
            $course->visible = 1;
            $course->enrollable = 0;

            // Get all students and reassign them as disabledstudents.
            $role = $DB->get_record('role', array('shortname' => 'student'));
            $disabledrole = $DB->get_record('role', array('shortname' => 'disabledstudent'));
            $context = context_course::instance($course->id);
            if ($pts = get_users_from_role_on_context($role, $context)) {
                foreach ($pts as $pt) {
                    role_unassign($role->id, $pt->userid, null, $context->id);
                    role_assign($disabledrole->id, $pt->userid, null, $context->id);
                }
            }
            break;
        }

        case COURSE_CLOSE_PRIVATE: {
            $course->guest = 0;
            $course->visible = 0;
            $course->enrollable = 0;
            // Do not unassign people.
            break;
        }

        default:
            if (!$rpccall) {
                print_error('Bad closing mode');
            } else {
                return 'Bad closing mode';
            }
    }
    $DB->update_record('course', $course);
}

/**
 * Function to deploy a course locally
 * @param int $category the category where to deploy into
 * @param object $sourcecourse a record with information about source course
 */
function publishflow_local_deploy($category, $sourcecourse) {
    global $CFG, $USER, $DB;

    $config = get_config('block_publishflow');

    include_once($CFG->dirroot.'/backup/restorelib.php');
    include_once($CFG->dirroot.'/backup/lib.php');

    if (!$category) {
        $category = $config->deploycategory;
    }

    // Lets get the publishflow published file.
    $coursecontextid = context_course::instance($sourcecourse->id)->id;
    $fs = get_file_storage();
    $backupfiles = $fs->get_area_files($coursecontextid,'backup', 'publishflow', 0, 'timecreated', false);

    if (!$backupfiles) {
        print_error('errornotpublished', 'block_publishflow');
    }

    $maxtime = '240';
    $maxmem = '512M';

    ini_set('max_execution_time', $maxtime);
    ini_set('memory_limit', $maxmem);

    $file = array_pop ($backupfiles);
    $newcourseid =  restore_automation::run_automated_restore($file->get_id(), null, $category) ;

    // Confirm/force idnumber in new course.
    $response = new StdClass();
    $response->courseid = $newcourseid;
    $DB->set_field('course', 'idnumber', $sourcecourse->idnumber, array('id' => "{$newcourseid}"));

    // Confirm/force not enrollable, enrolling will be performed by master teacher.

    /*
     * assign the localuser as author in all cases :
     * deployement : deployer will unassign self manually if needed
     * free use deployement : deployer will take control over session
     * retrofit : deployer will take control over new learning path in work
     */
    $coursecontext = context_course::instance($newcourseid);
    $teacherrole = $DB->get_field('role', 'id', array('shortname' => 'teacher'));
    role_assign($teacherrole, $USER->id, $coursecontext->id);

    return ($newcourseid);
}

/**
 * get recursively categories in the database proxies.
 * @param int hostid the current hostid
 * @param ref cats the category array
 * @param int parent the parent cat
 * @param int maxdepth if 0, this is the last depth to examine. -1 stands for no limit.
 */
function publishflow_get_remote_categories($hostid, &$cats, $parent = 0, $maxdepth = 0) {
    global $DB;
    static $depth = 0;

    if ($maxdepth > 0) {
        if ($depth == $maxdepth) {
            return;
        }
    }

    $select = " parentid = ? AND platformid = ? ";
    if ($catmenu = $DB->get_records_select('block_publishflow_remotecat', $select, array($parent, $hostid), 'sortorder')) {
        foreach ($catmenu as $cat) {
            $catentry = new stdClass();
            $catentry->orid = $cat->originalid;
            $catentry->name = str_repeat("&nbsp;", $depth).$cat->name;
            $cats[] = $catentry;
            $depth++;
            publishflow_get_remote_categories($hostid, $cats, $catentry->orid, $maxdepth);
            $depth--;
        }
    }
}

/**
 * These three functions are wrappers to other ways to allow people
 * to deploy based on role's capabilities. They will conditionnaly wrap to
 * local implentation functions that could provide a customized
 * strategy for allowing deployement.
 * @param int $userid a user ID to check for, defaults to $USER->id
 */
function block_publishflow_extra_deploy_check($userid = null) {
    global $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    if (function_exists('local_check_deploy_permission')) {
        return local_check_deploy_permission($userid);
    }
    return false;
}

function block_publishflow_extra_publish_check($userid = null) {
    global $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    if (function_exists('local_check_publish_permission')) {
        return local_check_publish_permission($userid);
    }
    return false;
}

function block_publishflow_extra_retrofit_check($userid = null) {
    global $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    if (function_exists('local_check_retrofit_permission')) {
        return local_check_retrofit_permission($userid);
    }
    return false;
}

function block_publishflow_cron_network_refreshment() {
    global $DB;

    $hosts = $DB->get_records('mnet_host', array('deleted' => 0));

    foreach ($hosts as $host) {
        // Ignore if not a moodle.
        if ($host->applicationid != $DB->get_field('mnet_application', 'id', array('name' => 'moodle'))) {
            continue;
        }

        // Ignore if no service bound.
        $sql = "
            SELECT
                mh.id
            FROM
                {mnet_host} mh,
                {mnet_host2service} hs,
                {mnet_service} s
            WHERE
                mh.id = hs.hostid AND
                hs.serviceid = s.id AND
                s.name = 'publishflow' AND
                hs.subscribe = 1 AND
                mh.id = ?
        ";
        if (!$DB->get_record_sql($sql, array($host->id))) {
            continue;
        }

        // Ignore if hub or self.
        if (($host->name == '') || ($host->name == "All Hosts")) {
            continue;
        }

        $result = block_publishflow_update_peer($host);
        if (empty($result)) {
            echo $host->name.get_string('updateok', 'block_publishflow')."\n";
        } else {
            echo $result;
        }
    }
}

function block_publishflow_update_peer($host) {
    global $DB, $USER, $CFG;

    if (!$hostcatalog = $DB->get_record('block_publishflow_catalog', array('platformid' => $host->id))) {
        // Add missing records.
        $hostcatalog = new StdClass;
        $hostcatalog->platformid = $host->id;
        $hostcatalog->type = 'moodle';
        $hostcatalog->lastaccessed = 0;
        $hostcatalog->id = $DB->insert_record('block_publishflow_catalog', $hostcatalog);
    }

    $caller = new stdClass;
    $caller->username = $USER->username;
    $caller->userwwwroot = $host->wwwroot;
    $caller->remotewwwroot = $CFG->wwwroot;
    $rpcclient = new mnet_xmlrpc_client();
    $rpcclient->set_method('blocks/publishflow/rpclib.php/publishflow_updateplatforms');
    $rpcclient->add_param($caller, 'struct');
    $rpcclient->add_param($host->wwwroot, 'string');
    $mnet_host = new mnet_peer();
    $mnet_host->set_wwwroot($host->wwwroot);
    $rpcclient->send($mnet_host);
    if (!is_array($rpcclient->response)) {
        $response = json_decode($rpcclient->response);
    }

    // We have to check if there is a response with content.
    if (empty($response)) {
        return $host->name.get_string('errorencountered', 'block_publishflow').$rpcclient->error[0];
    } else {
        if ($response->status == RPC_FAILURE) {
            return $host->name.get_string('errorencountered', 'block_publishflow').$response->error;
        } else if ($response->status == RPC_SUCCESS) {
            $hostcatalog->type = $response->node;
            $hostcatalog->lastaccess = time();

            $DB->update_record('block_publishflow_catalog', $hostcatalog);

            // Purge all previously proxied.
            $DB->delete_records('block_publishflow_remotecat', array('platformid' => $host->id));
            foreach ($response->content as $entry) {
                // If it's a new record, we have to create it.
                if (!$DB->get_record('block_publishflow_remotecat', array('originalid' => $entry->id, 'platformid' => $host->id))) {
                    $fullentry = array('platformid' => $host->id,
                                       'originalid' => $entry->id,
                                       'parentid' => $entry->parentid,
                                       'name' => $entry->name,
                                       'sortorder' => $entry->sortorder);
                    $DB->insert_record('block_publishflow_remotecat', $fullentry);
                }
            }
        } else {
            return $host->name.get_string('clientfailure', 'block_publishflow');
        }
    }
}

/**
 * This is a relocalized function in order to get block_publishflow more compact.
 * checks if a user has a some named capability effective somewhere in a course.
 * @param string $capability;
 * @param bool $excludesystem
 * @param bool $excludesite
 * @param bool $doanything
 * @param string $contextlevels restrict to some contextlevel may speedup the query.
 */
function block_publishflow_has_capability_somewhere($capability, $excludesystem = true, $excludesite = true,
                                           $doanything = false, $contextlevels = '') {
    global $USER, $DB;

    $contextclause = '';

    if ($contextlevels) {
        list($sql, $params) = $DB->get_in_or_equal(explode(',', $contextlevels), SQL_PARAMS_NAMED);
        $contextclause = "
           AND ctx.contextlevel $sql
        ";
    }
    $params['capability'] = $capability;
    $params['userid'] = $USER->id;

    $sitecontextexclclause = '';
    if ($excludesite) {
        $sitecontextexclclause = " ctx.id != 1  AND ";
    }

    // This is a a quick rough query that may not handle all role override possibility.

    $sql = "
        SELECT
            COUNT(DISTINCT ra.id)
        FROM
            {role_capabilities} rc,
            {role_assignments} ra,
            {context} ctx
        WHERE
            rc.roleid = ra.roleid AND
            ra.contextid = ctx.id AND
            $sitecontextexclclause
            rc.capability = :capability
            $contextclause
            AND ra.userid = :userid AND
            rc.permission = 1
    ";
    $hassome = $DB->count_records_sql($sql, $params);

    if (!empty($hassome)) {
        return true;
    }

    $systemcontext = context_system::instance();
    if (!$excludesystem && has_capability($capability, $systemcontext, $USER->id, $doanything)) {
        return true;
    }

    return false;
}
