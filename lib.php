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

defined('MOODLE_INTERNAL') || die();

/**
 * minimalistic edit form
 *
 * @package    block_publishflow
 * @category   blocks
 * @author     Valery Fremaux (valery.fremaux@edunao.com)
 * @copyright  2008 Valery Fremaux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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

// fakes a debug library if missing.
if (!function_exists('debug_trace')) {
    function debug_trace($str){
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
* opens a training session. Opening changes the course category, if setup in 
* publishflow site configuration, and may send notification to enrolled users
* @param object $course the course information
* @param boolean $notify
*/
function publishflow_session_open($course, $notify) {
    global $CFG, $SITE, $DB;

    $config = get_config('block_publishflow');

    //reopening is allowed
    if ($course->category == @$config->deploycategory || $course->category == @$config->closedcategory) {
        $course->category = @$config->runningcategory;
    }

    $course->visible = 1; 
    $course->guest = 0; 
    $course->startdate = time();
    $DB->update_record('course', $course);
    $context = context_course::instance($course->id);
    /// revalidate disabled people
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
        /// send notification to all enrolled members
        if ($users = get_users_by_capability($context, 'moodle/course:view', 'u.id, '.get_all_user_name_fields(true, 'u').', email, emailstop, mnethostid, mailformat')) {
            $infomap = array( 'SITE_NAME' => $SITE->shortname,
                              'MENTOR' => fullname($USER),
                              'DATE' => userdate(time()),
                              'COURSE' => $course->fullname,
                              'URL' => $CFG->wwwroot."/course/view.php?id={$course->id}");
            $rawtemplate = compile_mail_template('open_course_session', $infomap, 'local');
            $htmltemplate = compile_mail_template('open_course_session_html', $infomap, 'local');
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
    global $CFG, $DB;

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
            return "Publish flow is not properly configured for closing courses";
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
            // get all students and reassign them as disabledstudents
            $role = $DB->get_record('role', array('shortname' => 'student'));
            $disabledrole = $DB->get_record('role', array('shortname' => 'disabledstudent'));
            if ($pts = get_users_from_role_on_context($role, $context)) {
                foreach($pts as $pt){
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
            // do not unassign people
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
 * overrides backup/lib.php/backup_generate_preferences_artificially($course, $prefs)
 * discarding all user's stuff
 * @param object $course the current course object
 */
function publishflow_backup_generate_preferences($course) {
    global $CFG, $DB;

    $preferences = new StdClass;
    $preferences->backup_unique_code = time();
    $preferences->backup_name = backup_get_zipfile_name($course, $preferences->backup_unique_code);
    $count = 0;

    if ($allmods = $DB->get_records('modules') ) {
        foreach ($allmods as $mod) {
            $modname = $mod->name;
            $modfile = "$CFG->dirroot/mod/$modname/backuplib.php";
            $modbackup = $modname."_backup_mods";
            $modbackupone = $modname."_backup_one_mod";
            $modcheckbackup = $modname."_check_backup_mods";
            if (!file_exists($modfile)) {
                continue;
            }
            include_once($modfile);
            if (!function_exists($modbackup) || !function_exists($modcheckbackup)) {
                continue;
            }
            $var = "exists_".$modname;
            $preferences->$var = true;
            $count++;
            // check that there are instances and we can back them up individually
            if (!$DB->count_records('course_modules', array('course' => $course->id, 'module' => $mod->id)) || !function_exists($modbackupone)) {
                continue;
            }
            $var = 'exists_one_'.$modname;
            $preferences->$var = true;
            $varname = $modname.'_instances';
            $preferences->$varname = get_all_instances_in_course($modname, $course, null, true);
            foreach ($preferences->$varname as $instance) {
                $preferences->mods[$modname]->instances[$instance->id]->name = $instance->name;
                $var = 'backup_'.$modname.'_instance_'.$instance->id;
                $preferences->$var = true;
                $preferences->mods[$modname]->instances[$instance->id]->backup = true;
                $var = 'backup_user_info_'.$modname.'_instance_'.$instance->id;
                $preferences->$var = 0;
                $preferences->mods[$modname]->instances[$instance->id]->userinfo = 0;
                $var = 'backup_'.$modname.'_instances';
                $preferences->$var = 1; // We need this later to determine what to display in modcheckbackup.
            }

            //Check data
            //Check module info
            $preferences->mods[$modname]->name = $modname;

            $var = "backup_".$modname;
            $preferences->$var = true;
            $preferences->mods[$modname]->backup = true;

            //Check include user info
            $var = "backup_user_info_".$modname;
            $preferences->$var = 0;
            $preferences->mods[$modname]->userinfo = 0;
        }
    }

    //Check other parameters
    $preferences->backup_metacourse = 0;
    $preferences->backup_users = 2; // no users at all is 2
    $preferences->backup_logs = 0;
    $preferences->backup_user_files = 0;
    $preferences->backup_course_files = 1;
    $preferences->backup_site_files = 1;
    $preferences->backup_messages = 0;
    $preferences->backup_gradebook_history = 0;
    $preferences->backup_blogs = 0;
    $preferences->backup_course = $course->id;
    publishflow_backup_check_mods($course, $preferences);
    backup_add_static_preferences($preferences);
    return $preferences;
}

/**
 * This function activates in all mods complementary data preparation for backup
 * such as module specific ids to save in backup_ids table before running backup execution
 * @param object ref $course the current course information
 * @param object ref $backupprefs backup preferences telling what to backup (artificially generated)
 */
function publishflow_backup_check_mods(&$course, $backupprefs) {
    global $CFG,$DB;

    if ($allmods = $DB->get_records('modules')) {
        foreach ($allmods as $mod) {
            $modname = $mod->name;
            $modfile = $CFG->dirroot.'/mod/'.$modname.'/backuplib.php';
            if (!file_exists($modfile)) {
                continue;
            }
            require_once($modfile);
            $modbackup = $modname."_backup_mods";
            //If exists the lib & function
            $var = "exists_".$modname;
            if (isset($backupprefs->$var) && $backupprefs->$var) {
                $var = "backup_".$modname;
                // Only if selected.
                if (!empty($backupprefs->$var) and ($backupprefs->$var == 1)) {
                    //Now look for user-data status
                    $var = "backup_user_info_".$modname;
                    //Print the user info
                    //Call the check function to show more info
                    $modcheckbackup = $modname."_check_backup_mods";
                    $var = $modname.'_instances';
                    $instancestopass = array();
                    if (!empty($backupprefs->$var) && is_array($backupprefs->$var) && count($backupprefs->$var)) {
                        $table->data = array();
                        $countinstances = 0;
                        foreach ($backupprefs->$var as $instance) {
                            $var1 = 'backup_'.$modname.'_instance_'.$instance->id;
                            $var2 = 'backup_user_info_'.$modname.'_instance_'.$instance->id;
                            if (!empty($backupprefs->$var1)) {
                                $obj = new StdClass;
                                $obj->name = $instance->name;
                                $obj->userdata = $backupprefs->$var2;
                                $obj->id = $instance->id;
                                $instancestopass[$instance->id] = $obj;
                                $countinstances++;
                            }
                        }
                    }
                    // Void result here as we are silently backuping.
                    $result = $modcheckbackup($course->id, $backupprefs->$var, $backupprefs->backup_unique_code, $instancestopass);
                }
            }
        }
    }
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

    $deploycat = $DB->get_record('course_categories', array('id' => $category));

    //lets get the publishflow published file. 
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
    // confirm/force guest closure

    $file = array_pop ($backupfiles);
    $newcourse_id =  restore_automation::run_automated_restore($file->get_id(), null, $category) ;

    // Confirm/force idnumber in new course.
    $response = new StdClass();
    $response->courseid = $newcourse_id;
    $DB->set_field('course', 'idnumber', $sourcecourse->idnumber, array('id' => "{$newcourse_id}"));

    // confirm/force not enrollable // enrolling will be performed by master teacher

    // assign the localuser as author in all cases :
    // deployement : deployer will unassign self manually if needed
    // free use deployement : deployer will take control over session
    // retrofit : deployer will take control over new learning path in work
    $coursecontext = context_course::instance($newcourse_id);
    $teacherrole = $DB->get_field('role', 'id', array('shortname' => 'teacher'));
    role_assign($teacherrole, $USER->id, $coursecontext->id);

    return ($newcourse_id);
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

//************************ UNDER THIS LINE, MERGE FROM COURSEDELIVERY **********************
/// Library of functions and constants for module label

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

//****************** Menu functions  **********************

/**
* builds the bloc content in case Moodle has a pure
* "factory" behaviour
* @param object $block the block instance
*/
function block_build_factory_menu($block) {
    global $CFG, $DB, $USER, $COURSE, $OUTPUT, $MNET, $PAGE;

    $config = get_config('block_publishflow');

    $output = '';
    $coursecontext = context_course::instance($COURSE->id);
    $systemcontext = context_system::instance();

    // We are going to define where the catalog is. There can only be one catalog in the neighbourhood.
    if (@$config->moodlenodetype == 'factory,catalog') {
        $mainhiost = $DB->get_record('mnet_host', array('wwwroot' => $CFG->wwwroot));
    } else {
        if (!$catalog = $DB->get_record('block_publishflow_catalog', array('type' => 'catalog'))) {
            $output .= $OUTPUT->notification(get_string('nocatalog','block_publishflow'), 'notifyproblem', true);
            return $output;
        }
        $mainhost = $DB->get_record('mnet_host', array('id' => $catalog->platformid));
    }

    if (has_capability('block/publishflow:publish', $coursecontext) || block_publishflow_extra_publish_check()) {
        // First check we have backup.

        $realpath = delivery_check_available_backup($COURSE->id);
        if (empty($realpath)) {
            $dobackupstr = get_string('dobackup', 'block_publishflow');
            $output .= $OUTPUT->notification(get_string('unavailable','block_publishflow'), 'notifyproblem', true);
            $output .= "<center>";
            $output .= "<form name=\"makebackup\" action=\"{$CFG->wwwroot}/blocks/publishflow/backup.php\" method=\"GET\">";
            $output .= "<input type=\"hidden\" name=\"id\" value=\"{$COURSE->id}\" />";
            $output .= "<input type=\"submit\" name=\"go_btn\" value=\"$dobackupstr\" />";
            $output .= "</form>";
            $output .= "</center>";
            //$this->content->footer = '';
            return $output;
        }
        $output .= '<center>';
        // check for published status. We use get remote sessions here
        include_once($CFG->dirroot."/mnet/xmlrpc/client.php");
        if (!$MNET){
            $MNET = new mnet_environment();
            $MNET->init();
        }

        // We have to check for sessions in some catalog.
        // note 
        $rpcclient = new mnet_xmlrpc_client();
        $rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_get_sessions');
        $remoteuserhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid));
        $caller->username = $USER->username;
        $caller->remoteuserhostroot = $remoteuserhost->wwwroot;
        $caller->remotehostroot = $CFG->wwwroot;
        $rpcclient->add_param($caller, 'struct');
        $rpcclient->add_param($COURSE->idnumber, 'int');
        $rpcclient->add_param(1, 'int');
        $mnethost = new mnet_peer();
        $mnethost->set_wwwroot($mainhost->wwwroot);
        if (!$rpcclient->send($mnethost)) {
           $output .= get_string('unavailable', 'block_publishflow');
            if ($CFG->debug) {
                echo $OUTPUT->notification('Publish Status Call Error : ' . implode("\n", $rpcclient->error), 'notifyproblem');
            }
        }

        // get results and process
        // print_object($rpcclient->response);
        $sessioninstances = $rpcclient->response;
        $sessions = json_decode($sessioninstances);
        if ($sessions->status == 200) {
            // Check and print publication.
            $published = UNPUBLISHED;
            $visiblesessions = array();
            if (!empty($sessions->sessions)) {
                foreach ($sessions->sessions as $session) {
                    $published = ($published == UNPUBLISHED) ? PUBLISHED_HIDDEN : $published ; // Capture published.
                    if ($session->visible) { 
                        $published = PUBLISHED_VISIBLE; // Locks visible.
                        $visiblesessions[] = $session;
                    }
                }
            }
            // Prepare common options.
            $options['fromcourse'] = $COURSE->id;
            $options['where'] = $mainhost->id;

            switch ($published) {
                case PUBLISHED_VISIBLE :
                    // if a course is already published, we should propose to replace it with a new
                    // volume content. This will be done hiding all previous references to that Learning Path
                    // and installing the new one in catalog. 
                    // Older course sessions will not be affected by this, as their own content will not
                    // be changed.
                    // Learning Objects availability is guaranteed by the LOR not being able to discard
                    // validated material. 
                    $output .= get_string('alreadypublished', 'block_publishflow');
                    foreach ($visiblesessions as $session) {
                        $courseurl = urlencode('/course/view.php?id='.$session->id);
                        if ($mainhost->id == $USER->mnethostid) {
                           $output .= "<li><a href=\"{$mainhost->wwwroot}/course/view.php?id={$session->id}\">{$session->fullname}</a></li>"; 
                        } else {
                          $output .= "<li><a href=\"{$CFG->wwwroot}/auth/mnet/jump.php?hostid={$mainhost->id}&amp;wantsurl={$courseurl}\">{$session->fullname}</a></li>"; 
                        }
                    }
                    // unpublish button
                    $btn = get_string('unpublish', 'block_publishflow');
                    $confirm = get_string('unpublishconfirm', 'block_publishflow');
                    $tooltip = get_string('unpublishtooltip', 'block_publishflow');
                    $options['what'] = 'unpublish';
                    $button = new single_button(new moodle_url('/blocks/publishflow/publish.php', $options), $btn, 'get');
                    $button->tooltip = $tooltip;
                    $button->add_confirm_action($confirm);
                    $output .= $OUTPUT->render($button);
                    $btn = get_string('republish', 'block_publishflow');
                    $confirm = get_string('republishconfirm', 'block_publishflow');
                    $tooltip = get_string('republishtooltip', 'block_publishflow');
                    $options['what'] = 'publish';
                    $options['forcerepublish'] = 1;
                    break;

                case PUBLISHED_HIDDEN : {
                   $output .= get_string('publishedhidden', 'block_publishflow');
                    $btn = get_string('publish', 'block_publishflow');
                    $confirm = get_string('publishconfirm', 'block_publishflow');
                    $tooltip = get_string('publishtooltip', 'block_publishflow');
                    $options['what'] = 'publish';
                    $options['forcerepublish'] = 0;
                    break ;
                }

                default :
                    // If course is not published, publish it.
                    $output .= get_string('notpublishedyet', 'block_publishflow');
                    $btn = get_string('publish', 'block_publishflow');
                    $confirm = get_string('publishconfirm', 'block_publishflow');
                    $tooltip = get_string('publishtooltip', 'block_publishflow');
                    $options['what'] = 'publish';
            }

            // Make publish form.
            $options['fromcourse'] = $COURSE->id;
            $options['where'] = $mainhost->id;
            $button = new single_button(new moodle_url('/blocks/publishflow/publish.php', $options), $btn, 'get');
            $button->tooltip = $tooltip;
            $button->add_confirm_action($confirm);
            $output .= $OUTPUT->render($button);
            $output .= '<hr/></center>';
        } else {
            if ($CFG->debug) {
                echo $OUTPUT->notification("Error {$sessions->status} : {$sessions->error}");
            }
        }
    }

    // Add the test deployment target.
    if (($USER->mnethostid != $mainhost->id && 
            $USER->mnethostid != $CFG->mnet_localhost_id) || 
                    has_capability('block/publishflow:deployeverywhere', $systemcontext)) {
        if (has_capability('block/publishflow:deploy', $coursecontext) ||
                has_capability('block/publishflow:deployeverywhere', $systemcontext)) {

            $PAGE->requires->js ('/blocks/publishflow/js/block_js.js');
            $hostsavailable = $DB->get_records('block_publishflow_catalog', array('type' => 'learningarea'));
            $fieldsavailable = $DB->get_records_select('user_info_field', 'shortname like \'access%\'');
            $userhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid));
            $deploy_btn = get_string('deploy', 'block_publishflow');
            $output .= '<h3>'.get_string('deployfortest', 'block_publishflow').' '.$OUTPUT->help_icon('deployfortest', 'block_publishflow', false).'</h3>';
            $output .= '<form name="deployform" method="post" action="/blocks/publishflow/deploy.php">';
            $output .= '<div class="selector" align="center">';
            
            $output .= "<input type=\"hidden\" name=\"id\" value=\"{$block->instance->id}\" />";
            $output .= "<input type=\"hidden\" name=\"fromcourse\" value=\"{$COURSE->id}\" />";
            $output .= "<input type=\"hidden\" name=\"what\" value=\"deploy\" />";
            $output .= "<select id=\"publishflow-target-select\" name=\"where\" size=\"1\" onchange=\"doStuff(this, '{$CFG->wwwroot}');\">";
            $output .= "<option value='0' selected='selected'>".get_string('defaultplatform', 'block_publishflow')."</option>";
            $accessfields = $DB->get_records_select('user_info_field', ' shortname LIKE "access%" ');
            foreach ($hostsavailable as $host) {
                $platform = $DB->get_record('mnet_host', array('id' => $host->platformid));
                //If we cant deploy everywhere, we see if we are Remote Course Creator. Then, the access fields are use for further checking
                if (!has_capability('block/publishflow:deployeverywhere', $systemcontext)) {
                    if (has_capability('block/publishflow:deploy', $systemcontext)) {
                        if ($accessfields) {
                            foreach ($accessfields as $field) {
                                //We don't need to check if the user doesn't have the required field
                                if ($userallowedfields = $DB->get_record('user_info_data', array('userid' => $USER->id, 'fieldid' => $field->id))) {
                                    //We get the host prefix corresponding to the host
                                    preg_match('/http:\/\/([^.]*)/', $platform->wwwroot, $matches);
                                    $hostprefix = $matches[1];
                                    $hostprefix = strtoupper($hostprefix);
                                    //We try to match it to the field
                                       if (preg_match('/access'.$hostprefix.'/', $field->shortname)) {
                                           $output .='<option value='.$host->platformid.' > '.$platform->name.' </option>';
                                    }
                                }
                            }
                        }
                    }
                } else {
                    $output .='<option value='.$host->platformid.' > '.$platform->name.' </option>';
                }
            }
            $output .= '</select>';
            $output .= '</div>';
            //Creating the second list that will be populated by the Ajax Script
            $output .= '<div class="selector" id="category-div" name="category-div" align="center"></div>';
            $output .= "<input type=\"button\" name=\"deploy\" value=\"$deploy_btn\" onclick=\"document.forms['deployform'].submit();\" align=\"center\" />";
            $output .= '</form>';
            $output .= "<script type=\"text/javascript\">window.onload=doStuff(0, '{$CFG->wwwroot}');</script>";
        }
    }

    return $output;
}

/**
 * builds the bloc content in case Moodle combines
 * "factory" and "catalog" behaviour. It fits to pure "catalog" situaiton.
 * @param object $block the block instance
 */
function block_build_catalogandfactory_menu($block) {
    global $CFG, $DB, $USER, $COURSE, $OUTPUT, $PAGE;

    /// propose deployment on authorized satellites
    // first check we have backup

    $coursecontext = context_course::instance($COURSE->id);
    $systemcontext = context_system::instance();
  
    $output = '';
    $realpath = delivery_check_available_backup($COURSE->id);
    if (empty($realpath)) {
        $dobackupstr = get_string('dobackup', 'block_publishflow');
        $output .= $OUTPUT->notification(get_string('unavailable', 'block_publishflow'), 'notifyproblem', true);
        $output .= '<center>';
        $backupurl = new moodle_url('/blocks/publishflow/backup.php');
        $output .= '<form name="makebackup" action="'.$backupurl.'" method="GET">';
        $output .= '<input type="hidden" name="id" value="'.$COURSE->id.'" />';
        $output .= '<input type="submit" name="go_btn" value="'.$dobackupstr.'" />';
        $output .= '</form>';
        $output .= '</center>';
        //$this->content->footer = '';
        return $output;
    }

    if (has_capability('block/publishflow:deploy', $systemcontext) ||
            has_capability('block/publishflow:deployeverywhere', $systemcontext) ||
                    block_publishflow_extra_deploy_check()) {

        $PAGE->requires->js ('/blocks/publishflow/js/block_js.js');
        $hostsavailable = $DB->get_records('block_publishflow_catalog', array('type' => 'learningarea'));
        $fieldsavailable = $DB->get_records_select('user_info_field', 'shortname like \'access%\'');
        $userhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid));

        if (has_capability('block/publishflow:deploy', $systemcontext) ||
                block_publishflow_extra_deploy_check()) {
            $deployoptions['0'] = get_string('defaultplatform', 'block_publishflow');
        }

        $userhostroot = $DB->get_field('mnet_host', 'wwwroot', array('id' => $USER->mnethostid));
        if (!empty($hostsavailable)) {
            foreach ($hostsavailable as $host) {
                $platform = $DB->get_record('mnet_host', array('id' => $host->platformid));
                //If we cant deploy everywhere, we see if we are Remote Course Creator. Then, the access fields are use for further checking
                if (!has_capability('block/publishflow:deployeverywhere', $systemcontext)) {
                    if (has_capability('block/publishflow:deploy', $systemcontext) ||
                            block_publishflow_extra_deploy_check()) {
                        // check remotely for each host
                        $rpcclient = new mnet_xmlrpc_client();
                        $rpcclient->set_method('blocks/publishflow/rpclib.php/publishflow_rpc_check_user');
                        $user->username = $USER->username;
                        $user->remoteuserhostroot = $userhostroot;
                        $user->remotehostroot = $CFG->wwwroot;
                        $rpcclient->add_param($user, 'struct');
                        $rpcclient->add_param('block/publishflow:deploy', 'string');
                        $mnet_host = new mnet_peer();
                        $mnet_host->set_wwwroot($platform->wwwroot);
                        if (!$rpcclient->send($mnet_host)) {
                            if (debugging(DEBUG_DEVELOPER)) {
                                print_object($rpcclient);
                            }
                            print_error('failed', 'block_publishflow');
                        }
                        $response = json_decode($rpcclient->response);
                        if ($response->status == RPC_SUCCESS) {
                            $deployoptions[$host->platformid] = $platform->name;
                        }
                    }
                } else {
                    $deployoptions[$host->platformid] = $platform->name;
                }
            }
        }

        // Make the form and the list.
        $formurl = new moodle_url('/blocks/publishflow/deploy.php');
        $output .= '<form name="deployform" method="post" action="'.$formurl.'">';
        $output .= '<div class="selector" align="center">';
        $output .= '<input type="hidden" name="id" value="'.$block->instance->id.'" />';
        $output .= '<input type="hidden" name="fromcourse" value="'.$COURSE->id.'" />';
        $output .= '<input type="hidden" name="what" value="deploy" />';
        $output .= get_string('choosetarget', 'block_publishflow');

        if (!empty($deployoptions)) {
            $output .= "<select id=\"publishflow-target-select\" name=\"where\" size=\"1\" onchange=\"doStuff(this, '{$CFG->wwwroot}');\">";
            foreach ($deployoptions as $key => $option) {
                $output .= "<option value='{$key}'>$option</option>";
            }
            $output .= '</select>';

            //Creating the second list that will be populated by the Ajax Script
            if (@$block->config->allowfreecategoryselection) {
                $output .= get_string('choosecat', 'block_publishflow');
                $output .= '<div class="selector" id="category-div" align="center"></div>';
                $output .= "<script type=\"text/javascript\">window.onload=doStuff(0, '{$CFG->wwwroot}');</script>";
            }
            if (!empty($block->config->deploymentkey)) {
                $output .= '<br/>';
                $output .= get_string('enterkey', 'block_publishflow');
                $output .= "<input type=\"password\" name=\"deploykey\" value=\"\" size=\"8\" maxlength=\"10\" />";
            }
            $deploy_btn = get_string('deploy', 'block_publishflow');
            $output .= "<input type=\"button\" name=\"deploy\" value=\"$deploy_btn\" onclick=\"document.forms['deployform'].submit();\" align=\"center\"/>";
            $output .= '</div>';
            $output .= '</form>';
        } else {
            $output .= '<div>'.get_string('nodeploytargets', 'block_publishflow').'</div>';
        }
    }
    return $output;
}

