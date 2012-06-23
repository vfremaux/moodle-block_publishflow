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
	  	$catmenu = get_records('course_categories', 'parent', 0, 'id', 'id,name');	
	
	   	foreach($catmenu as $cat){
			$catentry = new stdClass;
			$catentry->orid = $cat->id;
			$catentry->name = $cat->name;
			$catresults[] = $catentry;
		
			// If the node isn't a leaf, we go deeper.
			if ($subcats = get_records('course_categories', 'parent', $cat->id, 'id','id,name')){
			    foreach($subcats as $sub){
			    
				  	$catentry = new stdClass;
				  	$catentry->orid = $sub->id;
				  	$catentry->name = '- - '.$sub->name;
				  	$catresults[] = $catentry;
				  
					// We won't go any deeper, as each level increases the select width
				  	if ($subcats2 = get_records('course_categories', 'parent', $sub->id, 'id', 'id,name')){
				    	foreach($subcats2 as $sub){
						  	$catentry = new stdClass;
						  	$catentry->orid = $sub->id;
						  	$catentry->name = '- - - - '.$sub->name;
						  	$catresults[] = $catentry;
				    	}				
					}
			    }		
			}
		}
	} else {
		// get local image of remote known categories		
		$catresults = array();
		publishflow_get_remote_categories($platformid, $catresults, 0, 3);
	}

/// We return the value to the Ajax.

	echo(json_encode($catresults));

?>