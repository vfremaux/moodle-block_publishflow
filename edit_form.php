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
 * minimalistic edit form
 *
 * @package    block_publishflow
 * @category   blocks
 * @author     Valery Fremaux (valery.fremaux@edunao.com)
 * @copyright  2008 Valery Fremaux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/formslib.php");

class block_publishflow_edit_form extends block_edit_form {

    public function specific_definition($mform) {

        $config = get_config('block_publishflow');

        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('checkbox', 'config_allowfreecategoryselection', get_string('allowfreecategoryselection', 'block_publishflow'));
        $mform->setType('config_allowfreecategoryselection', PARAM_BOOL);

        if (preg_match('/\\bcatalog\\b/', $config->moodlenodetype)) {
            $mform->addElement('text', 'config_deploymentkeydesc', get_string('deploymentkeydesc', 'block_publishflow'));   
            $mform->setType('config_deploymentkeydesc', PARAM_TEXT);
        }
    }
}
