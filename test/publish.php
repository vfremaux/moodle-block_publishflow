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
include('../../../config.php');
include_once($CFG->dirroot."/mnet/lib.php");
include_once($CFG->dirroot."/mnet/xmlrpc/client.php");

// Get imput params.

$fromcourse = required_param('fromcourse', PARAM_INT);
$action = required_param('what', PARAM_TEXT);
$where = required_param('where', PARAM_INT);

// Check we can do this.

require_login();
require_capability('moodle/site:doanything', context_system::instance());

$PAGE->set_title(get_string('deployment', 'block_publishflow'));

echo $OUTPUT->header();

// Get context objects.

$course = $DB->get_record('course', array('id' => $fromcourse));
echo "[$action from $fromcourse at $where]";

// Start triggering the remote deployment.

$rpcclient = new mnet_xmlrpc_client();
$rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_deliver');

// Use self admin account to proceed.
$rpcclient->add_param($USER->username, 'string');
$rpcclient->add_param($CFG->wwwroot, 'string');
$rpcclient->add_param($course->id, 'int');

$mnet_host = new mnet_peer();
$mnet_host->set_wwwroot($subhost);

if (!$rpcclient->send($mnet_host)) {
    print_object($rpcclient);
    print_error('failed', 'block_publishflow');
}

if ($rpcclient->response->status == 200) {
    print_string('ok');
}
