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
    include_once $CFG->libdir."/pear/HTML/AJAX/JSON.php";
    include_once $CFG->dirroot."/mnet/xmlrpc/client.php";

/// get imput params
    $fromcourse = required_param('fromcourse', PARAM_INT);
    $action = required_param('what', PARAM_TEXT);
    $where = required_param('where', PARAM_INT);  // Where is a vmoodle id

    $course = get_record('course', 'id', "$fromcourse");
    
/// check we can do this
    require_login($course);

    $navlinks = array(
                    array(
                        'title' => get_string('retrofitting', 'block_publishflow'), 
                        'name' => get_string('retrofitting', 'block_publishflow'), 
                        'url' => NULL, 
                        'type' => 'course'
                    )
                  );
    
    print_header_simple(get_string('deployment', 'block_publishflow'), get_string('deployment', 'block_publishflow'), build_navigation($navlinks));
    
    /// get context objects
    // $vmoodle = get_record('block_vmoodle', 'id', $where);
    // $mnethost = get_record('mnet_host', 'wwwroot', $vmoodle->vhostname);
    $mnethost = get_record('mnet_host', 'id', $where);
    
    // if ($CFG->debug)
        // echo "[$action from $fromcourse at $where]";
    
    /// start triggering the remote deployment
    if (!empty($USER->mnethostid)){
        $userhost = get_record('mnet_host', 'id', $USER->mnethostid);
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
    $rpcclient->add_param(1, 'int'); // force replace always when retrofitting
    
    $mnet_host = new mnet_peer();
    $mnet_host->set_wwwroot($mnethost->wwwroot);
    if (!$rpcclient->send($mnet_host)){
        print_object($rpcclient);
        error(get_string('failed', 'block_publishflow').'<br/>'.implode("<br/>\n", $rpcclient->error));        
    }

    $response = json_decode($rpcclient->response);

    // print_object($response);

    echo '<center>';
    if ($response->status == 100){
        notice("Remote Test Point : ".$response->teststatus);
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
        notice("Remote Error : ".$response->error);
    }
    echo " <a href=\"/course/view.php?id={$course->id}\">".get_string('backtocourse', 'block_publishflow').'</a>';
    echo '</center>';

    print_footer();
?>