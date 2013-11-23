<?php

/**
* these RPC functions are intended to be fired by external systems to control Moodle
* deployment of courses to MNET satellites
*
*/

/**
* Includes and requires
*
*/
include_once $CFG->libdir."/accesslib.php";
include_once $CFG->dirroot."/mnet/xmlrpc/client.php";
require_once($CFG->libdir . '/xmlize.php'); // needed explicitely as all situations do not provide it
/*
include_once $CFG->dirroot."/backup/restorelib.php";
include_once $CFG->dirroot."/backup/backuplib.php";
*/

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');       
include_once $CFG->dirroot."/blocks/publishflow/filesystemlib.php";
include_once $CFG->dirroot."/blocks/publishflow/lib.php";
require_once($CFG->dirroot."/blocks/publishflow/backup/restore_automation.class.php");
require_once($CFG->dirroot.'/enrol/locallib.php');
require_once($CFG->dirroot.'/group/lib.php');

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

/**
 * Invoke the local user who make the RPC call and check his rights.
 * @param    $user                    object                The calling user.
 * @param    $capability                string                The capability to check.
 * @param    $context                int                    The capability's context (optional / CONTEXT_SYSTEM by default).
 */
function publishflow_rpc_check_user($user, $capability, $context=null) {
    global $CFG, $USER,$DB;

    debug_trace("Checking user identity : ".json_encode($user));

    // Creating response
    $response = new stdclass;
    $response->status = RPC_SUCCESS;
    $response->errors = array();
    $response->error = '';

    // Checking user
    if (!array_key_exists('username', $user) || !array_key_exists('remoteuserhostroot', $user) || !array_key_exists('remotehostroot', $user)) {
        $response->status = RPC_FAILURE_USER;
        $response->errors[] = 'Bad client user format.';
        $response->error = 'Bad client user format.';
        debug_trace("User failed bad format");
        return(json_encode($response));
    }

    // Get local identity
    if (!$remotehost = $DB->get_record('mnet_host', array('wwwroot' => $user['remotehostroot']))){
        $response->status = RPC_FAILURE;
        $response->errors[] = 'Calling host is not registered. Check MNET configuration';
        $response->error = 'Calling host is not registered. Check MNET configuration';
        debug_trace("User failed no host");
        return(json_encode($response));
    }

    $userhost = $DB->get_record('mnet_host', array('wwwroot' => $user['remoteuserhostroot']));

    if (!$localuser = $DB->get_record('user', array('username' => addslashes($user['username']), 'mnethostid' => $userhost->id))){
        $response->status = RPC_FAILURE_USER;
        $response->errors[] = "Calling user has no local account. Register remote user first";
        $response->error = "Calling user has no local account. Register remote user first";
        debug_trace("User failed no local user");
        return(json_encode($response));
    }
    // Replacing current user by remote user

    $USER = $localuser;
    debug_trace("User catched is : ".json_encode($USER));

    // Checking capabilities
    if (!empty($capability)){
        if (is_null($context))
            $context = context_system::instance();
        debug_trace("Testing capability : $capability on $CFG->wwwroot ");
        if (!has_capability($capability, $context, $localuser->id)) {
            $response->status = RPC_FAILURE_CAPABILITY;
            $response->errors[] = 'Local user\'s identity has no capability to run';
            $response->error = 'Local user\'s identity has no capability to run';
            return(json_encode($response));
        }
    }
    return '';
}


/**
* external entry point for deploying a course template.
* @param array $caller the caller coordinates (username, user host root, calling host)
* @param string $idfield a string that tells the identifying basis (id|idnumber|shortname)
* @param string $courseidentifier the identifying value
* @param string $whereroot where to deploy. Must be a known mnet_host root
* @param array $parmsoverride an array of overriding course attributes can superseed the template course settings
*/
function publishflow_rpc_deploy($callinguser, $idfield, $courseidentifier, $whereroot, $parmsoverride = null, $json_response = true){
    global $USER, $CFG,$DB;
    $extresponse->status = RPC_SUCCESS;
    $extresponse->errors = array();
    $extresponse->error = '';

    if ($auth_response = publishflow_rpc_check_user((array)$callinguser, 'block/publishflow:deploy')){
        return publishflow_send_response($auth_response, $json_response, true);
    }

    switch($idfield){
        case 'id':
            $course = $DB->get_record('course', array('id' => $courseidentifier));
            break;
        case 'shortname':
            $course = $DB->get_record('course', array('shortname' => $courseidentifier));
            break;
        case 'idnumber':
            $course = $DB->get_record('course', array('idnumber' => $courseidentifier));
            break;
    }
    if (!empty($parmsoverride)){
        foreach($parmsoverride as $key => $value){
            if (isset($course->$key)) {
                $course->$key = $parmsoverride[$key];
            }
        }
    }
    if (!empty($whereroot)){
        if (!$mnethost = $DB->get_record('mnet_host', array('wwwroot' => $whereroot, 'deleted' => 0))){
            $extresponse->status = RPC_FAILURE;
            $extresponse->error = 'Deployment target host not found (or deleted)';        
            return(publishflow_send_response($extresponse, $json_response));
        }
    }

    if (!empty($USER->mnethostid)){
        if ($userhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid, 'deleted' => 0))){
            $userwwwroot = $userhost->wwwroot;
        } else {
            $extresponse->status = RPC_FAILURE;
            $extresponse->error = 'User host not found (or deleted)';        
            return(publishflow_send_response($extresponse, $json_response));
        }
    } else {
        $userwwwroot = $CFG->wwwroot;
    }

    $caller = new stdClass;
    $caller->username = $USER->username;
    $caller->remoteuserhostroot = $userwwwroot;
    $caller->remotehostroot = $CFG->wwwroot;

    $rpcclient = new mnet_xmlrpc_client();
    $rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_deploy');
    $rpcclient->add_param($caller, 'struct');
    $rpcclient->add_param(json_encode($course), 'string');
    $rpcclient->add_param(1, 'int'); // prepared for forcing replacement
    $mnet_host = new mnet_peer();
    $mnet_host->set_wwwroot($whereroot);
    if (!$rpcclient->send($mnet_host)){
        $extresponse->status = RPC_FAILURE;
        $extresponse->errors[] = 'REMOTE : '.implode("<br/>\n", $rpcclient->errors);        
        $extresponse->error = 'REMOTE : '.implode("<br/>\n", $rpcclient->errors);        
        return(publishflow_send_response($extresponse, $json_response));
    }

    $response = json_decode($rpcclient->response);

    if ($response->status == 200){
        $remotecourseid = $response->courseid;
        if ($USER->mnethostid != $mnethost->id){
            $extresponse->message = "<a href=\"/auth/mnet/jump.php?hostid={$mnethost->id}&amp;wantsurl=".urlencode('/course/view.php?id='.$remotecourseid)."\">".get_string('jumptothecourse', 'block_publishflow').'</a>';
        } else {
            $extresponse->message = "<a href=\"{$mnethost->wwwroot}/course/view.php?id={$remotecourseid}\">".get_string('jumptothecourse', 'block_publishflow').'</a>';
        }
        return(publishflow_send_response($extresponse, $json_response));
    } else {
        $extresponse->status = RPC_FAILURE;
        $extresponse->errors[] = 'Remote application error : ';
        $extresponse->errors[] = $response->errors;
        $extresponse->error = 'Remote application error.';
        return(publishflow_send_response($extresponse, $json_response));
    }
}

