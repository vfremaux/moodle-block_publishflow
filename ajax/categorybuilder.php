<?php

	/**
	 * This the page that will search the host catalog, process it
	 * and finally return the results.
	 *
	 * It is to be used by the Ajax from the publishflow block.
	 *
	 * @author Edouard Poncelet
	 *
	 **/
	require_once '../../../config.php';
	require_once $CFG->dirroot.'/blocks/publishflow/lib.php';
	$platformid = required_param('platformid', PARAM_INT);
	// We are going to need to reconstruct the categories tree.
	// But, to limit the width of the select list, we will not go under a depth of 3
	//This means we are in "local" mode, so we need to check the right table
	
    if ($platformid == 0){
    	
    	$catresults = array();
      
	  	$catmenu = $DB->get_records('course_categories', array('parent' => 0), 'id', 'id,name');	
	   	foreach($catmenu as $cat){
	    	add_local_category_results($catresults, $cat);
		}
	} else {
		// get local image of remote known categories		
		$catresults = array();
		publishflow_get_remote_categories($platformid, $catresults, 0, 3);
	}

/// We return the value to the Ajax.

	echo(json_encode($catresults));

	function add_local_category_results(& $catresults, $cat){
		global $DB;
		
		static $indent = ''; 
	
		$catentry = new stdClass;
		$catentry->orid = $cat->id;
		$catentry->name = $indent.$cat->name;
		$catresults[] = $catentry;
		// If the node isn't a leaf, we go deeper.
		if ($subcats = $DB->get_records('course_categories', array('parent' => $cat->id), 'sortorder', 'id,name')){
		    foreach($subcats as $sub){
		    	$indent = $indent.'- ';
		    	add_local_category_results($catresults, $sub);
		    	$indent = preg_replace('/$- /', '', $indent);
		    }
		}
	}

