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

$fromcourse = required_param('fromcourse', PARAM_INT);
$action = required_param('what', PARAM_TEXT);
$where = required_param('where', PARAM_INT);  // Where is a vmoodle id.

$url = new moodle_url('/blocks/publishflow/retrofit.php', array('fromcourse' => $fromcourse, 'what' => $action, 'where' => $where));

$course = $DB->get_record('course', array('id' => "$fromcourse"));

require_course_login($course, false);

$PAGE->set_url($url);

$PAGE->set_title(get_string('retrofitting', 'block_publishflow'));
$PAGE->set_heading(get_string('retrofit', 'block_publishflow'));
$PAGE->navbar->add(get_string('pluginname', 'block_publishflow'));
$PAGE->navbar->add(get_string('retrofit', 'block_publishflow'));

echo $OUTPUT->header();

$response = block_publishflow_retrofit($course, $where, $fromcourse);

echo $OUTPUT->box_start('plublishpanel');
echo '<center>';
if ($response->status == 100) {
    echo $OUTPUT->notification("Remote Test Point : ".$response->teststatus);
}
if ($response->status == 200) {
    $remotecourseid = $response->courseid;
    print_string('retrofitsuccess', 'block_publishflow');
    echo '<br/>';
    echo '<br/>';
    if ($USER->mnethostid != $mnethost->id) {
        $params = array('hostid' => $mnethost->id, 'wantsurl' => '/course/view.php?id='.$remotecourseid);
        $jumpurl = new moodle_url('/auth/mnet/jump.php', $params);
        echo '<a href="'.$jumpurl.'">'.get_string('jumptothecourse', 'block_publishflow').'</a> - ';
    } else {
        $label = get_string('jumptothecourse', 'block_publishflow');
        echo '<a href="'.$mnethost->wwwroot.'/course/view.php?id='.$remotecourseid.'">'.$label.'</a> - ';
    }
} else {
    echo $OUTPUT->notification("Remote Error : <pre>".$response->error.'</pre>');
}
$courseurl = new moodle_url('/course/view.php', array('id' => $course->id));
echo ' <a href="'.$courseurl.'">'.get_string('backtocourse', 'block_publishflow').'</a>';
echo '</center>';
echo $OUTPUT->box_end();

echo $OUTPUT->footer();