function publishflow_rpc_deploy_wrapped($wrap){
    debug_trace("WRAP : ".json_encode($wrap));    
    return publishflow_rpc_deploy(@$wrap['callinguser'], @$wrap['idfield'], @$wrap['courseidentifier'], @$wrap['whereroot'], @$wrap['parmsoverride'], @$wrap['json_response']);
}

/**
* test for existance.
*
*/
function publishflow_rpc_course_exists($callinguser, $idfield, $courseidentifier, $whereroot, $json_response = true){
    global $CFG,$DB;
    $extresponse->status = RPC_SUCCESS;
    $extresponse->errors = array();
    $extresponse->error = '';

    if ($auth_response = publishflow_rpc_check_user((array)$callinguser, 'block/publishflow:deploy')){
        return publishflow_send_response($auth_response, $json_response, true);
    }
    if ($whereroot == $CFG->wwwroot || empty($whereroot)){
        switch($idfield){
            case 'id':
                $course = $DB->get_record('course', array('id' => $courseidentifier));
                break;
            case 'shortname':
                $course = $DB->get_record('course', array('shortname' => $courseidentifier));
                break;
            case 'idnumber':
                $course = $DB->get_record('course', array('idnumber' => $courseidentifier));
                break;
        }
        if (empty($course)){
            $extresponse->status = RPC_FAILURE_RECORD;
            $extresponse->errors[] = 'Unkown course.';
            $extresponse->error = 'Unkown course.';
            debug_trace("Unkown course based on $idfield with $courseidentifier ");
            publishflow_send_response($extresponse, $json_response);
        }
        $extresponse->status = RPC_SUCCESS;
        $extresponse->message = "Course exists.";
        $visibility = $course->visibility;
        $cat = $DB->get_record('course_categories', array('id' => $course->category));
        $visibility = $visibility && $cat->visibility;
        while($cat->parent){
            $cat = $DB->get_record('course_categories', array('id' => $course->category));
            $visibility = $visibility && $cat->visibility;            
        }

        $extresponse->visible = $visibility;

    } else { // bounce to remote host

        if ($remotedeleted = $DB->get_field('mnet_host', 'deleted', array('wwwroot' => $whereroot))){
            $extresponse->status = RPC_FAILURE_RECORD;
            $extresponse->error = 'Unkown whereroot.';        
            publishflow_send_response($extresponse, $json_response);
        }

        if (!empty($USER->mnethostid)){
            if ($userhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid, 'deleted' => 0))){
                $userwwwroot = $userhost->wwwroot;
            } else {
            }
        } else {
            $userwwwroot = $CFG->wwwroot;
        }

        $callinguser = (object)$callinguser;
        $caller->username = $callinguser->username;
        $caller->remoteuserhostroot = $userwwwroot;
        $caller->remotehostroot = $CFG->wwwroot;
        $rpcclient = new mnet_xmlrpc_client();

        $rpcclient->set_method('blocks/publishflow/rpclib.php/publishflow_rpc_course_exists');
        $rpcclient->add_param($caller, 'struct');
        $rpcclient->add_param($idfield, 'string');
        $rpcclient->add_param($courseidentifier, 'string');
        $rpcclient->add_param($whereroot, 'string');
        $mnet_host = new mnet_peer();
        $mnet_host->set_wwwroot($whereroot);
        if (!$rpcclient->send($mnet_host)){
            $extresponse->status = RPC_FAILURE;
            $extresponse->errors[] = 'REMOTE : '.implode("<br/>\n", $rpcclient->error);
            $extresponse->error = 'REMOTE : '.implode("<br/>\n", $rpcclient->error);        
            return(publishflow_send_response($extresponse, $json_response));
        }
        $response = json_decode($rpcclient->response);
        if ($response->status == 200){
            $extresponse->status = RPC_SUCCESS;
            $extresponse->message = 'course exists';
            return(publishflow_send_response($extresponse, $json_response));
        } else {
            $extresponse->status = RPC_FAILURE;
            $extresponse->errors[] = 'Remote application error : ';
            $extresponse->errors[] = $response->errors;
            $extresponse->error = 'Remote application error.';
            return(publishflow_send_response($extresponse, $json_response));
        }
    }

    return(publishflow_send_response($extresponse, $json_response));
}

function publishflow_rpc_course_exists_wrapped($wrap){
    debug_trace("WRAP : ".json_encode($wrap));    
    return publishflow_rpc_course_exists(@$wrap['callinguser'], @$wrap['idfield'], @$wrap['courseidentifier'], @$wrap['whereroot'], @$wrap['json_response']);
}


