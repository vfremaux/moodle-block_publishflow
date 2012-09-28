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
 * minimalistic edit form
 *
 * @package   block_private_files
 * @copyright 2010 Petr Skoda (http://skodak.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

class block_publishflow_edit_form extends block_edit_form {
    function specific_definition($mform) {
        global $CFG,$DB;
      

        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));
        $mform->addElement('checkbox', 'config_allowfreecategoryselection', get_string('allowfreecategoryselection', 'block_publishflow'));
        if (preg_match('/\\bcatalog\\b/', $CFG->moodlenodetype)){   
            $mform->addElement('text', 'config_deploymentkeydesc', get_string('deploymentkeydesc', 'block_publishflow'));   
        }
    }
}
