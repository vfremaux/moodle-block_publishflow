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
 * a hack of choose_from_menu, adding multiple capability
 *
 * Given an array of values, output the HTML for a select element with those options.
 * Normally, you only need to use the first few parameters.
 *
 * @param array $options The options to offer. An array of the form
 *      $options[{value}] = {text displayed for that option};
 * @param string $name the name of this form control, as in &lt;select name="..." ...
 * @param array $selected the array of options to select initially, default none.
 * @param int $size gives the size for the list
 * @param string $nothing The label for the 'nothing is selected' option. Defaults to get_string('choose').
 *      Set this to '' if you don't want a 'nothing is selected' option.
 * @param string $script in not '', then this is added to the &lt;select> element as an onchange handler.
 * @param string $nothingvalue The value corresponding to the $nothing option. Defaults to 0.
 * @param boolean $return if false (the default) the the output is printed directly, If true, the
 *      generated HTML is returned as a string.
 * @param boolean $disabled if true, the select is generated in a disabled state. Default, false.
 * @param int $tabindex if give, sets the tabindex attribute on the &lt;select> element. Default none.
 * @param string $id value to use for the id attribute of the &lt;select> element. If none is given,
 *      then a suitable one is constructed.
 */
defined('MOODLE_INTERNAL') || die();

if (!function_exists('choose_from_menu_multiple')) {

    function choose_from_menu_multiple($options, $name, $selected = null, $size = 5, $nothing = 'choose', $script = '',
                                       $nothingvalue = '0', $return = false, $disabled = false, $tabindex = 0, $id = '') {
        if ($nothing == 'choose') {
            $nothing = get_string('choose').'...';
        }
        $attributes = ($script) ? 'onchange="'. $script .'"' : '';
        if ($disabled) {
            $attributes .= ' disabled="disabled"';
        }
        if ($tabindex) {
            $attributes .= ' tabindex="'.$tabindex.'"';
        }
        if (!$selected) {
            $selected = array();
        }
        if ($id === '') {
            $id = 'menu'.$name;
             // Name may contain [], which would make an invalid id. e.g. numeric question type editing form, assignment quickgrading.
            $id = str_replace('[', '', $id);
            $id = str_replace(']', '', $id);
        }
        $output = '<select multiple="multiple" size="'.$size.'" id="'.$id.'" name="'. $name .'" '. $attributes .'>' . "\n";
        if ($nothing) {
            $output .= '   <option value="'. s($nothingvalue) .'"'. "\n";
            if ($nothingvalue === $selected) {
                $output .= ' selected="selected"';
            }
            $output .= '>'. $nothing .'</option>' . "\n";
        }
        if (!empty($options)) {
            foreach ($options as $value => $label) {
                $output .= '   <option value="'. s($value) .'"';
                $selectedarr = (!is_array($selected)) ? array($selected) : $selected;
                if (in_array((string)$value, $selectedarr)) {
                    $output .= ' selected="selected"';
                }
                if ($label === '') {
                    $output .= '>'. $value .'</option>' . "\n";
                } else {
                    $output .= '>'. $label .'</option>' . "\n";
                }
            }
        }
        $output .= '</select>' . "\n";
        if ($return) {
            return $output;
        } else {
            echo $output;
        }
    }
}