/**
* opens a non running course or ask remotely for opening.
*
*/
function publishflow_rpc_open_course($callinguser, $idfield, $courseidentifier, $whereroot, $mode, $json_response = true){
    global $CFG,$DB;
    $extresponse->status = RPC_SUCCESS;
    $extresponse->errors = array();
    $extresponse->error = '';

    if ($auth_response = publishflow_rpc_check_user((array)$callinguser, 'block/publishflow:deploy')){
        return publishflow_send_response($auth_response, $json_response, true);
    }
    if ($whereroot == $CFG->wwwroot || empty($whereroot)){
        switch($idfield){
            case 'id':
                $course = $DB->get_record('course', array('id' => $courseidentifier));
                break;
            case 'shortname':
                $course = $DB->get_record('course', array('shortname' => $courseidentifier));
                break;
            case 'idnumber':
                $course = $DB->get_record('course', array('idnumber' => $courseidentifier));
                break;
        }
        if (empty($course)){
            $extresponse->status = RPC_FAILURE_RECORD;
            $extresponse->errors[] = 'Unkown course.';
            $extresponse->error = 'Unkown course.';
            debug_trace("Unkown course based on $idfield with $courseidentifier ");
            publishflow_send_response($extresponse, $json_response);
        }
        publishflow_session_open($course, $mode); // mode stands for notify signal
        $extresponse->message = "Course open.";

    } else { // bounce to remote host

        if ($remotedeleted = $DB->get_field('mnet_host', 'deleted', array('wwwroot' => $whereroot))){
            $extresponse->status = RPC_FAILURE_RECORD;
            $extresponse->error = 'Unkown whereroot.';        
            publishflow_send_response($extresponse, $json_response);
        }

        if (!empty($USER->mnethostid)){
            if ($userhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid, 'deleted' => 0))){
                $userwwwroot = $userhost->wwwroot;
            } else {
            }
        } else {
            $userwwwroot = $CFG->wwwroot;
        }

        $callinguser = (object)$callinguser;
        $caller->username = $callinguser->username;
        $caller->remoteuserhostroot = $userwwwroot;
        $caller->remotehostroot = $CFG->wwwroot;
        $rpcclient = new mnet_xmlrpc_client();

        $rpcclient->set_method('blocks/publishflow/rpclib.php/publishflow_rpc_open_course');
        $rpcclient->add_param($caller, 'struct');
        $rpcclient->add_param($idfield, 'string');
        $rpcclient->add_param($courseidentifier, 'string');
        $rpcclient->add_param($whereroot, 'string');
        $rpcclient->add_param($mode, 'string');
        $mnet_host = new mnet_peer();
        $mnet_host->set_wwwroot($whereroot);
        if (!$rpcclient->send($mnet_host)){
            $extresponse->status = RPC_FALURE;
            $extresponse->errors[] = 'REMOTE : '.implode("<br/>\n", $rpcclient->error);
            $extresponse->error = 'REMOTE : '.implode("<br/>\n", $rpcclient->error);        
            return(publishflow_send_response($extresponse, $json_response));
        }
        $response = json_decode($rpcclient->response);
        if ($response->status == 200){
            $extresponse->status = RPC_SUCCESS;
            $extresponse->message = 'course was successfully open';
            return(publishflow_send_response($extresponse, $json_response));
        } else {
            $extresponse->status = RPC_FAILURE;
            $extresponse->errors[] = 'Remote application error : ';
            $extresponse->errors[] = $response->errors;
            $extresponse->error = 'Remote application error.';
            return(publishflow_send_response($extresponse, $json_response));
        }
    }

    return(publishflow_send_response($extresponse, $json_response));
}

function publishflow_rpc_open_course_wrapped($wrap){
    debug_trace("WRAP : ".json_encode($wrap));    
    return publishflow_rpc_open_course(@$wrap['callinguser'], @$wrap['idfield'], @$wrap['courseidentifier'], @$wrap['whereroot'], @$wrap['mode'], @$wrap['json_response']);
}

/**
* closes a running course or ask remotely for closure.
*
*/
function publishflow_rpc_close_course($callinguser, $idfield, $courseidentifier, $whereroot, $mode, $json_response = true){
    global $CFG,$DB;
    $extresponse->status = RPC_SUCCESS;
    $extresponse->errors = array();
    $extresponse->error = '';

    if ($auth_response = publishflow_rpc_check_user((array)$callinguser, 'block/publishflow:deploy')){
        return publishflow_send_response($auth_response, $json_response, true);
    }
    if ($whereroot == $CFG->wwwroot || empty($whereroot)){
        switch($idfield){
            case 'id':
                $course = $DB->get_record('course', array('id' => $courseidentifier));
                break;
            case 'shortname':
                $course = $DB->get_record('course', array('shortname' => $courseidentifier));
                break;
            case 'idnumber':
                $course = $DB->get_record('course', array('idnumber' => $courseidentifier));
                break;
        }
        if (empty($course)){
            $extresponse->status = RPC_FAILURE_RECORD;
            $extresponse->errors[] = 'Unkown course.';
            $extresponse->error = 'Unkown course.';
            debug_trace("Unkown course based on $idfield with $courseidentifier ");
            publishflow_send_response($extresponse, $json_response);
        }
        if ($err = publishflow_course_close($course, $mode, true)){
            $extresponse->status = RPC_FAILURE;
            $extresponse->errors[] = $err;
            $extresponse->error = $err;
        }
    } else { // bounce to remote host

        if ($remotedeleted = $DB->get_field('mnet_host', 'deleted', array('wwwroot' => $whereroot))){
            $extresponse->status = RPC_FAILURE_RECORD;
            $extresponse->error = 'Unkown whereroot.';        
            publishflow_send_response($extresponse, $json_response);
        }

        if (!empty($USER->mnethostid)){
            if ($userhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid, 'deleted' => 0))){
                $userwwwroot = $userhost->wwwroot;
            } else {
            }
        } else {
            $userwwwroot = $CFG->wwwroot;
        }

        $callinguser = (object)$callinguser;
        $caller->username = $callinguser->username;
        $caller->remoteuserhostroot = $userwwwroot;
        $caller->remotehostroot = $CFG->wwwroot;
        $rpcclient = new mnet_xmlrpc_client();

        $rpcclient->set_method('blocks/publishflow/rpclib.php/publishflow_rpc_close_course');
        $rpcclient->add_param($caller, 'struct');
        $rpcclient->add_param($idfield, 'string');
        $rpcclient->add_param($courseidentifier, 'string');
        $rpcclient->add_param($whereroot, 'string');
        $rpcclient->add_param($mode, 'string');
        $mnet_host = new mnet_peer();
        $mnet_host->set_wwwroot($whereroot);
        if (!$rpcclient->send($mnet_host)){
            $extresponse->status = RPC_FALURE;
            $extresponse->errors[] = 'REMOTE : '.implode("<br/>\n", $rpcclient->error);
            $extresponse->error = 'REMOTE : '.implode("<br/>\n", $rpcclient->error);        
            return(publishflow_send_response($extresponse, $json_response));
        }
        $response = json_decode($rpcclient->response);
        if ($response->status == 200){
            $extresponse->status = RPC_SUCCESS;
            $extresponse->message = 'course closed successfully';
            return(publishflow_send_response($extresponse, $json_response));
        } else {
            $extresponse->status = RPC_FAILURE;
            $extresponse->errors[] = 'Remote application error : ';
            $extresponse->errors[] = $response->errors;
            $extresponse->error = 'Remote application error.';
            return(publishflow_send_response($extresponse, $json_response));
        }
    }
    return(publishflow_send_response($extresponse, $json_response));
}

