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
 * @package    block_publishflow
 * @category   blocks
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  2008 valery fremaux (valery.fremaux@gmail.com)
 */
defined('MOODLE_INTERNAL') || die();

/**
 * The component renderer
 */
class block_publishflow_renderer extends plugin_renderer_base {

    /**
     * builds the bloc content in case Moodle has a pure
     * "factory" behaviour
     * @param object $block the block instance
     */
    public function factory_menu($block, $deployoptions) {
        global $CFG, $DB, $USER, $COURSE;

        $template = new StdClass;
        $template->courseid = $COURSE->id;
        $template->blockid = $block->instance->id;
        $coursecontext = context_course::instance($COURSE->id);
        $systemcontext = context_system::instance();

        // We are going to define where the catalog is. There can only be one catalog in the neighbourhood.
        if (!$catalog = $DB->get_record('block_publishflow_catalog', array('type' => 'catalog'))) {
            $notif = get_string('nocatalog', 'block_publishflow');
            $template->nocatalognotif = $this->output->notification($notif, 'notifyproblem', true);
            return $this->output->render_from_template('block_publishflow/factory', $template);
        }
        $mainhost = $DB->get_record('mnet_host', array('id' => $catalog->platformid));

        if (has_capability('block/publishflow:publish', $coursecontext) || block_publishflow_extra_publish_check()) {

            // First check we have backup.
            $template->canpublish = true;
            $realpath = delivery_check_available_backup($COURSE->id);
            if (empty($realpath)) {
                $template->dobackupstr = get_string('dobackup', 'block_publishflow');
                $notif = get_string('unavailable', 'block_publishflow');
                $template->unavailablenotif = $this->output->notification($notif, 'notifyproblem', true);

                $template->formurl = new moodle_url('/blocks/publishflow/backup.php');
                return $this->output->render_from_template('block_publishflow/factory', $template);
            }

            // Check for published status. We use get remote sessions here.
            include_once($CFG->dirroot."/mnet/xmlrpc/client.php");

            // We have to check for sessions in some catalog.
            $rpcclient = new mnet_xmlrpc_client();
            $rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_get_sessions');
            $remoteuserhost = $DB->get_record('mnet_host', array('id' => $USER->mnethostid));
            $caller->username = $USER->username;
            $caller->remoteuserhostroot = $remoteuserhost->wwwroot;
            $caller->remotehostroot = $CFG->wwwroot;
            $rpcclient->add_param($caller, 'struct');
            $rpcclient->add_param($COURSE->idnumber, 'int');
            $rpcclient->add_param(1, 'int');
            $mnethost = new mnet_peer();
            $mnethost->set_wwwroot($mainhost->wwwroot);
            if (!$rpcclient->send($mnethost)) {
                $template->rpcerror = get_string('unavailable', 'block_publishflow');
                if ($CFG->debug) {
                    $notif = 'Publish Status Call Error : ' . implode("\n", $rpcclient->error);
                    $template->rpcdebug = $OUTPUT->notification($notif, 'notifyproblem');
                }
            }

            // Get results and process.
            $sessioninstances = $rpcclient->response;
            $sessions = json_decode($sessioninstances);
            if ($sessions->status == 200) {
                // Check and print publication.
                $published = UNPUBLISHED;
                $visiblesessions = array();
                if (!empty($sessions->sessions)) {
                    foreach ($sessions->sessions as $session) {
                        $published = ($published == UNPUBLISHED) ? PUBLISHED_HIDDEN : $published; // Capture published.
                        if ($session->visible) {
                            $published = PUBLISHED_VISIBLE; // Locks visible.
                            $visiblesessions[] = $session;
                        }
                    }
                }

                // Prepare common options.
                $options['fromcourse'] = $COURSE->id;
                $options['where'] = $mainhost->id;

                switch ($published) {
                    case PUBLISHED_VISIBLE :
                        /*
                         * if a course is already published, we should propose to replace it with a new
                         * volume content. This will be done hiding all previous references to that Learning Path
                         * and installing the new one in catalog.
                         * Older course sessions will not be affected by this, as their own content will not
                         * be changed.
                         * Learning Objects availability is guaranteed by the LOR not being able to discard
                         * validated material.
                         */
                        $template->alreadypublishedstr = get_string('alreadypublished', 'block_publishflow');
                        foreach ($visiblesessions as $session) {
                            $sessiontpl = new StdClass;
                            if ($mainhost->id == $USER->mnethostid) {
                                $sessiontpl->sessionurl = $mainhost->wwwroot.'/course/view.php?id='.$session->id;
                                $sessiontpl->sessionname = format_string($session->fullname);
                            } else {
                                $remotecourseurl = new moodle_url('/course/view.php', array('id' => $session->id));
                                $params = array('hostid' => $mainhost->id, 'wantsurl' => $remotecourseurl);
                                $sessiontpl->sessionurl = new moodle_url('/auth/mnet/jump.php', $params);
                                $sessiontpl->sessionname = format_string($session->fullname);
                            }
                            $template->sessions[] = $sessiontpl;
                        }

                        // Unpublish button.
                        $unpublishstr = get_string('unpublish', 'block_publishflow');
                        $confirm = get_string('unpublishconfirm', 'block_publishflow');
                        $tooltip = get_string('unpublishtooltip', 'block_publishflow');
                        $options['what'] = 'unpublish';
                        $buttonurl = new moodle_url('/blocks/publishflow/publish.php', $options);
                        $button = new single_button($buttonurl, $unpublishstr, 'get');
                        $button->tooltip = $tooltip;
                        $button->add_confirm_action($confirm);
                        $template->unpublishbutton = $OUTPUT->render($button);

                        $btn = get_string('republish', 'block_publishflow');
                        $confirm = get_string('republishconfirm', 'block_publishflow');
                        $tooltip = get_string('republishtooltip', 'block_publishflow');
                        $options['what'] = 'publish';
                        $options['forcerepublish'] = 1;
                        break;

                    case PUBLISHED_HIDDEN : {
                        $output .= get_string('publishedhidden', 'block_publishflow');
                        $btn = get_string('publish', 'block_publishflow');
                        $confirm = get_string('publishconfirm', 'block_publishflow');
                        $tooltip = get_string('publishtooltip', 'block_publishflow');
                        $options['what'] = 'publish';
                        $options['forcerepublish'] = 0;
                        break;
                    }

                    default :
                        // If course is not published, publish it.
                        $output .= get_string('notpublishedyet', 'block_publishflow');
                        $btn = get_string('publish', 'block_publishflow');
                        $confirm = get_string('publishconfirm', 'block_publishflow');
                        $tooltip = get_string('publishtooltip', 'block_publishflow');
                        $options['what'] = 'publish';
                }

                // Make publish form.
                $options['fromcourse'] = $COURSE->id;
                $options['where'] = $mainhost->id;
                $buttonurl = new moodle_url('/blocks/publishflow/publish.php', $options);
                $button = new single_button($buttonurl, $btn, 'get');
                $button->tooltip = $tooltip;
                $button->add_confirm_action($confirm);
                $template->publishbutton = $OUTPUT->render($button);
            } else {
                if ($CFG->debug) {
                    $template->rpcerror = $OUTPUT->notification("Error {$sessions->status} : {$sessions->error}");
                }
            }
        }

        // Add the test deployment target.
        if (($USER->mnethostid != $mainhost->id &&
                $USER->mnethostid != $CFG->mnet_localhost_id) ||
                        has_capability('block/publishflow:deployeverywhere', $systemcontext)) {
            if (has_capability('block/publishflow:deploy', $coursecontext) ||
                    has_capability('block/publishflow:deployeverywhere', $systemcontext)) {

                $template->candeployfortest = true;
                $template->deploystr = get_string('deploy', 'block_publishflow');
                $template->deployforteststr = get_string('deployfortest', 'block_publishflow');
                $template->deployfortesthelpicon = $OUTPUT->help_icon('deployfortest', 'block_publishflow', false);
                $template->formurl = new moodle_url('/blocks/publishflow/deploy.php');

                $attrs = array('id' => 'publishflow-target-select');
                $template->targetselect = html_writer::select($deployoptions, 'where', '', array(), $attrs);

            }
        }

        return $this->output->render_from_template('block_publishflow/factory', $template);
    }

