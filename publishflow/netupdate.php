<?php
	/**
	 * This page will be used to manually trigger the platforms catalog update_record
	 * This is an administration page and it's load is heavy.
	 *
	 * @author Edouard Poncelet
	 * @package block-publishflow
	 * @category blocks
	 *
	 **/

	require_once('../../config.php');
    global $CFG;

	$full = get_string('single_full','block_publishflow');
	$short = get_string('single_short','block_publishflow');

	$navlinks[] = array('name' => $full, 'link' => "$CFG->wwwroot", 'type' => 'misc');
	$navigation = build_navigation($navlinks);
	print_header($full, $short, $navigation, '', '', false, '');

	print_box(get_string('warningload','block_publishflow'));

	$hosts = get_records_select('mnet_host', " deleted = 0 AND wwwroot != '$CFG->wwwroot' ");

	// Building divs
	$diverror = '<div="Error" style="background-color:#FF0000">';
	$divwarning = '<div="Warning" style="background-color:#FFFF33">';
	$divok = '<div="OK" style="background-color:#99FF00">';
	$enddiv = '</div>';

	$warnings = 0;
	$errors = 0;

	// Creating the table
	$table->tablealign = 'center';
    $table->cellpadding = 5;
    $table->cellspacing = 0;
    $table->width = '60%';
	$table->size = array('30%','30%','30%');
    $table->head = array(get_string('platformname', 'block_publishflow'), get_string('platformstatus','block_publishflow'),get_string('platformlastupdate','block_publishflow'));
    $table->wrap = array('nowrap', 'nowrap','nowrap');
    $table->align = array('center', 'center','center');

	$id = 0;

	//We need to check each host
  	foreach($hosts as $host){

		// ignore non moodles
		if ($host->applicationid != get_field('mnet_application', 'id', 'name', 'moodle')) continue;

    	$name = $host->name;
		if(!($host->name) == "" && !($host->name == "All Hosts")){
			//If we don't find errors, we are OK.
			$divstatus = $divok;
			$divtime = $divok;

			//If we have no record, we create it
			if(!$catalogrecord = get_record('block_publishflow_catalog','platformid',$host->id)){
				$newcatalog = array('platformid' => $host->id);
				insert_record('block_publishflow_catalog', $newcatalog);
				$catalogrecord = get_record('block_publishflow_catalog','platformid',$host->id);
			}

			//If the host has no real type, it's an error
			if($catalogrecord->type == 'unknown'){
			 	$divstatus = $diverror;
			 	$errors++;
		  	}

		// No last access is an error
			if(empty($catalogrecord->lastaccess)){
				$divtime = $diverror;
				$errors++;
				$catalogrecord->lastaccess = time();
			}

		// Too old record is a warning
			elseif($catalogrecord->lastaccess < time()- (14 * 24 * 60 * 60)){
				$divtime = $divwarning;
				$warnings++;
			}
	
			$table->data[$id][0] = $name;
			$table->data[$id][1] = $divstatus.$catalogrecord->type.$enddiv;
			$table->data[$id][2] = $divtime.date('d-m-Y  G:i:s', $catalogrecord->lastaccess).$enddiv;
			$id++;
		 
		}
	}
	print_table($table);
	echo('<br/>');

	if(!($errors == 0)){
		print_box(get_string('errormonitoring','block_publishflow').'<br/>'.get_string('erroradvice','block_publishflow', $errors));
	} elseif(!($warnings == 0)) {
		print_box(get_string('errormonitoring','block_publishflow').'<br/>'.get_string('warningadvice','block_publishflow', $warnings));
	} else {
		print_box(get_string('OKmonitoring','block_publishflow'));
	}

    echo('<center>');
    print_single_button('/blocks/publishflow/doupgrade.php','',get_string('perform','block_publishflow'),'','','','','',get_string('confirmupgrade','block_publishflow'));
    echo('</center>');

    echo('<br/>');

    print_footer($COURSE);

