<?php

/**
 * Implements a result page for driving the publish 
 * transaction.
 * @package blocks-publishflow
 * @category blocks
 *
 */

/**
* Requires and includes
*/
    include '../../config.php';
    include_once $CFG->dirroot."/mnet/lib.php";
    include_once $CFG->dirroot."/mnet/xmlrpc/client.php";

/// get imput params
    $fromcourse = required_param('fromcourse', PARAM_INT);
    $action = required_param('what', PARAM_TEXT);
    $where = required_param('where', PARAM_INT); // Where is an mnet_host id
    $force = optional_param('forcerepublish', 0, PARAM_INT); // If we have to replace
/// check we can do this
    $course = $DB->get_record('course', array('id' => "$fromcourse"));

    require_login($course);

    $navigation = array(
                    array(
                        'title' => get_string('publishing', 'block_publishflow'), 
                        'name' => get_string('publishing', 'block_publishflow'), 
                        'url' => NULL, 
                        'type' => 'course'
                    )
                  );
    $PAGE->set_title(get_string('deployment', 'block_publishflow'));
    $PAGE->set_heading(get_string('deployment', 'block_publishflow'));

    
    $PAGE->set_url('/blocks/publishflow/publish.php',array('fromcourse'=>$fromcourse,'what'=>$action,'where'=>$where,'forcerepublish'=>$force));

    /* SCANMSG: may be additional work required for $navigation variable */
    echo $OUTPUT->header();
/// get context objects
    $mnethost = $DB->get_record('mnet_host', array('id' => $where));
    // if ($CFG->debug)
    //    echo "[$action from $fromcourse at $where]";

/// start triggering the remote deployment
    if (empty($CFG->coursedeliveryislocal)){
        echo "<center>";
        echo $OUTPUT->box(get_string('networktransferadvice', 'block_publishflow'));
        echo "<br/>";
    }    
/// start triggering the remote deployment
    if (!empty($USER->mnethostid)){
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
    $mnet_host = new mnet_peer();
    $mnet_host->set_wwwroot($mnethost->wwwroot);
    if (!$rpcclient->send($mnet_host)){
        print_object($rpcclient);
        print_error('failed', 'block_publishflow');        
    }

    $response = json_decode($rpcclient->response);

    // print_object($response);

    if ($response->status == 100){ // Test point
        notice ("Remote test point : ".$response->teststatus);
    }
    if ($response->status == 200){
        $remotecourseid = $response->courseid;

        switch($action){
            case 'publish':{
                // confirm/force published status in course record if not done, to avoid strange access effects
                if (!empty($CFG->coursepublishedcategory)){
	                $DB->set_field('course', 'category', $CFG->coursepublishedcategory, array('id' => "{$fromcourse}"));
	            }
                break;
            }
            case 'unpublish': {
                $lpcontext = context_course::instance($fromcourse);
                if (!empty($CFG->coursesuspendedcategory)){
	                $DB->set_field('course', 'category', $CFG->coursesuspendedcategory, array('id' => "{$fromcourse}"));
	            }
                break;      
            }
        }
        print_string('publishsuccess', 'block_publishflow');
        echo '<br/>';
        echo '<br/>';
        if ($USER->mnethostid != $mnethost->id){
            echo "<a href=\"/auth/mnet/jump.php?hostid={$mnethost->id}&amp;wantsurl=".urlencode('/course/view.php?id='.$remotecourseid)."\">".get_string('jumptothecourse', 'block_publishflow').'</a> - ';
        } else {
            echo "<a href=\"{$mnethost->wwwroot}/course/view.php?id={$remotecourseid}\">".get_string('jumptothecourse', 'block_publishflow').'</a> - ';
        }
    } else {
        notice("Remote error : ".$response->error);
    }
    echo " <a href=\"/course/view.php?id={$course->id}\">".get_string('backtocourse', 'block_publishflow').'</a>';
    echo '</center>';

    echo $OUTPUT->footer();
?>