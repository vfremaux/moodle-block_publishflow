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
 * This page will be used to manually trigger the platforms catalog update_record
 * This is an administration page and it's load is heavy.
 *
 * @author Edouard Poncelet
 * @package block_publishflow
 * @category blocks
 *
 **/
require('../../config.php');

$PAGE->set_url('/blocks/publishflow/netupdate.php');

$systemcontext = context_system::instance();
require_login();
require_capability('moodle/site:config', $systemcontext);

$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('netupdate', 'block_publishflow'));
$PAGE->set_heading(get_string('pluginname', 'block_publishflow'));
$PAGE->navbar->add(get_string('pluginname', 'block_publishflow'));
$PAGE->navbar->add(get_string('setup', 'block_publishflow'), $CFG->wwwroot.'/admin/settings.php?section=blocksettingpublishflow');
$PAGE->navbar->add(get_string('single_short','block_publishflow'));
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();

echo $OUTPUT->box(get_string('warningload','block_publishflow'), 'publishflow-notification');

$hosts = $DB->get_records_select('mnet_host', " deleted = 0 AND wwwroot != '$CFG->wwwroot' ");

// Building divs
$diverror = '<div class="error" style="background-color:#FF0000">';
$divwarning = '<div class="warning" style="background-color:#FFFF33">';
$divok = '<div class="ok" style="background-color:#99FF00">';
$enddiv = '</div>';

$warnings = 0;
$errors = 0;

// Creating the table
$table = new html_table();
$table->tablealign = 'center';
$table->cellpadding = 5;
$table->cellspacing = 0;
$table->width = '60%';
$table->size = array('30%','30%','30%');
$table->head = array(get_string('platformname', 'block_publishflow'), get_string('platformstatus','block_publishflow'), get_string('platformlastupdate','block_publishflow'));
$table->wrap = array('nowrap', 'nowrap','nowrap');
$table->align = array('center', 'center','center');

$id = 0;

//We need to check each host
foreach ($hosts as $host) {

    // ignore non moodles
    if ($host->applicationid != $DB->get_field('mnet_application', 'id', array('name' => 'moodle'))) continue;

    $name = $host->name;
    if (!($host->name) == "" && !($host->name == "All Hosts")) {
        //If we don't find errors, we are OK.
        $divstatus = $divok;
        $divtime = $divok;

        // If we have no record, we create it
        if (!$catalogrecord = $DB->get_record('block_publishflow_catalog', array('platformid' => $host->id))) {
            $newcatalog = array('platformid' => $host->id);
            $DB->insert_record('block_publishflow_catalog', $newcatalog);
            $catalogrecord = $DB->get_record('block_publishflow_catalog', array('platformid' => $host->id));
        }

        // If the host has no real type, it's an error.
        if ($catalogrecord->type == 'unknown') {
            $divstatus = $diverror;
            $errors++;
        }

    // No last access is an error
        if (empty($catalogrecord->lastaccess)) {
            $divtime = $diverror;
            $errors++;
            $catalogrecord->lastaccess = time();
        }

    // Too old record is a warning
        elseif ($catalogrecord->lastaccess < time()- (14 * 24 * 60 * 60)) {
            $divtime = $divwarning;
            $warnings++;
        }
        $table->data[$id][0] = $name;
        $table->data[$id][1] = $divstatus.$catalogrecord->type.$enddiv;
        $table->data[$id][2] = $divtime.date('d-m-Y  G:i:s', $catalogrecord->lastaccess).$enddiv;
        $id++;
    }
}
echo html_writer::table($table);
echo '<br/>';

if (!($errors == 0)) {
    echo $OUTPUT->box(get_string('errormonitoring','block_publishflow').'<br/>'.get_string('erroradvice','block_publishflow', $errors));
} elseif (!($warnings == 0)) {
    echo $OUTPUT->box(get_string('errormonitoring','block_publishflow').'<br/>'.get_string('warningadvice','block_publishflow', $warnings));
} else {
    echo $OUTPUT->box(get_string('OKmonitoring','block_publishflow'));
}

echo '<center><p>';
$button = new single_button(new moodle_url('/blocks/publishflow/doupgrade.php'), get_string('perform','block_publishflow'), '');
$button->disabled = '';
echo $OUTPUT->render($button);
echo '</p></center>';

echo '<br/>';

echo $OUTPUT->footer($COURSE);
