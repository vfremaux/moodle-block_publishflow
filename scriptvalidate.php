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
require('../../config.php');

require_once($CFG->dirroot.'/blocks/publishflow/lib.php');
require_once($CFG->dirroot.'/local/moodlescript/classes/engine/parser.class.php');

$url = new moodle_url('/blocks/publishflow/scriptvalidate.php');

require_login();

$context = context_system::instance();
$PAGE->set_url($url);
$PAGE->set_context($context);

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('scriptvalidator', 'block_publishflow'));

$config = get_config('block_publishflow');

if (!empty($config->postprocessing)) {

    echo $OUTPUT->heading(get_string('script', 'block_publishflow'), 3);
    echo '<pre>';
    echo $config->postprocessing;
    echo '</pre>';

    $parser = new \local_moodlescript\engine\parser($config->postprocessing);
    $parser->parse();

    echo $OUTPUT->heading(get_string('validationresult', 'block_publishflow'), 3);
    echo '<pre>';
    echo $parser->print_errors();
    echo '</pre>';

    echo $OUTPUT->heading(get_string('stack', 'block_publishflow'), 3);
    echo '<pre>';
    echo $parser->print_stack();
    echo '</pre>';

    echo $OUTPUT->heading(get_string('trace', 'block_publishflow'), 3);
    echo '<pre>';
    echo $parser->print_trace();
    echo '</pre>';
} else {
    echo $OUTPUT->notification(get_string('noscript', 'block_publishflow'));
}

$buttonurl = new moodle_url('/admin/settings.php', array('section' => 'block_publishflow'));
$label = get_string('backsettings', 'block_publishflow');
echo $OUTPUT->single_button($buttonurl, $label);

echo $OUTPUT->footer();
