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
    include_once $CFG->dirroot."/blocks/publishflow/lib.php";

/// get imput params
    $id = required_param('id', PARAM_INT); // the block ID
    $fromcourse = required_param('fromcourse', PARAM_INT);
    $action = required_param('what', PARAM_TEXT);
    $where = required_param('where', PARAM_INT);  
    $category = optional_param('category', 0, PARAM_INT);
    $deploykey = optional_param('deploykey', '', PARAM_TEXT);
    $forcecache = optional_param('force', 0, PARAM_TEXT);

/// get the block context
    if (!$instance = get_record('block_instance', 'id', $id)){
        error('Bad block ID');
    }

    $theBlock = block_instance('publishflow', $instance);
    
/// check we can do this
    $course = get_record('course', 'id', "$fromcourse");

    require_login($course);
    
 	if(!has_capability('block/publishflow:deployeverwhere', get_context_instance(CONTEXT_SYSTEM))){
 		// check on remote host the deploy capability
 		// TODO
 	}

	// check the deploykey
	if (!empty($theBlock->config->deploymentkey)){
		if ($theBlock->config->deploymentkey !== $deploykey){
			print_error('badkey', 'block_publishflow', $CFG->wwwroot."/course/view.php?id=$fromcourse");
		}
	}

    $navlinks = array(
	    array(
	        'title' => get_string('deploying', 'block_publishflow'), 
	        'name' => get_string('deploying', 'block_publishflow'), 
	        'url' => NULL, 
	        'type' => 'course'
	    )
    );
    
    print_header_simple(get_string('deployment', 'block_publishflow'), get_string('deployment', 'block_publishflow'), build_navigation($navlinks));
    
/// get context objects
    $mnethost = get_record('mnet_host', 'id', $where);
    

///If we want to deploy on a local platform, we need to bypass the RPC with a quick function
if($where == 0){
	$remotecourseid = publishflow_local_deploy($category, $course);
 	print_string('deploysuccess', 'block_publishflow');

	echo '<br/>';
	echo '<br/>';
	$userhost = get_record('mnet_host', 'id', $USER->mnethostid);
	echo "<a href=\"{$CFG->wwwroot}/course/view.php?id={$remotecourseid}\">".get_string('jumptothecourse', 'block_publishflow').'</a> - ';
	echo " <a href=\"/course/view.php?id={$course->id}\">".get_string('backtocourse', 'block_publishflow').'</a>';
	echo '</center>';
} else {
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

	$parmsoverride = array('category' => $category);

	$rpcclient = new mnet_xmlrpc_client();
	$rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_deploy');
	$rpcclient->add_param($caller, 'struct');
	$rpcclient->add_param(json_encode($course), 'string');
	$rpcclient->add_param($forcecache, 'int'); // prepared for forcing replacement
	$rpcclient->add_param($parmsoverride,'struct');
	$rpcclient->add_param(1,'int'); // json response required

	$mnet_host = new mnet_peer();
	$mnet_host->set_wwwroot($mnethost->wwwroot);
	if (!$rpcclient->send($mnet_host)){
	    print_object($rpcclient);
	    error(get_string('failed', 'block_publishflow').'<br/>'.implode("<br/>\n", $rpcclient->error));        
	}

	$response = json_decode($rpcclient->response);

	// print_object($response);
	
	echo '<center>';
	if ($response->status == 200){
	    $remotecourseid = $response->courseid;
	    print_string('deploysuccess', 'block_publishflow');
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
}
    print_footer();
?>