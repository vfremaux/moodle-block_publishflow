<?php

function xmldb_block_publishflow_uninstall(){
    global $DB,$CFG;
    
    include_once $CFG->dirroot.'/blocks/publishflow/lib.php';
    if ($disabledstudentrole = $DB->get_record('role', array('shortname' => 'disabledstudent'))){
        $DB->delete_records('role_capabilities', array('roleid' => $disabledstudentrole->id));
        $DB->delete_records('role', array('id' => $disabledstudentrole->id));
        $DB->delete_records('role_assignments', array('roleid' => $disabledstudentrole->id));
        $DB->delete_records('role_allow_assign', array('roleid' => $disabledstudentrole->id));
        $DB->delete_records('role_allow_override', array('roleid' => $disabledstudentrole->id));
        $DB->delete_records('role_allow_assign', array('allowassign' => $disabledstudentrole->id));
        $DB->delete_records('role_allow_override', array('allowoverride' => $disabledstudentrole->id));
    }
    
    return true;
}
