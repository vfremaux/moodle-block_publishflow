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
 * Controls publication/deployment of courses in a
 * distributed moodle configuration.
 *
 * @package block_publishflow
 * @category blocks
 * @author Valery Fremaux (valery.fremaux@club-internet.fr)
 * @author Wafa Adham (admin@adham.ps)
 */
defined('MOODLE_INTERNAL') || die();

if (!defined('COURSESESSIONS_PRIVATE')) {
    define('COURSESESSIONS_PRIVATE', 0);
    define('COURSESESSIONS_PROTECTED', 1);
    define('COURSESESSIONS_PUBLIC', 2);
}

$options = array ('normalmoodle' => get_string('normalmoodle', 'block_publishflow'),
                  'factory' => get_string('factory', 'block_publishflow'),
                  'catalog' => get_string('catalog', 'block_publishflow'),
                  'factory,catalog' => get_string('combined', 'block_publishflow'),
                  'learningarea' => get_string('learningarea', 'block_publishflow')
            );

$key = 'block_publishflow/moodlenodetype';
$label = get_string('configmoodlenodetype', 'block_publishflow');
$desc = get_string('configmoodlenodetype_desc', 'block_publishflow');
$default = 'normalmoodle';
$settings->add(new admin_setting_configselect($key, $label, $desc, $default, $options));

$key = 'block_publishflow/enableretrofit';
$label = get_string('configenableretrofit', 'block_publishflow');
$desc = get_string('configenableretrofit_desc', 'block_publishflow');
$default = 1;
$settings->add(new admin_setting_configcheckbox($key, $label, $desc, $default));

$key = 'block_publishflow/enablesessionmanagement';
$label = get_string('configenablesessionmanagement', 'block_publishflow');
$desc = get_string('configenablesessionmanagement_desc', 'block_publishflow');
$default = 1;
$settings->add(new admin_setting_configcheckbox($key, $label, $desc, $default));

$key = 'block_publishflow/coursedeliveryislocal';
$label = get_string('configcoursedeliveryislocal', 'block_publishflow');
$desc = get_string('configcoursedeliveryislocal_desc', 'block_publishflow');
$default = 0;
$settings->add(new admin_setting_configcheckbox($key, $label, $desc, $default));

$options2 = array('private' => get_string('cdprivate', 'block_publishflow'),
    'publicwrite' => get_string('cdpublicwrite', 'block_publishflow'),
    'publicread' => get_string('cdpublicread', 'block_publishflow')
);

$key = 'block_publishflow/publicsessions';
$label = get_string('configpublicsessions', 'block_publishflow');
$desc = get_string('configpublicsessions_desc', 'block_publishflow');
$default = 'private';
$settings->add(new admin_setting_configselect($key, $label, $desc, $default, $options2));

require_once($CFG->dirroot.'/lib/coursecatlib.php');
$catlist = coursecat::make_categories_list();

$key = 'block_publishflow/deploycategory';
$label = get_string('configdeploycategory', 'block_publishflow');
$desc = get_string('configdeploycategory_desc', 'block_publishflow');
$settings->add(new admin_setting_configselect($key, $label, $desc, '', $catlist));

$key = 'block_publishflow/runningcategory';
$label = get_string('configrunningcategory', 'block_publishflow');
$desc = get_string('configrunningcategory_desc', 'block_publishflow');
$catlist2 = $catlist;
$catlist2[0] = get_string('leavehere', 'block_publishflow');
$settings->add(new admin_setting_configselect($key, $label, $desc, '', $catlist2));

$key = 'block_publishflow/closedcategory';
$label = get_string('configclosedcategory', 'block_publishflow');
$desc = get_string('configclosedcategory_desc', 'block_publishflow');
$settings->add(new admin_setting_configselect($key, $label, $desc, '', $catlist2));

// This is a site level setting that is shared with other components (vmoodle).
$key = 'mainhostprefix';
$label = get_string('configmainhostprefix', 'block_publishflow');
$desc = get_string('configmainhostprefix_desc', 'block_publishflow');
$settings->add(new admin_setting_configtext($key, $label, $desc, ''));

$key = 'block_publishflow/factoryprefix';
$label = get_string('configfactoryprefix', 'block_publishflow');
$desc = get_string('configfactoryprefix_desc', 'block_publishflow');
$settings->add(new admin_setting_configtext($key, $label, $desc, ''));

$systemcontext = context_system::instance();
$roles = role_fix_names(get_all_roles(), $systemcontext, ROLENAME_ORIGINAL);
$rolenames = array();
foreach ($roles as $r) {
    $rolenames[$r->id] = $r->localname;
}
$roleoptions = array_merge(array('0' => get_string('noassignation', 'block_publishflow')), $rolenames);

$key = 'block_publishflow/defaultrole';
$label = get_string('configdefaultrole', 'block_publishflow');
$desc = get_string('configdefaultrole_desc', 'block_publishflow');
$settings->add(new admin_setting_configselect($key, $label, $desc, 0, $roleoptions));

$key = 'block_publishflow/deployprofilefield';
$label = get_string('configdeployprofilefield', 'block_publishflow');
$desc = get_string('configdeployprofilefield_desc', 'block_publishflow');
$settings->add(new admin_setting_configtext($key, $label, $desc, ''));

$key = 'block_publishflow/deployprofilefieldvalue';
$label = get_string('configdeployprofilefieldvalue', 'block_publishflow');
$desc = get_string('configdeployprofilefieldvalue_desc', 'block_publishflow');
$settings->add(new admin_setting_configtext($key, $label, $desc, ''));

$str = get_string('validatescript', 'block_publishflow');
$label = get_string('scriptconfig', 'block_publishflow');
$validateurl = new moodle_url('/blocks/publishflow/scriptvalidate.php');
$html = '<a href="'.$validateurl.'" target="_blank">'.$str.'</a>';
$settings->add(new admin_setting_heading('scriptinghdr', $label, $html));

$key = 'block_publishflow/postprocessing';
$label = get_string('configpostprocessing', 'block_publishflow');
$desc = get_string('configpostprocessing_desc', 'block_publishflow');
$settings->add(new admin_setting_configtextarea($key, $label, $desc, ''));

$str = get_string('synchonizingnetworkconfig', 'block_publishflow');
$label = get_string('synchonizingnetworkconfig', 'block_publishflow');
$updateurl = new moodle_url('/blocks/publishflow/netupdate.php');
$html = '<a href="'.$updateurl.'" target="_blank">'.$str.'</a>';
$settings->add(new admin_setting_heading('synchronizationhdr', $label, $html));
