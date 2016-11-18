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

defined('MOODLE_INTERNAL') || die();

/**
 * Controls publication/deployment of courses in a 
 * distributed moodle configuration.
 *
 * @package block_publishflow
 * @category blocks
 * @author Valery Fremaux (valery.fremaux@club-internet.fr)
 * @author Wafa Adham (admin@adham.ps)
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

        $config = get_config('block_publishflow');

        if (preg_match('/\\bmain\\b/', @$config->moodlenodetype))
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

        $config = get_config('block_publishflow');

        $blockrec = $DB->get_record('block', array('name' => 'publishflow'));
        $blockrec->cron = 0 + @$config->coursedelivery_networkrefreshautomation;
        $DB->update_record('block', $blockrec);
    }

    // Apart from the constructor, there is only ONE function you HAVE to define, get_content().
    // Let's take a walkthrough! :)

    function get_content() {
        // We intend to use the $CFG global variable
        global $CFG, $COURSE, $USER, $VMOODLES, $MNET, $OUTPUT, $DB, $PAGE;

        $config = get_config('block_publishflow');

        include_once $CFG->dirroot.'/blocks/publishflow/rpclib.php';
        include_once $CFG->dirroot.'/blocks/publishflow/lib.php';

        $output = '';

        $context_course = context_course::instance($COURSE->id);

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;

        // Making bloc content
        $filemanagerlink = new moodle_url('/blocks/publishflow/pffilesedit.php', array('id' => $COURSE->id));

        $systemcontext = context_system::instance();

        $footeroutput = '';
        if (has_capability('block/publishflow:managepublishedfiles', $systemcontext)) {
            $footeroutput = "<div>";
            $managepublishedfiles = get_string('managepublishedfiles', 'block_publishflow');
            $footeroutput .= '<a href="'.$filemanagerlink.'">'.$managepublishedfiles.'</a>';
            $footeroutput .= '</div>';
        }
    
        if($COURSE->idnumber){

            if ($config->moodlenodetype == 'factory') {

            /*** PURE FACTORY ****/
                $output .=  block_build_factory_menu($this);
            } elseif (preg_match('/\\bcatalog\\b/', $config->moodlenodetype)) {

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
            $output .= $OUTPUT->single_button(new moodle_url('/blocks/publishflow/submit.php', $qoptions), get_string('reference', 'block_publishflow'), 'post');
            $output .= '</p>';
            $output .= $OUTPUT->box_end();
        } 

        $this->content->text = $output;
        $this->content->footer = $footeroutput;

        // And that's all! :)
        return $this->content;
    }

    public function get_required_javascript() {
        global $CFG, $PAGE;

        $PAGE->requires->jquery();
    }

    function makebackupform(){
        global $COURSE, $CFG;

        if (has_capability('moodle/course:backup', context_course::instance($COURSE->id))){
            $dobackupstr = get_string('dobackup', 'block_publishflow');
            $this->content->text .= '<center>';
            $backupurl = new moodle_url('/blocks/publishflow/backup.php');
            $this->content->text .= '<form name="makebackup" action="'.$backupurl.'" method="GET">';
            $this->content->text .= '<input type="hidden" name="id" value="'.$COURSE->id.'" />';
            $this->content->text .= '<input type="submit" name="go_btn" value="'.$dobackupstr.'" />';
            $this->content->text .= '</form>';
            $this->content->text .= '</center>';
        }
    }

    static function crontask() {
        global $CFG;

        mtrace("\nStarting renewing remote catalogs...");
        include_once $CFG->dirroot.'/mnet/xmlrpc/client.php';
        include_once $CFG->dirroot.'/blocks/publishflow/lib.php';
        automate_network_refreshment();
        mtrace("Finishing renewing remote catalogs\n");
    }

}