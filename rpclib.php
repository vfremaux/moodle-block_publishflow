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
 * these RPC functions are intended to be fired by external systems to control Moodle
 * deployment of courses to MNET satellites
 *
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/accesslib.php');
require_once($CFG->dirroot.'/mnet/xmlrpc/client.php');
require_once($CFG->libdir.'/xmlize.php'); // Needed explicitely as all situations do not provide it.

require_once($CFG->dirroot.'/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot.'/blocks/publishflow/lib.php');
require_once($CFG->dirroot.'/blocks/publishflow/backup/restore_automation.class.php');
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
 * User is an array with at least three members :
 * - username: the local username
 * - remotehostroot: the moodle instance the call is comming from
 * - remoteuserhostoot: the moodle instance the user is originate from which may not be remotehostroot if the user is
 * remotely a remote mnet account.
 *
 * @param object $user The calling user as an array
 * @param string $capability The capability to check.
 * @param string $context The capability's context ('any' or int contextid / CONTEXT_SYSTEM by default).
 * @return string Some json encoded result;
 */
function publishflow_rpc_check_user($user, $capability, $context = null, $jsonresponse = false) {
    global $CFG, $USER, $DB;

    $config = get_config('block_publishflow');

    if (function_exists('debug_trace')) {
        debug_trace("Checking user identity : ".json_encode($user));
    }

    // Creating response.
    $response = new stdclass;
    $response->status = RPC_SUCCESS;
    $response->errors = array();
    $response->error = '';

    // Checking user.
    if (!array_key_exists('username', $user) ||
            !array_key_exists('remoteuserhostroot', $user) ||
                    !array_key_exists('remotehostroot', $user)) {
        $response->status = RPC_FAILURE_USER;
        $response->errors[] = 'Bad client user format.';
        $response->error = 'Bad client user format.';
        if (function_exists('debug_trace')) {
            debug_trace("User failed bad format");
        }
        return json_encode($response);
    }

    // Get local identity.
    $params = array('wwwroot' => $user['remotehostroot']);
    if (!$remotehost = $DB->get_record('mnet_host', $params)) {
        $response->status = RPC_FAILURE;
        $response->errors[] = 'Calling host is not registered. Check MNET configuration';
        $response->error = 'Calling host is not registered. Check MNET configuration';
        if (function_exists('debug_trace')) {
            debug_trace("User failed no host");
        }
        return json_encode($response);
    }

    $userhost = $DB->get_record('mnet_host', array('wwwroot' => $user['remoteuserhostroot']));

    // Resolve same IDNumber identity.
    if (is_dir($CFG->dirroot.'/blocks/user_mnet_hosts')) {
        // We may have a primary assignation that should be respected. This assignation may be or not be mnet.
        $umhconfig = get_config('block_user_mnet_hosts');
        if (!empty($umhconfig->singleaccountcheck)) {
            require_once($CFG->dirroot.'/blocks/user_mnet_hosts/xlib.php');
            $localuser = user_mnet_hosts_get_local_user($user, $remotehost);
        }
    }
    if (!$localuser) {
        // Try standard MNET resolution.
        $params = array('username' => addslashes($user['username']), 'mnethostid' => $userhost->id);
        $localuser = $DB->get_record('user', $params);
    }

    if (!$localuser) {
        $response->status = RPC_FAILURE_USER;
        $response->errors[] = "Calling user has no local account. Register remote user first";
        $response->error = "Calling user has no local account. Register remote user first";
        if (function_exists('debug_trace')) {
            debug_trace("User failed no local user");
        }
        return json_encode($response);
    }
    // Replacing current user by remote user.

    $USER = $localuser;
    if (function_exists('debug_trace')) {
        debug_trace("User catched is : ".json_encode($USER));
    }

    // Checking capabilities.
    if (!empty($capability)) {

        // Pre test the profile for deployement.
        if ($capability == 'block/publishflow:deploy') {
            if (!empty($config->deployprofilefield)) {
                if (\core_text::strpos($config->deployprofilefield, 'profile_field_') === 0) {
                    $fieldname = str_replace('profile_field_', '', $config->deployprofilefield);
                    if ($field = $DB->get_record('user_info_field', array('shortname' => $fieldname))) {
                        // Custom profile value.
                        $params = array('userid' => $USER->id, 'fieldid' => $field->id);
                        $info = $DB->get_field('user_info_data', 'data', $params);
                    } else {
                        // Standard profile value.
                        $fieldname = $config->deployprofilefield;
                        $info = @$USER->$fieldname;
                    }
                    // Info can be "0".
                    if ($info == $config->deployprofilefieldvalue) {
                        // Give success.
                        if (!empty($jsonresponse)) {
                            return json_encode($response);
                        }
                        return '';
                    }
                }
            }
        }

        if ($context == 'any') {
            if (!block_publishflow_has_capability_somewhere($capability, false, false, false,
                                                            CONTEXT_COURSE.','.CONTEXT_COURSECAT)) {
                $response->status = RPC_FAILURE_CAPABILITY;
                $response->errors[] = 'Local user\'s identity has no capability to run';
                $response->error = 'Local user\'s identity has no capability to run';
                return json_encode($response);
            }
        } else {
            if (is_null($context)) {
                $context = context_system::instance();
            } else {
                $context = context::instance_by_id($context);
            }
            if (function_exists('debug_trace')) {
                debug_trace("Testing capability : $capability on $CFG->wwwroot ");
            }
            if (!has_capability($capability, $context, $localuser->id)) {
                $response->status = RPC_FAILURE_CAPABILITY;
                $response->errors[] = 'Local user\'s identity has no capability to run';
                $response->error = 'Local user\'s identity has no capability to run';
                return json_encode($response);
            }
        }
    }
    if (!empty($jsonresponse)) {
        return json_encode($response);
    }
    return '';
}

/**
 * external entry point for deploying a course template.
 * @param array $callinguser the caller coordinates (username, user host root, calling host)
 * @param string $idfield a string that tells the identifying basis (id|idnumber|shortname)
 * @param string $courseidentifier the identifying value
 * @param string $whereroot where to deploy. Must be a known mnet_host root
 * @param array $parmsoverride an array of overriding course attributes can superseed the template course settings
 * @param bool $jsonresponse true if we want a jsonified serialized response.
 */
function publishflow_rpc_deploy($callinguser, $idfield, $courseidentifier, $whereroot, $parmsoverride = null,
                                $jsonresponse = true) {
    global $USER, $CFG, $DB;

    if (function_exists('debug_trace')) {
        debug_trace($CFG->wwwroot." DEPLOY");
    }

    $extresponse->status = RPC_SUCCESS;
    $extresponse->errors = array();
    $extresponse->error = '';

    if ($authresponse = publishflow_rpc_check_user((array)$callinguser, 'block/publishflow:deploy')) {
        return publishflow_send_response($authresponse, $jsonresponse, true);
    }

    switch ($idfield) {
        case 'id': {
            $course = $DB->get_record('course', array('id' => $courseidentifier));
            break;
        }

        case 'shortname': {
            $course = $DB->get_record('course', array('shortname' => $courseidentifier));
            break;
        }

        case 'idnumber': {
            $course = $DB->get_record('course', array('idnumber' => $courseidentifier));
            break;
        }
    }

    if (!empty($parmsoverride)) {
        foreach ($parmsoverride as $key => $value) {
            if (isset($course->$key)) {
                $course->$key = $parmsoverride[$key];
            }
        }
    }

    if (!empty($whereroot)) {
        $params = array('wwwroot' => $whereroot, 'deleted' => 0);
        if (!$mnethost = $DB->get_record('mnet_host', $params)) {
            $extresponse->status = RPC_FAILURE;
            $extresponse->error = 'Deployment target host not found (or deleted)';
            return publishflow_send_response($extresponse, $jsonresponse);
        }
    }

    if (!empty($USER->mnethostid)) {
        if ($userhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid, 'deleted' => 0))) {
            $userwwwroot = $userhost->wwwroot;
        } else {
            $extresponse->status = RPC_FAILURE;
            $extresponse->error = 'User host not found (or deleted)';
            return publishflow_send_response($extresponse, $jsonresponse);
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
    $rpcclient->add_param(1, 'int'); // Prepared for forcing replacement.
    $mnethost = new mnet_peer();
    $mnethost->set_wwwroot($whereroot);

    if (!$rpcclient->send($mnethost)) {
        $extresponse->status = RPC_FAILURE;
        $extresponse->errors[] = 'REMOTE : '.implode("<br/>\n", $rpcclient->errors);
        $extresponse->error = 'REMOTE : '.implode("<br/>\n", $rpcclient->errors);
        return publishflow_send_response($extresponse, $jsonresponse);
    }

    $response = json_decode($rpcclient->response);

    if ($response->status == 200) {
        $remotecourseid = $response->courseid;
        $linkstr = get_string('jumptothecourse', 'block_publishflow');
        if ($USER->mnethostid != $mnethost->id) {
            $params = array('hostid' => $mnethost->id, 'wantsurl' => '/course/view.php?id='.$remotecourseid);
            $jumpurl = new moodle_url('/auth/mnet/jump.php', $params);
            $extresponse->message = '<a href="'.$jumpurl.'">'.$linkstr.'</a>';
        } else {
            $extresponse->message = "<a href=\"{$mnethost->wwwroot}/course/view.php?id={$remotecourseid}\">".$linkstr.'</a>';
        }
        return publishflow_send_response($extresponse, $jsonresponse);
    } else {
        $extresponse->status = RPC_FAILURE;
        $extresponse->errors[] = 'Remote application error : ';
        $extresponse->errors[] = $response->errors;
        $extresponse->error = 'Remote application error.';
        return publishflow_send_response($extresponse, $jsonresponse);
    }
}

/**
 * test for existance.
 * @param object $callinguser The caller as an object
 * @param string $idfield the name of the identifying field
 * @param string $courseidentifier the course identifier
 * @param string $whereroot a wwwroot of the mnet moodle to check in
 * @param bool $jsonresponse true if we want a jsonified serialized response.
 */
function publishflow_rpc_course_exists($callinguser, $idfield, $courseidentifier, $whereroot, $jsonresponse = true) {
    global $CFG, $DB;

    if (function_exists('debug_trace')) {
        debug_trace($CFG->wwwroot." COURSE EXISTS");
    }

    $extresponse->status = RPC_SUCCESS;
    $extresponse->errors = array();
    $extresponse->error = '';

    if ($authresponse = publishflow_rpc_check_user((array)$callinguser, 'block/publishflow:deploy')) {
        return publishflow_send_response($authresponse, $jsonresponse, true);
    }
    if ($whereroot == $CFG->wwwroot || empty($whereroot)) {
        switch ($idfield) {
            case 'id': {
                $course = $DB->get_record('course', array('id' => $courseidentifier));
                break;
            }

            case 'shortname': {
                $course = $DB->get_record('course', array('shortname' => $courseidentifier));
                break;
            }

            case 'idnumber': {
                $course = $DB->get_record('course', array('idnumber' => $courseidentifier));
                break;
            }
        }
        if (empty($course)) {
            $extresponse->status = RPC_FAILURE_RECORD;
            $extresponse->errors[] = 'Unkown course.';
            $extresponse->error = 'Unkown course.';
            publishflow_send_response($extresponse, $jsonresponse);
        }
        $extresponse->status = RPC_SUCCESS;
        $extresponse->message = "Course exists.";
        $visibility = $course->visibility;
        $cat = $DB->get_record('course_categories', array('id' => $course->category));
        $visibility = $visibility && $cat->visibility;
        while ($cat->parent) {
            $cat = $DB->get_record('course_categories', array('id' => $course->category));
            $visibility = $visibility && $cat->visibility;
        }

        $extresponse->visible = $visibility;

    } else {
        // Bounce to remote host.

        if ($remotedeleted = $DB->get_field('mnet_host', 'deleted', array('wwwroot' => $whereroot))) {
            $extresponse->status = RPC_FAILURE_RECORD;
            $extresponse->error = 'Unkown whereroot.';
            publishflow_send_response($extresponse, $jsonresponse);
        }

        if (!empty($USER->mnethostid)) {
            if ($userhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid, 'deleted' => 0))) {
                $userwwwroot = $userhost->wwwroot;
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
        $mnethost = new mnet_peer();
        $mnethost->set_wwwroot($whereroot);

        if (!$rpcclient->send($mnethost)) {
            $extresponse->status = RPC_FAILURE;
            $extresponse->errors[] = 'REMOTE : '.implode("<br/>\n", $rpcclient->error);
            $extresponse->error = 'REMOTE : '.implode("<br/>\n", $rpcclient->error);
            return publishflow_send_response($extresponse, $jsonresponse);
        }
        $response = json_decode($rpcclient->response);
        if ($response->status == 200) {
            $extresponse->status = RPC_SUCCESS;
            $extresponse->message = 'course exists';
            return publishflow_send_response($extresponse, $jsonresponse);
        } else {
            $extresponse->status = RPC_FAILURE;
            $extresponse->errors[] = 'Remote application error : ';
            $extresponse->errors[] = $response->errors;
            $extresponse->error = 'Remote application error.';
            return publishflow_send_response($extresponse, $jsonresponse);
        }
    }

    return publishflow_send_response($extresponse, $jsonresponse);
}

/**
 * opens a non running course or ask remotely for opening.
 * @param object $callinguser the calling user as an object
 * @param string $idfield te field identifying the course
 * @param string $courseidentifier the course identifier
 * @param string $whereroot the mnet host where to operate
 * @param string $mode the opening mode, that may send or not send notifications to enrolled users
 * @param bool $jsonresponse true if we want a jsonified serialized response.
 */
function publishflow_rpc_open_course($callinguser, $idfield, $courseidentifier, $whereroot, $mode, $jsonresponse = true) {
    global $CFG, $DB;

    if (function_exists('debug_trace')) {
        debug_trace($CFG->wwwroot." OPEN COURSE");
    }

    $extresponse->status = RPC_SUCCESS;
    $extresponse->errors = array();
    $extresponse->error = '';

    if ($authresponse = publishflow_rpc_check_user((array)$callinguser, 'block/publishflow:deploy')) {
        return publishflow_send_response($authresponse, $jsonresponse, true);
    }
    if ($whereroot == $CFG->wwwroot || empty($whereroot)) {
        switch ($idfield) {
            case 'id': {
                $course = $DB->get_record('course', array('id' => $courseidentifier));
                break;
            }

            case 'shortname': {
                $course = $DB->get_record('course', array('shortname' => $courseidentifier));
                break;
            }

            case 'idnumber': {
                $course = $DB->get_record('course', array('idnumber' => $courseidentifier));
                break;
            }
        }

        if (empty($course)) {
            $extresponse->status = RPC_FAILURE_RECORD;
            $extresponse->errors[] = 'Unkown course.';
            $extresponse->error = 'Unkown course.';
            publishflow_send_response($extresponse, $jsonresponse);
        }
        publishflow_session_open($course, $mode); // Mode stands for notify signal.
        $extresponse->message = "Course open.";

    } else {
        // Bounce to remote host.

        if ($remotedeleted = $DB->get_field('mnet_host', 'deleted', array('wwwroot' => $whereroot))) {
            $extresponse->status = RPC_FAILURE_RECORD;
            $extresponse->error = 'Unkown whereroot.';
            publishflow_send_response($extresponse, $jsonresponse);
        }

        if (!empty($USER->mnethostid)) {
            if ($userhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid, 'deleted' => 0))) {
                $userwwwroot = $userhost->wwwroot;
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
        $mnethost = new mnet_peer();
        $mnethost->set_wwwroot($whereroot);

        if (!$rpcclient->send($mnethost)) {
            $extresponse->status = RPC_FALURE;
            $extresponse->errors[] = 'REMOTE : '.implode("<br/>\n", $rpcclient->error);
            $extresponse->error = 'REMOTE : '.implode("<br/>\n", $rpcclient->error);
            return publishflow_send_response($extresponse, $jsonresponse);
        }
        $response = json_decode($rpcclient->response);
        if ($response->status == 200) {
            $extresponse->status = RPC_SUCCESS;
            $extresponse->message = 'course was successfully open';
            return publishflow_send_response($extresponse, $jsonresponse);
        } else {
            $extresponse->status = RPC_FAILURE;
            $extresponse->errors[] = 'Remote application error : ';
            $extresponse->errors[] = $response->errors;
            $extresponse->error = 'Remote application error.';
            return publishflow_send_response($extresponse, $jsonresponse);
        }
    }

    return publishflow_send_response($extresponse, $jsonresponse);
}

/**
 * closes a running course or ask remotely for closure.
 * @param object $callinguser The calling user as an object
 * @param string $idfield The field identifying the course
 * @param string $courseidentifier The course identifier
 * @param string $whereroot The mnet host where to operate
 * @param string $mode The closing mode, that may give more or less access to enrolled users
 * @param bool $jsonresponse true if we want a jsonified serialized response.
 */
function publishflow_rpc_close_course($callinguser, $idfield, $courseidentifier, $whereroot, $mode, $jsonresponse = true) {
    global $CFG, $DB;

    if (function_exists('debug_trace')) {
        debug_trace($CFG->wwwroot." CLOSE COURSE");
    }

    $extresponse->status = RPC_SUCCESS;
    $extresponse->errors = array();
    $extresponse->error = '';

    if ($authresponse = publishflow_rpc_check_user((array)$callinguser, 'block/publishflow:deploy')) {
        return publishflow_send_response($authresponse, $jsonresponse, true);
    }
    if ($whereroot == $CFG->wwwroot || empty($whereroot)) {

        switch ($idfield) {
            case 'id': {
                $course = $DB->get_record('course', array('id' => $courseidentifier));
                break;
            }

            case 'shortname': {
                $course = $DB->get_record('course', array('shortname' => $courseidentifier));
                break;
            }

            case 'idnumber': {
                $course = $DB->get_record('course', array('idnumber' => $courseidentifier));
                break;
            }
        }

        if (empty($course)) {
            $extresponse->status = RPC_FAILURE_RECORD;
            $extresponse->errors[] = 'Unkown course.';
            $extresponse->error = 'Unkown course.';
            publishflow_send_response($extresponse, $jsonresponse);
        }

        if ($err = publishflow_course_close($course, $mode, true)) {
            $extresponse->status = RPC_FAILURE;
            $extresponse->errors[] = $err;
            $extresponse->error = $err;
        }
    } else {
        // Bounce to remote host.

        if ($remotedeleted = $DB->get_field('mnet_host', 'deleted', array('wwwroot' => $whereroot))) {
            $extresponse->status = RPC_FAILURE_RECORD;
            $extresponse->error = 'Unkown whereroot.';
            publishflow_send_response($extresponse, $jsonresponse);
        }

        if (!empty($USER->mnethostid)) {
            if ($userhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid, 'deleted' => 0))) {
                $userwwwroot = $userhost->wwwroot;
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
        $mnethost = new mnet_peer();
        $mnethost->set_wwwroot($whereroot);

        if (!$rpcclient->send($mnethost)) {
            $extresponse->status = RPC_FALURE;
            $extresponse->errors[] = 'REMOTE : '.implode("<br/>\n", $rpcclient->error);
            $extresponse->error = 'REMOTE : '.implode("<br/>\n", $rpcclient->error);
            return publishflow_send_response($extresponse, $jsonresponse);
        }
        $response = json_decode($rpcclient->response);
        if ($response->status == 200) {
            $extresponse->status = RPC_SUCCESS;
            $extresponse->message = 'course closed successfully';
            return publishflow_send_response($extresponse, $jsonresponse);
        } else {
            $extresponse->status = RPC_FAILURE;
            $extresponse->errors[] = 'Remote application error : ';
            $extresponse->errors[] = $response->errors;
            $extresponse->error = 'Remote application error.';
            return publishflow_send_response($extresponse, $jsonresponse);
        }
    }
    return publishflow_send_response($extresponse, $jsonresponse);
}

/**
 * RPC function for platform catalog updating
 * This is going to reply with the complete category tree and the moodle node type.
 *
 * We do not take the private categories, as we can't easily know who was allowed to see them.
 * @param object $callinguser the calling user as an object
 *
 * @author Edouard Poncelet
 */
function publishflow_updateplatforms($callinguser) {
    global $DB;

    $config = get_config('block_publishflow');

    $nodetype = $config->moodlenodetype;
    $records = $DB->get_records('course_categories', array('visible' => '1'), '', 'name, id, parent, sortorder');

    $response = new stdclass;
    if ($nodetype == '') {
        $response = new StdClass;
        $response->status = RPC_FAILURE;
        $response->errors[] = 'Not Publishflow Compatible Platform (check remote platform type publishflowsettings)';
        $response->error = 'Not Publishflow Compatible Platform (check remote platform type publishflowsettings)';
    } else {
        $response = new StdClass;
        $response->status = RPC_SUCCESS;
        $response->errors = array();
        $response->error = '';
        $response->node = $config->moodlenodetype;

        foreach ($records as $record) {
            $values = array('id' => $record->id,
                            'name' => $record->name,
                            'parentid' => $record->parent,
                            'sortorder' => $record->sortorder);
            $response->content[] = $values;
        }
    }

    return json_encode($response);
}

/*
 * RPC functions for coursedelivery related data or content administration services
 *
 * @package mod-coursedelivery
 * @author Valery Fremaux
 *
 */

if (!defined('COURSESESSIONS_PRIVATE')) {
    define('COURSESESSIONS_PRIVATE', 0);
    define('COURSESESSIONS_PROTECTED', 1);
    define('COURSESESSIONS_PUBLIC', 2);
}

/**
 * retrieves instances of a course that are in use in this moodle. this is keyed by the idnumber scheme
 * Applies to: Training Satellites
 * @param object $callinguser the calling user as an object
 * @param string $courseidnumber The course idnumber
 * @param string $jsonresponse if true, will provide answer as a serialized json string.
 */
function delivery_get_sessions($callinguser, $courseidnumber, $jsonresponse) {
   global $USER, $DB;

    if (function_exists('debug_trace')) {
        debug_trace($CFG->wwwroot." GET SESSIONS");
    }

    $response->status = RPC_SUCCESS;
    $response->errors = array();
    $response->error = '';

    // Get local identity for user.
    $authresponse = publishflow_rpc_check_user((array)$callinguser);

    // Set a default value for publicsessions.
    if (!isset($config->publicsessions)) {
        set_config('coursedelivery_publicsessions', 1);
    }

    /*
     * If sessions are not publicly shown, we must check agains user identity
     * If protected, we just not allow deployment but let session be displayed.
     */
    $candeploy = false;
    $candeployfree = false;
    $canpublish = false;
    if ($config->publicsessions != COURSESESSIONS_PUBLIC) {

        // User must be registered or we have an error reading session data.
        if ($config->publicsessions == COURSESESSIONS_PRIVATE && $authresponse) {
            return publishflow_send_response($authresponse, $jsonresponse, true);
        }
        // In semi private mode, we can continue without error but just check capabilities if real user.
        if (!$authresponse) {
            $candeploy = has_capability('block/publishflow:deploy', context_system::instance(), $USER->id);
            $canpublish = has_capability('block/publishflow:publish', context_system::instance(), $USER->id);
        }
    }
    // Get all courses that are instances of the given idnumber LP identifier.

    $fields = 'id,shortname,fullname,startdate,visible';
    $sessions = $DB->get_records('course', array('idnumber' => $courseidnumber), 'shortname', $fields);
    if (!empty($sessions)) {
        foreach ($sessions as $session) {
            $context = context_course::instance($session->id);
            $systemcontext = context_system::instance();

            if ($session->visible ||
                    has_capability('moodle/course:viewhiddencourses', $context, $USER->id) ||
                            $canpublish) {
                $session->noaccess = 1; // Session is not reachable by jump.
                if ($config->publicsessions || has_capability('moodle/course:view', $context, $USER->id)) {
                    $session->noaccess = 0; // Session is reachable by jump.
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
 * @param string $callinguser the caller as an object
 * @param int $lpcatalogcourseid the requested course
 * @param int $transferoffset the byte the transfer should start at
 * @param int $transferlimit the max amount of bytes to be transfered in the chunk. Is set to 0, 
 * transfers all file as one block.
 * @param string $jsonresponse if true, will provide answer as a serialized json string.
 * @return a base 64 encoded file, or file chunk that sends the zip archive content
 */
function delivery_deliver($callinguser, $lpcatalogcourseid, $transferoffset = 0, $transferlimit = 0, $jsonresponse = true) {
    global $CFG, $DB;

    if (function_exists('debug_trace')) {
        debug_trace($CFG->wwwroot." DELIVER");
    }

    $config = get_config('block_publishflow');

    $response->status = RPC_SUCCESS;
    $response->errors = array();
    $response->error = '';

    // Check username and origin of the query.
    if ($authresponse = publishflow_rpc_check_user((array)$callinguser, '', 'any')) {
        return publishflow_send_response($authresponse, $jsonresponse, true);
    }

    // Check availability of the course.
    if (!$course = $DB->get_record('course', array('id' => $lpcatalogcourseid))) {
        $response->status = RPC_FAILURE;
        $response->errors[] = "Delivery source course {$lpcatalogcourseid} does not exist";
        $response->error = "Delivery source course {$lpcatalogcourseid} does not exist";
        return publishflow_send_response($response, $jsonresponse);
    }

    $file = delivery_check_available_backup($lpcatalogcourseid, $loopback);

    if (!empty($loopback)) {
        return publishflow_send_response($loopback, $jsonresponse);
    }

    if (empty($file)) {
        $response->status = RPC_FAILURE;
        $response->errors[] = 'No available deployable backup for this course '. ' ' . $file->get_filename();
        $response->error = 'No available deployable backup for this course '. ' ' . $file->get_filename();
        return publishflow_send_response($response, $jsonresponse);
    } else {

        // Move the file to temp folder.
        $tempfile = $CFG->tempdir.'/backup/'.$file->get_filename();
        $file->copy_content_to($tempfile);

        // Open the backup, get content, encode it and send it.
        if (file_exists($tempfile)) {
            if (empty($config->coursedeliveryislocal)) {
                $response->local = false; 
                backup_file2data($tempfile, $filecontent);
                $response->archivename = $tempfile;
                $encoded = base64_encode($filecontent);
                if ($transferlimit == 0) {
                    $response->file = $encoded;
                    $response->filepos = strlen($encoded);
                    $response->remains = 0;
                } else {
                    // Transfers a chunk.
                    $response->file = substr($encoded, $transferoffset, $transferlimit);
                    $transferredlength = strlen($response->file);
                    $response->filepos = $transferoffset + $transferredlength;
                    $response->remains = strlen(substr($encoded, $response->filepos));
                }
            } else {
                // Local delivery is on. We give only file reference.
                $response->local = true;
                $response->archivename =  $tempfile;
                $response->filename = $file->get_filename();
            }
            return publishflow_send_response($response, $jsonresponse);
        } else {
            $response->status = RPC_FAILURE;
            $response->errors[] = 'Chosen file does not exist : '. $realpath->path;
            $response->error = 'Chosen file does not exist : '. $realpath->path;
            return publishflow_send_response($response, $jsonresponse);
        }
    }
}

/**
 * checks locally if a deployable/publishable backup is available
 * @param reference $loopback variable given to setup an XMLRPC loopback message for testing
 * @return boolean
 */
function delivery_check_available_backup($courseid, &$loopback = null) {
    global $CFG, $DB;

    $fs = get_file_storage();
    $coursecontext = context_course::instance($courseid);
    $files = $fs->get_area_files($coursecontext->id, 'backup', 'publishflow', 0, 'timecreated', false);

    if (count($files) > 0) {
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
 * @param string $callinguser The calling user as an object
 * @param string $sourcecourseserial The complete course moodle metadata from the origin platform
 * @param bool $forcereplace if true, will replace the existing course with the same idnumber reference
 * @param object $parmsoverride an object which holds overrides of some attributes of the course
 * @param string $jsonresponse if true, will provide answer as a serialized json string.
 */
function delivery_deploy($callinguser, $sourcecourseserial, $forcereplace, $parmsoverride = null, $jsonresponse = true) {
    global $CFG, $USER, $DB, $PAGE;

    if (function_exists('debug_trace')) {
        debug_trace($CFG->wwwroot." DELIVERY_DEPLOY");
    }

    $config = get_config('block_publishflow');

    $response->status = RPC_SUCCESS;
    $response->errors = array();
    $response->error = '';

    $sourcecourse = json_decode($sourcecourseserial);
    $callinguser = (array)$callinguser;

    if ($authresponse = publishflow_rpc_check_user($callinguser, 'block/publishflow:deploy', 'any')) {
        return publishflow_send_response($authresponse, $jsonresponse, true);
    }

    if (function_exists('debug_trace')) {
        debug_trace($CFG->wwwroot." Starting delivery");
    }

    // First bump up server execution characteristics.
    ini_set('max_execution_time', '600');
    ini_set('memory_limit', '256M');

    // Check the local availability of the archive.

    $resourcefile = null;

    if (!empty($resource)) {
        $resourcefile = file_storage::get_file_by_id($resource->localfileid);
    }

    if (empty($resource) || $forcereplace) {

        // We de not have a package at remote side.
        $mnethost = new mnet_peer();
        $mnethost->set_wwwroot($callinguser['remotehostroot']);

        if (empty($config->coursedeliveryislocal)) {
            if (function_exists('debug_trace')) {
                debug_trace($CFG->wwwroot." Starting network delivery");
            }
            // We try to get the archive file through the network.
            $caller = (object)$callinguser;
            $caller->remotehostroot = $CFG->wwwroot;
            $transfercomplete = false;
            $archivefile = '';
            $offset = 0;
            while (!$transfercomplete) {

                // Get the archive on back call
                $rpcclient = new mnet_xmlrpc_client();
                $rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_deliver');
                $rpcclient->add_param($caller, 'struct');
                $rpcclient->add_param($sourcecourse->id, 'int');
                $rpcclient->add_param($offset, 'int');
                $rpcclient->add_param(4194304, 'int'); // 4Mo transfer max.
                $rpcclient->add_param(1, 'int'); // Json response required.

                if (!$rpcclient->send($mnethost)) {
                    // Make a suitable response.
                    $response->status = RPC_FAILURE;
                    $response->error = 'Remote error : Could not get the course archive file';
                    $response->errors[] = 'Remote error : Could not get the course archive file';
                    if ($rpcclient->errors) {
                        foreach ($rpcclient->errors as $error) {
                            $response->errors[] = $error;
                        }
                    }
                    return publishflow_send_response($response, $jsonresponse);
                }
                $backresponse = json_decode($rpcclient->response);
                /*
                 * Local test point. Stops after first chunk is transferred
                 * debug_trace('DEPLOY : XML-RPC backcall succeeded');
                 * Processing XML-RPC response
                 * XML-RPC worked well, and answers with remote test status
                 */
                if ($backresponse->status == RPC_TEST) {
                    $response->status = RPC_TEST;
                    $response->teststatus = 'Remote test point : '.$backresponse->teststatus;
                    return publishflow_send_response($response, $jsonresponse);
                }

                // XML-RPC worked well, but remote procedure may fail.
                if ($backresponse->status == RPC_SUCCESS) {
                    $archivefile = $archivefile . base64_decode($backresponse->file);
                    $archivename = $backresponse->archivename;
                    if ($backresponse->remains == 0) {
                        $transfercomplete = true;
                    } else {
                        // File is chunked because too big. prepare for next chunk.
                        $offset = $backresponse->filepos;
                    }
                } else {
                    // XML-RPC remote procedure failed, although transmission is OK.
                    $response->status = RPC_FAILURE;
                    $response->error = 'Remote error : Could not get the course archive file because of call back remote error.';
                    $response->errors[] = 'Remote error : Could not get the course archive file because of call back remote error.';
                    if ($backresponse->errors) {
                        foreach ($backresponse->errors as $error) {
                            $response->errors[] = $error;
                        }
                    }
                    return publishflow_send_response($response, $jsonresponse);
                }
            }
            // Save the archive locally.
            if (!file_exists(dirname($realpath))) {
                filesystem_create_dir($realpath, $recursive = 1);
            }
            $status = backup_data2file($realpath, $archivefile);
        } else {
            if (function_exists('debug_trace')) {
                debug_trace($CFG->wwwroot." Starting local filesystem delivery");
            }
            /*
             * we make a local delivery by copying the archive directly in file system
             * this very fast, but is not workable on remotely distributed hosts.
             * both moodle data must be on the same storage system.
             */

            $caller = (object)$callinguser;
            $caller->remotehostroot = $CFG->wwwroot;

            // Get the archive location (physical path) on back call.
            $rpcclient = new mnet_xmlrpc_client();
            $rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_deliver');
            $rpcclient->add_param($caller, 'struct');
            $rpcclient->add_param($sourcecourse->id, 'int');
            $rpcclient->add_param(0, 'int'); // Transfer offset.
            $rpcclient->add_param(0, 'int'); // Transfer limit.
            $rpcclient->add_param(1, 'int'); // Json response required.

            if (function_exists('debug_trace')) {
                debug_trace($CFG->wwwroot." Getting remotely known mbz location.");
            }
            if (!$rpcclient->send($mnethost)) {
                // Make a suitable response.
                $response->status = RPC_FAILURE;
                $response->error = 'Remote error : Could not get the LP archive descriptor for local delivery';
                $response->errors[] = '<br/>XML-RPC Callback errors :<br/>';
                if ($rpcclient->error->errors) {
                    foreach ($rpcclient->error as $error) {
                        $response->errors[] = $error;
                    }
                }
                return publishflow_send_response($response, $jsonresponse);
            }
            $backresponse = json_decode($rpcclient->response);
            /*
             * Local test point
             * debug_trace('DEPLOY : XML-RPC backcall succeeded for local delivery ');
             * Processing XML-RPC response
             * XML-RPC worked well, but remote procedure may fail
             */
            if ($backresponse->status == RPC_SUCCESS) {
                if ($backresponse->local) {
                    if (function_exists('debug_trace')) {
                        debug_trace($CFG->wwwroot." got file name {$backresponse->archivename}");
                    }
                    $archivename = $backresponse->archivename;  // Contains the file full path.
                    $filename = $backresponse->filename;

                    $tempfile = $CFG->tempdir.'/backup/'.$filename;
                    $destination_file_path = $CFG->tempdir.'/backup/'.$filename;
                    if (!is_dir($CFG->tempdir.'/backup')) {
                        mkdir($CFG->tempdir.'/backup/', 0777, true);
                    }

                    // Actually locally copying archive.
                    if (!copy($archivename, $tempfile)) {
                        $response->status = RPC_FAILURE;
                        $response->error = "Local delivery : copy error from [$archivename] to [$tempfile] ";
                        $response->errors[] = "Local delivery : copy error from [$archivename] to [$tempfile] ";
                        return publishflow_send_response($response, $jsonresponse);
                    }
                    if (function_exists('debug_trace')) {
                        debug_trace($CFG->wwwroot." archive copied");
                    }
                } else {
                    $response->status = RPC_FAILURE;
                    $response->errors[] = 'Local delivery remote : remote end has not local delivery set on';
                    $response->error = 'Local delivery remote : remote end has not local delivery set on';
                    return publishflow_send_response($response, $jsonresponse);
                }
            } else {
                // XML-RPC remote procedure failed, although transmission is OK.
                $response->status = RPC_FAILURE;
                $response->errors[] = 'Remote error : Could not get remote file description';
                $response->error = 'Remote error : Could not get remote file description';
                return publishflow_send_response($response, $jsonresponse);
            }
        }
    }
    // Restores a new course silently, giving sufficient parameters and force category.

    if (function_exists('debug_trace')) {
        debug_trace($CFG->wwwroot." start restore");
    }

    if (empty($parmsoverride['category'])) {
        $deploycat = $DB->get_record('course_categories', array('id' => @$config->deploycategory));
    } else {
        $deploycat = $DB->get_record('course_categories', array('id' => $parmsoverride['category']));
    }
    if (!$deploycat) {
        $response->status = RPC_FAILURE;
        $response->errors[] = 'Deployment category has not been setup. Contact local administrator.';
        $response->error = 'Deployment category has not been setup. Contact local administrator.';
        return publishflow_send_response($response, $jsonresponse);
    }

    if (function_exists('debug_trace')) {
        debug_trace($CFG->wwwroot." automation starts restore");
    }
    $newcourseid =  restore_automation::run_automated_restore(null, $tempfile, $deploycat->id);
    // Confirm/force idnumber in new course.
    if (function_exists('debug_trace')) {
        debug_trace($CFG->wwwroot." automation restore finished");
    }
    $response->courseid = $newcourseid;

    $DB->set_field('course', 'idnumber', $sourcecourse->idnumber, array('id' => "{$response->courseid}"));

    /*
     * assign the localuser as author in all cases :
     * deployement : deployer will unassign self manually if needed
     * free use deployement : deployer will take control over session
     * retrofit : deployer will take control over new learning path in work
     */

    if (function_exists('debug_trace')) {
        debug_trace($CFG->wwwroot." setting role");
    }
    if (!empty($config->defaultrole)) {
        $coursecontext = context_course::instance($response->courseid);

        $enrolplugin = enrol_get_plugin('manual');

        $params = array('enrol' => 'manual', 'courseid' => $newcourseid, 'status' => ENROL_INSTANCE_ENABLED);
        if ($enrols = $DB->get_records('enrol', $params, 'sortorder ASC')) {
            $enrol = reset($enrols);
            $enrolplugin->enrol_user($enrol, $USER->id, $config->defaultrole);
        }
    }

    if (block_publishflow_supports_feature('restore/postscripting')) {
        if (!empty($config->postprocessing)) {
            if (function_exists('debug_trace')) {
                debug_trace($CFG->wwwroot." Starting postprocessing");
            }
            include_once($CFG->dirroot.'/blocks/publishflow/pro/lib.php');

            $datacontext = new StdClass;
            $datacontext->newcourseid = $newcourseid;
            $datacontext->callinguser = $callinguser;
            $errorresult = block_publishflow_postscript($datacontext);

            if (!empty($errorresult)) {
                $response->status = RPC_FAILURE_RUN;
                $response->errors[] = 'Post processing failed somewhere.';
                $response->errors[] = $errorresult;
                $response->error = "Post processing failed somewhere.\n".$errorresult;
                return publishflow_send_response($response, $jsonresponse);
            }
        }
    }

    if (function_exists('debug_trace')) {
        debug_trace($CFG->wwwroot." make response");
    }
    // Give back the information for jumping.
    $response->status = RPC_SUCCESS;
    $response->username = $username;
    $response->remotehostroot = $remotehostroot;
    $response->course_idnumber = $sourcecourse->idnumber;
    $response->catalogcourseid = $sourcecourse->id;

    if (function_exists('debug_trace')) {
        debug_trace($CFG->wwwroot." sending response. Getting out.");
    }
    return publishflow_send_response($response, $jsonresponse);
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
 * @param string $callinguser The caller as an object
 * @param string $action what to do
 * @param string $sourcecourseserial the course information as a JSON serialized string
 * @param string $forcereplace if true, forces download and replacement of the existing course
 * @param string $jsonresponse if true, will provide answer as a serialized json string.
 */
function delivery_publish($callinguser, $action, $sourcecourseserial, $forcereplace, $jsonresponse = true) {
    global $CFG, $USER, $SESSION, $DB;

    if (function_exists('debug_trace')) {
        debug_trace($CFG->wwwroot." PUBLISH");
    }

    $config = get_config('block_publishflow');

    $response->status = RPC_SUCCESS;
    $response->errors = array();
    $response->error = '';
    $sourcecourse = json_decode($sourcecourseserial);

    $callinguser = (array)$callinguser;
    if ($authresponse = publishflow_rpc_check_user((array)$callinguser, 'block/publishflow:publish', 'any')) {
        return publishflow_send_response($authresponse, $jsonresponse, true);
    }
    $remotehostroot = $callinguser['remotehostroot'];

    switch ($action) {
        case 'unpublish': {
                $DB->set_field('course', 'visible', 0, array('idnumber' => $sourcecourse->idnumber));
                $response->status = RPC_SUCCESS;
                $response->username = $username;
                $response->remotehostroot = $remotehostroot;
                $response->courseid = $DB->get_field('course', 'id', array('idnumber' => $sourcecourse->idnumber));
                return publishflow_send_response($response, $jsonresponse);
            }
            break;

        case 'publish' : {
                // Course exists and we just republish it.
                $courseexists = $DB->count_records('course', array('idnumber' => $sourcecourse->idnumber));
                if (!$forcereplace && $courseexists) {
                    // We do not need anything else than making this course(s) visible back.
                    $DB->set_field('course', 'visible', 1, array('idnumber' => $sourcecourse->idnumber)); 
                    $response->status = RPC_SUCCESS;
                    $response->username = $username;
                    $response->remotehostroot = $remotehostroot;
                    // TODO : are we always sure there only one ?
                    $response->courseid = $DB->get_field('course', 'id', array('idnumber' => $sourcecourse->idnumber));
                    return publishflow_send_response($response, $jsonresponse);
                }

                /*
                 * Course does not exist, or we are asked to renew the volume
                 * if course exists, we must hide all previous instances of such a course
                 * National administrator or section administrator should then clean up
                 * irrelevant instances.
                 */
                if ($courseexists) {
                    $DB->set_field('course', 'visible', 0, array('idnumber' => $sourcecourse->idnumber)); 
                }

                // First bump up server execution characteristics.
                ini_set('max_execution_time', '600');
                ini_set('memory_limit', '256M');

                // Check the local availability of the archive.
                $realpath = $CFG->dataroot.'/courselivery/'.$CFG->coursedelivery_coursefordelivery.'/'.$sourcecourse->idnumber.'.zip';
                // If we force replace with a new one, delete old local archive.
                if ($forcereplace) {
                    unlink($realpath);
                }

                if (!file_exists($realpath)) {

                    // We de not have a package at remote side.
                    if (function_exists('debug_trace')) {
                        debug_trace('Up to fetch a course back');
                    }
                    $mnethost = new mnet_peer();
                    $mnethost->set_wwwroot($remotehostroot);
                    $caller = (object)$callinguser;
                    $caller->remotehostroot = $CFG->wwwroot;

                    if (empty($config->coursedeliveryislocal)) {
                        // We try to get the archive file through the network.
                        $transfercomplete = false;
                        $archivefile = '';
                        $offset = 0;
                        while (!$transfercomplete) {
                            // Get the archive on back call.
                            $rpcclient = new mnet_xmlrpc_client();
                            $rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_deliver');
                            $rpcclient->add_param($caller, 'struct');
                            $rpcclient->add_param($sourcecourse->id, 'int');
                            $rpcclient->add_param($offset, 'int');
                            $rpcclient->add_param(4194304, 'int'); // 4Mo transfer max.
                            if (!$rpcclient->send($mnethost)) {
                                // Make a suitable response.
                                $response->status = RPC_FAILURE;
                                $response->error = 'Remote error : Could not get the course archive file';
                                $response->errors[] = '<br/>XML-RPC Callback errors :<br/>';
                                if (!empty($rpcclient->errors)) {
                                    foreach ($rpcclient->errors as $error) {
                                        $response->errors[] = $error;
                                    }
                                }
                                return publishflow_send_response($response, $jsonresponse);
                            }
                            $backresponse = json_decode($rpcclient->response);
                            if (function_exists('debug_trace')) {
                                debug_trace('XML-RPC backcall succeeded');
                            }
                            /*
                             * Processing XML-RPC response
                             * XML-RPC worked well, but remote procedure may fail
                             */
                            if ($backresponse->status == RPC_SUCCESS) {
                                $archivefile = $archivefile . base64_decode($backresponse->file);
                                $archivename = $backresponse->archivename;
                                if ($backresponse->remains == 0) {
                                    $transfercomplete = true;
                                } else { 
                                    // File is chunked because too big. prepare for next chunk.
                                    $offset = $backresponse->filepos;
                                }
                            } else {
                                // XML-RPC remote procedure failed, although transmission is OK.
                                $response->status = RPC_FAILURE;
                                $response->error = 'Remote error : Could not get the course archive file because of call back remote error : ';
                                $response->errors[] = 'Remote error : Could not get the course archive file because of call back remote error : ';
                                if (!empty($backresponse->errors)) {
                                    foreach ($backresponse->errors as $error) {
                                        $response->errors[] = $error;
                                    }
                                }
                                return publishflow_send_response($response, $jsonresponse);
                            }
                        }
                        // Save the archive locally.
                        if (!file_exists(dirname($realpath))) {
                            mkdir($realpath, 0777, true);
                        }
                        $status = backup_data2file($realpath, $archivefile);
                    } else {
                        /*
                         * we make a local delivery by copying the archive directly in file system
                         * this very fast, but is not workable on remotely distributed hosts.
                         * both moodle data must be on the same storage system.
                         */

                        // Get the archive name on back call.
                        $rpcclient = new mnet_xmlrpc_client();
                        $rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_deliver');
                        $rpcclient->add_param($caller, 'struct');
                        $rpcclient->add_param($sourcecourse->id, 'int');

                        if (!$rpcclient->send($mnethost)) {
                            // Make a suitable response.
                            $response->status = RPC_FAILURE;
                            $response->error = 'Remote error : Could not get the course archive descriptor for local delivery ';
                            $response->error .= '<br/>XML-RPC Callback errors :<br/>';
                            $response->error .= implode('<br/>', $rpcclient->error);
                            return publishflow_send_response($response, $jsonresponse);
                        }
                        $backresponse = json_decode($rpcclient->response);
                        /*
                         * Processing XML-RPC response
                         * XML-RPC worked well, but remote procedure may fail
                         */
                        if ($backresponse->status == RPC_SUCCESS) {
                            if ($backresponse->local) {
                                $archivename = $backresponse->archivename;
                                $tempfile = $CFG->tempdir."/backup/".$backresponse->filename;
                                // Actually locally copying archive.
                                if (!copy($archivename, $tempfile)) {
                                    $response->status = RPC_FAILURE;
                                    $response->errors[] = 'Local delivery : copy error';
                                    $response->error = 'Local delivery : copy error';
                                    return publishflow_send_response($response, $jsonresponse);
                                }
                            } else {
                                $response->status = RPC_FAILURE;
                                $response->error = 'Local delivery remote : remote end has not local delivery set on';
                                return publishflow_send_response($response, $jsonresponse);
                            }
                        } else {
                            // XML-RPC remote procedure failed, although transmission is OK.
                            $response->status = RPC_FAILURE;
                            $response->errors[] = 'Remote error : Could not get remote file description';
                            $response->error = 'Remote error : Could not get remote file description';
                            return publishflow_send_response($response, $jsonresponse);
                        }
                    }
                } else {
                    if (function_exists('debug_trace')) {
                        debug_trace('PUBLISHING : Remote side has already an archive there');
                    }
                }
                /*
                 * TODO : extract the archive for deployment and make proper $restore and $info record
                 * Restores a new course silently, giving sufficient parameters and force category
                 */
                $deploycat = $DB->get_record('course_categories', array('id' => $config->_deploycategory));

                $newcourseid =  restore_automation::run_automated_restore(null, $tempfile, $deploycat->id);

                // Confirm/force idnumber in new course.
                $response->courseid = $newcourseid;
                $DB->set_field('course', 'idnumber', $sourcecourse->idnumber, array('id' => "{$response->courseid}"));

                // Give back the information for jumping.
                $response->status = RPC_SUCCESS;
                $response->course_idnumber = $sourcecourse->idnumber;
                $response->username = $username;
                $response->remotehostroot = $remotehostroot;
                $response->catalogcourseid = $sourcecourse->id;
                return publishflow_send_response($response, $jsonresponse);
            }
            break;
    }
}

/**
 * Converts properly output message.
 * @param object $response the response
 * @param boolean $jsonresponse if true ensire output is a json encoded string
 * @param boolean true if $response is already json encoded.
 */
function publishflow_send_response($response, $jsonresponse = true, $isjson = false) {
    if ($isjson) {
        if ($jsonresponse) {
            return $response;
        } else {
            return json_decode($response);
        }
    } else {
        if ($jsonresponse) {
            return json_encode($response);
        } else {
            return $response;
        }
    }
}
