<?php
/**
 * This page does the work and updates the catalog
 *
 * @author Edouard Poncelet
 * @package block-publishflow
 * @category blocks
 *
 **/

	require_once('../../config.php');
	include_once $CFG->dirroot."/mnet/xmlrpc/client.php";
	include_once $CFG->dirroot."/mnet/peer.php";
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

    global $CFG, $USER;

   	$full = get_string('single_full', 'block_publishflow');
   	$short = get_string('single_short', 'block_publishflow');

    $navlinks[] = array('name' => $full, 'link' => "$CFG->wwwroot", 'type' => 'misc');
    $navigation = build_navigation($navlinks);

    $system_context = get_context_instance(CONTEXT_SYSTEM);
    $PAGE->set_context($system_context); 
    $PAGE->set_title($full);
    
    $PAGE->set_heading($short);
    /* SCANMSG: may be additional work required for $navigation variable */
    $PAGE->set_focuscontrol('');
    $PAGE->set_cacheable(false);
    $PAGE->set_button('');
    
    $PAGE->set_url('/blocks/publishflow/doupgrade.php');
    
    echo $OUTPUT->header();

    $hosts = $DB->get_records('mnet_host', array('deleted' => 0));

	foreach($hosts as $host){
		if ($host->wwwroot == $CFG->wwwroot) continue; // do not try to deal with yourself
		if ($host->applicationid != $DB->get_field('mnet_application', 'id', array('name' => 'moodle'))) continue;
		if(!($host->name) == "" && !($host->name == "All Hosts")){
             
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
			if (!is_array($rpcclient->response)){
				$response = json_decode($rpcclient->response);
			}
       
			//We have to check if there is a response with content	 
			if(empty($response)){
				echo $OUTPUT->box($host->name.get_string('errorencountered', 'block_publishflow').$rpcclient->error[0], 'errorbox');
			} else {
			
				if ($response->status == RPC_FAILURE){
					echo '<p>';
					echo $OUTPUT->box($host->name.get_string('errorencountered', 'block_publishflow').$response->error, 'errorbox');
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
						  	$fullentry = array('platformid' => $host->id,'originalid' => $entry->id, 'parentid' => $entry->parentid, 'name' => $entry->name, 'sortorder' => $entry->sortorder);
						  	$DB->insert_record('block_publishflow_remotecat', $fullentry);
						}
					}
				  	echo $OUTPUT->box($host->name.get_string('updateok','block_publishflow'), 'notifysuccess');
			  	} else {
				  	echo $OUTPUT->box($host->name.get_string('clientfailure','block_publishflow'), 'errorbox');
			  	}
			}
		}
	}

    echo('<center>');
    echo $OUTPUT->single_button(new moodle_url('/blocks/publishflow/netupdate.php'), get_string('backsettings','block_publishflow'), 'get');
    echo('</center>');

    echo $OUTPUT->footer($COURSE);