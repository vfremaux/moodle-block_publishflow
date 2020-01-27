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

require_once($CFG->dirroot.'/blocks/publishflow/upgradelib.php');

function xmldb_block_publishflow_install_recovery() {
    xmldb_block_publishflow_install();
}

function xmldb_block_publishflow_install() {
    global $USER, $DB, $CFG;

    // We need add a custom role here : disabledstudent.
    // A disabled student still is enrolled within a course, but cannot interfere anymore with content.
    $rolename = get_string('disabledstudentrole', 'block_publishflow');
    $roledesc = get_string('disabledstudentdesc', 'block_publishflow');

    if (!$DB->record_exists('role', ['shortname' => 'disabledstudent'])) {
        if ($newroleid = create_role($rolename, 'disabledstudent', $roledesc)) {
            $standardwritecapsforstudents = array("moodle/calendar:manageownentries",
                    "moodle/calendar:managegroupentries",
                    "moodle/calendar:manageentries",
                    "mod/assignment:submit",
                    "mod/chat:chat",
                    "mod/chat:deletelog",
                    "mod/choice:choose",
                    "mod/choice:deleteresponses",
                    "mod/data:writeentry",
                    "mod/data:comment",
                    "mod/data:rate",
                    "mod/data:approve",
                    "mod/data:manageentries",
                    "mod/data:managecomments",
                    "mod/data:managetemplates",
                    "mod/data:manageuserpresets",
                    "mod/forum:startdiscussion",
                    "mod/forum:replypost",
                    "mod/forum:addnews",
                    "mod/forum:replynews",
                    "mod/forum:rate",
                    "mod/forum:createattachment",
                    "mod/forum:editanypost",
                    "mod/forum:throttlingapplies",
                    "mod/glossary:write",
                    "mod/glossary:manageentries",
                    "mod/glossary:managecategories",
                    "mod/glossary:comment",
                    "mod/glossary:managecomments",
                    "mod/glossary:import",
                    "mod/glossary:approve",
                    "mod/glossary:rate",
                    "mod/lams:participate",
                    "mod/lams:manage",
                    "mod/lesson:edit",
                    "mod/lesson:manage",
                    "mod/quiz:attempt",
                    "mod/quiz:manage",
                    "mod/quiz:preview",
                    "mod/quiz:grade",
                    "mod/quiz:deleteattempts",
                    "mod/scorm:skipview",
                    "mod/scorm:savetrack",
                    "mod/wiki:participate",
                    "mod/wiki:manage",
                    "mod/wiki:overridelock",
                    "mod/workshop:participate",
                    "mod/workshop:manage",
                    "block/rss_client:createprivatefeeds",
                    "block/rss_client:createsharedfeeds",
                    "block/rss_client:manageownfeeds",
                    "block/rss_client:manageanyfeeds");
            foreach ($standardwritecapsforstudents as $writecap) {
                $rolecap = new StdClass;
                $rolecap->roleid = $newroleid;
                $rolecap->context = 1;
                $rolecap->capability = $writecap;
                $rolecap->timemodified = time();
                $rolecap->permission = CAP_PREVENT;
                $rolecap->modifierid = $USER->id;
                $DB->insert_record('role_capabilities', $rolecap);
            }
        }
    }

    set_config('block_publishflow_late_install', 1);
    return true;
}

function xmldb_block_publishflow_late_install() {
    global $USER, $DB;

    // We need to replace the word "block" with word "blocks".
    $rpcs = $DB->get_records('mnet_remote_rpc', array('pluginname' => 'publishflow'));

    if (!empty($rpcs)) {
        foreach ($rpcs as $rpc) {
            $rpc->xmlrpcpath = str_replace('block/', 'blocks/', $rpc->xmlrpcpath);
            $DB->update_record('mnet_remote_rpc', $rpc);
        }
    }

    // We need to replace the word "block" with word "blocks".
    $rpcs = $DB->get_records('mnet_rpc', array('pluginname' => 'publishflow'));

    if (!empty($rpcs)) {
        foreach ($rpcs as $rpc) {
            $rpc->xmlrpcpath = str_replace('block/', 'blocks/', $rpc->xmlrpcpath);
            $DB->update_record('mnet_rpc', $rpc);
        }
    }

    block_publishflow_add_deployer_role();

    return true;
}
