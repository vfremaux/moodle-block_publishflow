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
 * @package    block_publishflow
 * @category   blocks
 * @author     Valery Fremaux (valery.fremaux@edunao.com)
 * @copyright  2008 Valery Fremaux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * This page updates the catalog
 *
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/mnet/xmlrpc/client.php');
require_once($CFG->dirroot.'/mnet/peer.php');
require_once($CFG->dirroot.'/blocks/publishflow/lib.php');

// Security.

$systemcontext = context_system::instance();
require_login();
require_capability('moodle/site:config', $systemcontext);

$PAGE->set_url('/blocks/publishflow/doupgrade.php');

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

$full = get_string('single_full', 'block_publishflow');
$short = get_string('single_short', 'block_publishflow');

$PAGE->set_context($systemcontext);
$PAGE->set_title($full);
$PAGE->set_heading($short);
$PAGE->navbar->add($full);
$PAGE->set_focuscontrol('');
$PAGE->set_cacheable(false);

echo $OUTPUT->header();

$hosts = $DB->get_records('mnet_host', array('deleted' => 0));

foreach ($hosts as $host) {
    if ($host->wwwroot == $CFG->wwwroot) {
        // Do not try to deal with yourself.
        continue;
    }

    if ($host->applicationid != $DB->get_field('mnet_application', 'id', array('name' => 'moodle'))) {
        continue;
    }

    if (($host->name != '') && ($host->name != 'All Hosts')) {
        $result = block_publishflow_update_peer($host);
        if (empty($result)) {
            echo $OUTPUT->box($host->name.get_string('updateok', 'block_publishflow'), 'notifysuccess');
        } else {
            echo $OUTPUT->box($result, 'notifyfailure');
        }
    }
}

echo '<center>';
$buttonurl = new moodle_url('/blocks/publishflow/netupdate.php');
echo $OUTPUT->single_button($buttonurl, get_string('backsettings', 'block_publishflow'), 'get');
echo '</center>';

echo $OUTPUT->footer($COURSE);