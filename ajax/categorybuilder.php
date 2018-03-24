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
 * This the page that will search the host catalog, process it
 * and finally return the results.
 *
 * It is to be used by the Ajax from the publishflow block.
 *
 * @package    block_publishflow
 * @category   blocks
 * @author Edouard Poncelet
 * @author Valery Fremaux (valery.fremaux@gmail.com)
 * @author Wafa Adham (admin@adham.ps)
 * @copyright 2008 onwards Valery Fremaux (http://www.myLearningFactory.com)
 *
 */
require('../../../config.php');
require_once($CFG->dirroot.'/blocks/publishflow/lib.php');
$platformid = required_param('platformid', PARAM_INT);

require_login();

/*
 * We are going to need to reconstruct the categories tree.
 * But, to limit the width of the select list, we will not go under a depth of 3
 * This means we are in "local" mode, so we need to check the right table
 */

if ($platformid == 0) {
    $catresults = array();

    $catmenu = $DB->get_records('course_categories', array('parent' => 0), 'id', 'id,name');
    foreach ($catmenu as $cat) {
        add_local_category_results($catresults, $cat);
    }
} else {
    // Get local image of remote known categories.
    $catresults = array();
    publishflow_get_remote_categories($platformid, $catresults, 0, 3);
}

// We return the value to the Ajax.

echo json_encode($catresults);

function add_local_category_results(& $catresults, $cat) {
    global $DB;

    static $indent = '';

    $catentry = new stdClass;
    $catentry->orid = $cat->id;
    $catentry->name = $indent.$cat->name;
    $catresults[] = $catentry;
    // If the node isn't a leaf, we go deeper.
    if ($subcats = $DB->get_records('course_categories', array('parent' => $cat->id), 'sortorder', 'id,name')) {
        foreach ($subcats as $sub) {
            $indent = $indent.'- ';
            add_local_category_results($catresults, $sub);
            $indent = preg_replace('/$- /', '', $indent);
        }
    }
}
