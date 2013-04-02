<?php
/**
* This file contains a class used for full course restore automation.
* 
* @Author Wafa Adham ,wafa@adham.ps
* @copyright 
* 
*/

require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');


class restore_automation {

    /**
    * given a stored backup file, this function creates course from
    * this backup automatically . 
    * @param mixed $backup_file_id backup file id
    * @param mixed $course_category_id  destination restore category.
    */
    public static function run_automated_restore($backup_file_id=null,$file_path=null,$course_category_id){
        global $CFG,$DB,$USER;    
        $fs = get_file_storage();
        
        if(!$backup_file_id && !$file_path)
        {
            print_error("invalid backup file");
        }
       
        if($file_path != null)
        {
            $array = split("/",$file_path);
            $file_name= array_pop($array);
            
            $file_rec = new stdClass();
            $file_rec->contextid = 1;
            $file_rec->component = 'backup';
            $file_rec->filearea = 'publishflow';
            $file_rec->itemid = 0;
            $file_rec->filename = $file_name;
            $file_rec->filepath = "/";
            
            //try load the file 
            $file = $fs->get_file($file_rec->contextid ,   $file_rec->component,   $file_rec->filearea,   $file_rec->itemid,   $file_rec->filepath,   $file_rec->filename);
            if($file)
            {
                $file->delete();
            }
            
            $file  = $fs->create_file_from_pathname($file_rec,$file_path); 
        }
        else
        {
            $file = $fs->get_file_by_id($backup_file_id);
         
            if(empty($file))
            {
                print_error("backup file does not exist.");
            }
        }
        //copy file to temp place .
        $tempfile = $CFG->dataroot."/temp/backup/".$file->get_filename();
        $result = $file->copy_content_to($tempfile);

        //start by extracting the file to temp dir .
        $tempdir= $CFG->dataroot."/temp/backup/".$file->get_contenthash();

        //create temp directory
        if(!file_exists($tempdir))
        {           
            if(!mkdir($tempdir))
            {
                print_error("could'nt create backup temp directory. operation faild.");
            } 
        }
      

        $fp = get_file_packer('application/zip');
        $unzipresult = $fp->extract_to_pathname($CFG->dataroot."/temp/backup/".$file->get_filename(), $tempdir);

        //test category exists
        $cat = $DB->get_record('course_categories',array('id'=>$course_category_id));
        
        if(!$cat)
        {
            print_error("Invalid destination category");
        }
        
        //create the base course.
        $data->fullname = "Course restore in progress...";
        $data->shortname= "course_shortname".(rand(0,293736));
        $data->category = $course_category_id;    
        //create the base course 
        $course = create_course($data);
           
        $rc = new restore_controller($file->get_contenthash(), $course->id, backup::INTERACTIVE_NO,
                                        backup::MODE_GENERAL, $USER->id,backup::TARGET_NEW_COURSE);

        $rc->set_status(backup::STATUS_AWAITING);                                
        $rc->execute_plan(); 
        $results = $rc->get_results();    
        
        //cleanup
        unlink($tempfile); 
      
         return $course->id;
    }    
    
}
?>