<?php

function xmldb_block_publishflow_upgrade($oldversion=0) {
/// This function does anything necessary to upgrade 
/// older versions to match current functionality 

    global $CFG;

    $result = true;


// This corresponds to the reworking of publishflow protocols



    if($oldversion < 2010060801){

	include_once $CFG->dirroot.'/blocks/publishflow/rpclib.php';
	$service = get_record('mnet_service', 'name', 'coursedelivery_admin');
	$rpc->function_name = 'publishflow_updateplatforms';
        $rpc->xmlrpc_path = 'blocks/publishflow/rpclib.php/publishflow_updateplatforms';
        $rpc->parent_type = 'block';  
        $rpc->parent = 'publishflow';
        $rpc->enabled = 0; 
        $rpc->help = 'Triggers the status renewal of the platform.';
        $rpc->profile = '';
        if (!$rpcid = insert_record('mnet_rpc', $rpc)){
            notify('Error installing coursedelivery_admin RPC calls.');
            $result = false;
        }
		$rpcmap->serviceid = $service->id;
		$rpcmap->rpcid = $rpcid;
		insert_record('mnet_service2rpc', $rpcmap);
	}

    if($oldversion < 2010060706){

	$table = new XMLDBTable('block_publishflow_catalog');

	    /// Drop it if it existed before
		    drop_table($table, true, false);

	    /// Adding fields to table block_publishflow_catalog
		    $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE,null, null, null);
		    $table->addFieldInfo('platformid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null,null, 0, null);
		    $table->addFieldInfo('lastaccess', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null,null, 0, 0);
		    $table->addFieldInfo('type', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null,null, null, 'unknown');

	    /// Adding keys to table block_publishflow_catalog
		    $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

	    /// Adding indexes to table block_publishflow_catalog
		    $table->addIndexInfo('mdl_hosts', XMLDB_INDEX_NOTUNIQUE, array('platformid'));

	    /// Launch create table for block_publishflow_catalog
		    create_table($table);


	$table = new XMLDBTable('block_publishflow_remotecat');

	    /// Drop it if it existed before
		    drop_table($table, true, false);

	    /// Adding fields to table block_publishflow_remotecat
		    $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE,null, null, null);
		    $table->addFieldInfo('platformid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null,null, 0, null);
		    $table->addFieldInfo('parentid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null,null, 0, null);
		    $table->addFieldInfo('originalid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null,null, 0, null);
		    $table->addFieldInfo('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null,null, null, 'none');

	    /// Adding keys to table block_publishflow_remotecat
		    $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

	    /// Adding indexes to table block_publishflow_remotecat
		    $table->addIndexInfo('mdl_remotecats', XMLDB_INDEX_NOTUNIQUE, array('platformid'));

	    /// Launch create table for block_publishflow_remotecat
		    create_table($table);

    }

//This corresponds to the merge between coursedelivery and publishflow
    if ($oldversion < 2010060603){

	include_once $CFG->dirroot.'/blocks/publishflow/rpclib.php';

	$table = new XMLDBTable('coursedelivery');

	    /// Drop it if it existed before
		    drop_table($table, true, false);

	    /// Adding fields to table coursedelivery
		    $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE,null, null, null);
		    $table->addFieldInfo('course', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null,null, 0, null);
		    $table->addFieldInfo('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null,null, null, 'none');

	    /// Adding keys to table coursedelivery
		    $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

	    /// Adding indexes to table coursedelivery
		    $table->addIndexInfo('mdl_search_docid', XMLDB_INDEX_NOTUNIQUE, array('course'));

	    /// Launch create table for coursedelivery
		    create_table($table);

	    ///Installing coursedelivery hook
		    publishflow_install();
        
        if ($service = get_record('mnet_service', 'name', 'coursedelivery')){

            $rpc->function_name = 'delivery_get_catalog';
            $rpc->xmlrpc_path = 'blocks/publishflow/rpclib.php/delivery_get_catalog';
            $rpc->parent_type = 'block';  
            $rpc->parent = 'coursedelivery';
            $rpc->enabled = 0; 
            $rpc->help = 'Get remote catalog of available courses.';
            $rpc->profile = '';
            if (!$rpcid = insert_record('mnet_rpc', $rpc)){
                notify('Error installing coursedelivery_data RPC calls.');
                $result = false;
            }
            $rpcmap->serviceid = $service->id;
            $rpcmap->rpcid = $rpcid;
            insert_record('mnet_service2rpc', $rpcmap);
        }        
        
        if ($service = get_record('mnet_service', 'name', 'coursedelivery_admin')){

            $rpc->function_name = 'delivery_publish';
            $rpc->xmlrpc_path = 'blocks/publishflow/rpclib.php/delivery_publish';
            $rpc->parent_type = 'block';  
            $rpc->parent = 'publishflow';
            $rpc->enabled = 0; 
            $rpc->help = 'Publishes a course within the Catalog.';
            $rpc->profile = '';
            if (!$rpcid = insert_record('mnet_rpc', $rpc)){
                notify('Error installing coursedelivery_data RPC calls.');
                $result = false;
            }
            $rpcmap->serviceid = $service->id;
            $rpcmap->rpcid = $rpcid;
            insert_record('mnet_service2rpc', $rpcmap);
        }        
    }
        
    if ($oldversion < 2010102803){
        if ($service = get_record('mnet_service', 'name', 'coursedelivery_admin')){

            $rpc->function_name = 'delivery_rpc_check_user';
            $rpc->xmlrpc_path = 'blocks/publishflow/rpclib.php/publishflow_rpc_check_user';
            $rpc->parent_type = 'block';  
            $rpc->parent = 'publishflow';
            $rpc->enabled = 0; 
            $rpc->help = 'Checkes a user\'s remote capability.';
            $rpc->profile = '';
            if (!$rpcid = insert_record('mnet_rpc', addslashes_object($rpc))){
                notify('Error installing publishflow_rpc_check_user RPC calls.');
                $result = false;
            }
            $rpcmap->serviceid = $service->id;
            $rpcmap->rpcid = $rpcid;
            insert_record('mnet_service2rpc', $rpcmap);
        }        
    }        

    if ($oldversion < 2011031500){
        if ($service = get_record('mnet_service', 'name', 'coursedelivery_admin')){

            $rpc->function_name = 'delivery_rpc_close_course';
            $rpc->xmlrpc_path = 'blocks/publishflow/rpclib.php/publishflow_rpc_close_course';
            $rpc->parent_type = 'block';  
            $rpc->parent = 'publishflow';
            $rpc->enabled = 0; 
            $rpc->help = 'Checkes a user\'s remote capability.';
            $rpc->profile = '';
            if (!$rpcid = insert_record('mnet_rpc', addslashes_object($rpc))){
                notify('Error installing publishflow_rpc_close_course RPC calls.');
                $result = false;
            }
            $rpcmap->serviceid = $service->id;
            $rpcmap->rpcid = $rpcid;
            insert_record('mnet_service2rpc', $rpcmap);
        }        
    }        
        
    return $result;
}

?>
