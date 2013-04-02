ck <?php
require_once('../../../config.php');
require_once ('restore_automation.class.php');


$path = 'D:/wamp/ent2moodledata/sub1/temp/backup/backup-moodle2-course-metameta-20120917-1716-nu.mbz';
restore_automation::run_automated_restore(null,$path,1);
                              
                                
?>
