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
 * Implements a result page for driving the deploy transaction.
 * @package block_publishflow
 * @category blocks
 * @author Valery Fremaux (valery.fremaux@gmail.com)
 * @author Wafa Adham (admin@adham.ps)
 * @copyright 2008 onwards Valery Fremaux (http://www.myLearningFactory.com)
 */
require('../../config.php');
require_once($CFG->dirroot.'/mnet/lib.php');
require_once($CFG->dirroot.'/mnet/xmlrpc/client.php');
require_once($CFG->dirroot.'/blocks/publishflow/lib.php');

$id = required_param('id', PARAM_INT); // The block ID.
$fromcourse = required_param('fromcourse', PARAM_INT);
$action = required_param('what', PARAM_TEXT);
$where = required_param('where', PARAM_INT);
$category = optional_param('category', 0, PARAM_INT);
$deploykey = optional_param('deploykey', null, PARAM_TEXT);
$forcecache = optional_param('force', 1, PARAM_INT);

$course = $DB->get_record('course', array('id' => "$fromcourse"));

// Security.

require_login($course);

$systemcontext = context_course::instance($fromcourse);
$PAGE->set_context($systemcontext); 
$PAGE->set_button('');
$params = array('id' => $id,
                'fromcourse' => $fromcourse,
                'where' => $where,
                'what' => $action,
                'category' => $category,
                'force' => $forcecache,
                'deploykey' => $deploykey);
$PAGE->set_url('/blocks/publishflow/deploy.php', $params);
$PAGE->navbar->add(get_string('pluginname', 'block_publishflow'));
$PAGE->navbar->add(get_string('deploying', 'block_publishflow'));

print $OUTPUT->header();

// Get the block context.

if (!$instance = $DB->get_record('block_instances', array('id' => $id))){
    print_error('errorbadblockid', 'block_publishflow');
}

$theblock = block_instance('publishflow', $instance);

// Check we can do this.
$course = $DB->get_record('course', array('id' => "$fromcourse"));

if (!has_capability('block/publishflow:deployeverwhere', contextsystem::instance())) {
    // TODO : Check on remote host the deploy capability.
    assert(1);
}


// Check the deploykey.
if (!empty($theblock->config->deploymentkey)) {
    if ($theblock->config->deploymentkey !== $deploykey) {
        print_error('badkey', 'block_publishflow', new moodle_url('/course/view.php', array('id' => $fromcourse)));
    }
}

$mnethost = $DB->get_record('mnet_host', array('id' => $where));

// If we want to deploy on a local platform, we need to bypass the RPC with a quick function.
if ($where == 0) {
    $remotecourseid = publishflow_local_deploy($category, $course);

    echo $OUTPUT->box_start('plublishpanel');
    print_string('deploysuccess', 'block_publishflow');

    echo '<br/>';
    echo '<br/>';
    $userhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid));
    $courseurl = new moodle_url('/course/view.php', array('id' => $remotecourseid));
    echo '<a href="'.$courseurl.'">'.get_string('jumptothecourse', 'block_publishflow').'</a> - ';
    $courseurl = new moodle_url('/course/view.php', array('id' => $course->id));
    echo ' <a href="'.$courseurl.'">'.get_string('backtocourse', 'block_publishflow').'</a>';
    echo '</center>';
    echo $OUTPUT->box_end();
} else {
    // Start triggering the remote deployment.
    if (!empty($USER->mnethostid)) {
        $userhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid));
        $userwwwroot = $userhost->wwwroot;
    } else {
        $userwwwroot = $CFG->wwwroot;
    }

<<<<<<< HEAD
/**
* Requires and includes
*/
    include '../../config.php';
    include_once $CFG->dirroot."/mnet/lib.php";
    include_once $CFG->dirroot."/mnet/xmlrpc/client.php";
    include_once $CFG->dirroot."/blocks/publishflow/lib.php";

/// get imput params
    $id = required_param('id', PARAM_INT); // the block ID
    $fromcourse = required_param('fromcourse', PARAM_INT);
    $action = required_param('what', PARAM_TEXT);
    $where = required_param('where', PARAM_INT);  
    $category = optional_param('category', 0, PARAM_INT);
    $deploykey = optional_param('deploykey', null,PARAM_TEXT);
    $forcecache = optional_param('force', 1, PARAM_INT);

    $course = $DB->get_record('course', array('id' => "$fromcourse"));

    require_login($course);
            
    $system_context = get_context_instance(CONTEXT_COURSE,$fromcourse);
    $PAGE->set_context($system_context); 
    $PAGE->set_button('');
    $PAGE->set_url('/blocks/publishflow/deploy.php',array('id' => $id,'fromcourse' => $fromcourse,'where' => $where,'what' => $action,'category' => $category, 'force' => $forcecache,'deplykey' => $deploykey));

    print $OUTPUT->header();
    
/// get the block context
    if (!$instance = $DB->get_record('block_instances', array('id' => $id))){
        print_error('errorbadblockid', 'block_publishflow');
    }

    $theBlock = block_instance('publishflow', $instance);
/// check we can do this
    $course = $DB->get_record('course', array('id' => "$fromcourse"));


 /*	if(!has_capability('block/publishflow:deployeverwhere', context_system::instance())){
 		// check on remote host the deploy capability
 		// TODO
 	}
    */

	// check the deploykey
	if (!empty($theBlock->config->deploymentkey)){
		if ($theBlock->config->deploymentkey !== $deploykey){
			print_error('badkey', 'block_publishflow', $CFG->wwwroot."/course/view.php?id=$fromcourse");
		}
	}

    $navigation = array(
	    array(
	        'title' => get_string('deploying', 'block_publishflow'), 
	        'name' => get_string('deploying', 'block_publishflow'), 
	        'url' => NULL, 
	        'type' => 'course'
	    )
    );
   
    $mnethost = $DB->get_record('mnet_host', array('id' => $where));

