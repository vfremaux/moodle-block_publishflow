<?php //$Id: block_publishflow.php,v 1.5 2012-09-27 14:50:12 wa Exp $

/**
* Controls publication/deployment of courses in a 
* distributed moodle configuration.
*
* @package block-publishflow
* @category blocks
* @author Valery Fremaux (valery.fremaux@club-internet.fr)
*
*/

/**
* Includes and requires
*/


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
        return array('course' => true, 'site' => false, 'all' => false);
    }

    function specialization() {
    	if (!empty($this->config)){
	    	$this->config_save($this->config);
	    }
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

    // Apart from the constructor, there is only ONE function you HAVE to define, get_content().
    // Let's take a walkthrough! :)

    function get_content() {
        // We intend to use the $CFG global variable
        global $CFG, $COURSE, $USER, $VMOODLES, $MNET,$OUTPUT,$DB,$PAGE;

		
        include_once $CFG->dirroot.'/blocks/publishflow/rpclib.php';
        include_once $CFG->dirroot.'/blocks/publishflow/lib.php';
        
        $output = '';
		//require_once($CFG->libdir.'/pear/HTML/AJAX/JSON.php');
        $context_course = context_course::instance($COURSE->id);

        if($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        
        // Making bloc content
        $filemanagerlink= $CFG->wwwroot."/blocks/publishflow/pffilesedit.php?id=".$COURSE->id;
        
        $systemcontext = get_context_instance(CONTEXT_SYSTEM);  
        
        if(has_capability('block/publishflow:managepublishedfiles',$systemcontext))
        {  
            $output  = "<div>";
            $managepublishedfiles = get_string('managepublishedfiles', 'block_publishflow');
            $output  .= "<b><a href='".$filemanagerlink."'>$managepublishedfiles</a></b>";
            $output  .= "</div>";
        }
    
        if($COURSE->idnumber){

            if ($CFG->moodlenodetype == 'factory'){
            
            /*** PURE FACTORY ****/
            $output .=  block_build_factory_menu($this);
	        
            } 
	        elseif (preg_match('/\\bcatalog\\b/', $CFG->moodlenodetype)) {
	    
            /*** CATALOG OR CATALOG & FACTORY ****/  
            $output .=  block_build_catalogandfactory_menu($this);
	    
            } else {
            
            /*** TRAINING CENTER ****/
			$output .=  block_build_trainingcenter_menu($this);
	        }
	    
        } else {            
	        // this is an unregistered course that has no IDNumber reference. This causes a problem for instance identification            
	        $output .= $OUTPUT->notification(get_string('unregistered','block_publishflow'), 'notifyproblem', true);
            $qoptions['fromcourse'] = $COURSE->id;
            $qoptions['what'] = 'submit';
            $qoptions['id'] = $this->instance->id;
            $output .= $OUTPUT->single_button(new moodle_url($CFG->wwwroot.'/blocks/publishflow/submit.php', $qoptions), get_string('reference', 'block_publishflow'), 'post');
	    } 

	    // If you like, you can specify a "footer" text that will be printed at the bottom of your block.
	    // If you don't want a footer, set this variable to an empty string. DO NOT delete the line entirely!
        $this->content->text = $output;
	    $this->content->footer = '';
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
}

?>
