<?php

if (!defined('COURSESESSIONS_PRIVATE')){
    define('COURSESESSIONS_PRIVATE', 0);
    define('COURSESESSIONS_PROTECTED', 1);
    define('COURSESESSIONS_PUBLIC', 2);
}

$options = array ('' => get_string('normalmoodle', 'block_publishflow'),
                  'factory' => get_string('factory', 'block_publishflow'),
                  'catalog' => get_string('catalog', 'block_publishflow'),
                  'factory,catalog' => get_string('combined', 'block_publishflow'),
                  'learningarea' => get_string('learningarea', 'block_publishflow')
            );

$settings->add(new admin_setting_configselect('block_publishflow/moodlenodetype', get_string('moodlenodetype', 'block_publishflow'),
                   get_string('configmoodlenodetype', 'block_publishflow'), '', $options));

$settings->add(new admin_setting_configcheckbox('block_publishflow/enableretrofit', get_string('enableretrofit', 'block_publishflow'),
                   get_string('configenableretrofit', 'block_publishflow'), 1));

$settings->add(new admin_setting_configcheckbox('block_publishflow/coursedeliveryislocal', get_string('islocal','block_publishflow'),
            get_string('coursedeliveryislocal','block_publishflow'), 0));

$options2 = array('private' => get_string('cdprivate','block_publishflow'),
          'publicwrite' => get_string('cdpublicwrite','block_publishflow'),
          'publicread' => get_string('cdpublicread', 'block_publishflow')
          );

$settings->add(new admin_setting_configselect('block_publishflow/publicsessions', get_string('publicsessions','block_publishflow'),
            get_string('publicsessions_desc','block_publishflow'), 'private', $options2));


$courses = $DB->get_records_menu('course', null, 'shortname', 'id,shortname');

$categoriesoptions = $DB->get_records_menu('course_categories', null, '', 'id, name');
$categoriesoptions[0] = get_string('leavehere', 'block_publishflow');

$settings->add(new admin_setting_configselect('block_publishflow/deploycategory', get_string('deploycategory','block_publishflow'),
            get_string('deploycategory_desc','block_publishflow'),'',$categoriesoptions));

$settings->add(new admin_setting_configselect('block_publishflow/runningcategory', get_string('runningcategory','block_publishflow'),
            get_string('runningcategory_desc','block_publishflow'),'',$categoriesoptions));

$settings->add(new admin_setting_configselect('block_publishflow/closedcategory', get_string('closedcategory','block_publishflow'),
            get_string('closedcategory_desc','block_publishflow'),'',$categoriesoptions));

$settings->add(new admin_setting_configtext('mainhostprefix', get_string('mainhostprefix','block_publishflow'),
            get_string('mainhostprefix_desc','block_publishflow'),''));

$settings->add(new admin_setting_configtext('factoryprefix', get_string('factoryprefix','block_publishflow'),
            get_string('factoryprefix_desc','block_publishflow'),''));

$systemcontext = context_system::instance();
$roles = role_fix_names(get_all_roles(), $systemcontext, ROLENAME_ORIGINAL);
$rolenames = array();
foreach ($roles as $r) {
    $rolenames[$r->id] = $r->localname;
}
$roleoptions = array_merge(array('0' => get_string('noassignation', 'block_publishflow')), $rolenames);

$settings->add(new admin_setting_configselect('block_publishflow/defaultrole', get_string('defaultrole','block_publishflow'),
            get_string('defaultrole_desc','block_publishflow'), 0, $roleoptions));

$syncstr = get_string('synchonizingnetworkconfig', 'block_publishflow');
$settings->add(new admin_setting_heading('synchronization', get_string('synchonizingnetworkconfig', 'block_publishflow'), "<a href=\"{$CFG->wwwroot}/blocks/publishflow/netupdate.php\">$syncstr</a>"));

$options = array ('' => get_string('noautomatednetworkrefreshment', 'block_publishflow'),
                  DAYSECS => get_string('oneday', 'block_publishflow'),
                  DAYSECS * 7 => get_string('oneweek', 'block_publishflow'),
                  DAYSECS * 30 => get_string('onemonth', 'block_publishflow'),
                  '1' => 'Now (Just for Testing)'
            );

$settings->add(new admin_setting_configselect('block_publishflow/networkrefreshautomation', get_string('networkrefreshautomation', 'block_publishflow'),
                   get_string('networkrefreshautomation', 'block_publishflow'), '', $options));