///If we want to deploy on a local platform, we need to bypass the RPC with a quick function
if($where == 0){
	$remotecourseid = publishflow_local_deploy($category, $course);
 	print_string('deploysuccess', 'block_publishflow');

	echo '<br/>';
	echo '<br/>';
	$userhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid));
	echo "<a href=\"{$CFG->wwwroot}/course/view.php?id={$remotecourseid}\">".get_string('jumptothecourse', 'block_publishflow').'</a> - ';
	echo " <a href=\"/course/view.php?id={$course->id}\">".get_string('backtocourse', 'block_publishflow').'</a>';
	echo '</center>';
} else {
    /// start triggering the remote deployment
	if (!empty($USER->mnethostid)){
	    $userhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid));
	    $userwwwroot = $userhost->wwwroot;
	} else {
	    $userwwwroot = $CFG->wwwroot;
	}

	$caller = new stdClass;
	$caller->username = $USER->username;
	$caller->remoteuserhostroot = $userwwwroot;
	$caller->remotehostroot = $CFG->wwwroot;

	$parmsoverride = array('category' => $category);

	$rpcclient = new mnet_xmlrpc_client();
	$rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_deploy');
	$rpcclient->add_param($caller, 'struct');
	$rpcclient->add_param(json_encode($course), 'string');
	$rpcclient->add_param($forcecache, 'int'); // prepared for forcing replacement
	$rpcclient->add_param($parmsoverride,'struct');
	$rpcclient->add_param(1,'int'); // json response required

	$mnet_host = new mnet_peer();
	$mnet_host->set_wwwroot($mnethost->wwwroot);
	if (!$rpcclient->send($mnet_host)){
	    print_object($rpcclient);
	    print_error('failed', 'block_publishflow');        
	}

	$response = json_decode($rpcclient->response);

	// print_object($response);
	echo '<center>';
	if ($response->status == 200){
	    $remotecourseid = $response->courseid;
	    print_string('deploysuccess', 'block_publishflow');
	    echo '<br/>';
	    echo '<br/>';
	    if ($USER->mnethostid != $mnethost->id){
			echo "<a href=\"/auth/mnet/jump.php?hostid={$mnethost->id}&amp;wantsurl=".urlencode('/course/view.php?id='.$remotecourseid)."\">".get_string('jumptothecourse', 'block_publishflow').'</a> - ';
	    } else {
			echo "<a href=\"{$mnethost->wwwroot}/course/view.php?id={$remotecourseid}\">".get_string('jumptothecourse', 'block_publishflow').'</a> - ';
	    }
	} else {
	    notice("Remote Error : ".$response->error);
	}
	echo " <a href=\"/course/view.php?id={$course->id}\">".get_string('backtocourse', 'block_publishflow').'</a>';
	echo '</center>';
}
    echo $OUTPUT->footer();
?>
=======
    $caller = new stdClass;
    $caller->username = $USER->username;
    $caller->remoteuserhostroot = $userwwwroot;
    $caller->remotehostroot = $CFG->wwwroot;

    $parmsoverride = array('category' => $category);

    $rpcclient = new mnet_xmlrpc_client();
    $rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_deploy');
    $rpcclient->add_param($caller, 'struct');
    $rpcclient->add_param(json_encode($course), 'string');
    $rpcclient->add_param($forcecache, 'int'); // Prepared for forcing replacement.
    $rpcclient->add_param($parmsoverride, 'struct');
    $rpcclient->add_param(1, 'int'); // Json response required.

    $mnethost = new mnet_peer();
    $mnethost->set_wwwroot($mnethost->wwwroot);
    if (!$rpcclient->send($mnethost)) {
        $debugout = ($CFG->debug | DEBUG_DEVELOPER) ? var_export($rpcclient) : '';
        print_error('failed', 'block_publishflow', new moodle_url('/course/view.php', array('id' => $fromcourse, '', $debugout)));
    }

    $response = json_decode($rpcclient->response);

    echo $OUTPUT->box_start('plublishpanel');
    echo '<center>';
    if ($response->status == 200) {
        $remotecourseid = $response->courseid;
        print_string('deploysuccess', 'block_publishflow');
        echo '<br/>';
        echo '<br/>';
        if ($USER->mnethostid != $mnethost->id) {
            $params = array('hostid' => $mnethost->id, 'wantsurl' => '/course/view.php?id='.$remotecourseid);
            $jumpurl = new moodle_url('/auth/mnet/jump.php', $params);
            echo '<a href="'.$jumpurl.'">'.get_string('jumptothecourse', 'block_publishflow').'</a> - ';
        } else {
            $jumpurl = "{$mnethost->wwwroot}/course/view.php?id={$remotecourseid}";
            echo '<a href="'.$jumpurl.'">'.get_string('jumptothecourse', 'block_publishflow').'</a> - ';
        }
    } else {
        echo $OUTPUT->notification("Remote Error : ".$response->error);
    }
    $courseurl = new moodle_url('/course/view.php', array('id' => $course->id));
    echo ' <a href="'.$courseurl.'">'.get_string('backtocourse', 'block_publishflow').'</a>';
    echo '</center>';
    echo $OUTPUT->box_end();
}
echo $OUTPUT->footer();
>>>>>>> MOODLE_34_STABLE
