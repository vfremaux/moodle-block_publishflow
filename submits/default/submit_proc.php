<?php

	/**
	* this is a procedure manager handling all steps for submitting.
	*
	*/

    if ($step == STEP_INITIAL){
        $step == STEP_COMPLETED;
        $result = include($CFG->dirroot.'/blocks/publishflow/submit.controller.php');        
    }

