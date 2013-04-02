<?php 
    //This script is used to configure and execute the backup proccess in a learning path context.

    require_once ('../../config.php');
    require_once ($CFG->dirroot.'/backup/lib.php');
    require_once ($CFG->dirroot.'/backup/backuplib.php');
    require_once ($CFG->libdir.'/blocklib.php');
    require_once ($CFG->libdir.'/adminlib.php');
    require_once ($CFG->dirroot.'/blocks/publishflow/lib.php');

    $id         = optional_param('id', null, PARAM_INT);       // course id
    $cancel     = optional_param('cancel');
    $launch     = optional_param('launch');

    if (!empty($id)) {
        require_login($id);
        if (!has_capability('moodle/site:backup', get_context_instance(CONTEXT_COURSE, $id))) {
            error("You need to be a teacher or admin user to use this page.", $CFG->wwwroot.'/login/index.php');
        }
    }

    //Check site
    if (!$site = get_site()) {
        error('Site not found!');
    }

    //Check necessary functions exists. Thanks to gregb@crowncollege.edu
    backup_required_functions();

    //Check backup_version
    if ($id) {
        $linkto = "backup.php?id=".$id.((!empty($to)) ? '&amp;to='.$to : '');
    } else {
        $linkto = "backup.php";
    }
    upgrade_backup_db($linkto);

    //Get strings
    if (empty($to)) {
        $strcoursebackup = get_string('coursebackup');
    } else {
        $strcoursebackup = get_string('importdata');
    }
    $stradministration = get_string('administration');

    //Get and check course
    if (! $course = get_record('course', 'id', $id)) {
        error('Course ID was incorrect (can\'t find it)');
    }

    print_header(get_string('autobackup', 'block_publishflow'), get_string('autobackup', 'block_publishflow'), $SITE->fullname, array());

    // $PAGE->print_tabs('backup');

    //Print form
    print_container_start(true, 'emptyleftspace');
    
    print_heading(format_string("$strcoursebackup: $course->fullname ($course->shortname)"));
    print_simple_box_start('center');

    //Call the form, depending the step we are
    if (!$launch) {
        // if we're at the start, clear the cache of prefs
        if (isset($SESSION->backupprefs[$course->id])) {
            unset($SESSION->backupprefs[$course->id]);
        }

// TODO use form api 

// START BACKUP FORM //
?>
<form id="form1" method="post" action="/blocks/publishflow/backup.php">
<table cellpadding="5" style="margin-left:auto;margin-right:auto;">
</table>
<?php
    $backup_unique_code = time();
    $backup_name = backup_get_zipfile_name($course, $backup_unique_code);
?>
<div style="text-align:center;margin-left:auto;margin-right:auto">
<input type="hidden" name="backup_course_file" value="1">
<input type="hidden" name="id"     value="<?php  p($id) ?>" />
<input type="hidden" name="to"     value="<?php p($to) ?>" />
<input type="hidden" name="backup_unique_code" value="<?php p($backup_unique_code); ?>" />
<input type="hidden" name="backup_name" value="<?php p($backup_name); ?>" />
<input type="hidden" name="launch" value="check" />
<input type="submit" value="<?php  print_string('continue') ?>" />
<input type="submit" name="cancel" value="<?php  print_string('cancel') ?>" />
</div>
</form>
<?php
// END BACKUP FORM //

    } else if ($launch == 'check') {


    $backupprefs = new StdClass;
    $count = 0;
    backup_fetch_prefs_from_request($backupprefs, $count, $course);

    if ($count == 0) {
        notice('No backupable modules are installed!');
    }

    $sql = "
        DELETE FROM 
            {$CFG->prefix}backup_ids 
        WHERE 
            backup_code = '{$backupprefs->backup_unique_code}'
    ";
    if (!execute_sql($sql, false)){
        error('Couldn\'t delete previous backup ids.');
    }
?>
<form id="form" method="post" action="/blocks/publishflow/backup.php">
<table cellpadding="5" style="text-align:center;margin-left:auto;margin-right:auto">
<?php
    if (empty($to)) {
        //Now print the Backup Name tr
        echo "<tr>";
        echo "<td align=\"right\"><b>";
        echo get_string("name").":";
        echo "</b></td><td>";
        //Add as text field
        echo "<input type=\"text\" name=\"backup_name\" size=\"40\" value=\"" . $backupprefs->backup_name . "\" />";
        echo "</td></tr>";

        //Line
        echo "<tr><td colspan=\"2\"><hr /></td></tr>";

        //Now print the To Do list
        echo "<tr>";
        echo "<td colspan=\"2\" align=\"center\"><b>";

    }
?>
</table>
<div style="text-align:center;margin-left:auto;margin-right:auto">
<input type="hidden" name="to"     value="<?php p($to) ?>" />
<input type="hidden" name="id"     value="<?php  p($id) ?>" />
<input type="hidden" name="launch" value="execute" />
<input type="submit" value="<?php  print_string('continue') ?>" />
<input type="submit" name="cancel" value="<?php  print_string('cancel') ?>" />
</div>
</form>
<?php

        //include_once("backup_check.html");
    } else if ($launch == 'execute') {
        global $preferences;
        global $SESSION;
        
        // force preference values
        
        $SESSION->backupprefs[$course->id] = publishflow_backup_generate_preferences($course);        
                    
        include_once($CFG->dirroot.'/blocks/publishflow/backup_execute.html');
    }

    print_simple_box_end();
    print_container_end();
    print_footer($course);
    die;

?>
