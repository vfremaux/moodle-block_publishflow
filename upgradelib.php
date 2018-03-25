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
 * Implements a result page for driving the deploy
 * transaction.
 * @package blocks_publishflow
 * @category blocks
 */
defined('MOODLE_INTERNAL') || die();

function block_publishflow_add_deployer_role() {
    global $DB;

    $context = context_system::instance();

    /*
     * Create the deployer role if not exists.
     * A Deployer is usually a non editing teacher who can deploy (resp. publish) the course
     * to his authorized targets.
     */
    if (!$DB->record_exists('role', array('shortname' => 'deployer'))) {
        $rolestr = get_string('deployerrole', 'block_publishflow');
        $roledesc = get_string('deployerrole_desc', 'block_publishflow');
        $roleid = create_role($rolestr, 'deployer', str_replace("'", "\\'", $roledesc), '');
        set_role_contextlevels($roleid, array(CONTEXT_COURSE, CONTEXT_COURSECAT, CONTEXT_SYSTEM));
        $nonediting = $DB->get_record('role', array('shortname' => 'teacher'));
        role_cap_duplicate($nonediting, $roleid);
        role_change_permission($roleid, $context, 'block/publishflow:deploy', CAP_ALLOW);
        role_change_permission($roleid, $context, 'block/publishflow:publish', CAP_ALLOW);
        role_change_permission($roleid, $context, 'block/publishflow:retrofit', CAP_ALLOW);
    }
}