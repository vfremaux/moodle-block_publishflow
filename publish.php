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
 * Implements a result page for driving the publish transaction.
 * @package block_publishflow
 * @category blocks
 * @author Valery Fremaux (valery.fremaux@gmail.com)
 * @author Wafa Adham (admin@adham.ps)
 * @copyright 2008 onwards Valery Fremaux (http://www.myLearningFactory.com)
 */
require('../../config.php');
require_once($CFG->dirroot.'/mnet/lib.php');
require_once($CFG->dirroot.'/mnet/xmlrpc/client.php');

// Get imput params.

$fromcourse = required_param('fromcourse', PARAM_INT);
$action = required_param('what', PARAM_TEXT);
$where = required_param('where', PARAM_INT); // Where is an mnet_host id.
$force = optional_param('forcerepublish', 0, PARAM_INT); // If we have to replace.

$params = array('fromcourse' => $fromcourse, 'what' => $action, 'where' => $where, 'forcerepublish' => $force);
$url = new moodle_url('/blocks/publishflow/publish.php', $params);

// Check we can do this.

$course = $DB->get_record('course', array('id' => "$fromcourse"));
$context = context_course::instance($course->id);

require_login($course);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('deployment', 'block_publishflow'));
$PAGE->set_heading(get_string('deployment', 'block_publishflow'));
$PAGE->navbar->add(get_string('pluginname', 'block_publishflow'));
$PAGE->navbar->add(get_string('publishing', 'block_publishflow'));

echo $OUTPUT->header();

// Get context objects.

$wherehostrec = $DB->get_record('mnet_host', array('id' => $where));

// Start triggering the remote deployment.

if (empty($CFG->coursedeliveryislocal)) {
    echo '<center>';
    echo $OUTPUT->box(get_string('networktransferadvice', 'block_publishflow'));
    echo '<br/>';
}

// Start triggering the remote deployment.

if (!empty($USER->mnethostid)) {
    $userhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid));
    $userwwwroot = $userhost->wwwroot;
} else {
    $userwwwroot = $CFG->wwwroot;
}

$caller->username = $USER->username;
$caller->remoteuserhostroot = $userwwwroot;
$caller->remotehostroot = $CFG->wwwroot;
$rpcclient = new mnet_xmlrpc_client();
$rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_publish');
$rpcclient->add_param($caller, 'struct');
$rpcclient->add_param($action, 'string');
$rpcclient->add_param(json_encode($course), 'string');
$rpcclient->add_param($force, 'int');
$mnethost = new mnet_peer();
$mnethost->set_wwwroot($wherehostrec->wwwroot);
if (!$rpcclient->send($mnethost)) {
    $debugout = ($CFG->debug | DEBUG_DEVELOPER) ? var_export($rpcclient) : '';
    print_error('failed', 'block_publishflow', new moodle_url('/course/view.php', array('id' => $fromcourse)), '', $debugout);
}

$response = json_decode($rpcclient->response);

$template = new StdClass;

echo $OUTPUT->box_start('plublishpanel');

if ($response->status == 100) {
    $template->publisherrornotif = $OUTPUT->notification("Remote Test Point : ".$response->teststatus, 'notifyproblem');
}

if ($response->status == 200) {
    $remotecourseid = $response->courseid;

    switch($action) {
        case 'publish': {
            // Confirm/force published status in course record if not done, to avoid strange access effects.
            if (!empty($CFG->coursepublishedcategory)) {
                $DB->set_field('course', 'category', $CFG->coursepublishedcategory, array('id' => "{$fromcourse}"));
            }
            break;
        }
        case 'unpublish': {
            $lpcontext = context_course::instance($fromcourse);
            if (!empty($CFG->coursesuspendedcategory)) {
                $DB->set_field('course', 'category', $CFG->coursesuspendedcategory, array('id' => "{$fromcourse}"));
            }
            break;
        }
    }

    $template->publishsuccessnotif = $OUTPUT->notification(get_string('publishsuccess', 'block_publishflow'), 'notifysuccess');

    if ($USER->mnethostid != $wherehost->id) {
        $params = array('hostid' => $wherehost->id, 'wantsurl' => '/course/view.php?id='.$remotecourseid);
        $jumpurl = new moodle_url('/auth/mnet/jump.php', $params);
    } else {
        $outgoingurl = $wherehost->wwwroot.'/course/view.php?id='.$remotecourseid;
        $jumpurl = new moodle_url('/blocks/publishflow/outgoing.php', array('wheretogo' => $outgoingurl));
    }
    $button = new single_button($jumpurl, get_string('jumptothecourse', 'block_publishflow'));
    $button->id = 'responseform';
    $button->add_action(new confirm_action(get_string('remotejumpadvice', 'block_publishflow'), null,
        get_string('confirmjump', 'block_publishflow')));
    $template->remotecoursebutton = $OUTPUT->render($button);

} else {
    $template->publisherrornotif = $OUTPUT->notification("Remote Error : <pre>".$response->error.'</pre>', 'notifyproblem');
}

$courseurl = new moodle_url('/course/view.php', array('id' => $course->id));
$attrs = array('value' => get_string('backtocourse', 'block_publishflow'), 'type' => 'button');
$button = html_writer::empty_tag('input', $attrs);
$template->localcoursebutton = html_writer::link($courseurl, $button);

echo $OUTPUT->render_from_template('block_publishflow/publishresponse', $template);

echo $OUTPUT->box_end();

echo $OUTPUT->footer();
