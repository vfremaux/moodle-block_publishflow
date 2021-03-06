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
 * @package    block_publishflow
 * @category   blocks
 * @author     Valery Fremaux (valery.fremaux@edunao.com)
 * @copyright  2008 Valery Fremaux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * Maybe useless redirector.
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/blocks/publishflow/lib.php');

$id = required_param('id', PARAM_INT); // Course.

require_login();
redirect(new moodle_url('/course/view.php', array('id' => $id)));