    /**
     * builds the bloc content in case Moodle combines
     * "factory" and "catalog" behaviour. It fits to pure "catalog" situaiton.
     * @param object $block the block instance
     */
    public function catalog_and_factory_menu($block, $deployoptions) {
        global $CFG, $COURSE;

        if (is_dir($CFG->dirroot.'/local/vmoodle')) {
            include_once($CFG->dirroot.'/local/vmoodle/xlib.php');
        }

        $template = new StdClass;

        // First check we have backup.
        $realpath = delivery_check_available_backup($COURSE->id);
        if (empty($realpath)) {
            $template->backup = false;
            $template->dobackupstr = get_string('dobackup', 'block_publishflow');
            $notif = get_string('unavailable', 'block_publishflow');
            $template->unavailablenotif = $this->output->notification($notif, 'notifyproblem', true);
            $template->formurl = new moodle_url('/blocks/publishflow/backup.php');
            $template->courseid = $COURSE->id;
            return $this->output->render_from_template('block_publishflow/catalogfactory', $template);
        }

        $template->backup = true;

        // Make the form and the list.
        $template->formurl = new moodle_url('/blocks/publishflow/deploy.php');
        $template->blockid = $block->instance->id;
        $template->courseid = $COURSE->id;
        $template->choosetargetstr = get_string('choosetarget', 'block_publishflow');

        if (!empty($deployoptions)) {
            $template->hastargets = true;
            $attrs = array('id' => 'publishflow-target-select');
            $template->targetselect = html_writer::select($deployoptions, 'where', '', array(), $attrs);

            if (!empty($block->config->allowfreecategoryselection)) {
                $template->allowfree = true;
                $template->choosecatstr = get_string('choosecat', 'block_publishflow');
            }
            if (!empty($block->config->deploymentkey)) {
                $template->needkey = true;
                $template->enterkeystr = get_string('enterkey', 'block_publishflow');
            }
            $template->deploystr = get_string('deploy', 'block_publishflow');
        } else {
            $template->hastargets = false;
            $template->nodeploytargetsnotif = $this->output->notification(get_string('nodeploytargets', 'block_publishflow'));
        }

        return $this->output->render_from_template('block_publishflow/catalogfactory', $template);
    }

