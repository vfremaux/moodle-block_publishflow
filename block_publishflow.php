<?php
/**
 * Controls publication/deployment of courses in a 
 * distributed moodle configuration.
 *
 * @package block-publishflow
 * @category blocks
 * @version Moodle 2.x
 * @author Valery Fremaux (valery.fremaux@club-internet.fr)
 * @contributor Wafa Adham (admin@adham.ps)
 *
 */

if (get_config('block_publishflow_late_install')) {
    set_config('block_publishflow_late_install', 0);
    require_once($CFG->dirroot.'/blocks/publishflow/db/install.php');
    xmldb_block_publishflow_late_install();
}

/**
 * Constants
 */

define('UNPUBLISHED', 0);
define('PUBLISHED_HIDDEN', 1);
define('PUBLISHED_VISIBLE', 2);

/**
 * the standard override of common moodle block Class
 */
class block_publishflow extends block_base {

    function init() {
        global $CFG;

        if (preg_match('/\\bmain\\b/', @$CFG->moodlenodetype))
            $this->title = get_string('deployname', 'block_publishflow');
        elseif (@$CFG->moodlenodetype == 'factory')
            $this->title = get_string('publishname', 'block_publishflow');
        elseif (@$CFG->moodlenodetype == 'learningarea')
            $this->title = get_string('managename', 'block_publishflow');
        elseif (@$CFG->moodlenodetype == 'factory,catalog')
            $this->title = get_string('combinedname', 'block_publishflow');
        else
            $this->title = get_string('blockname', 'block_publishflow');
        $this->content_type = BLOCK_TYPE_TEXT;
        
    }

    function applicable_formats() {
        return array('course' => true, 'site' => false, 'all' => false, 'my' => false);
    }

    function specialization() {
    	$unused = 0;
    	$this->config_save($unused);
    }

    function has_config() {
        return true;
    }

    function instance_allow_multiple() {
        return false;
    }

    function instance_allow_config() {
        return true;
    }

    function config_save($data) {
        global $DB, $CFG;

        // transfer the automationrefresh value to the cron attribute of the block

        $blockrec = $DB->get_record('block', array('name' => 'publishflow'));
        $blockrec->cron = 0 + @$CFG->coursedelivery_networkrefreshautomation;
        $DB->update_record('block', $blockrec);
    }

    // Apart from the constructor, there is only ONE function you HAVE to define, get_content().
    // Let's take a walkthrough! :)

    function get_content() {
        // We intend to use the $CFG global variable
        global $CFG, $COURSE, $USER, $VMOODLES, $MNET,$OUTPUT,$DB,$PAGE;

        include_once $CFG->dirroot.'/blocks/publishflow/rpclib.php';
        include_once $CFG->dirroot.'/blocks/publishflow/lib.php';
        
        $output = '';

        $context_course = context_course::instance($COURSE->id);

        if($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;

        // Making bloc content
        $filemanagerlink = new moodle_url('/blocks/publishflow/pffilesedit.php', array('id' => $COURSE->id));

        $systemcontext = context_system::instance();

        $footeroutput = '';
        if (has_capability('block/publishflow:managepublishedfiles',$systemcontext)) {
            $footeroutput = "<div>";
            $managepublishedfiles = get_string('managepublishedfiles', 'block_publishflow');
            $footeroutput .= "<a href='".$filemanagerlink."'>$managepublishedfiles</a>";
            $footeroutput .= "</div>";
        }
    
        if($COURSE->idnumber){

            if ($CFG->moodlenodetype == 'factory'){
            
            /*** PURE FACTORY ****/
                $output .=  block_build_factory_menu($this);
            } elseif (preg_match('/\\bcatalog\\b/', $CFG->moodlenodetype)) {

            /*** CATALOG OR CATALOG & FACTORY ****/  
                $output .=  block_build_catalogandfactory_menu($this);
            } else {
            
            /*** TRAINING CENTER ****/
                $output .=  block_build_trainingcenter_menu($this);
            }

        } else {            
            // this is an unregistered course that has no IDNumber reference. This causes a problem for instance identification            
            $output .= $OUTPUT->box_start('noticebox');
            $output .= $OUTPUT->notification(get_string('unregistered','block_publishflow'), 'notifyproblem', true);
            // @TODO add help button $output .= $OUTPUT->help_button();
            $qoptions['fromcourse'] = $COURSE->id;
            $qoptions['what'] = 'submit';
            $qoptions['id'] = $this->instance->id;
            $output .= '<p>';
            $output .= $OUTPUT->single_button(new moodle_url($CFG->wwwroot.'/blocks/publishflow/submit.php', $qoptions), get_string('reference', 'block_publishflow'), 'post');
            $output .= '</p>';
            $output .= $OUTPUT->box_end();
        } 

        $this->content->text = $output;
        $this->content->footer = $footeroutput;

        // And that's all! :)
        return $this->content;
    }
    
    function makebackupform(){
        global $COURSE, $CFG;

        if (has_capability('moodle/course:backup', context_course::instance($COURSE->id))){
            $dobackupstr = get_string('dobackup', 'block_publishflow');
            $this->content->text .= "<center>";
            $this->content->text .= "<form name=\"makebackup\" action=\"{$CFG->wwwroot}/blocks/publishflow/backup.php\" method=\"GET\">";
            $this->content->text .= "<input type=\"hidden\" name=\"id\" value=\"{$COURSE->id}\" />";
            $this->content->text .= "<input type=\"submit\" name=\"go_btn\" value=\"$dobackupstr\" />";
            $this->content->text .= "</form>";
            $this->content->text .= "</center>";
        }
    }
    
    function cron(){
        global $CFG;

        mtrace("\nStarting renewing remote catalogs");
        include_once $CFG->dirroot."/mnet/xmlrpc/client.php";
        include_once $CFG->dirroot.'/blocks/publishflow/lib.php';  
        automate_network_refreshment();        
        mtrace("Finishing renewing remote catalogs");
    }

    static function check_jquery() {
        global $CFG, $PAGE, $OUTPUT;

        if ($CFG->version >= 2013051400) {
            return; // Moodle 2.5 natively loads JQuery
        }

        $current = '1.8.2';

        if (empty($OUTPUT->jqueryversion)){
            $OUTPUT->jqueryversion = '1.8.2';
            $PAGE->requires->js('/blocks/publishflow/js/jquery-'.$current.'.min.js', true);
        } else {
            if ($OUTPUT->jqueryversion < $current){
                debugging('the previously loaded version of jquery is lower than required. This may cause issues to publishflow. Programmers might consider upgrading JQuery version in the component that preloads JQuery library.', DEBUG_DEVELOPER, array('notrace'));
            }
        }
        $PAGE->requires->js('/blocks/publishflow/js/block_js.js', true);
    }
}

block_publishflow::check_jquery();
