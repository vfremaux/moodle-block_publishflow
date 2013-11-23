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
 * This file contains the mnet services for the user_mnet_host plugin
 *
 * @since 2.0
 * @package blocks
 * @subpackage vmoodle
 * @copyright 2012 Valery Fremaux
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * Moodle 2.2 Conversion By Wafa Adham
 */

 
  $publishes = array(
    'publishflow' => array(
        'servicename' => 'publishflow',
        'description' => get_string('publishflow_name', 'block_publishflow'),
        'apiversion' => 1,
        'classname'  => '',
        'filename'   => 'rpclib.php',
        'methods'    => array(
            'publishflow_rpc_deploy',
            'publishflow_rpc_deploy_wrapped',
            'publishflow_rpc_course_exists',
            'publishflow_rpc_course_exists_wrapped',
            'publishflow_rpc_open_course',
            'publishflow_rpc_open_course_wrapped',
            'publishflow_rpc_close_course',
            'publishflow_rpc_close_course_wrapped',
            'publishflow_updateplatforms',
            'delivery_get_sessions',
            'delivery_deliver',
            'delivery_deploy',
            'delivery_publish'
        ),
    ),
);

$subscribes = array(
    'publishflow' => array(
        'publishflow_rpc_deploy' => 'blocks/publishflow/rpclib.php/publishflow_rpc_deploy',
        'publishflow_rpc_deploy_wrapped' => 'blocks/publishflow/rpclib.php/publishflow_rpc_deploy_wrapped',
        'publishflow_rpc_course_exists' => 'blocks/publishflow/rpclib.php/publishflow_rpc_course_exists',
        'publishflow_rpc_course_exists_wrapped' => 'blocks/publishflow/rpclib.php/publishflow_rpc_course_exists_wrapped',
        'publishflow_rpc_open_course' => 'blocks/publishflow/rpclib.php/publishflow_rpc_open_course',
        'publishflow_rpc_open_course_wrapped' => 'blocks/publishflow/rpclib.php/publishflow_rpc_open_course',
        'publishflow_rpc_close_course' => 'blocks/publishflow/rpclib.php/publishflow_rpc_close_course',
        'publishflow_rpc_close_course_wrapped' => 'blocks/publishflow/rpclib.php/publishflow_rpc_close_course_wrapped',
        'publishflow_updateplatforms' => 'blocks/publishflow/rpclib.php/publishflow_updateplatforms',
        'delivery_get_sessions' => 'blocks/publishflow/rpclib.php/delivery_get_sessions',
        'delivery_deliver' => 'blocks/publishflow/rpclib.php/delivery_deliver',
        'delivery_deploy' => 'blocks/publishflow/rpclib.php/delivery_deploy',
        'delivery_publish' => 'blocks/publishflow/rpclib.php/delivery_publish'
    ),
);