    /**
     * builds the bloc content in case Moodle has a pure
     * "training center" behaviour
     * @param object $block the block instance
     */
    public function trainingcenter_menu() {
        global $DB, $COURSE, $OUTPUT;

        $config = get_config('block_publishflow');

        // Students usually do not see this block.

        $coursecontext = context_course::instance($COURSE->id);

        $template = new StdClass;
        $template->courseid = $COURSE->id;

        // In a learning area, we are refeeding the factory and propose to close the training session.
        if (!empty($config->enableretrofit)) {
            $template->enableretrofit = true;
            $template->retrofittitlestr = get_string('retrofitting', 'block_publishflow');
            $template->retrofithelpicon = $OUTPUT->help_icon('retrofit', 'block_publishflow', false);

            $factoryhost = \block_publishflow::get_factory();

            if (empty($factoryhost)) {
                $template->factory = false;
                $notif = get_string('nofactory', 'block_publishflow');
                $template->nofactorynotif = $OUTPUT->notification($notif, 'notifyproblem', true);

                return $this->output->render_from_template('block_publishflow/trainingcenter', $template);
            } else {
                $template->factory = true;
                $realpath = delivery_check_available_backup($COURSE->id);

                if (empty($realpath)) {
                    $template->backup = false;
                    $template->dobackupstr = get_string('dobackup', 'block_publishflow');
                    $notif = get_string('unavailable', 'block_publishflow');
                    $template->unavailablenotif .= $OUTPUT->notification($notif, 'notifyproblem', true);
                    $template->formurl = new moodle_url('/blocks/publishflow/backup.php');

                    return $this->output->render_from_template('block_publishflow/trainingcenter', $template);
                } else {
                    $template->dobackupstr = get_string('refreshbackup', 'block_publishflow');
                    $template->retrofitstr = get_string('retrofit', 'block_publishflow');
                    // Should be given to entity author marked users.
                    if (has_capability('block/publishflow:retrofit', $coursecontext) ||
                            block_publishflow_extra_retrofit_check()) {
                        $template->canretrofit = true;
                        $params = array('fromcourse' => $COURSE->id, 'what' => 'retrofit', 'where' => $factoryhost->id);
                        $template->retrofiturl = new moodle_url('/blocks/publishflow/retrofit.php', $params);
                    }
                }
            }
        }

        if (!empty($config->enablesessionmanagement)) {
            $template->canmanagesessions = true;
            $template->coursecontrolstr = get_string('coursecontrol', 'block_publishflow');

            /*
             * Should be given to entity trainers (mts)
             * we need also fix the case where all categories are the same
             */
            if (has_capability('block/publishflow:manage', $coursecontext)) {
                if ($COURSE->category == $config->runningcategory && $COURSE->visible) {
                    $template->controlstr = get_string('close', 'block_publishflow');
                    $params = array('fromcourse' => $COURSE->id, 'what' => 'close');
                    $template->controlurl = new moodle_url('/blocks/publishflow/close.php', $params);
                } else if ($COURSE->category == $config->deploycategory) {
                    $template->controlstr = get_string('open', 'block_publishflow');
                    $params = array('fromcourse' => $COURSE->id, 'what' => 'open');
                    $template->controlurl = new moodle_url('/blocks/publishflow/open.php', $params);
                } else if ($COURSE->category == $config->closedcategory) {
                    $template->controlstr = get_string('reopen', 'block_publishflow');
                    $params = array('fromcourse' => $COURSE->id, 'what' => 'open');
                    $template->controlurl = new moodle_url('/blocks/publishflow/open.php', $params);
                }
            }
        }

        return $this->output->render_from_template('block_publishflow/trainingcenter', $template);
    }

    public function ident_form($theblock) {
        global $COURSE;

        $output = '';

        // This is an unregistered course that has no IDNumber reference. This causes a problem for instance identification.
        $output .= $this->output->box_start('noticebox');
        $output .= $this->output->notification(get_string('unregistered', 'block_publishflow'), 'notifyproblem', true);
        // @TODO add help button $output .= $OUTPUT->help_button();
        $qoptions['fromcourse'] = $COURSE->id;
        $qoptions['what'] = 'submit';
        $qoptions['id'] = $theblock->instance->id;
        $output .= '<p>';
        $buttonurl = new moodle_url('/blocks/publishflow/submit.php', $qoptions);
        $output .= $this->output->single_button($buttonurl, get_string('reference', 'block_publishflow'), 'post');
        $output .= '</p>';
        $output .= $this->output->box_end();

        return $output;
    }
}