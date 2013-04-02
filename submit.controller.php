<?php

if (!defined('MOODLE_INTERNAL')) die('You cannot access this script directly');

include_once $CFG->dirroot."/mnet/xmlrpc/client.php";
include_once $CFG->dirroot."/mnet/lib.php";
include_once $CFG->libdir."/pear/HTML/AJAX/JSON.php";

if ($action == 'submit'){

    // call the proper controller agains current submission procedure...
    // must setup an idnumber variable !
    include $CFG->dirroot."/blocks/publishflow/submits/{$theBlock->config->submitto}/submit.controller.php";

    if ($step == STEP_COMPLETED){
        set_field('course', 'idnumber', $idnumber, 'id', $fromcourse);
        echo '<br/>';
        print_box_start();
        print_string('indexingof', 'block_publishflow');
        echo ' "'.$COURSE->fullname.'" ';
        print_string('completed', 'block_publishflow', $idnumber);
        print_box_end();
        print_continue($CFG->wwwroot.'/course/view.php?id='.$COURSE->id);
    }
        
}

?>