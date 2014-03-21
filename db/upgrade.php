<?php

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
        
    return $result;
}

