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
 * Forum external functions and service definitions.
 *
 * @package    block_use_stats
 * @copyright  2017 Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$functions = array(

    'block_publishflow_publish' => array(
        'classname' => 'block_publishflow_external',
        'methodname' => 'publish',
        'classpath' => 'blocks/publishflow/externallib.php',
        'description' => 'Publishes a course archive',
        'type' => 'write',
        'capabilities' => 'block/publishflow:publish'
    ),

    'block_publishflow_deploy' => array(
        'classname' => 'block_publishflow_external',
        'methodname' => 'deploy',
        'classpath' => 'blocks/publishflow/externallib.php',
        'description' => 'Deploys a course archive to a learning target',
        'type' => 'read',
        'capabilities' => array('block/publishflow:deploy','block/publishflow:deployeverywhere')
    ),

    'block_publishflow_check_user_access' => array(
        'classname' => 'block_publishflow_external',
        'methodname' => 'check_user_access',
        'classpath' => 'blocks/publishflow/externallib.php',
        'description' => 'Checks the remote access capability of a user',
        'type' => 'read',
    ),

    'block_publishflow_check_course_exists' => array(
        'classname' => 'block_publishflow_external',
        'methodname' => 'check_course_exists',
        'classpath' => 'blocks/publishflow/externallib.php',
        'description' => 'Checks if a course exists remotely',
        'type' => 'read',
    ),

    'block_publishflow_open_course' => array(
        'classname' => 'block_publishflow_external',
        'methodname' => 'open_course',
        'classpath' => 'blocks/publishflow/externallib.php',
        'description' => 'Remotely opens a course for teaching',
        'type' => 'write',
    ),

    'block_publishflow_close_course' => array(
        'classname' => 'block_publishflow_external',
        'methodname' => 'close_course',
        'classpath' => 'blocks/publishflow/externallib.php',
        'description' => 'Remotely closes a course for teaching',
        'type' => 'write',
    ),

);
