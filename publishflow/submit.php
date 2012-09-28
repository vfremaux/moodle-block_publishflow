<?php

/**
 * Submits a course for indexing
 * @package blocks-publishflow
 * @category blocks
 *
 */

/**
* Requires and includes
*/
    include '../../config.php';
    include_once $CFG->dirroot."/mnet/lib.php";
    include_once $CFG->libdir."/pear/HTML/AJAX/JSON.php";
    include_once $CFG->dirroot."/mnet/xmlrpc/client.php";
    include_once $CFG->dirroot."/blocks/publishflow/submitlib.php";

/**
* Constants
*/
// This is a common step for any indexing procedure. Other processes may add their own step declarations.
define('STEP_COMPLETED', -1);
define('STEP_INITIAL', 0);

/// get imput params
    $id = required_param('id', PARAM_INT); // The block instance ID
    $fromcourse = required_param('fromcourse', PARAM_INT);
    $action = required_param('what', PARAM_TEXT);
    $step = optional_param('step', STEP_INITIAL, PARAM_INT);
    $course = $DB->get_record('course', array('id' => "$fromcourse"));
/// check we can do this

    require_login($course);    
    $navigation = array(
        array(
            'name' => format_string($course->shortname), 
            'link' => $CFG->wwwroot."/couse/view.php?id={$course->id}", 
            'type' => 'url'
        ),
        array(
            'name' => get_string('indexing', 'block_publishflow'), 
            'link' => NULL, 
            'type' => 'title'
        ),
        array(
            'name' => get_string('step', 'block_publishflow').($step + 1), 
            'link' => NULL, 
            'type' => 'title'
        )
      );

    $PAGE->set_title(get_string('indexing', 'block_publishflow'));
    $PAGE->set_heading(get_string('indexing', 'block_publishflow'));
    echo $OUTPUT->header();

    $result = 0;

    // runs the proper indexing procedure
    $instance = $DB->get_record('block_instance', array('id' => $id));
    $theBlock = block_instance('publishflow', $instance);
    if (!isset($theBlock->config->submitto)) $theBlock->config->submitto = 'default';
    $result = include $CFG->dirroot."/blocks/publishflow/submits/{$theBlock->config->submitto}/submit_proc.php";

    echo $OUTPUT->footer();
?>
