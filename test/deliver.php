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
 * Implements a result page for testing the deliver call
 * @package blocks-publishflow
 * @category blocks
 *
 */

require('../../../config.php');
require_once($CFG->dirroot."/mnet/lib.php");
require_once($CFG->dirroot."/mnet/xmlrpc/client.php");

// Get input params.

$courseid = required_param('course', PARAM_INT);
$where = required_param('where', PARAM_RAW);

// Check we can do this.

require_login();
require_capablity('moodle:site:doanything', context_system::instance());

$PAGE->set_title(get_string('deployment', 'block_publishflow'));

echo $OUTPUT->header();

// Get context objects.

$vmoodle->vhostname = "http://".$where;

// Start triggering the remote deployment.

$rpcclient = new mnet_xmlrpc_client();
$rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_deliver');

$caller->username = $USER->username;
$caller->remoteuserhostroot = "http://sub1.moodle31.com";
$caller->remotehostroot = "http://dev.moodle31.com";

$rpcclient->add_param($caller, 'struct');
$rpcclient->add_param($courseid, 'int');

$mnethost = new mnet_peer();
$mnethost->set_wwwroot($vmoodle->vhostname);

if (!$rpcclient->send($mnethost)) {
    print_error('failed', 'block_publishflow');
    if (function_exists('debug_trace')) {
        debug_trace(var_export($rpcclient));
    }
}

echo "decoding";
$response = json_decode($rpcclient->response);
echo(var_export($response));

mtrace("<p>Archive name : ".$response->archivename."<br/>");
if (!$response->local) {
    mtrace("Transferred size : ".strlen($response->file)."\n");
} else {
    mtrace("local transfer activated");
}
