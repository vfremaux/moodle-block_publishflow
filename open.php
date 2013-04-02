<?php

/**
 * Implements a result page for driving the training session closing
 * transaction.
 * @package blocks-publishflow
 * @category blocks
 *
 */

/**
* Requires and includes
*/
    include '../../config.php';
    include_once $CFG->libdir."/pear/HTML/AJAX/JSON.php";
    require_once($CFG->dirroot.'/blocks/publishflow/lib.php');
    require_once($CFG->dirroot.'/local/lib/mailtemplatelib.php');

/// get imput params
    $fromcourse = required_param('fromcourse', PARAM_INT);
    $action = required_param('what', PARAM_TEXT);
    $step = optional_param('step', COURSE_OPEN_CHOOSE_OPTIONS, PARAM_INT);
    
/// check we can do this
    $course = get_record('course', 'id', "$fromcourse");

    require_login($course);

    $navigation = array(
                    array(
                        'title' => get_string('opening', 'block_publishflow'), 
                        'name' => get_string('opening', 'block_publishflow'), 
                        'url' => NULL, 
                        'type' => 'course'
                    )
                  );
    
    print_header_simple(get_string('deployment', 'block_publishflow'), get_string('deployment', 'block_publishflow'), $navigation);
    
/// get context objects
    
    // if ($CFG->debug)
    //    echo "[$action from $fromcourse]";
    
/// start triggering the remote deployment

    $strdoopen = get_string('doopen', 'block_publishflow');
    $url = $CFG->wwwroot."/blocks/publishflow/open.php?what=open&amp;fromcourse={$course->id}";
    $context = get_context_instance(CONTEXT_COURSE, $course->id);

    switch($step){
         case COURSE_OPEN_CHOOSE_OPTIONS:
            // prints a choice with helpers
            print_heading(get_string('notification', 'block_publishflow'), 2);
            print_string('opennotifyhelper', 'block_publishflow');
            echo "<p align=\"right\"><a href=\"{$url}&amp;step=".COURSE_OPEN_EXECUTE."&amp;notify=1\">$strdoopen</a></p>";

            print_heading(get_string('withoutnotification', 'block_publishflow'), 2);
            print_string('openwithoutnotifyhelper', 'block_publishflow');
            echo "<p align=\"right\"><a href=\"{$url}&amp;step=".COURSE_OPEN_EXECUTE."&amp;notify=0\">$strdoopen</a></p>";

            echo '<p align="center"><center>';
            $opts['id'] = $course->id;
            print_single_button($CFG->wwwroot.'/course/view.php', $opts, get_string('cancel'));
            echo '</center></p>';

         break;
         case COURSE_OPEN_EXECUTE:
            $notify = required_param('notify', PARAM_INT);
            publishflow_session_open($course, $notify);
            print_box(get_string('courseopen', 'block_publishflow'), 'center');
            echo " <p align=\"center\"><a href=\"/course/view.php?id={$course->id}\">".get_string('backtocourse', 'block_publishflow').'</a></p>';
            echo '</center>';
            break;
    }
    print_footer();
?>