function publishflow_rpc_close_course_wrapped($wrap){
    debug_trace("WRAP : ".json_encode($wrap));    
    return publishflow_rpc_close_course(@$wrap['callinguser'], @$wrap['idfield'], @$wrap['courseidentifier'], @$wrap['whereroot'], @$wrap['mode'], @$wrap['json_response']);
}

/**
  * RPC function for platform catalog updating
  * This is going to reply with the complete category tree and the moodle node type.
  *
  * We do not take the private categories, as we can't easily know who was allowed to see them.
  *
  * @author Edouard Poncelet
  **/


function publishflow_updateplatforms($callinguser, $platformroot){
    global $CFG,$DB;

    $nodetype = $CFG->moodlenodetype;
    $records = $DB->get_records('course_categories', array('visible' => '1'), '', 'name,id,parent');
    
    $response = new stdclass;
    if($nodetype == ''){
    	$response = new StdClass;
        $response->status = RPC_FAILURE;
        $response->errors[] = 'Not Publishflow Compatible Platform (check remote platform type publishflowsettings)';
        $response->error = 'Not Publishflow Compatible Platform (check remote platform type publishflowsettings)';
    } else {
    	$response = new StdClass;
        $response->status = RPC_SUCCESS;
        $response->errors = array();
        $response->error = '';
        $response->node = $CFG->moodlenodetype;

        foreach($records as $record){
            $values = array('id' => $record->id, 'name' => $record->name, 'parentid' => $record->parent);
            $response->content[] = $values;
        }
    }

    debug_trace(json_encode($response));

    return json_encode($response);
}

//*******************************UNDER THIS LINE: MERGE FROM COURSEDELIVERY ********************/////////////

/**
* RPC functions for coursedelivery related data or content administration services
*
* @package mod-coursedelivery
* @author Valery Fremaux
* 
*/

if (!defined('COURSESESSIONS_PRIVATE')){
    define('COURSESESSIONS_PRIVATE', 0);
    define('COURSESESSIONS_PROTECTED', 1);
    define('COURSESESSIONS_PUBLIC', 2);
}

/**
* retrieves instances of a course that are in use in this moodle
* Applies to : Training Satellites
* @param string $username
* @param string $remotehostroot
* @param string $course_idnumber the Unique identifier for that Learning Path
*/
function delivery_get_sessions($callinguser, $course_idnumber, $json_response){
    global $CFG,$DB;
        
    $response->status = RPC_SUCCESS;
    $response->errors = array();
    $response->error = '';

    // get local identity for user
    $auth_response = publishflow_rpc_check_user((array)$callinguser);

    // set a default value for publicsessions
    if (!isset($CFG->coursedelivery_publicsessions)){
        set_config('coursedelivery_publicsessions', 1);
    }

    // if sessions are not publicly shown, we must check agains user identity
    // If protected, we just not allow deployment but let session be displayed.
    $candeploy = false;
    $candeployfree = false;
    $canpublish = false;
    if ($CFG->coursedelivery_publicsessions != COURSESESSIONS_PUBLIC){

        // user must be registered or we have an error reading session data
        if ($CFG->coursedelivery_publicsessions == COURSESESSIONS_PRIVATE && $auth_response){
            return publishflow_send_response($auth_response, $json_response, true);
        }
        // in semi private mode, we can continue without error but just check capabilities if real user.
        if (!$auth_response){
            $candeploy = has_capability('block/publishflow:deploy', context_system::instance(), $USER->id);
            $canpublish = has_capability('block/publishflow:publish', context_system::instance(), $USER->id);
        }
    }    
    // get all courses that are instances of the given idnumber LP identifier

    $sessions = $DB->get_records('course', array('idnumber' => $course_idnumber), 'shortname', 'id,shortname,fullname,startdate,visible');
    if (!empty($sessions)){
        foreach($sessions as $session){
            $context = context_course::instance($session->id);
            $systemcontext = context_system::instance();

            if ($session->visible || has_capability('moodle/course:viewhiddencourses', $context, $USER->id) || $canpublish){
                $session->noaccess = 1; // session is not reachable by jump
                if ($CFG->coursedelivery_publicsessions || has_capability('moodle/course:view', $context, $USER->id)){
                    $session->noaccess = 0; // session is reachable by jump
                }
                $validsessions[] = $session;
            }
        }
    }
    $response->candeploy = $candeploy;
    $response->canpublish = $canpublish;
    $response->sessions = $validsessions;
    $response->allsessionscount = count($sessions);
    $response->username = $username;
    $response->remoteuserhostroot = $remoteuserhostroot;
    return publishflow_send_response($response);
}

