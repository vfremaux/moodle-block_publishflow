<?php

/**
 * Implements a result page for driving the deploy 
 * transaction.
 * @package blocks-publishflow
 * @category blocks
 *
 */

/**
* Requires and includes
*/
    include '../../config.php';
    include_once $CFG->dirroot."/mnet/lib.php";
    include_once $CFG->dirroot."/mnet/xmlrpc/client.php";

/// get input params

    $fromcourse = required_param('fromcourse', PARAM_INT);
    $action = required_param('what', PARAM_TEXT);
    $where = required_param('where', PARAM_INT);  // Where is a vmoodle id

	$url = new moodle_url('/blocks/publishflow/retrofit.php', array('fromcourse' => $fromcourse,'what' => $action,'where' => $where));

/// check we can do this

    $course = $DB->get_record('course', array('id' => "$fromcourse"));

    require_course_login($course, false);
 
    $PAGE->set_url($url);

    $PAGE->set_title(get_string('retrofitting', 'block_publishflow'));
    $PAGE->set_heading(get_string('retrofit', 'block_publishflow'));
    $PAGE->navbar->add(get_string('pluginname', 'block_publishflow'));
    $PAGE->navbar->add(get_string('retrofit', 'block_publishflow'));
  
    echo $OUTPUT->header();

/// get context objects

    $mnethost = $DB->get_record('mnet_host', array('id' => $where));

/// start triggering the remote deployment
    if (!empty($USER->mnethostid)){
        $userhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid));
        $userwwwroot = $userhost->wwwroot;
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
    $course->retrofit = true;
    $rpcclient->add_param(json_encode($course), 'string');
    $rpcclient->add_param(false, 'int'); // unused freeuse
    $mnet_host = new mnet_peer();
    $mnet_host->set_wwwroot($mnethost->wwwroot);
    if (!$rpcclient->send($mnet_host)){
    	$debugout = ($CFG->debug | DEBUG_DEVELOPER) ? var_export($rpcclient) : '' ;
        print_error('failed', 'block_publishflow', $CFG->wwwroot.'/course/view.php?id='.$fromcourse, '', $debugout);
    }

    $response = json_decode($rpcclient->response);

	echo $OUTPUT->box_start('plublishpanel');
    echo '<center>';
    if ($response->status == 100){
        echo $OUTPUT->notification("Remote Test Point : ".$response->teststatus);
    }
    if ($response->status == 200){
        $remotecourseid = $response->courseid;
        print_string('retrofitsuccess', 'block_publishflow');
        echo '<br/>';
        echo '<br/>';
        if ($USER->mnethostid != $mnethost->id){
            echo "<a href=\"/auth/mnet/jump.php?hostid={$mnethost->id}&amp;wantsurl=".urlencode('/course/view.php?id='.$remotecourseid)."\">".get_string('jumptothecourse', 'block_publishflow').'</a> - ';
        } else {
            echo "<a href=\"{$mnethost->wwwroot}/course/view.php?id={$remotecourseid}\">".get_string('jumptothecourse', 'block_publishflow').'</a> - ';
        }
    } else {
        echo $OUTPUT->notification("Remote Error : ".$response->error);
    }
    echo " <a href=\"/course/view.php?id={$course->id}\">".get_string('backtocourse', 'block_publishflow').'</a>';
    echo '</center>';
    echo $OUTPUT->box_end();

    echo $OUTPUT->footer();