/**
 * builds the bloc content in case Moodle has a pure
 * "training center" behaviour
 * @param object $block the block instance
 */
function block_build_trainingcenter_menu($block) {
    global $CFG, $DB, $USER, $COURSE, $OUTPUT, $PAGE;

    $config = get_config('block_publishflow');

    // students usually do not see this block

    $coursecontext = context_course::instance($COURSE->id);
    $systemcontext = context_system::instance();

    $output = '';

    if (!has_capability('block/publishflow:retrofit', $coursecontext) &&
            !has_capability('block/publishflow:manage', $coursecontext) &&
                !block_publishflow_extra_retrofit_check()) {
       // $this->content->footer = '';
        //return $this->content;
    }

    // in a learning area, we are refeeding the factory and propose to close the training session
    if (!empty($config->enableretrofit)) {
        $retrofitstr = get_string('retrofitting', 'block_publishflow');
        $retrofithelpstr = $OUTPUT->help_icon('retrofit', 'block_publishflow', false);
        $output .= "<b>$retrofitstr</b>$retrofithelpstr<br/>";
        $output .= '<center>';
        // try both strategies, using the prefix directly in mnethosts or the catalog records
        // there should be only one factiry. The first in the way will be considered
        // further records will be ignored
        $factoriesavailable = $DB->get_records_select('block_publishflow_catalog'," type LIKE '%factory%' ");

        // Alternative strategy.
        if (!$factoriesavailable) {
            $select = (!empty($CFG->factoryprefix)) ? " wwwroot LIKE 'http://{$CFG->factoryprefix}%' " : '';
            if ($select != '') {
                $factoryhost = $DB->get_record_select('mnet_host', $select);
            }
        } else {
            $factory = array_pop($factoriesavailable);
            $factoryhost = $DB->get_record('mnet_host', array('id' => $factory->platformid));
        }

        if (empty($factoryhost)) {
            $output .= $OUTPUT->notification(get_string('nofactory', 'block_publishflow'), 'notifyproblem', true);
        } else {
            $realpath = delivery_check_available_backup($COURSE->id);

            if (empty($realpath)) {
                $dobackupstr = get_string('dobackup', 'block_publishflow');
                $output .= $OUTPUT->notification(get_string('unavailable','block_publishflow'), 'notifyproblem', true);
                $output .= '<center>';
                $backupurl = new moodle_url('/blocks/publishflow/backup.php');
                $output .= '<form name="makebackup" action="'.$backupurl.'" method="GET">';
                $output .= '<input type="hidden" name="id" value="'.$COURSE->id.'" />';
                $output .= '<input type="submit" name="go_btn" value="'.$dobackupstr.'" />';
                $output .= '</form>';
                $output .= '</center>';
                //$this->content->footer = '';
                return $output;
            } else {
                $strretrofit = get_string('retrofit', 'block_publishflow');
                // should be given to entity author marked users
                if (has_capability('block/publishflow:retrofit', $coursecontext) ||
                        block_publishflow_extra_retrofit_check()){
                    $params = array('fromcourse' => $COURSE->id, 'what' => 'retrofit', 'where' => $factoryhost->id);
                    $retrofiturl = new moodle_url('/blocks/publishflow/retrofit.php', $params);
                    $output .= '<a href="'.$retrofiturl.'">'.$strretrofit.'</a><br/><br/>';
                }
            }
        }
    }
    $strclose = get_string('close', 'block_publishflow');
    $stropen = get_string('open', 'block_publishflow');
    $strreopen = get_string('reopen', 'block_publishflow');
    $coursecontrolstr =  get_string('coursecontrol', 'block_publishflow');
    /*
     * Should be given to entity trainers (mts)
     * we need also fix the case where all categories are the same
     */
    if (has_capability('block/publishflow:manage', $coursecontext)) {
        $output .= '<div class="block_publishflow_coursecontrol">';
        $output .= "<b>$coursecontrolstr</b><br/>";
        if ($COURSE->category == $config->runningcategory && $COURSE->visible) {
            $closeurl = new moodle_url('/blocks/publishflow/close.php', array('fromcourse' => $COURSE->id, 'what' => 'close'));
            $output .= '<a href="'.$closeurl.'">'.$strclose.'</a>';
        } else if ($COURSE->category == $config->deploycategory) {
            $openurl = new moodle_url('/blocks/publishflow/open.php', array('fromcourse' => $COURSE->id, 'what' => 'open'));
            $output .= '<a href="'.$openurl.'">'.$stropen.'</a>';
        } else if ($COURSE->category == $config->closedcategory) {
            $openurl = new moodle_url('/blocks/publishflow/open.php', array('fromcourse' => $COURSE->id, 'what' => 'open'));
            $output .= '<a href="'.$openurl.'">'.$strreopen.'</a>';
        }
        $output .= '</div>';
    }
    $output .= '</center>';

    return $output;
}

function block_publishflow_cron_network_refreshment() {
    global $DB;

    $hosts = $DB->get_records('mnet_host', array('deleted' => 0));

    foreach ($hosts as $host) {
        if ($host->applicationid != $DB->get_field('mnet_application', 'id', array('name' => 'moodle'))) {
            continue;
        }
        if (!($host->name) == "" && !($host->name == "All Hosts")) {
            $result = block_publishflow_update_peer($host);
            if (empty($result)) {
                echo $host->name.get_string('updateok', 'block_publishflow');
            } else {
                echo $result;
            }
        }
    }
}

function block_publishflow_update_peer($host) {
    global $DB, $USER, $CFG;

    $hostcatalog = $DB->get_record('block_publishflow_catalog', array('platformid' => $host->id));

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