/**
* delivers an archive from the given course id. The latest archive is returned
* from the proper file location. The transfer can be chunked for tranporting big files
* exceeding 4Mo
* @param string $username the caller's username
* @param string $remotewwwroot the remote host the delivery order is coming from
* @param int $lp_catalogcourseid the requested course
* @param int $transferoffset the byte the transfer should start at
* @param int $transferlimit the max amount of bytes to be transfered in the chunk. Is set to 0, 
* transfers all file as one block.
* @return a base 64 encoded file, or file chunk that sends the zip archive content
*/
function delivery_deliver($callinguser, $lp_catalogcourseid, $transferoffset = 0, $transferlimit = 0, $json_response = true){
    global $CFG,$DB;
    
    $response->status = RPC_SUCCESS;
    $response->errors = array();
    $response->error = '';
    debug_trace('DELIVER : Start');

    // Check username and origin of the query    
    if ($auth_response = publishflow_rpc_check_user((array)$callinguser)){
        return publishflow_send_response($auth_response, $json_response, true);
    }

    debug_trace('DELIVER : Passed auth');

    // Check availability of the course
    if (!$course = $DB->get_record('course', array('id' => $lp_catalogcourseid))){
        $response->status = RPC_FAILURE;
        $response->errors[] = "Delivery source course {$lp_catalogcourseid} does not exist";
        $response->error = "Delivery source course {$lp_catalogcourseid} does not exist";
        return publishflow_send_response($response, $json_response);
    }
    
    
    $file = delivery_check_available_backup($lp_catalogcourseid, $loopback);
   
    if (!empty($loopback)) return publishflow_send_response($loopback, $json_response);

    debug_trace('DELIVER : Post loopback');

    if (empty($file)){
        $response->status = RPC_FAILURE;
        $response->errors[] = 'No available deployable backup for this course '. ' ' . $file->get_;
        $response->error = 'No available deployable backup for this course '. ' ' . $file->dir;
        return publishflow_send_response($response, $json_response);
    } else {    
//         DebugBreak();
        //move the file to temp folder.
        $tempfile = $CFG->dataroot."/temp/backup/".$file->get_filename();
        $file->copy_content_to($tempfile);
           
        // Open the backup, get content, encode it and send it
        if (file_exists($tempfile)){
            if (empty($CFG->coursedeliveryislocal)){
                $response->local = false; 
                backup_file2data($tempfile, $filecontent);
                $response->archivename = $tempfile;
                $encoded = base64_encode($filecontent);
                if ($transferlimit == 0){
                    $response->file = $encoded;
                    $response->filepos = strlen($encoded);
                    $response->remains = 0;
                } else { // transfers a chunk
                    $response->file = substr($encoded, $transferoffset, $transferlimit);
                    $transferredlength = strlen($response->file);
                    $response->filepos = $transferoffset + $transferredlength;
                    $response->remains = strlen(substr($encoded, $response->filepos));
                }
            } else {
                // local delivery is on. We give only file reference.
                $response->local = true;
                $response->archivename =  $tempfile;
                $response->filename = $file->get_filename();
            }
            return publishflow_send_response($response, $json_response);
        } else {
            $response->status = RPC_FAILURE;
            $response->errors[] = 'Chosen file does not exist : '. $realpath->path;
            $response->error = 'Chosen file does not exist : '. $realpath->path;
            return publishflow_send_response($response, $json_response);
        }
    }
}

/**
* checks locally if a deployable/publishable backup is available
* @param reference $loopback variable given to setup an XMLRPC loopback message for testing
* @return boolean
*/
function delivery_check_available_backup($courseid, &$loopback = null){
    global $CFG,$DB;
  
    $fs = get_file_storage();
    $coursecontext = get_context_instance(CONTEXT_COURSE,$courseid);
    $files = $fs->get_area_files($coursecontext->id,'backup', 'publishflow', 0, 'timecreated', false);
    
    if(count($files)>0)
    {
        return array_pop($files);
    }
    
    return false;
}

