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
 * Implements a result page for driving the deploy
 * transaction.
 * @package blocks-publishflow
 * @category blocks
 */

require('../../config.php');
require_once($CFG->dirroot.'/mnet/lib.php');
require_once($CFG->dirroot.'/mnet/xmlrpc/client.php');
require_once($CFG->dirroot.'/blocks/publishflow/lib.php');

$fromcourse = required_param('fromcourse', PARAM_INT);
$action = required_param('what', PARAM_TEXT);
$where = required_param('where', PARAM_INT);  // A mnet host ID.

$url = new moodle_url('/blocks/publishflow/retrofit.php', array('fromcourse' => $fromcourse, 'what' => $action, 'where' => $where));

$course = $DB->get_record('course', array('id' => "$fromcourse"));

require_course_login($course, false);

$PAGE->set_url($url);

$PAGE->set_title(get_string('retrofitting', 'block_publishflow'));
$PAGE->set_heading(get_string('retrofit', 'block_publishflow'));
$PAGE->navbar->add(get_string('pluginname', 'block_publishflow'));
$PAGE->navbar->add(get_string('retrofit', 'block_publishflow'));

echo $OUTPUT->header();

$wherehost = $DB->get_record('mnet_host', array('id' => $where));
$response = block_publishflow_retrofit($course, $wherehost->wwwroot, $fromcourse);

$template = new StdClass;

echo $OUTPUT->box_start('plublishpanel');

if ($response->status == 100) {
    $template->retrofiterrornotif = $OUTPUT->notification("Remote Test Point : ".$response->teststatus, 'notifyproblem');
}

if ($response->status == 200) {
    $remotecourseid = $response->courseid;
    $template->retrofitsuccessnotif = $OUTPUT->notification(get_string('retrofitsuccess', 'block_publishflow'), 'notifysuccess');

    if ($USER->mnethostid != $wherehost->id) {
        $params = array('hostid' => $wherehost->id, 'wantsurl' => '/course/view.php?id='.$remotecourseid);
        $jumpurl = new moodle_url('/auth/mnet/jump.php', $params);
    } else {
        $jumpurl = $wherehost->wwwroot.'/course/view.php?id='.$remotecourseid;
    }
    $button = new single_button($jumpurl, get_string('jumptothecourse', 'block_publishflow'));
    $button->id = 'responseform';
    $button->add_action(new confirm_action(get_string('remotejumpadvice', 'block_publishflow'), null,
        get_string('confirmjump', 'block_publishflow')));
    $template->remotecoursebutton = $OUTPUT->render($button);
} else {
    $template->retrofiterrornotif = $OUTPUT->notification("Remote Error : <pre>".$response->error.'</pre>', 'notifyproblem');
}

$courseurl = new moodle_url('/course/view.php', array('id' => $course->id));
$attrs = array('value' => get_string('backtocourse', 'block_publishflow'), 'type' => 'button');
$button = html_writer::empty_tag('input', $attrs);
$template->localcoursebutton = html_writer::link($courseurl, $button);

echo $OUTPUT->render_from_template('block_publishflow/rerofitresponse', $template);

echo $OUTPUT->box_end();

echo $OUTPUT->footer();
