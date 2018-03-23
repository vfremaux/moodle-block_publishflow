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

function xmldb_block_publishflow_uninstall() {
    global $DB, $CFG;

    include_once($CFG->dirroot.'/blocks/publishflow/lib.php');
    if ($disabledstudentrole = $DB->get_record('role', array('shortname' => 'disabledstudent'))) {
        $DB->delete_records('role_capabilities', array('roleid' => $disabledstudentrole->id));
        $DB->delete_records('role', array('id' => $disabledstudentrole->id));
        $DB->delete_records('role_assignments', array('roleid' => $disabledstudentrole->id));
        $DB->delete_records('role_allow_assign', array('roleid' => $disabledstudentrole->id));
        $DB->delete_records('role_allow_override', array('roleid' => $disabledstudentrole->id));
        $DB->delete_records('role_allow_assign', array('allowassign' => $disabledstudentrole->id));
        $DB->delete_records('role_allow_override', array('allowoverride' => $disabledstudentrole->id));
    }

    // dismount all XML-RPC
    $service = $DB->get_record('mnet_service', array('name' => 'publishflow'));
    if ($service) {
        $DB->delete_records('mnet_service', array('id' => $service->id));
        $DB->delete_records('mnet_rpc', array('pluginname' => 'publishflow'));
        $DB->delete_records('mnet_remote_rpc', array('pluginname' => 'publishflow'));
        $DB->delete_records('mnet_service2rpc', array('serviceid' => $service->id));
        $DB->delete_records('mnet_remote_service2rpc', array('serviceid' => $service->id));
        $DB->delete_records('mnet_host2service', array('serviceid' => $service->id));
    }

    set_config('block_publishflow_late_install', null);

    return true;
}
