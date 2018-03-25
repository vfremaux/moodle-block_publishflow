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

function block_publishflow_postscript($datacontext) {
    global $DB, $USER, $CFG, $SITE;

    $config = get_config('block_publishflow');

    if (!empty($config->postprocessing)) {
        if (function_exists('debug_trace')) {
            debug_trace($CFG->wwwroot." Loading moodlescript");
        }
        include_once($CFG->dirroot.'/local/moodlescript/lib.php');
        include_once($CFG->dirroot.'/local/moodlescript/classes/engine/parser.class.php');

        // Building a global context.
        $globalcontext = new StdClass;
        $globalcontext->courseid = $datacontext->newcourseid;
        $globalcontext->config = $config;
        if ($datacontext->callinguser['remoteuserhostroot'] != $CFG->wwwroot) {
            $globalcontext->userwwwroot = ''.$datacontext->callinguser['remoteuserhostroot'];
            $globalcontext->userhostname = $DB->get_field('mnet_host', 'name', array('wwwroot' => $datacontext->callinguser['remoteuserhostroot']));
        } else {
            $globalcontext->userwwwroot = $CFG->wwwroot;
            $globalcontext->userhostname = $SITE->fullname;
        }
        $globalcontext->username = $USER->username;
        $globalcontext->userfullname = fullname($USER);

        // Make a postprocessing parser, parse postprocessing script and execute the resulting stack.
        $parser = new \local_moodlescript\engine\parser($config->postprocessing);
        $stack = $parser->parse((array)$globalcontext);

        if ($parser->has_errors()) {
            if (function_exists('debug_trace')) {
                if ($CFG->debug = DEBUG_DEVELOPER) {
                    debug_trace($CFG->wwwroot." Parsed trace : ".$parser->print_trace());
                }
                debug_trace($CFG->wwwroot." Parsed stack errors : ".$parser->print_errors());
            }
            $report = $parser->print_errors();
            $report .= "\n".$parser->print_stack();
            return $report;
        }

        if (function_exists('debug_trace')) {
            debug_trace($CFG->wwwroot." Parsed stack :\n ".$parser->print_stack());
        }

        $result = $stack->check((array)$globalcontext);
        if ($stack->has_errors()) {
            if (function_exists('debug_trace')) {
                if ($CFG->debug = DEBUG_DEVELOPER) {
                    debug_trace($CFG->wwwroot." Check warnings : ".$stack->print_log('warnings'));
                    debug_trace($CFG->wwwroot." Check errors : ".$stack->print_log('errors'));
                }
            }
            return $stack->print_log('errors');
        }

        $result = $stack->execute((array)$globalcontext);

        if (function_exists('debug_trace')) {
            if ($stack->has_errors()) {
                // If the engine is robust enough. There should be not...
                debug_trace($CFG->wwwroot." Stack errors : ".$stack->print_log('warnings'));
                debug_trace($CFG->wwwroot." Stack errors : ".$stack->print_log('errors'));
            }
        }
        if (function_exists('debug_trace')) {
            if ($CFG->debug = DEBUG_DEVELOPER) {
                debug_trace($CFG->wwwroot." Stack execution log : ".$stack->print_log('log'));
            }
        }

        // Everyting ok.
        return false;
    }
}
