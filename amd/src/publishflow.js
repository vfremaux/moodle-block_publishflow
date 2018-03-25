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
 * Javascript controller for controlling the sections.
 *
 * @module     block_publishflow/publishflow
 * @package    blocks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// jshint unused: true, undef:true
define(['jquery', 'core/log'], function ($, log) {

    var publishflow = {

        init: function() {
            $('#publishflow-target-select').bind('change', this.feed_categories);

            log.debug("AMD publishflow initialized");
        },

        feed_categories: function() {

            var that = $(this);

            var platformid = 0 + this.options[this.selectedIndex].value;

            var url = M.cfg.wwwroot + '/blocks/publishflow/ajax/categorybuilder.php';

            $.post(
                url, { platformid: platformid }, function(data) {

                    var catArray = JSON.parse(data, null);

                    var html = '<select  name="category"  size="1">';
                    for (var i = 0; i < catArray.length; i++) {
                        html = html + '<option value=' + catArray[i].orid + '>' + catArray[i].name + '</option>';
                    }
                    html = html + '</select>';
                    $('#category-div').html(html);
                },
                'json'
            );
        }
    };

    return publishflow;
});