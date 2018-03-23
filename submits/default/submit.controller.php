<?php

if (!defined('MOODLE_INTERNAL')) die('You cannot access this script directly.');

/**
* This is the default indexing process that only generates a local randomized
* Unique ID for the course.
*
*/

include_once $CFG->dirroot.'/blocks/publishflow/submitlib.php';

$idnumber = block_publishflow_generate_id();
$step = STEP_COMPLETED;

