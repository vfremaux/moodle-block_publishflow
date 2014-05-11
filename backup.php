<?php
  /**
  * 
  * 
  * 
  * 
  */
  
  require_once('../../config.php');
  require_once($CFG->dirroot.'/blocks/publishflow/backup/util/includes/backup_includes.php');
  require_once('backup/backup_automation.class.php');
    
  $course_id = required_param('id',PARAM_INT);

  if(!$course = $DB->get_record('course',array('id'=>$course_id)))
  {
      print_error("invalid course");
  }
  
  $full = "Course backup - ".$course->fullname;
  
  $system_context = context_course::instance($course_id);
  $PAGE->set_context($system_context); 
  $PAGE->set_title($full);

  $PAGE->set_heading($full);
    /* SCANMSG: may be additional work required for $navigation variable */
  $PAGE->set_focuscontrol('');
  $PAGE->set_cacheable(false);
  $PAGE->set_button('');

  $PAGE->set_url('/blocks/publishflow/backup.php');

  print $OUTPUT->header();
  print($OUTPUT->heading("Publishflow - Course Backup"));
  
  print("<div class='pf-backup-step'>Performing course backup .... Please wait</div>");
  
  print("<div class='pf-backup-step'>Testing Automated Backup</div>");
  backup_automation::run_publishflow_coursebackup($course_id);   
  backup_automation::remove_excess_publishflow_backups($course);   

  
  print("<div style='text-align:center;'>");
  print($OUTPUT->single_button($CFG->wwwroot."/course/view.php?id=".$course_id,"Backup Complete"));
  print('</div>');
  
  print $OUTPUT->footer();
  
?>