/**
* invokes the deployment procedure for a published learning path. The user name
* that invokes this procedure MUST have sufficient priviledge on the local
* platform to create courses.
* the deployment procedure calls back the delivery site to get the archive of the 
* learning path if it is not already available, instanciate a new course silently.
* the end result of calling this RPC call should be a jump to this course
* setup form, for finishing setup.
*
* @param string $username  ====> $caller
* @param string $userhostroot the reference mnethost root of the user  =====> $caller
* @param string $remotehostroot ====>$caller
* @param string $sourcecourseserial The complete course moodle metadata from the origin platform
*/
function delivery_deploy($callinguser, $sourcecourseserial, $forcereplace, $parmsoverride = null, $json_response = true){
    global $CFG, $USER,$DB,$PAGE;

    debug_trace('DEPLOY : Start');
 
    $response->status = RPC_SUCCESS;
    $response->errors = array();
    $response->error = '';
     
    $sourcecourse = json_decode($sourcecourseserial);
    $callinguser = (array)$callinguser;

    if ($auth_response = publishflow_rpc_check_user((array)$callinguser, 'block/publishflow:deploy')){
        return publishflow_send_response($auth_response, $json_response, true);
    }

    if (!isset($CFG->coursedelivery_coursefordelivery) || !$course = $DB->get_record('course', array('id' => $CFG->coursedelivery_coursefordelivery))){
        $response->status = RPC_FAILURE_CONFIG;
        $response->errors[] = "Target configuration seems to be undone.";
        $response->error = "Target configuration seems to be undone.";
        return publishflow_send_response($response, $json_response);
    }

    // first bump up server execution characteristics
    $maxtime = ini_get('max_execution_time');
    $maxmem = ini_get('memory_limit');
    ini_set('max_execution_time', '600');
    ini_set('memory_limit', '256M');

    // Check the local availability of the archive

    
    $resourcefile = null;
    
    if(!empty($resource)){
        $resourcefile = file_storage::get_file_by_id($resource->localfileid);
    }
    
    //$realpath = $CFG->dataroot.'/'.$CFG->coursedelivery_coursefordelivery.'/'.$sourcecourse->idnumber.'.zip';
    //$temppath = $CFG->dataroot."/temp/backup/"
    if (empty($resource) || $forcereplace){


        // We de not have a package at remote side
        debug_trace('DEPLOY : Up to fetch a course back');
        $mnet_host = new mnet_peer();
        $mnet_host->set_wwwroot($callinguser['remotehostroot']);

        if (empty($CFG->coursedeliveryislocal)){
            // We try to get the archive file through the network
            $caller = (object)$callinguser;
            $caller->remotehostroot = $CFG->wwwroot;
            $transfercomplete = false;
            $archivefile = '';
            $offset = 0;
            while(!$transfercomplete){

                // Get the archive on back call
                $rpcclient = new mnet_xmlrpc_client();
                $rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_deliver');
                $rpcclient->add_param($caller, 'struct');
                $rpcclient->add_param($sourcecourse->id, 'int');
                $rpcclient->add_param($offset, 'int');
                $rpcclient->add_param(4194304, 'int'); // 4Mo transfer max
                $rpcclient->add_param(1, 'int'); // json response required

                if (!$rpcclient->send($mnet_host)){
                    // make a suitable response
                    $response->status = RPC_FAILURE;
                    $response->error = 'Remote error : Could not get the course archive file';
                    $response->errors[] = 'Remote error : Could not get the course archive file';
                    if ($rpcclient->errors){
                        foreach($rpcclient->errors as $error){
                            $response->errors[] = $error;
                        }
                    }
                    ini_set('max_execution_time', $maxtime);
                    ini_set('memory_limit', $maxmem);
                    return publishflow_send_response($response, $json_response);
                }
                $backresponse = json_decode($rpcclient->response);
                // Local test point. Stops after first chunk is transferred
                debug_trace('DEPLOY : XML-RPC backcall succeeded');
                /// Processing XML-RPC response
                // XML-RPC worked well, and answers with remote test status
                if ($backresponse->status == RPC_TEST){
                    $response->status = RPC_TEST;
                    $response->teststatus = 'Remote test point : '.$backresponse->teststatus;
                    ini_set('max_execution_time', $maxtime);
                    ini_set('memory_limit', $maxmem);
                    return publishflow_send_response($response, $json_response);
                }

                // XML-RPC worked well, but remote procedure may fail
                if ($backresponse->status == RPC_SUCCESS){
                    $archivefile = $archivefile . base64_decode($backresponse->file);
                    $archivename = $backresponse->archivename;
                    if ($backresponse->remains == 0){
                        $transfercomplete = true;
                    } else { 
                        // File is chunked because too big. prepare for next chunk
                        $offset = $backresponse->filepos;
                    }
                } else { // XML-RPC remote procedure failed, although transmission is OK
                    $response->status = RPC_FAILURE;
                    $response->error = 'Remote error : Could not get the course archive file because of call back remote error.';
                    $response->errors[] = 'Remote error : Could not get the course archive file because of call back remote error.';
                    if ($backresponse->errors){
                        foreach($backresponse->errors as $error){
                            $response->errors[] = $error;
                        }
                    }
                    ini_set('max_execution_time', $maxtime);
                    ini_set('memory_limit', $maxmem);
                    return publishflow_send_response($response, $json_response);
                }
            }
            // Save the archive locally
            if (!file_exists(dirname($realpath))){
                filesystem_create_dir($realpath, $recursive = 1);
            }
            $status = backup_data2file($realpath, $archivefile);
        } else {
            // we make a local delivery by copying the archive directly in file system
            // this very fast, but is not workable on remotely distributed hosts.
            // both moodle data must be on the same storage system.

            $caller = (object)$callinguser;
            $caller->remotehostroot = $CFG->wwwroot;

            // Get the archive on back call
            $rpcclient = new mnet_xmlrpc_client();
            $rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_deliver');
            $rpcclient->add_param($caller, 'struct');
            $rpcclient->add_param($sourcecourse->id, 'int');
            $rpcclient->add_param(0, 'int'); // transfer offset
            $rpcclient->add_param(0, 'int'); // transfer limit
            $rpcclient->add_param(1, 'int'); // json response required

            if (!$rpcclient->send($mnet_host)){
                // make a suitable response
                $response->status = RPC_FAILURE;
                $response->error = 'Remote error : Could not get the LP archive descriptor for local delivery';
                $response->errors[] = '<br/>XML-RPC Callback errors :<br/>';
                if ($rpcclient->error->errors){
                    foreach($rpcclient->error as $error){
                        $response->errors[] = $error;
                    }
                }
                ini_set('max_execution_time', $maxtime);
                ini_set('memory_limit', $maxmem);
                return publishflow_send_response($response, $json_response);
            }
            $backresponse = json_decode($rpcclient->response);
            // Local test point
            debug_trace('DEPLOY : XML-RPC backcall succeeded for local delivery ');
            /// Processing XML-RPC response
            // XML-RPC worked well, but remote procedure may fail
            if ($backresponse->status == RPC_SUCCESS){
                if ($backresponse->local){
                    $archivename = $backresponse->archivename;  //contains the file full path 
                   // $filename = $backresponse->filename;
                  
                    $temp_file = $CFG->dataroot."/temp/backup/".$backresponse->filename;
                    $destination_file_path = $CFG->dataroot."/temp/backup/".$filename;
                    if (!is_dir($CFG->dataroot.'/temp/backup')){
	                    mkdir($CFG->dataroot."/temp/backup/", 0777, true);
	                }
                    // actually locally copying archive
                    if (!copy($archivename, $temp_file)){
                        $response->status = RPC_FAILURE;
                        $response->error = "Local delivery : copy error from [$archivename] to [$temp_file] ";
                        $response->errors[] = "Local delivery : copy error from [$archivename] to [$temp_file] ";
                        ini_set('max_execution_time', $maxtime);
                        ini_set('memory_limit', $maxmem);
                        return publishflow_send_response($response, $json_response);
                    }
                } else {
                    $response->status = RPC_FAILURE;
                    $response->errors[] = 'Local delivery remote : remote end has not local delivery set on';
                    $response->error = 'Local delivery remote : remote end has not local delivery set on';
                    ini_set('max_execution_time', $maxtime);
                    ini_set('memory_limit', $maxmem);
                    return publishflow_send_response($response, $json_response);
                }
            } else { // XML-RPC remote procedure failed, although transmission is OK
                $response->status = RPC_FAILURE;
                $response->errors[] = 'Remote error : Could not get remote file description';
                $response->error = 'Remote error : Could not get remote file description';
                ini_set('max_execution_time', $maxtime);
                ini_set('memory_limit', $maxmem);
                return publishflow_send_response($response, $json_response);
            }                        
        }
    }
    // Restores a new course silently, giving sufficient parameters and force category
    debug_trace('DEPLOY : Starting deployment process');

    if (empty($parmsoverride['category'])){    
        $deploycat = $DB->get_record('course_categories', array('id' => @$CFG->coursedelivery_deploycategory));
    } else {
        $deploycat = $DB->get_record('course_categories', array('id' => $parmsoverride['category']));
    }
    if (!$deploycat){
        $response->status = RPC_FAILURE;
        $response->errors[] = 'Deployment category has not been setup. Contact local administrator.';
        $response->error = 'Deployment category has not been setup. Contact local administrator.';
        ini_set('max_execution_time', $maxtime);
        ini_set('memory_limit', $maxmem);
        return publishflow_send_response($response, $json_response);
    }

    
    $newcourse_id =  restore_automation::run_automated_restore(null,$temp_file,$deploycat->id) ;
    // confirm/force idnumber in new course
    $response->courseid =  $newcourse_id ;
    
    $DB->set_field('course', 'idnumber', $sourcecourse->idnumber, array('id' => "{$response->courseid}"));

    // assign the localuser as author in all cases :
    // deployement : deployer will unassign self manually if needed
    // free use deployement : deployer will take control over session
    // retrofit : deployer will take control over new learning path in work
  
    $new_course = $DB->get_record('course',array('id'=>$newcourse_id));
    
    if (!empty($CFG->coursedelivery_defaultrole)){
        $coursecontext = context_course::instance($response->courseid);
        
       // role_assign($CFG->coursedelivery_defaultrole, $USER->id, $coursecontext->id);
       $manager = new course_enrolment_manager($PAGE,$new_course);
       $instances = $manager->get_enrolment_instances();
       $instance = array_pop($instances);
       $plugins = $manager->get_enrolment_plugins();
       $plugin = $plugins['manual'] ;
       $plugin->enrol_user($instance, $USER->id, $CFG->coursedelivery_defaultrole);

    }

    // give back the information for jumping
    $response->status = RPC_SUCCESS;
    $response->username = $username;
    $response->remotehostroot = $remotehostroot;
    $response->course_idnumber = $sourcecourse->idnumber;
    $response->catalogcourseid = $sourcecourse->id;

    ini_set('max_execution_time', $maxtime);
    ini_set('memory_limit', $maxmem);
    return publishflow_send_response($response, $json_response);
}

