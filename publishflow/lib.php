<?php

require_once($CFG->dirroot."/blocks/publishflow/backup/restore_automation.class.php");
/**
*
*/

// fakes a debug library if missing
if (!function_exists('debug_trace')){
	function debug_trace($str){
	}
}

/**
*
*/

define('COURSE_CLOSE_CHOOSE_MODE', 0);
define('COURSE_CLOSE_EXECUTE', 1);

define('COURSE_CLOSE_PUBLIC', 0);
define('COURSE_CLOSE_PROTECTED', 1);
define('COURSE_CLOSE_PRIVATE', 2);

define('COURSE_OPEN_CHOOSE_OPTIONS', 0);
define('COURSE_OPEN_EXECUTE', 1);

/**
*
*/

/**
* opens a training session. Opening changes the course category, if setup in 
* publishflow site configuration, and may send notification to enrolled users
* @param object $course the course information
* @param boolean $notify
*/
function publishflow_session_open($course, $notify){
    global $CFG, $SITE,$DB;

    //reopening is allowed
    if ($course->category == @$CFG->coursedelivery_deploycategory || $course->category == @$CFG->coursedelivery_closedcategory){
        $course->category = @$CFG->coursedelivery_runningcategory;
    }

    $course->visible = 1; 
    $course->guest = 0; 
    $course->startdate = time(); 
    $DB->update_record('course', addslashes_recursive($course));
    $context = context_course::instance($course->id);
    /// revalidate disabled people
    $role = $DB->get_record('role', array('shortname' => 'student'));
    $disabledrole = $DB->get_record('role', array('shortname' => 'disabledstudent'));
    if ($pts = get_users_from_role_on_context($disabledrole, $context)){
        foreach($pts as $pt){
            role_unassign($disabledrole->id, $pt->userid, null, $context->id);
            role_assign($role->id, $pt->userid, null, $context->id);
        }
    }

    $notify = required_param('notify', PARAM_INT);
    if ($notify){
        /// send notification to all enrolled members
        if ($users = get_users_by_capability($context, 'moodle/course:view', 'u.id, firstname, lastname, email, emailstop, mnethostid, mailformat')){                
            $infomap = array( 'SITE_NAME' => $SITE->shortname,
                              'MENTOR' => fullname($USER),
                              'DATE' => userdate(time()),
                              'COURSE' => $course->fullname,
                              'URL' => $CFG->wwwroot."/course/view.php?id={$course->id}");
            $rawtemplate = compile_mail_template('open_course_session', $infomap, 'local');
            $htmltemplate = compile_mail_template('open_course_session_html', $infomap, 'local');
            foreach($users as $user){
                email_to_user($user, $USER, get_string('sessionopening', 'block_publishflow', $SITE->shortname.':'.format_string($COURSE->shortname)), $rawtemplate, $htmltemplate);
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
function publishflow_course_close($course, $mode, $rpccall = false){
    global $CFG;
    if (!$course){
    	if (!$rpccall){
	        error("Cannot close null course");
	    } else {
	    	return "Cannot close null course";
	    }
    }
    if (empty($CFG->coursedelivery_closedcategory)){
    	if (!$rpccall){
        	error("Publish flow is not properly configured for closing courses");
        } else {
        	return "Publish flow is not properly configured for closing courses";
        }
    }

    $context = context_course::instance($course->id);
    if ($course->category == @$CFG->coursedelivery_runningcategory || $course->category == @$CFG->coursedelivery_deploycategory){
        $course->category = $CFG->coursedelivery_closedcategory;
    }

    switch($mode){
        case COURSE_CLOSE_PUBLIC:
            // open course for guests
            $course->guest = 1;
            $course->visible = 1;
            $course->enrollable = 0;
            // get all students and reassign them as disabledstudents
            $role = $DB->get_record('role', array('shortname' => 'student'));
            $disabledrole = $DB->get_record('role', array('shortname' => 'disabledstudent'));
            if ($pts = get_users_from_role_on_context($role, $context)){
                foreach($pts as $pt){
                    role_unassign($role->id, $pt->userid, null, $context->id);
                    role_assign($disabledrole->id, $pt->userid, null, $context->id);
                }
            }                    
        break;
        case COURSE_CLOSE_PROTECTED:
            $course->guest = 0;
            $course->visible = 1;
            $course->enrollable = 0;

            // get all students and reassign them as disabledstudents
            $role = $DB->get_record('role', array('shortname' => 'student'));
            $disabledrole = $DB->get_record('role', array('shortname' => 'disabledstudent'));
            $context = context_course::instance($course->id);
            if ($pts = get_users_from_role_on_context($role, $context)){
                foreach($pts as $pt){
                    role_unassign($role->id, $pt->userid, null, $context->id);
                    role_assign($disabledrole->id, $pt->userid, null, $context->id);
                }
            }
        break;
        case COURSE_CLOSE_PRIVATE:
            $course->guest = 0;
            $course->visible = 0;
            $course->enrollable = 0;
            // do not unassign people
        break;
        default:
        	if (!$rpccall){
	            print_error('Bad closing mode');
			} else {
				return 'Bad closing mode';
			}
    }
    $DB->update_record('course', addslashes_recursive($course));
}

/**
* overrides backup/lib.php/backup_generate_preferences_artificially($course, $prefs)
* discarding all user's stuff
* @param object $course the current course object
*/
function publishflow_backup_generate_preferences($course) {
    global $CFG,$DB;
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
            $preferences->$varname = get_all_instances_in_course($modname, $course, NULL, true);
            foreach ($preferences->$varname as $instance) {
                $preferences->mods[$modname]->instances[$instance->id]->name = $instance->name;
                $var = 'backup_'.$modname.'_instance_'.$instance->id;
                $preferences->$var = true;
                $preferences->mods[$modname]->instances[$instance->id]->backup = true;
                $var = 'backup_user_info_'.$modname.'_instance_'.$instance->id;
                $preferences->$var = 0;
                $preferences->mods[$modname]->instances[$instance->id]->userinfo = 0;
                $var = 'backup_'.$modname.'_instances';
                $preferences->$var = 1; // we need this later to determine what to display in modcheckbackup.
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
function publishflow_backup_check_mods(&$course, $backupprefs){
	global $CFG,$DB;
    if ($allmods = $DB->get_records("modules") ) {
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
                //Only if selected
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
                    // void result here as we are silently backuping
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
function publishflow_local_deploy($category, $sourcecourse){
    global $CFG, $USER, $DB;

    include_once $CFG->dirroot."/backup/restorelib.php";
    include_once $CFG->dirroot."/backup/lib.php";

    $deploycat = $DB->get_record('course_categories', array('id' => $category));
    
    //lets get the publishflow published file. 
    $coursecontextid = get_context_instance(CONTEXT_COURSE,$sourcecourse->id)->id;
    $fs = get_file_storage();
    $backupfiles = $fs->get_area_files($coursecontextid,'backup','publishflow',0,"timecreated",false);
    
    if(!$backupfiles)
    {
        print_error("course is not published,please publish first.");
    }

    
    $file = array_pop ($backupfiles);
    $newcourse_id =  restore_automation::run_automated_restore($file->get_id(),null,$category) ;
    
    /*
    $course_header->category = $deploycat;
    $course_header->course_id = $sourcecourse->id;
    $course_header->course_password = '';
    $course_header->course_fullname = $sourcecourse->fullname;
    $course_header->course_shortname = $sourcecourse->shortname;
    $course_header->course_idnumber = $sourcecourse->idnumber;
    $course_header->course_summary = $sourcecourse->summary;
    $course_header->course_format = $sourcecourse->format;
    $course_header->course_showgrades = 0;
    $course_header->course_newsitems = 0;
    $course_header->course_teacher = '';
    $course_header->course_teachers = '';
    $course_header->course_student = '';
    $course_header->course_students = '';
    $course_header->course_guest = '';
    $course_header->course_startdate = 0;
    $course_header->course_numsections = $sourcecourse->numsections;
    $course_header->course_maxbytes = $sourcecourse->maxbytes;
    $course_header->course_showreports = $sourcecourse->showreports;
    $course_header->course_lang = 'fr_utf8';
    $course_header->course_theme = '';
    $course_header->course_cost = $sourcecourse->cost;
    $course_header->course_marker = '';
    $course_header->course_visible = 1;
    $course_header->course_hiddensections = '';
    $course_header->course_timecreated = time();
    $course_header->course_timemodified = time();
    $course_header->course_metacourse = 0;
    $course_header->course_enrolperiod = $sourcecourse->enrolperiod;

    // set in sessions for calling to restore_execute
    $SESSION->course_header = $course_header;       

    // hack the standard category determination
    $restore->restore_restorecatto = ''; // forces considering course_header override.
    $restore->course_startdateoffset = 0;
    $restore->metacourse = 0;
    $restore->backup_unique_code = time();

    restore_create_new_course($restore, $course_header);

    import_backup_file_silently($realpath, $course_header->course_id, true, false, array('restore_course_files' => 1));
    */
    // confirm/force idnumber in new course
    $response->courseid = $newcourse_id;
    $DB->set_field('course', 'idnumber', $sourcecourse->idnumber, array('id' => "{$newcourse_id}"));

    ini_set('max_execution_time', $maxtime);
    ini_set('memory_limit', $maxmem);
    // confirm/force guest closure

    //$DB->set_field('course', 'guest', 0, array('id' => "{$newcourse_id}"));


    // confirm/force not enrollable // enrolling will be performed by master teacher

    //$DB->set_field('course', 'enrollable', 0, array('id' => "{$newcourse_id}"));


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
*
*/
function publishflow_get_remote_categories($hostid, &$cats, $parent = 0, $maxdepth = 0){
     global $DB;
	static $depth = 0;

	if ($maxdepth > 0){
		if ($depth == $maxdepth){
			return;
		}
	}
   	if ($catmenu = $DB->get_records_select('block_publishflow_remotecat', " parentid = $parent AND platformid = $hostid ")){
		foreach($catmenu as $cat){
		
        	$catentry = new stdClass();
			$catentry->id = $cat->originalid;
			$catentry->name = str_repeat("&nbsp;", $depth).$cat->name;			
			$cats[] = $catentry;
			$depth++;
			publishflow_get_remote_categories($hostid, $cats, $catentry->id, $maxdepth);
			$depth--;
		}
	}
}

//************************ UNDER THIS LINE, MERGE FROM COURSEDELIVERY **********************
/// Library of functions and constants for module label

/**
 * A standard hook at install time
 *
function publishflow_install() {
    global $DB;
    $result = true;
    // installing TAO Data services
    if (!$DB->get_record('mnet_service', array('name' => 'coursedelivery_data'))){
        $service->name = 'coursedelivery_data';
        $service->description = get_string('coursedelivery_data_name', 'block_publishflow');
        $service->apiversion = 1;
        $service->offer = 1;
        if (!$serviceid = $DB->insert_record('mnet_service', $service)){
            echo $OUTPUT->notification('Error installing coursedelivery_data service.');
            $result = false;
        }
        $rpc->function_name = 'delivery_get_sessions';
        $rpc->xmlrpc_path = 'block/publishflow/rpclib.php/delivery_get_sessions';
        $rpc->parent_type = 'block';  
        $rpc->parent = 'publishflow';
        $rpc->enabled = 0; 
        $rpc->help = 'Get remote instances of LP sessions.';
        $rpc->profile = '';
        if (!$rpcid = $DB->insert_record('mnet_rpc', $rpc)){
            echo $OUTPUT->notification('Error installing delivery_data RPC calls.');
            $result = false;
        }
        $rpcmap->serviceid = $serviceid;
        $rpcmap->rpcid = $rpcid;
        $DB->insert_record('mnet_service2rpc', $rpcmap);

        $rpc->function_name = 'delivery_get_catalog';
        $rpc->xmlrpc_path = 'block/publishflow/rpclib.php/delivery_get_catalog';
        $rpc->parent_type = 'block';  
        $rpc->parent = 'publishflow';
        $rpc->enabled = 0; 
        $rpc->help = 'Get remote catalog of available LPs.';
        $rpc->profile = '';
        if (!$rpcid = $DB->insert_record('mnet_rpc', $rpc)){
            echo $OUTPUT->notification('Error installing coursedelivery_data RPC calls.');
            $result = false;
        }
        $rpcmap->serviceid = $serviceid;
        $rpcmap->rpcid = $rpcid;
        $DB->insert_record('mnet_service2rpc', $rpcmap);
    }

    // installing Delivery Admin services
    if (!$DB->get_record('mnet_service', array('name' => 'coursedelivery_admin'))){
        unset($service);
        $service->name = 'coursedelivery_admin';
        $service->description = get_string('coursedelivery_admin_name', 'block_publishflow');
        $service->apiversion = 1;
        $service->offer = 1;
        if (!$serviceid = $DB->insert_record('mnet_service', $service)){
            echo $OUTPUT->notification('Error installing coursedelivery_admin service.');
            $result = false;
        }
        $rpc->function_name = 'delivery_deliver';
        $rpc->xmlrpc_path = 'block/publishflow/rpclib.php/delivery_deliver';
        $rpc->parent_type = 'block';  
        $rpc->parent = 'publishflow';
        $rpc->enabled = 0; 
        $rpc->help = 'Delivers a course archive for remote deployment.';
        $rpc->profile = '';
        if (!$rpcid = $DB->insert_record('mnet_rpc', $rpc)){
            echo $OUTPUT->notification('Error installing tao_admin RPC calls.');
            $result = false;
        }
        $rpcmap->serviceid = $serviceid;
        $rpcmap->rpcid = $rpcid;
        $DB->insert_record('mnet_service2rpc', $rpcmap);

        $rpc->function_name = 'delivery_deploy';
        $rpc->xmlrpc_path = 'block/publishflow/rpclib.php/delivery_deploy';
        $rpc->parent_type = 'block';  
        $rpc->parent = 'publishflow';
        $rpc->enabled = 0; 
        $rpc->help = 'Triggers the deployment of a LP.';
        $rpc->profile = '';
        if (!$rpcid = $DB->insert_record('mnet_rpc', $rpc)){
            echo $OUTPUT->notification('Error installing coursedelivery_admin RPC calls.');
            $result = false;
        }
        $rpcmap->rpcid = $rpcid;
        $DB->insert_record('mnet_service2rpc', $rpcmap);

        $rpc->function_name = 'delivery_publish';
        $rpc->xmlrpc_path = 'block/publishflow/rpclib.php/delivery_publish';
        $rpc->parent_type = 'block';  
        $rpc->parent = 'publishflow';
        $rpc->enabled = 0; 
        $rpc->help = 'Triggers the publication of a LP.';
        $rpc->profile = '';
        if (!$rpcid = $DB->insert_record('mnet_rpc', $rpc)){
            echo $OUTPUT->notification('Error installing coursedelivery_admin RPC calls.');
            $result = false;
        }
        $rpcmap->rpcid = $rpcid;
        $DB->insert_record('mnet_service2rpc', $rpcmap);

        $rpc->function_name = 'publishflow_updateplatforms';
        $rpc->xmlrpc_path = 'block/publishflow/rpclib.php/publishflow_updateplatforms';
        $rpc->parent_type = 'block';  
        $rpc->parent = 'publishflow';
        $rpc->enabled = 0; 
        $rpc->help = 'Get information about network topology.';
        $rpc->profile = '';
        if (!$rpcid = $DB->insert_record('mnet_rpc', $rpc)){
            echo $OUTPUT->notification('Error installing publishflow_updateplatforms RPC call.');
            $result = false;
        }
        $rpcmap->rpcid = $rpcid;
        $DB->insert_record('mnet_service2rpc', $rpcmap);

        $rpc->function_name = 'publishflow_rpc_close_course';
        $rpc->xmlrpc_path = 'block/publishflow/rpclib.php/publishflow_rpc_close_course';
        $rpc->parent_type = 'block';
        $rpc->parent = 'publishflow';
        $rpc->enabled = 0;
        $rpc->help = 'Closes an open training session.';
        $rpc->profile = '';
        if (!$rpcid = $DB->insert_record('mnet_rpc', $rpc)){
            echo $OUTPUT->notification('Error installing publishflow_rpc_close_course RPC call.');
            $result = false;
        }
        $rpcmap->rpcid = $rpcid;
        $DB->insert_record('mnet_service2rpc', $rpcmap);

        $rpc->function_name = 'publishflow_rpc_open_course';
        $rpc->xmlrpc_path = 'block/publishflow/rpclib.php/publishflow_rpc_open_course';
        $rpc->parent_type = 'block';
        $rpc->parent = 'publishflow';
        $rpc->enabled = 0;
        $rpc->help = 'Opens an open training session.';
        $rpc->profile = '';
        if (!$rpcid = $DB->insert_record('mnet_rpc', $rpc)){
            echo $OUTPUT->notification('Error installing publishflow_rpc_open_course RPC call.');
            $result = false;
        }
        $rpcmap->rpcid = $rpcid;
        $DB->insert_record('mnet_service2rpc', $rpcmap);
    }
    return $result;
}

/**
 * A standard hook at uninstall time
 *
function publishflow_uninstall() {
    global $DB;
    $return = true;
    // delete all coursedelivery related mnet services and MNET bindings
    if ($dataservice = $DB->get_record('mnet_service', array('name' => 'coursedelivery_data'))){
        $DB->delete_records('mnet_host2service', array('serviceid' => $dataservice->id));
        $DB->delete_records('mnet_service2rpc', array('serviceid' => $dataservice->id));
    }

    if($adminservice = $DB->get_record('mnet_service', array('name' => 'coursedelivery_admin'))){
        $DB->delete_records('mnet_host2service', array('serviceid' => $adminservice->id));
        $DB->delete_records('mnet_service2rpc', array('serviceid' => $adminservice->id));
    }

    $DB->delete_records('mnet_rpc', array('pluginname' => 'publishflow'));
    $DB->delete_records('mnet_service', array('name' => 'coursedelivery_data'));
    $DB->delete_records('mnet_service', array('name' => 'coursedelivery_admin'));
    return $return;
}

/**
* These three functions are wrappers to other ways to allow people
* to deploy based on role's capabilities. They will conditionnaly wrap to
* local implentation functions that could provide a customized
* strategy for allowing deployement.
* @param int $userid a user ID to check for, defaults to $USER->id
*/
function block_publishflow_extra_deploy_check($userid = null){
	global $USER;
	if (empty($userid)) $userid = $USER->id;

	if (function_exists('local_check_deploy_permission')){
		return local_check_deploy_permission($userid);
	}
	return false;
}

function block_publishflow_extra_publish_check($userid = null){
	global $USER;
	if (empty($userid)) $userid = $USER->id;

	if (function_exists('local_check_publish_permission')){
		return local_check_publish_permission($userid);
	}
	return false;
}

function block_publishflow_extra_retrofit_check($userid = null){
	global $USER;
	if (empty($userid)) $userid = $USER->id;

	if (function_exists('local_check_retrofit_permission')){
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
function block_build_factory_menu($block){
	global $CFG,$DB,$USER,$COURSE,$OUTPUT,$MNET,$PAGE;

	$output = '';
	$context_course = context_course::instance($COURSE->id);
	$context_system = get_context_instance(CONTEXT_SYSTEM);
              
	//We are going to define where the catalog is. There can only be one catalog in the neighbourhood.
	if(@$CFG->moodlenodetype == 'factory,catalog'){
		$mainhost = $DB->get_record('mnet_host', array('wwwroot' => $CFG->wwwroot));
	} else {
		if (!$catalog = $DB->get_record('block_publishflow_catalog', array('type' => 'catalog'))){
			$output .= $OUTPUT->notification(get_string('nocatalog','block_publishflow'), 'notifyproblem', true);    
	        return $output;
	    }
		$mainhost = $DB->get_record('mnet_host', array('id' => $catalog->platformid));
	}
              
	if (has_capability('block/publishflow:publish', $context_course)||block_publishflow_extra_publish_check()){
		// first check we have backup 
		       
		$realpath = delivery_check_available_backup($COURSE->id);
		if (empty($realpath)){
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
		include_once $CFG->dirroot."/mnet/xmlrpc/client.php";
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
		$mnet_host = new mnet_peer();
		$mnet_host->set_wwwroot($mainhost->wwwroot);
		if (!$rpcclient->send($mnet_host)){
		   $output .= get_string('unavailable', 'block_publishflow');
		    if ($CFG->debug){
		        echo $OUTPUT->notification('Publish Status Call Error : ' . implode("\n", $rpcclient->error), 'notifyproblem');
		    }
		}

		// get results and process
		// print_object($rpcclient->response);
		$sessioninstances = $rpcclient->response;
		$sessions = json_decode($sessioninstances);
		if ($sessions->status == 200){
		    // check and print publication
		    $published = UNPUBLISHED;
		    $visiblesessions = array();
		    if (!empty($sessions->sessions)){
		        foreach($sessions->sessions as $session){
		            $published = ($published == UNPUBLISHED) ? PUBLISHED_HIDDEN : $published ; // capture published
		            if ($session->visible) { 
		                $published = PUBLISHED_VISIBLE; // locks visible
		                $visiblesessions[] = $session;
		            }
		        }
		    }
		    // prepare common options
		    $options['fromcourse'] = $COURSE->id;
		    $options['where'] = $mainhost->id;
		    switch ($published){
		        case PUBLISHED_VISIBLE : {
		            // if a course is already published, we should propose to replace it with a new
		            // volume content. This will be done hiding all previous references to that Learning Path
		            // and installing the new one in catalog. 
		            // Older course sessions will not be affected by this, as their own content will not
		            // be changed.
		            // Learning Objects availability is guaranteed by the LOR not being able to discard
		            // validated material. 
		          $output .= get_string('alreadypublished','block_publishflow');
		            foreach($visiblesessions as $session){
		                $courseurl = urlencode('/course/view.php?id='.$session->id);
		                if ($mainhost->id == $USER->mnethostid){
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
		        }
		        break;
		        case PUBLISHED_HIDDEN : {
		           $output .= get_string('publishedhidden','block_publishflow');
		            $btn = get_string('publish', 'block_publishflow');
		            $confirm = get_string('publishconfirm', 'block_publishflow');
		            $tooltip = get_string('publishtooltip', 'block_publishflow');
		            $options['what'] = 'publish';
		            $options['forcerepublish'] = 0;
		        }
		        break ;
		        default : {
		            // if course is not published, publish it                                
		            $output .= get_string('notpublishedyet','block_publishflow');
		            $btn = get_string('publish', 'block_publishflow');
		            $confirm = get_string('publishconfirm', 'block_publishflow');
		            $tooltip = get_string('publishtooltip', 'block_publishflow');
		            $options['what'] = 'publish';
		        }
		    }
		    // make publish form
		    $options['fromcourse'] = $COURSE->id;
		    $options['where'] = $mainhost->id;
		    $button = new single_button(new moodle_url('/blocks/publishflow/publish.php', $options), $btn, 'get');
		    $button->tooltip = $tooltip;
		    $button->add_confirm_action($confirm);
		    $output .= $OUTPUT->render($button);
		    $output .= '<hr/></center>';
		} else {
		    if ($CFG->debug){
		        echo $OUTPUT->notification("Error {$sessions->status} : {$sessions->error}");
		    }
		}
	}

    // Add the test deployment target    
    if (($USER->mnethostid != $mainhost->id && $USER->mnethostid != $CFG->mnet_localhost_id) || has_capability('block/publishflow:deployeverywhere', $context_system)){
        if(has_capability('block/publishflow:deploy', $context_course) || has_capability('block/publishflow:deployeverywhere', $context_system)){
            //require_js (array('yui_yahoo','yui_event','yui_connection'));
            $PAGE->requires->js ('/blocks/publishflow/js/block_js.js');
            $hostsavailable = $DB->get_records('block_publishflow_catalog', array('type' => 'learningarea'));
            $fieldsavailable = $DB->get_records_select('user_info_field','shortname like \'access%\'');
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
            foreach($hostsavailable as $host){
				$platform = $DB->get_record('mnet_host', array('id' => $host->platformid));
                //If we cant deploy everywhere, we see if we are Remote Course Creator. Then, the access fields are use for further checking
                if (!has_capability('block/publishflow:deployeverywhere', $context_system)){
                	if(has_capability('block/publishflow:deploy', $context_system)){
                    	if($accessfields){
                        	foreach($accessfields as $field){
                            	//We don't need to check if the user doesn't have the required field
                                if ($userallowedfields = $DB->get_record('user_info_data', array('userid' => $USER->id, 'fieldid' => $field->id))){
                                    //We get the host prefix corresponding to the host
                                    preg_match('/http:\/\/([^.]*)/', $platform->wwwroot, $matches);
                                    $hostprefix = $matches[1];
                                    $hostprefix = strtoupper($hostprefix);
                                    //We try to match it to the field
                                   	if (preg_match('/access'.$hostprefix.'/', $field->shortname)){                                
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
			$output .= "</select>";
			$output .= "</div>";
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
function block_build_catalogandfactory_menu($block){
	global $CFG,$DB,$USER,$COURSE,$OUTPUT,$PAGE;

	/// propose deployment on authorized satellites
	// first check we have backup
              
    $context_course = context_course::instance($COURSE->id);
    $context_system = get_context_instance(CONTEXT_SYSTEM);
  
    $output = '';                    
    $realpath = delivery_check_available_backup($COURSE->id);
    if (empty($realpath)){
        $dobackupstr = get_string('dobackup', 'block_publishflow');
        $output .= $OUTPUT->notification(get_string('unavailable','block_publishflow'), 'notifyproblem', true);
        $output .= '<center>';
		$output .= "<form name=\"makebackup\" action=\"{$CFG->wwwroot}/blocks/publishflow/backup.php\" method=\"GET\">";
        $output .= "<input type=\"hidden\" name=\"id\" value=\"{$COURSE->id}\" />";
        $output .= "<input type=\"submit\" name=\"go_btn\" value=\"$dobackupstr\" />";
        $output .= '</form>';
        $output .= '</center>';
        //$this->content->footer = '';
        return $output;
    }

    if(has_capability('block/publishflow:deploy', $context_system)||has_capability('block/publishflow:deployeverwhere', $context_system)||block_publishflow_extra_deploy_check()){

        //require_js (array('yui_yahoo', 'yui_event', 'yui_connection'));
        $PAGE->requires->js ('/blocks/publishflow/js/block_js.js');
        $hostsavailable = $DB->get_records('block_publishflow_catalog', array('type' => 'learningarea'));
        $fieldsavailable = $DB->get_records_select('user_info_field', 'shortname like \'access%\'');
        $userhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid));

        if(has_capability('block/publishflow:deploy',$context_system)||block_publishflow_extra_deploy_check()){
            $deployoptions['0'] = get_string('defaultplatform', 'block_publishflow');
        }

        $userhostroot = $DB->get_field('mnet_host', 'wwwroot', array('id' => $USER->mnethostid));
        if (!empty($hostsavailable)){
            foreach($hostsavailable as $host){
				$platform = $DB->get_record('mnet_host', array('id' => $host->platformid));
                //If we cant deploy everywhere, we see if we are Remote Course Creator. Then, the access fields are use for further checking
                if (!has_capability('block/publishflow:deployeverywhere', $context_system)){
                	if (has_capability('block/publishflow:deploy', $context_system) || block_publishflow_extra_deploy_check()){
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
                        if (!$rpcclient->send($mnet_host)){
                            print_object($rpcclient);
                            print_error('failed', 'block_publishflow');
                        }
                        $response = json_decode($rpcclient->response);
                        if ($response->status == RPC_SUCCESS){
	                        $deployoptions[$host->platformid] = $platform->name;
	                    }
					}
                } else {
                    $deployoptions[$host->platformid] = $platform->name;
              	}
        	}
    	}

        // make the form and the list
        $output .= '<form name="deployform" method="post" action="/blocks/publishflow/deploy.php">';
        $output .= '<div class="selector" align="center">';
        $output .= "<input type=\"hidden\" name=\"id\" value=\"{$block->instance->id}\" />";
        $output .= "<input type=\"hidden\" name=\"fromcourse\" value=\"{$COURSE->id}\" />";
        $output .= "<input type=\"hidden\" name=\"what\" value=\"deploy\" />";
        $output .= get_string('choosetarget', 'block_publishflow');

        if (!empty($deployoptions)){
            $output .= "<select id=\"publishflow-target-select\" name=\"where\" size=\"1\" onchange=\"doStuff(this, '{$CFG->wwwroot}');\">";
            foreach($deployoptions as $key => $option){
                $output .= "<option value='{$key}'>$option</option>";
            }
			$output .= '</select>';
                     
            //Creating the second list that will be populated by the Ajax Script
            if (@$block->config->allowfreecategoryselection){
                $output .= get_string('choosecat', 'block_publishflow');
                $output .= '<div class="selector" id="category-div" align="center"></div>';
                $output .= "<script type=\"text/javascript\">window.onload=doStuff(0, '{$CFG->wwwroot}');</script>";
            }
            if (!empty($block->config->deploymentkey)){
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
function block_build_trainingcenter_menu($block){
	global $CFG,$DB,$USER,$COURSE,$OUTPUT,$PAGE;

    // students usually do not see this block
    $context_course = context_course::instance($COURSE->id);
    $context_system = get_context_instance(CONTEXT_SYSTEM);
    $output = '';
    if (!has_capability('block/publishflow:retrofit', $context_course) && !has_capability('block/publishflow:manage', $context_course) && !block_publishflow_extra_retrofit_check()){
       // $this->content->footer = '';
        //return $this->content;
    }

    // in a learning area, we are refeeding the factory and propose to close the training session
    if (!empty($CFG->enableretrofit)){                    
        $retrofitstr = get_string('retrofitting', 'block_publishflow');
        $retrofithelpstr = $OUTPUT->help_icon('retrofit', 'block_publishflow', false);
        $output .= "<b>$retrofitstr</b>$retrofithelpstr<br/>";
        $output .= '<center>';
        // try both strategies, using the prefix directly in mnethosts or the catalog records
        // there should be only one factiry. The first in the way will be considered
        // further records will be ignored
        $factoriesavailable = $DB->get_records_select('block_publishflow_catalog'," type LIKE '%factory%' ");
        // alternative strategy
        if (!$factoriesavailable){
            $select = (!empty($CFG->factoryprefix)) ? " wwwroot LIKE 'http://{$CFG->factoryprefix}%' " : '' ;
            if($select != ''){
                $factoryhost = $DB->get_record_select('mnet_host', $select);
            }
        } else {
            $factory = array_pop($factoriesavailable);
            $factoryhost = $DB->get_record('mnet_host', array('id' => $factory->platformid));
        }

        if (empty($factoryhost)){
            $output .= $OUTPUT->notification(get_string('nofactory', 'block_publishflow'), 'notifyproblem', true);
        } else {
            $realpath = delivery_check_available_backup($COURSE->id);

            if (empty($realpath)){
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
            } else {
                $strretrofit = get_string('retrofit', 'block_publishflow');
                // should be given to entity author marked users
                if (has_capability('block/publishflow:retrofit', $context_course) || block_publishflow_extra_retrofit_check()){
                    $output .= "<a href=\"{$CFG->wwwroot}/blocks/publishflow/retrofit.php?fromcourse={$COURSE->id}&amp;what=retrofit&amp;where={$factoryhost->id}\">$strretrofit</a><br/><br/>";
                }
            }
        }
    }
    $strclose = get_string('close', 'block_publishflow');
    $stropen = get_string('open', 'block_publishflow');
    $strreopen = get_string('reopen', 'block_publishflow');
    $coursecontrolstr =  get_string('coursecontrol', 'block_publishflow');
    // should be given to entity trainers (mts)
    // we need also fix the case where all categories are the same
    if (has_capability('block/publishflow:manage', $context_course)){
        $output .= '<div class="block_publishflow_coursecontrol">';
        $output .= "<b>$coursecontrolstr</b><br/>";
        if ($COURSE->category == $CFG->coursedelivery_runningcategory && $COURSE->visible){
            $output .= "<a href=\"{$CFG->wwwroot}/blocks/publishflow/close.php?fromcourse={$COURSE->id}&amp;what=close\">$strclose</a>";
        } else if ($COURSE->category == $CFG->coursedelivery_deploycategory) {
            $output .= "<a href=\"{$CFG->wwwroot}/blocks/publishflow/open.php?fromcourse={$COURSE->id}&amp;what=open\">$stropen</a>";
        } else if ($COURSE->category == $CFG->coursedelivery_closedcategory) {
            $output .= "<a href=\"{$CFG->wwwroot}/blocks/publishflow/open.php?fromcourse={$COURSE->id}&amp;what=open\">$strreopen</a>";
        }
        $output .= '</div>';
    }
    $output .= '</center>';
    
    return $output;
}


function automate_network_refreshment()
{    global $DB,$CFG,$USER;
    
     $hosts = $DB->get_records('mnet_host', array('deleted' => 0));
  
    foreach($hosts as $host){
        if ($host->applicationid != $DB->get_field('mnet_application', 'id', array('name' => 'moodle'))) continue;
        if(!($host->name) == "" && !($host->name == "All Hosts")){
             
            $hostcatalog = $DB->get_record('block_publishflow_catalog', array('platformid' => $host->id));
            $caller = new stdClass;
            $caller->username = $USER->username;
            $caller->userwwwroot = $host->wwwroot;
            $caller->remotewwwroot = $CFG->wwwroot;
            $rpcclient = new mnet_xmlrpc_client();
            $rpcclient->set_method('blocks/publishflow/rpclib.php/publishflow_updateplatforms');
            $rpcclient->add_param(null, 'struct');
            $rpcclient->add_param($host->wwwroot, 'string');
            $mnet_host = new mnet_peer();
            $mnet_host->set_wwwroot($host->wwwroot);
            $rpcclient->send($mnet_host);
            if (!is_array($rpcclient->response)){
                $response = json_decode($rpcclient->response);
            }
       
            //We have to check if there is a response with content     
            if(empty($response)){
                echo($host->name.get_string('errorencountered', 'block_publishflow').$rpcclient->error[0]);
            } else {
            
                if ($response->status == RPC_FAILURE){
                    echo '<p>';
                    echo($host->name.get_string('errorencountered', 'block_publishflow').$response->error);
                    echo '</p>';
                }
    
                elseif($response->status == RPC_SUCCESS){
                    $hostcatalog->type = $response->node;
                    $hostcatalog->lastaccess = time();
                   
                    $DB->update_record('block_publishflow_catalog', $hostcatalog);
    
                    // purge all previously proxied
                    $DB->delete_records('block_publishflow_remotecat', array('platformid' => $host->id));
                    foreach($response->content as $entry){
                        //If it's a new record, we have to create it
                        if(!$DB->get_record('block_publishflow_remotecat', array('originalid' => $entry->id, 'platformid' => $host->id))){
                              $fullentry = array('platformid' => $host->id,'originalid' => $entry->id, 'parentid' => $entry->parentid, 'name' => addslashes($entry->name));
                              $DB->insert_record('block_publishflow_remotecat', $fullentry);
                        }
                    }
                     echo($host->name.get_string('updateok','block_publishflow'));
                  } else {
                     echo($host->name.get_string('clientfailure','block_publishflow'));
                  }
            }
        }
    }
}

?>
