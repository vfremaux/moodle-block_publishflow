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
 * Manage backup files
 * @package   moodlecore
 * @copyright 2010 Dongsheng Cai <dongsheng@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

class pf_files_edit_form extends moodleform {

    public function definition() {
        $mform =& $this->_form;

        $contextid = $this->_customdata['contextid'];
        $options = array('subdirs' => 0, 'maxfiles' => -1, 'accepted_types' => '*', 'return_types' => FILE_INTERNAL);

        $mform->addElement('filemanager', 'files_filemanager', get_string('files'), null, $options);

        $mform->addElement('hidden', 'id', $this->_customdata['courseid']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'contextid', $this->_customdata['contextid']);
        $mform->setType('contextid', PARAM_INT);

        $mform->addElement('hidden', 'filearea', $this->_customdata['filearea']);
        $mform->setType('filearea', PARAM_ALPHA);

        $mform->addElement('hidden', 'component', $this->_customdata['component']);
        $mform->setType('component', PARAM_ALPHANUM);

        $mform->addElement('hidden', 'returnurl', $this->_customdata['returnurl']);
        $mform->setType('returnurl', PARAM_URL);

        $this->add_action_buttons(true, get_string('savechanges'));
        $this->set_data($this->_customdata['data']);
    }
}
