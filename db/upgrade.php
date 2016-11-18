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
 * Implements a result page for driving the deploy 
 * transaction.
 * @package blocks_publishflow
 * @category blocks
 */

function xmldb_block_publishflow_upgrade($oldversion=0) {
/// This function does anything necessary to upgrade 
/// older versions to match current functionality 

    global $CFG, $DB;

    $result = true;

// Moodle 2 -- Upgrade break

    $dbman = $DB->get_manager();

    if ($result && $oldversion < 2014031900) {
    
    /// Define field sortorder to be added to publishflow
        $table = new xmldb_table('block_publishflow_remotecat');
        $field = new xmldb_field('sortorder');
        $field->set_attributes(XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'name');

    /// Launch add field parent
        $result = $result || $dbman->add_field($table, $field);

        /// customlabel savepoint reached
        upgrade_block_savepoint($result, 2014031900, 'publishflow');
    }

    if ($result && $oldversion < 2016031900) {

        $configs = array(
            'moodlenodetype' => 'moodlenodetype',
            'enableretrofit' => 'enableretrofit',
            'coursedeliveryislocal' => 'coursedeliveryislocal',
            'coursedelivery_publicsessions' => 'publicsessions',
            'coursedelivery_deploycategory' => 'deploycategory',
            'coursedelivery_runningcategory' => 'runningcategory',
            'coursedelivery_closedcategory' => 'closedcategory',
            'coursedelivery_defaultrole' => 'defaultrole',
            'coursedelivery_networkrefreshautomation' => 'networkrefreshautomation',
        );

        foreach ($configs as $oldconf => $newconf) {
            if (!empty($oldconf)) {
                set_config($newconf, @$CFG->$oldconf, 'block_publishflow');
                set_config($oldconf, null);
            }
        }

        upgrade_block_savepoint(true, 2016031900, 'publishflow');
    }

    return $result;
}

