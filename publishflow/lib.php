<?php

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

function publishflow_session_open($course, $notify){
    global $CFG, $SITE;
    
    //reopening is allowed
    if ($course->category == @$CFG->coursedelivery_deploycategory || $course->category == @$CFG->coursedelivery_closedcategory){
        $course->category = @$CFG->coursedelivery_runningcategory;
    }

    $course->visible = 1; 
    $course->guest = 0; 
    $course->startdate = time(); 
    update_record('course', addslashes_recursive($course));
    
    $context = get_context_instance(CONTEXT_COURSE, $course->id);
    
    /// revalidate disabled people
    $role = get_record('role', 'shortname', 'student');
    $disabledrole = get_record('role', 'shortname', 'disabledstudent');
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

    $context = get_context_instance(CONTEXT_COURSE, $course->id);
    
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
            $role = get_record('role', 'shortname', 'student');
            $disabledrole = get_record('role', 'shortname', 'disabledstudent');
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
            $role = get_record('role', 'shortname', 'student');
            $disabledrole = get_record('role', 'shortname', 'disabledstudent');
            $context = get_context_instance(CONTEXT_COURSE, $course->id);
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
    update_record('course', addslashes_recursive($course));
}

/**
* overrides backup/lib.php/backup_generate_preferences_artificially($course, $prefs)
* discarding all user's stuff
* @author Valery Fremaux
*
*/
function publishflow_backup_generate_preferences($course) {
    global $CFG;
    $preferences = new StdClass;
    $preferences->backup_unique_code = time();
    $preferences->backup_name = backup_get_zipfile_name($course, $preferences->backup_unique_code);
    $count = 0;

    if ($allmods = get_records('modules') ) {
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
            if (!count_records('course_modules','course',$course->id,'module',$mod->id) || !function_exists($modbackupone)) {
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
*/
function publishflow_backup_check_mods(&$course, $backupprefs){
	global $CFG;
	
    if ($allmods = get_records("modules") ) {
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
 *Function to deploy a course locally
 */
function publishflow_local_deploy($category,$sourcecourse){

    global $CFG, $USER;
    
    include_once $CFG->dirroot."/backup/restorelib.php";
    include_once $CFG->dirroot."/backup/lib.php";

    $deploycat = get_record('course_categories', 'id', $category);
    
    $path	=	$CFG->dataroot.'/'.$sourcecourse->id.'/backupdata/';
    
    
    $sNewestFile = null;
    $iNewestTime = 0;
    foreach (glob($path.'/*.zip') AS $files)
	{
	  $iFileTime = filemtime($files);
	  if ($iFileTime > $iNewestTime){
	      $sNewestFile = $files;
	      $iNewestTime = $iFileTime;
	  }
    }
    
    $realpath 	= 	$sNewestFile;

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

    // confirm/force idnumber in new course
    $response->courseid = $course_header->course_id;
    set_field('course', 'idnumber', $sourcecourse->idnumber, 'id', "{$course_header->course_id}");

    // confirm/force guest closure
    set_field('course', 'guest', 0, 'id', "{$course_header->course_id}");

    // confirm/force not enrollable // enrolling will be performed by master teacher
    set_field('course', 'enrollable', 0, 'id', "{$course_header->course_id}");

    // assign the localuser as author in all cases :
    // deployement : deployer will unassign self manually if needed
    // free use deployement : deployer will take control over session
    // retrofit : deployer will take control over new learning path in work
    $coursecontext = get_context_instance(CONTEXT_COURSE, $course_header->course_id);
    $teacherrole = get_field('role', 'id', 'shortname', 'teacher');
    role_assign($teacherrole, $USER->id, 0, $coursecontext->id);

    return ($course_header->course_id);

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

	static $depth = 0;

	if ($maxdepth > 0){
		if ($depth == $maxdepth){
			return;
		}
	}
	
   	if ($catmenu = get_records_select('block_publishflow_remotecat', " parentid = $parent AND platformid = $hostid ")){
		foreach($catmenu as $cat){
			$catentry = new stdClass;
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
 */
function publishflow_install() {
    
    $result = true;
    
    // installing TAO Data services
    if (!get_record('mnet_service', 'name', 'coursedelivery_data')){
        $service->name = 'coursedelivery_data';
        $service->description = get_string('coursedelivery_data_name', 'block_publishflow');
        $service->apiversion = 1;
        $service->offer = 1;
        if (!$serviceid = insert_record('mnet_service', $service)){
            notify('Error installing coursedelivery_data service.');
            $result = false;
        }
        
        $rpc->function_name = 'delivery_get_sessions';
        $rpc->xmlrpc_path = 'blocks/publishflow/rpclib.php/delivery_get_sessions';
        $rpc->parent_type = 'block';  
        $rpc->parent = 'publishflow';
        $rpc->enabled = 0; 
        $rpc->help = 'Get remote instances of LP sessions.';
        $rpc->profile = '';
        if (!$rpcid = insert_record('mnet_rpc', $rpc)){
            notify('Error installing delivery_data RPC calls.');
            $result = false;
        }
        $rpcmap->serviceid = $serviceid;
        $rpcmap->rpcid = $rpcid;
        insert_record('mnet_service2rpc', $rpcmap);

        $rpc->function_name = 'delivery_get_catalog';
        $rpc->xmlrpc_path = 'blocks/publishflow/rpclib.php/delivery_get_catalog';
        $rpc->parent_type = 'block';  
        $rpc->parent = 'publishflow';
        $rpc->enabled = 0; 
        $rpc->help = 'Get remote catalog of available LPs.';
        $rpc->profile = '';
        if (!$rpcid = insert_record('mnet_rpc', $rpc)){
            notify('Error installing coursedelivery_data RPC calls.');
            $result = false;
        }
        $rpcmap->serviceid = $serviceid;
        $rpcmap->rpcid = $rpcid;
        insert_record('mnet_service2rpc', $rpcmap);
    }

    // installing Delivery Admin services
    if (!get_record('mnet_service', 'name', 'coursedelivery_admin')){
        
        unset($service);
        $service->name = 'coursedelivery_admin';
        $service->description = get_string('coursedelivery_admin_name', 'block_publishflow');
        $service->apiversion = 1;
        $service->offer = 1;
        if (!$serviceid = insert_record('mnet_service', $service)){
            notify('Error installing coursedelivery_admin service.');
            $result = false;
        }
        
        $rpc->function_name = 'delivery_deliver';
        $rpc->xmlrpc_path = 'blocks/publishflow/rpclib.php/delivery_deliver';
        $rpc->parent_type = 'block';  
        $rpc->parent = 'publishflow';
        $rpc->enabled = 0; 
        $rpc->help = 'Delivers a course archive for remote deployment.';
        $rpc->profile = '';
        if (!$rpcid = insert_record('mnet_rpc', $rpc)){
            notify('Error installing tao_admin RPC calls.');
            $result = false;
        }
        $rpcmap->serviceid = $serviceid;
        $rpcmap->rpcid = $rpcid;
        insert_record('mnet_service2rpc', $rpcmap);

        $rpc->function_name = 'delivery_deploy';
        $rpc->xmlrpc_path = 'blocks/publishflow/rpclib.php/delivery_deploy';
        $rpc->parent_type = 'block';  
        $rpc->parent = 'publishflow';
        $rpc->enabled = 0; 
        $rpc->help = 'Triggers the deployment of a LP.';
        $rpc->profile = '';
        if (!$rpcid = insert_record('mnet_rpc', $rpc)){
            notify('Error installing coursedelivery_admin RPC calls.');
            $result = false;
        }
        $rpcmap->rpcid = $rpcid;
        insert_record('mnet_service2rpc', $rpcmap);

        $rpc->function_name = 'delivery_publish';
        $rpc->xmlrpc_path = 'blocks/publishflow/rpclib.php/delivery_publish';
        $rpc->parent_type = 'block';  
        $rpc->parent = 'publishflow';
        $rpc->enabled = 0; 
        $rpc->help = 'Triggers the publication of a LP.';
        $rpc->profile = '';
        if (!$rpcid = insert_record('mnet_rpc', $rpc)){
            notify('Error installing coursedelivery_admin RPC calls.');
            $result = false;
        }
        $rpcmap->rpcid = $rpcid;
        insert_record('mnet_service2rpc', $rpcmap);

        $rpc->function_name = 'publishflow_updateplatforms';
        $rpc->xmlrpc_path = 'blocks/publishflow/rpclib.php/publishflow_updateplatforms';
        $rpc->parent_type = 'block';  
        $rpc->parent = 'publishflow';
        $rpc->enabled = 0; 
        $rpc->help = 'Get information about network topology.';
        $rpc->profile = '';
        if (!$rpcid = insert_record('mnet_rpc', $rpc)){
            notify('Error installing publishflow_updateplatforms RPC call.');
            $result = false;
        }
        $rpcmap->rpcid = $rpcid;
        insert_record('mnet_service2rpc', $rpcmap);

        $rpc->function_name = 'publishflow_rpc_close_course';
        $rpc->xmlrpc_path = 'blocks/publishflow/rpclib.php/publishflow_rpc_close_course';
        $rpc->parent_type = 'block';
        $rpc->parent = 'publishflow';
        $rpc->enabled = 0;
        $rpc->help = 'Closes an open training session.';
        $rpc->profile = '';
        if (!$rpcid = insert_record('mnet_rpc', $rpc)){
            notify('Error installing publishflow_rpc_close_course RPC call.');
            $result = false;
        }
        $rpcmap->rpcid = $rpcid;
        insert_record('mnet_service2rpc', $rpcmap);

        $rpc->function_name = 'publishflow_rpc_open_course';
        $rpc->xmlrpc_path = 'blocks/publishflow/rpclib.php/publishflow_rpc_open_course';
        $rpc->parent_type = 'block';
        $rpc->parent = 'publishflow';
        $rpc->enabled = 0;
        $rpc->help = 'Opens an open training session.';
        $rpc->profile = '';
        if (!$rpcid = insert_record('mnet_rpc', $rpc)){
            notify('Error installing publishflow_rpc_open_course RPC call.');
            $result = false;
        }
        $rpcmap->rpcid = $rpcid;
        insert_record('mnet_service2rpc', $rpcmap);
    }
    
    return $result;
}

/**
 * A standard hook at uninstall time
 */
function publishflow_uninstall() {
    
    $return = true;
    
    // delete all coursedelivery related mnet services and MNET bindings
    if ($dataservice = get_record('mnet_service', 'name', 'coursedelivery_data')){
    
        delete_records('mnet_host2service', 'serviceid', $dataservice->id);
        delete_records('mnet_service2rpc', 'serviceid', $dataservice->id);
    }

    if($adminservice = get_record('mnet_service', 'name', 'coursedelivery_admin')){
        delete_records('mnet_host2service', 'serviceid', $adminservice->id);
        delete_records('mnet_service2rpc', 'serviceid', $adminservice->id);
    }

    delete_records('mnet_rpc', 'parent', 'publishflow');
    delete_records('mnet_service', 'name', 'coursedelivery_data');
    delete_records('mnet_service', 'name', 'coursedelivery_admin');
    
    return $return;
}

/**
* These both functions are wrappers to other ways to allow people
* to deploy based on role's capabilities. They will conditionnaly wrap to
* local implentation functions that could provide a customized
* strategy for allowing deployement.
*
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

?>
