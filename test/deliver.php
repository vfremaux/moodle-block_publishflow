<?php

/**
 * Implements a result page for testing the deliver call
 * @package blocks-publishflow
 * @category blocks
 *
 */

/**
* Requires and includes
*/
    include '../../../config.php';
    include_once $CFG->dirroot."/mnet/lib.php";
    include_once $CFG->dirroot."/mnet/xmlrpc/client.php";
    include_once $CFG->libdir."/pear/HTML/AJAX/JSON.php";

/// get input params
    $courseid = required_param('course', PARAM_INT);
    $where = required_param('where', PARAM_RAW);
    
/// check we can do this
    require_capablity('moodle:site:doanything', get_context_instance(CONTEXT_SYSTEM));
    
    print_header_simple(get_string('deployment', 'block_publishflow'));
    
/// get context objects
    $vmoodle = get_record('block_vmoodle', 'vhostname', "http://$where");
    
/// start triggering the remote deployment
    $rpcclient = new mnet_xmlrpc_client();
    $rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_deliver');
    $rpcclient->add_param($USER->username, 'string');
    $rpcclient->add_param($CFG->wwwroot, 'string');
    $rpcclient->add_param($courseid, 'int');
    
    $mnet_host = new mnet_peer();
    $mnet_host->set_wwwroot($vmoodle->vhostname);
    if (!$rpcclient->send($mnet_host)){
        print_object($rpcclient);
        error(get_string('failed', 'block_publishflow').'<br/>'.implode("<br/>\n", $rpcclient->error));        
    }

    if (!empty($rpcclient->error)){
        print_object($rpcclient->response);
    }
    
    echo "decoding";
    $response = json_decode($rpcclient->response);
    print_object($response);

    mtrace("<p>Archive name : ".$response->archivename."<br/>");
    if (!$response->local)
        mtrace("Transferred size : ".strlen($response->file)."\n");
    else 
        mtrace("local transfer activated");
    
?>