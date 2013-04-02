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
    include_once $CFG->dirroot.'/blocks/publishflow/lib.php';

/// get imput params
    $fromcourse = required_param('fromcourse', PARAM_INT);
    $action = required_param('what', PARAM_TEXT);
    $step = optional_param('step', COURSE_CLOSE_CHOOSE_MODE, PARAM_TEXT);
/// check we can do this
    $course = $DB->get_record('course', array('id' => "$fromcourse"));

    require_login($course);

    $navigation = array(
                    array(
                        'title' => get_string('closing', 'block_publishflow'), 
                        'name' => get_string('closing', 'block_publishflow'), 
                        'url' => NULL, 
                        'type' => 'course'
                    )
                  );
    $PAGE->set_title(get_string('deployment', 'block_publishflow'));
    $PAGE->set_heading(get_string('deployment', 'block_publishflow'));
    /* SCANMSG: may be additional work required for $navigation variable */
    echo $OUTPUT->header();
/// start triggering the remote deployment

    $strdoclose = get_string('doclose', 'block_publishflow');
    $url = $CFG->wwwroot."/blocks/publishflow/close.php?what=close&amp;fromcourse={$course->id}";
    echo $OUTPUT->box_start();

    switch($step){
         case COURSE_CLOSE_CHOOSE_MODE:
            // prints a choice with helpers
            print_string('closehelper', 'block_publishflow');

            echo $OUTPUT->heading(get_string('closepublic', 'block_publishflow'));
            print_string('closepublichelper', 'block_publishflow');
            echo "<p align=\"right\"><a href=\"{$url}&amp;step=".COURSE_CLOSE_EXECUTE."&amp;mode=".COURSE_CLOSE_PUBLIC."\">$strdoclose</a></p>";

            echo $OUTPUT->heading(get_string('closeprotected', 'block_publishflow'));
            print_string('closeprotectedhelper', 'block_publishflow');
            echo "<p align=\"right\"><a href=\"{$url}&amp;step=".COURSE_CLOSE_EXECUTE."&amp;mode=".COURSE_CLOSE_PROTECTED."\">$strdoclose</a></p>";

            echo $OUTPUT->heading(get_string('closeprivate', 'block_publishflow'));
            print_string('closeprivatehelper', 'block_publishflow');
            echo "<p align=\"right\"><a href=\"{$url}&amp;step=".COURSE_CLOSE_EXECUTE."&amp;mode=".COURSE_CLOSE_PRIVATE."\">$strdoclose</a></p>";

            echo '<p align="center"><center>';
            $opts['id'] = $course->id;
            echo $OUTPUT->single_button(new moodle_url($CFG->wwwroot.'/course/view.php', $opts), get_string('cancel'), 'get');
            echo '</center></p>';
         break;
         case COURSE_CLOSE_EXECUTE:
            $mode = required_param('mode', PARAM_INT);
            publishflow_course_close($course, $mode);
            echo $OUTPUT->box(get_string('courseclosed', 'block_publishflow'), 'center');
            echo " <a href=\"/course/view.php?id={$course->id}\">".get_string('backtocourse', 'block_publishflow').'</a>';
            echo '</center>';
            break;
    }

    echo $OUTPUT->box_end();

    echo $OUTPUT->footer();
?>