/**
* invokes the publication procedure for a published learning path. The user name
* that invokes this procedure MUST have sufficient priviledge on the local
* platform to create courses.
* the deployment procedure calls back the delivery site to get the archive of the 
* learning path if it is not already available, instanciate a new course silently.
* the end result of calling this RPC call should be a jump to this course
* setup form, for finishing setup.
*
* @param string $username
* @param string $remotehostroot
* @param string $action what to do
* @param string $sourcecourseserial the course information as a JSON serialized string
* @param string $forcereplace if true, forces download and replacement of the existing course
*/
function delivery_publish($callinguser, $action, $sourcecourseserial, $forcereplace, $json_response = true){
    global $CFG, $USER, $SESSION,$DB;
    $response->status = RPC_SUCCESS;
    $response->errors = array();
    $response->error = '';
    $sourcecourse = json_decode($sourcecourseserial);

    $callinguser = (array)$callinguser;
    if ($auth_response = publishflow_rpc_check_user((array)$callinguser, 'block/publishflow:publish')){
        return publishflow_send_response($auth_response, $json_response, true);
    }
    $remotehostroot = $callinguser['remotehostroot'];

    switch($action){
        case 'unpublish' : {
            $DB->set_field('course', 'visible', 0, array('idnumber' => $sourcecourse->idnumber)); 
            $response->status = RPC_SUCCESS;
            $response->username = $username;
            $response->remotehostroot = $remotehostroot;
            $response->courseid = $DB->get_field('course', 'id', array('idnumber' => $sourcecourse->idnumber));
            return publishflow_send_response($response, $json_response);
        }
        break;
        case 'publish' : {
            // Course exists and we just republish it
            $courseexists = $DB->count_records('course', array('idnumber' => $sourcecourse->idnumber));
            if (!$forcereplace && $courseexists){
                // we do not need anything else than making this course(s) visible back.
                $DB->set_field('course', 'visible', 1, array('idnumber' => $sourcecourse->idnumber)); 
                $response->status = RPC_SUCCESS;
                $response->username = $username;
                $response->remotehostroot = $remotehostroot;
                // TODO : are we always sure there only one ?
                $response->courseid = $DB->get_field('course', 'id', array('idnumber' => $sourcecourse->idnumber));
                return publishflow_send_response($response, $json_response);
            }

            // Course does not exist, or we are asked to renew the volume
            // if course exists, we must hide all previous instances of such a course
            // National administrator or section administrator should then clean up 
            // irrelevant instances 
            if ($courseexists){
                $DB->set_field('course', 'visible', 0, array('idnumber' => $sourcecourse->idnumber)); 
            }

            if (!isset($CFG->coursedelivery_coursefordelivery) || !$course = $DB->get_record('course', array('id' => $CFG->coursedelivery_coursefordelivery))){
                $response->status = RPC_FAILURE_CONFIG;
                $response->errors[] = "Target configuration seems being empty.";
                $response->error = "Target configuration seems being empty.";
                return publishflow_send_response($response, $json_response);
            }
            // first bump up server execution characteristics
            $maxtime = ini_get('max_execution_time');
            $maxmem = ini_get('memory_limit');
            ini_set('max_execution_time', '600');
            ini_set('memory_limit', '150M');

            // Check the local availability of the archive
            $realpath = '';//$CFG->dataroot.'/'.$CFG->coursedelivery_coursefordelivery.'/'.$sourcecourse->idnumber.'.zip';
            // If we force replace with a new one, delete old local archive.
            if ($forcereplace){
                unlink($realpath);
            }

            if (!file_exists($realpath)){        

                // We de not have a package at remote side
                debug_trace('Up to fetch a course back');
                $mnet_host = new mnet_peer();
                $mnet_host->set_wwwroot($remotehostroot);
                $caller = (object)$callinguser;
                $caller->remotehostroot = $CFG->wwwroot;

                if (empty($CFG->coursedeliveryislocal)){
                    // We try to get the archive file through the network
                    $transfercomplete = false;
                    $archivefile = '';
                    $offset = 0;
                    while(!$transfercomplete){
                        // Get the archive on back call
                        $rpcclient = new mnet_xmlrpc_client();
                        $rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_deliver');
                        $rpcclient->add_param($caller, 'struct');
                        $rpcclient->add_param($sourcecourse->id, 'int');
                        $rpcclient->add_param($offset, 'int');
                        $rpcclient->add_param(4194304, 'int'); // 4Mo transfer max
                        if (!$rpcclient->send($mnet_host)){
                            // make a suitable response
                            $response->status = RPC_FAILURE;
                            $response->error = 'Remote error : Could not get the course archive file';
                            $response->errors[] = '<br/>XML-RPC Callback errors :<br/>';
                            if (!empty($rpcclient->errors)){
                                foreach($rpcclient->errors as $error){
                                    $response->errors[] = $error;
                                }
                            }
                            ini_set('max_execution_time', $maxtime);
                            ini_set('memory_limit', $maxmem);
                            return publishflow_send_response($response, $json_response);
                        }
                        $backresponse = json_decode($rpcclient->response);
                        debug_trace('XML-RPC backcall succeeded');
                        /// Processing XML-RPC response
                        // XML-RPC worked well, but remote procedure may fail
                        if ($backresponse->status == RPC_SUCCESS){
                            $archivefile = $archivefile . base64_decode($backresponse->file);
                            $archivename = $backresponse->archivename;
                            if ($backresponse->remains == 0){
                                $transfercomplete = true;
                            } else { 
                                // File is chunked because too big. prepare for next chunk
                                $offset = $backresponse->filepos;
                            }
                        } else { // XML-RPC remote procedure failed, although transmission is OK
                            $response->status = RPC_FAILURE;
                            $response->error = 'Remote error : Could not get the course archive file because of call back remote error : ';
                            $response->errors[] = 'Remote error : Could not get the course archive file because of call back remote error : ';
                            if (!empty($backresponse->errors)){
                                foreach($backresponse->errors as $error){
                                    $response->errors[] = $error;
                                }
                            }
                            ini_set('max_execution_time', $maxtime);
                            ini_set('memory_limit', $maxmem);
                            return publishflow_send_response($response, $json_response);
                        }
                    }
                    // Save the archive locally
                    if (!file_exists(dirname($realpath))){
                        filesystem_create_dir($realpath, $recursive = 1);
                    }
                    $status = backup_data2file($realpath, $archivefile);
                } else {
                    // we make a local delivery by copying the archive directly in file system
                    // this very fast, but is not workable on remotely distributed hosts.
                    // both moodle data must be on the same storage system.

                    // Get the archive name on back call
                    $rpcclient = new mnet_xmlrpc_client();
                    $rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_deliver');
                    $rpcclient->add_param($caller, 'struct');
                    $rpcclient->add_param($sourcecourse->id, 'int');

                    if (!$rpcclient->send($mnet_host)){
                        // make a suitable response
                        $response->status = RPC_FAILURE;
                        $response->error = 'Remote error : Could not get the course archive descriptor for local delivery ';
                        $response->error .= '<br/>XML-RPC Callback errors :<br/>';
                        $response->error .= implode('<br/>', $rpcclient->error);
                        ini_set('max_execution_time', $maxtime);
                        ini_set('memory_limit', $maxmem);
                        return publishflow_send_response($response, $json_response);
                    }
                    $backresponse = json_decode($rpcclient->response);
                    /// Processing XML-RPC response
                    // XML-RPC worked well, but remote procedure may fail
                    if ($backresponse->status == RPC_SUCCESS){
                        if ($backresponse->local){
                            $archivename = $backresponse->archivename;
                            $temp_file = $CFG->dataroot."/temp/backup/".$backresponse->filename;
                            // actually locally copying archive
                            if (!copy($archivename, $temp_file)){
                                $response->status = RPC_FAILURE;
                                $response->errors[] = 'Local delivery : copy error';
                                $response->error = 'Local delivery : copy error';
                                ini_set('max_execution_time', $maxtime);
                                ini_set('memory_limit', $maxmem);
                                return publishflow_send_response($response, $json_response);
                            }
                        } else {
                            $response->status = RPC_FAILURE;
                            $response->error = 'Local delivery remote : remote end has not local delivery set on';
                            ini_set('max_execution_time', $maxtime);
                            ini_set('memory_limit', $maxmem);
                            return publishflow_send_response($response, $json_response);
                        }
                    } else { // XML-RPC remote procedure failed, although transmission is OK
                        $response->status = RPC_FAILURE;
                        $response->errors[] = 'Remote error : Could not get remote file description';
                           $response->error = 'Remote error : Could not get remote file description';
                        ini_set('max_execution_time', $maxtime);
                        ini_set('memory_limit', $maxmem);
                        return publishflow_send_response($response, $json_response);
                    }                        
                }
            } else {
                debug_trace('PUBLISHING : Remote side has already an archive there');
            }
            // TODO : extract the archive for deployment and make proper $restore and $info record
            // Restores a new course silently, giving sufficient parameters and force category
            $deploycat = $DB->get_record('course_categories', array('id' => $CFG->coursedelivery_deploycategory));
            
            $newcourse_id =  restore_automation::run_automated_restore(null,$temp_file,$deploycat->id) ;
            
            /*$course_header->category = $deploycat;
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
            $restore->restore_restorecatto = '';
            $restore->course_startdateoffset = 0;
            $restore->metacourse = 0;
            $restore->backup_unique_code = time();

            debug_trace('PUBLISHING : Up to deploy course. We have everything in '.$realpath);
            restore_create_new_course($restore, $course_header);
            import_backup_file_silently($realpath, $course_header->course_id, true, false, array('restore_course_files' => 1));

            // move backup file to definitve location
            if (isset($archivename)){
                $archivefilename = basename($archivename);
                $finalpath = $CFG->dataroot.'/'.$course_header->course_id.'/backupdata/'.$archivefilename;
                copy($realpath, $finalpath);
            }*/
            // confirm/force idnumber in new course
            $response->courseid = $newcourse_id;
            $DB->set_field('course', 'idnumber', $sourcecourse->idnumber, array('id' => "{$response->courseid}"));

            // confirm/force guest opening
          //  $DB->set_field('course', 'guest', 1, array('id' => "{$response->courseid}"));

            // confirm/force not enrollable
          //  $DB->set_field('course', 'enrollable', 0, array('id' => "{$response->courseid}"));

            /*
            Pairformance dev 
            if (!isset($sourcecourse->retrofit)){
                // confirm/force published status in course record if not done, to avoid strange access effects
                $DB->set_field('course', 'approval_status_id', COURSE_STATUS_PUBLISHED, array('id' => "{$response->courseid}"));
            } else {
                // reset to unsubmitted course if we are backfeeding the factory
                $DB->set_field('course', 'approval_status_id', COURSE_STATUS_NOTSUBMITTED, array('id' => "{$response->courseid}"));
            }
            */
            // give back the information for jumping
            $response->status = RPC_SUCCESS;
            $response->course_idnumber = $sourcecourse->idnumber;
            $response->username = $username;
            $response->remotehostroot = $remotehostroot;
            $response->catalogcourseid = $sourcecourse->id;
            ini_set('max_execution_time', $maxtime);
            ini_set('memory_limit', $maxmem);
            return publishflow_send_response($response, $json_response);

        }
        break;
    }
}

// converts properly output message.
function publishflow_send_response($response, $json_response = true, $isjson = false){
    if ($isjson){
        if ($json_response){
            return $response;
        } else {
            return json_decode($response);
        }
    } else {
        if ($json_response){
            return json_encode($response);
        } else {
            return $response;
        }
    }    
}

?>