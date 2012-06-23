<?php

/**
 * Implements a result page for driving the publis/deploy 
 * transaction.
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

/// get imput params
    $fromcourse = required_param('fromcourse', PARAM_INT);
    $action = required_param('what', PARAM_TEXT);
    $where = required_param('where', PARAM_INT);
    
/// check we can do this
    require_capability('moodle/site:doanything', get_context_instance(CONTEXT_SYSTEM));
    
    print_header_simple(get_string('deployment', 'block_publishflow'));
    
/// get context objects
    $course = get_record('course', 'id', $fromcourse, '', 'id, idnumber');
    
    echo "[$action from $fromcourse at $where]";
    
/// start triggering the remote deployment
    $rpcclient = new mnet_xmlrpc_client();
    $rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_deliver');
    $rpcclient->add_param($USER->username, 'string');
    $rpcclient->add_param($CFG->wwwroot, 'string');
    $rpcclient->add_param($course->id, 'int');
    
    $mnet_host = new mnet_peer();
    $mnet_host->set_wwwroot($CFG->wwwroot);
    if (!$rpcclient->send($mnet_host)){
        print_object($rpcclient);
        error(get_string('failed', 'block_publishflow').'<br/>'.implode("<br/>\n", $rpcclient->error));        
    }
    
    if ($rpcclient->response->status == 200){
        print_string('ok');
    }

?>