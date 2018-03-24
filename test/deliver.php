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
include('../../../config.php');
include_once($CFG->dirroot."/mnet/lib.php");
include_once($CFG->dirroot."/mnet/xmlrpc/client.php");

// Get input params

$courseid = required_param('course', PARAM_INT);
$where = required_param('where', PARAM_RAW);

// Check we can do this.

require_login();
require_capablity('moodle:site:doanything', context_system::instance());

$PAGE->set_title(get_string('deployment', 'block_publishflow'));

echo $OUTPUT->header();

// Get context objects.

$vmoodle->vhostname = "http://".$where;//$DB->get_record('block_vmoodle', array('vhostname' => "http://$where"));

// Start triggering the remote deployment.

$rpcclient = new mnet_xmlrpc_client();
$rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_deliver');

$caller->username = $USER->username;
$caller->remoteuserhostroot = "http://sub1.moodle31.com";
$caller->remotehostroot = "http://dev.moodle31.com";

$rpcclient->add_param($caller, 'struct');
$rpcclient->add_param($courseid, 'int');

//   $rpcclient->add_param($CFG->wwwroot, 'string');

$mnet_host = new mnet_peer();
$mnet_host->set_wwwroot($vmoodle->vhostname);
if (!$rpcclient->send($mnet_host)){
    print_object($rpcclient);
    print_error('failed', 'block_publishflow');
}

if (!empty($rpcclient->error)){
    print_object($rpcclient->response);
}

echo "decoding";
$response = json_decode($rpcclient->response);
print_object($response);

mtrace("<p>Archive name : ".$response->archivename."<br/>");
if (!$response->local) {
    mtrace("Transferred size : ".strlen($response->file)."\n");
} else {
    mtrace("local transfer activated");
}
