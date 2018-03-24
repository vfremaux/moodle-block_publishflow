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
 * Controls publication/deployment of courses in a
 * distributed moodle configuration.
 *
 * @package block_publishflow
 * @category blocks
 * @author Valery Fremaux (valery.fremaux@gmail.com)
 * @author Wafa Adham (admin@adham.ps)
 * @copyright 2008 onwards Valery Fremaux (http://www.myLearningFactory.com)
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/blocks/moodleblock.class.php');

if (get_config('block_publishflow', 'late_install')) {
    set_config('late_install', 0, 'block_publishflow');
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

    public function init() {

        $config = get_config('block_publishflow');

        if (empty($config->moodlenodetype)) {
            set_config('moodlenodetype', 'normalmoodle', 'block_publishflow');
            $config->moodlenodetype = 'normalmoodle';
        }

        if (preg_match('/\\bcatalog\\b/', $config->moodlenodetype)) {
            $this->title = get_string('deployname', 'block_publishflow');
        } else if ($config->moodlenodetype == 'factory') {
            $this->title = get_string('publishname', 'block_publishflow');
        } else if ($config->moodlenodetype == 'learningarea') {
            if (!empty($config->enablesessionmanagement)) {
                $this->title = get_string('managename', 'block_publishflow');
            } else {
                $this->title = get_string('retrofitname', 'block_publishflow');
            }
        } else if (@$config->moodlenodetype == 'factory,catalog') {
            $this->title = get_string('combinedname', 'block_publishflow');
        } else {
            // Normal moodle case.
            $this->title = get_string('blockname', 'block_publishflow');
        }
        $this->content_type = BLOCK_TYPE_TEXT;
    }

    public function applicable_formats() {
        return array('course' => true, 'site' => false, 'all' => false, 'my' => false);
    }

    public function specialization() {
        $unused = 0;
    }

    public function has_config() {
        return true;
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function instance_allow_config() {
        return true;
    }

    public function get_content() {
        global $CFG, $COURSE, $OUTPUT, $PAGE;

        include_once($CFG->dirroot.'/blocks/publishflow/rpclib.php');
        include_once($CFG->dirroot.'/blocks/publishflow/lib.php');

        // This needs comming after the includes or config will be rewritten.
        $config = get_config('block_publishflow');

        $output = '';

        $coursecontext = context_course::instance($COURSE->id);

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;

        $caps = array(
            'block/publishflow:deploy',
            'block/publishflow:addinstance',
            'block/publishflow:publish',
            'block/publishflow:retrofit',
            'block/publishflow:managepublishedfiles',
            'block/publishflow:manage',
        );
        if (!has_any_capability($caps, $coursecontext)) {
            $this->content->text = '';
            $this->content->footer = '';
            return $this->content;
        }

        if (empty($config->moodlenodetype)) {
            set_config('moodlenodetype', 'normalmoodle', 'block_publishflow');
            $config->moodlenodetype = 'normalmoodle';
        }

        if ($config->moodlenodetype == 'normalmoodle') {
            $this->content->text = $OUTPUT->notification(get_string('notinpublishsystem', 'block_publishflow'));
            $this->content->footer = '';
            return $this->content;
        }

        $renderer = $PAGE->get_renderer('block_publishflow');

        // Making bloc content.
        $filemanagerlink = new moodle_url('/blocks/publishflow/pffilesedit.php', array('id' => $COURSE->id));

        $systemcontext = context_system::instance();
        $coursecontext = context_course::instance($COURSE->id);

        $footeroutput = '';
        if (has_capability('block/publishflow:managepublishedfiles', $systemcontext)) {
            $footeroutput = '<div>';
            $managepublishedfiles = get_string('managepublishedfiles', 'block_publishflow');
            $footeroutput .= '<a href="'.$filemanagerlink.'">'.$managepublishedfiles.'</a>';
            $footeroutput .= '</div>';
        }

        if (!$COURSE->idnumber) {
            if (empty($config->submitto) || $config->submitto == 'default') {
                // Silently generate en IDNumber.
                $COURSE->idnumber = \block_publishflow::generate_id();
                $DB->set_field('course', 'idenumber', $idnumber, array('id' => $COURSE->id));
            } else {
                $output .= $renderer->ident_form($this);
            }
        }

        if ($config->moodlenodetype == 'factory') {
            /* PURE FACTORY */
            if (has_capability('block/publishflow:publish', $coursecontext) ||
                        has_capability('block/publishflow:deployeverywhere', $systemcontext) ||
                                block_publishflow_extra_deploy_check()) {
                $deploymentoptions = $this->get_deployment_options();
                $output .= $renderer->factory_menu($this, $deploymentoptions);
            }
        } else if (preg_match('/\\bcatalog\\b/', $config->moodlenodetype)) {

            /* CATALOG OR CATALOG & FACTORY. */
            if (has_capability('block/publishflow:deploy', $coursecontext) ||
                            has_capability('block/publishflow:deployeverywhere', $systemcontext) ||
                                    block_publishflow_extra_deploy_check()) {
                $deploymentoptions = $this->get_deployment_options();
                $output .= $renderer->catalog_and_factory_menu($this, $deploymentoptions);
            }
        } else if ($config->moodlenodetype == 'learningarea') {

            /* TRAINING CENTER */
            if (has_capability('block/publishflow:retrofit', $coursecontext) ||
                        has_capability('block/publishflow:manage', $coursecontext) ||
                                has_capability('block/publishflow:deployeverywhere', $systemcontext) ||
                                        block_publishflow_extra_deploy_check()) {
                $output .= $renderer->trainingcenter_menu($this);
            }
        }

        $this->content->text = $output;
        $this->content->footer = $footeroutput;

        // And that's all! :).
        return $this->content;
    }

    public function get_required_javascript() {
        global $PAGE;

        $PAGE->requires->jquery();
        $PAGE->requires->js_call_amd('block_publishflow/publishflow', 'init');
    }

    public function makebackupform() {
        global $COURSE;

        if (has_capability('moodle/course:backup', context_course::instance($COURSE->id))) {
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

    static public function crontask() {
        global $CFG;

        mtrace("\nStarting renewing remote catalogs...");
        include_once($CFG->dirroot.'/mnet/xmlrpc/client.php');
        include_once($CFG->dirroot.'/blocks/publishflow/lib.php');
        block_publishflow_cron_network_refreshment();
        mtrace("Finishing renewing remote catalogs\n");
    }

    /**
     * generates a course unique ID of fixed length
     * @param int $length
     * @return a new idnumber as a string
     */
    static public function generate_id($length = 10) {
        global $DB;

        $continue = true;

        while ($continue) {
            // Generate.
            $idnumber = '';
            for ($i = 0; $i < $length; $i++) {
                $num = rand(65, 90);
                $idnumber .= chr($num);
            }
            // Test for unicity.
            $continue = $DB->count_records('course', array('idnumber' => $idnumber));
        }
        return $idnumber;
    }

    protected function get_deployment_options() {
        global $DB, $USER, $COURSE, $CFG;

        $systemcontext = context_system::instance();
        $coursecontext = context_course::instance($COURSE->id);

        $hostsavailable = $DB->get_records('block_publishflow_catalog', array('type' => 'learningarea'));
        $fieldsavailable = $DB->get_records_select('user_info_field', 'shortname like \'access%\'');
        $userhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid));

        $deployoptions = array();
        if (has_capability('block/publishflow:deploy', $systemcontext) ||
                block_publishflow_extra_deploy_check()) {
            $deployoptions['0'] = get_string('defaultplatform', 'block_publishflow');
        }

        $userhostroot = $DB->get_field('mnet_host', 'wwwroot', array('id' => $USER->mnethostid));

        if (is_dir($CFG->dirroot.'/local/vmoodle')) {
            include_once($CFG->dirroot.'/local/vmoodle/xlib.php');
        }

        if (!empty($hostsavailable)) {
            foreach ($hostsavailable as $host) {
                $platform = $DB->get_record('mnet_host', array('id' => $host->platformid));

                if (!preg_match('#'.$CFG->mainhostprefix.'#', $platform->wwwroot)) {
                    // Main platform is usually always enabled.
                    if (is_dir($CFG->dirroot.'/local/vmoodle')) {
                        if (!vmoodle_is_enabled($platform->wwwroot)) {
                            continue;
                        }
                    }
                }

                // If we cant deploy everywhere, we see if we are Remote Course Creator.
                // Then, the access fields are use for further checking.
                if (!has_capability('block/publishflow:deployeverywhere', $systemcontext)) {
                    if (has_capability('block/publishflow:deploy', $coursecontext) ||
                            block_publishflow_extra_deploy_check()) {
                        // Check remotely for each host.
                        $rpcclient = new mnet_xmlrpc_client();
                        $rpcclient->set_method('blocks/publishflow/rpclib.php/publishflow_rpc_check_user');

                        $user = new Stdclass;
                        $user->username = $USER->username;
                        $user->remoteuserhostroot = $userhostroot;
                        $user->remotehostroot = $CFG->wwwroot;

                        $rpcclient->add_param($user, 'struct');
                        $rpcclient->add_param('block/publishflow:deploy', 'string');
                        $rpcclient->add_param('any', 'string'); // Any context remotely.
                        $rpcclient->add_param(true, 'boolean'); // Require json response.
                        $mnethost = new mnet_peer();
                        $mnethost->set_wwwroot($platform->wwwroot);
                        if (!$rpcclient->send($mnethost)) {
                            print_error("Bad RPC request");
                        }
                        $response = json_decode($rpcclient->response);
                        if ($response->status == RPC_SUCCESS) {
                            $deployoptions[$host->platformid] = $platform->name;
                        }
                    }
                } else {
                    $deployoptions[$host->platformid] = $platform->name;
                }
            }
        }

        return $deployoptions;
    }
}