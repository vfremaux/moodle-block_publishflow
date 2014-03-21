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
    include_once $CFG->dirroot."/blocks/publishflow/lib.php";

/// get imput params

    $id = required_param('id', PARAM_INT); // the block ID
    $fromcourse = required_param('fromcourse', PARAM_INT);
    $action = required_param('what', PARAM_TEXT);
    $where = required_param('where', PARAM_INT);  
    $category = optional_param('category', 0, PARAM_INT);
    $deploykey = optional_param('deploykey', null,PARAM_TEXT);
    $forcecache = optional_param('force', 1, PARAM_INT);

    $course = $DB->get_record('course', array('id' => "$fromcourse"));

    require_login($course);
            
    $system_context = get_context_instance(CONTEXT_COURSE,$fromcourse);
    $PAGE->set_context($system_context); 
    $PAGE->set_button('');
    $PAGE->set_url('/blocks/publishflow/deploy.php',array('id' => $id,'fromcourse' => $fromcourse,'where' => $where,'what' => $action,'category' => $category, 'force' => $forcecache,'deplykey' => $deploykey));
	$PAGE->navbar->add(get_string('pluginname', 'block_publishflow'));
	$PAGE->navbar->add(get_string('deploying', 'block_publishflow'));

    print $OUTPUT->header();
    
/// get the block context

    if (!$instance = $DB->get_record('block_instances', array('id' => $id))){
        print_error('errorbadblockid', 'block_publishflow');
    }

    $theBlock = block_instance('publishflow', $instance);

/// check we can do this

 /*	if(!has_capability('block/publishflow:deployeverwhere', context_system::instance())){
 		// check on remote host the deploy capability
 		// TODO
 	}
    */

	// check the deploykey
	if (!empty($theBlock->config->deploymentkey)){
		if ($theBlock->config->deploymentkey !== $deploykey){
			print_error('badkey', 'block_publishflow', $CFG->wwwroot."/course/view.php?id=$fromcourse");
		}
	}
   
    $mnethost = $DB->get_record('mnet_host', array('id' => $where));

///If we want to deploy on a local platform, we need to bypass the RPC with a quick function

	if($where == 0){
		$remotecourseid = publishflow_local_deploy($category, $course);

		echo $OUTPUT->box_start('plublishpanel');
	 	print_string('deploysuccess', 'block_publishflow');
	
		echo '<br/>';
		echo '<br/>';
		$userhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid));
		echo "<a href=\"{$CFG->wwwroot}/course/view.php?id={$remotecourseid}\">".get_string('jumptothecourse', 'block_publishflow').'</a> - ';
		echo " <a href=\"/course/view.php?id={$course->id}\">".get_string('backtocourse', 'block_publishflow').'</a>';
		echo '</center>';
		echo $OUTPUT->box_end();
	} else {
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
	    	$debugout = ($CFG->debug | DEBUG_DEVELOPER) ? var_export($rpcclient) : '' ;
	        print_error('failed', 'block_publishflow', $CFG->wwwroot.'/course/view.php?id='.$fromcourse, '', $debugout);
		}
	
		$response = json_decode($rpcclient->response);
	
		// print_object($response);
		echo $OUTPUT->box_start('plublishpanel');
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
		    echo $OUTPUT->notification("Remote Error : ".$response->error);
		}
		echo " <a href=\"/course/view.php?id={$course->id}\">".get_string('backtocourse', 'block_publishflow').'</a>';
		echo '</center>';
		echo $OUTPUT->box_end();
	}
    echo $OUTPUT->footer();
