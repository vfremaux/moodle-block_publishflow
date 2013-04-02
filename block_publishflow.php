<?php //$Id: block_publishflow.php,v 1.25 2012-05-24 21:36:27 vf Exp $

/**
* Controls publication/deployment of courses in a 
* distributed moodle configuration.
*
* @package block-publishflow
* @category blocks
* @author Valery Fremaux (valery.fremaux@club-internet.fr)
*
*/

/**
* Includes and requires
*/
include_once $CFG->dirroot.'/blocks/publishflow/rpclib.php';
include_once $CFG->dirroot.'/blocks/publishflow/lib.php';
include_once $CFG->libdir.'/pear/HTML/AJAX/JSON.php';

/**
* Constants
*/

define('UNPUBLISHED', 0);
define('PUBLISHED_HIDDEN', 1);
define('PUBLISHED_VISIBLE', 2);

/**
* the standard override of common moodle block Class
*/
class block_publishflow extends block_base {

    function init() {
        global $CFG;
                
        if (preg_match('/\\bmain\\b/', @$CFG->moodlenodetype))
            $this->title = get_string('deployname', 'block_publishflow');
        elseif (@$CFG->moodlenodetype == 'factory')
            $this->title = get_string('publishname', 'block_publishflow');
        elseif (@$CFG->moodlenodetype == 'learningarea')
            $this->title = get_string('managename', 'block_publishflow');
        elseif (@$CFG->moodlenodetype == 'factory,catalog')
            $this->title = get_string('combinedname', 'block_publishflow');
        else
            $this->title = get_string('blockname', 'block_publishflow');
        $this->content_type = BLOCK_TYPE_TEXT;
        $this->version = 2011031500;
    }

    function applicable_formats() {
        return array('course' => true, 'site' => false, 'all' => false);
    }

    function specialization() {
    	if (!empty($this->config)){
	    	$this->config_save($this->config);
	    }
    }

    function has_config() {
    	return true;
    }

    function instance_allow_multiple() {
        return false;
    }

    function instance_allow_config() {
        return true;
    }

    /**
    *
    */
    function user_can_addto($page) {
        global $CFG, $COURSE;

        $context = get_context_instance(CONTEXT_COURSE, $COURSE->id);
        if (has_capability('block/publishflow:addtocourse', $context)){
        	return true;
        }
        return false;
    }


    // Apart from the constructor, there is only ONE function you HAVE to define, get_content().
    // Let's take a walkthrough! :)

    function get_content() {
        // We intend to use the $CFG global variable
        global $CFG, $COURSE, $USER, $VMOODLES, $MNET;

		require_once('lib.php');
		require_once($CFG->libdir.'/pear/HTML/AJAX/JSON.php');
        
        $context_system = get_context_instance(CONTEXT_SYSTEM);
        $context_course = get_context_instance(CONTEXT_COURSE, $COURSE->id);

        if($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        
        if(!has_any_capability(array('block/publishflow:retrofit', 'block/publishflow:deploy', 'block/publishflow:publish'), $context_course) && !has_capability('block/publishflow:deployeverywhere', $context_system, $USER->id, false)){
        	$this->content->text = '';
        	$this->content->footer = '';
        	return $this->content;
        }

        // Making bloc content

        if($COURSE->idnumber){

	        /*** PURE FACTORY ****/

            if ($CFG->moodlenodetype == 'factory'){

			   //We are going to define where the catalog is. There can only be one catalog in the neighbourhood.
				if(@$CFG->moodlenodetype == 'factory,catalog'){
				      $mainhost = get_record('mnet_host','wwwroot',$CFG->wwwroot);
				} else {
					if (!$catalog = get_record('block_publishflow_catalog','type','catalog')){
		                $this->content->text .= notify(get_string('nocatalog','block_publishflow'), 'notifyproblem', 'center', true);
		                $this->content->footer .= '';
		                return;
			      	}
		      		$mainhost = get_record('mnet_host', 'id', $catalog->platformid);
				}
	
	            if (has_capability('block/publishflow:publish', $context_course)||block_publishflow_extra_publish_check()){
	
	                // first check we have backup                    
	                $realpath = delivery_check_available_backup($COURSE->id);
	                
		            if (empty($realpath)){
		                $dobackupstr = get_string('dobackup', 'block_publishflow');
		                $this->content->text .= notify(get_string('unavailable','block_publishflow'), 'notifyproblem', 'center', true);
		                $this->content->text .= "<center>";
		                $this->content->text .= "<form name=\"makebackup\" action=\"{$CFG->wwwroot}/blocks/publishflow/backup.php\" method=\"GET\">";
		                $this->content->text .= "<input type=\"hidden\" name=\"id\" value=\"{$COURSE->id}\" />";
		                $this->content->text .= "<input type=\"submit\" name=\"go_btn\" value=\"$dobackupstr\" />";
		                $this->content->text .= "</form>";
		                $this->content->text .= "</center>";
		                $this->content->footer = '';
		                return;
		            }
	
					$this->content->text .= '<center>';
	                // check for published status. We use get remote sessions here
	                
	                include_once $CFG->dirroot."/mnet/xmlrpc/client.php";
	
	                if (!$MNET){
	                    $MNET = new mnet_environment();
	                    $MNET->init();
	                }
	                    
	                // We have to check for sessions in some catalog.
	                // note 
	                $rpcclient = new mnet_xmlrpc_client();
	                $rpcclient->set_method('blocks/publishflow/rpclib.php/delivery_get_sessions');
	                $remoteuserhost = get_record('mnet_host', 'id', $USER->mnethostid);
	                $caller->username = $USER->username;
	                $caller->remoteuserhostroot = $remoteuserhost->wwwroot;
	                $caller->remotehostroot = $CFG->wwwroot;
	                $rpcclient->add_param($caller, 'struct');
	                $rpcclient->add_param($COURSE->idnumber, 'int');
	                $rpcclient->add_param(1, 'int');
	
	                $mnet_host = new mnet_peer();
	                $mnet_host->set_wwwroot($mainhost->wwwroot);
	                if (!$rpcclient->send($mnet_host)){
	                    $this->content->text .= get_string('unavailable', 'block_publishflow');
	                    if ($CFG->debug){
	                        notify('Publish Status Call Error : ' . implode("\n", $rpcclient->error), 'notifyproblem', 'center', false);
	                    }
	                }
	
	                // get results and process
	                // print_object($rpcclient->response);
	                
	                $sessioninstances = $rpcclient->response;
	                $sessions = json_decode($sessioninstances);
	                if ($sessions->status == 200){
	                    
	                    // check and print publication
	                    $published = UNPUBLISHED;
	                    $visiblesessions = array();
	                    
	                    if (!empty($sessions->sessions)){
	                        foreach($sessions->sessions as $session){
	                            $published = ($published == UNPUBLISHED) ? PUBLISHED_HIDDEN : $published ; // capture published
	                            if ($session->visible) { 
	                                $published = PUBLISHED_VISIBLE; // locks visible
	                                $visiblesessions[] = $session;
	                            }
	                        }
	                    }
	
	                    // prepare common options
	                    $options['fromcourse'] = $COURSE->id;
	                    $options['where'] = $mainhost->id;
	
	                    switch ($published){
	                        case PUBLISHED_VISIBLE : {
	
	                            // if a course is already published, we should propose to replace it with a new
	                            // volume content. This will be done hiding all previous references to that Learning Path
	                            // and installing the new one in catalog. 
	                            // Older course sessions will not be affected by this, as their own content will not
	                            // be changed.
	                            // Learning Objects availability is guaranteed by the LOR not being able to discard
	                            // validated material. 
	
	                            $this->content->text .= get_string('alreadypublished','block_publishflow');
	                            
	                            foreach($visiblesessions as $session){
	                                $courseurl = urlencode('/course/view.php?id='.$session->id);
	                                if ($mainhost->id == $USER->mnethostid){
	                                    $this->content->text .= "<li><a href=\"{$mainhost->wwwroot}/course/view.php?id={$session->id}\">{$session->fullname}</a></li>"; 
	                                } else {
	                                    $this->content->text .= "<li><a href=\"{$CFG->wwwroot}/auth/mnet/jump.php?hostid={$mainhost->id}&amp;wantsurl={$courseurl}\">{$session->fullname}</a></li>"; 
	                                }
	                            }
	                            
	                            // unpublish button
	                            $btn = get_string('unpublish', 'block_publishflow');
	                            $confirm = get_string('unpublishconfirm', 'block_publishflow');
	                            $tooltip = get_string('unpublishtooltip', 'block_publishflow');
	                            $options['what'] = 'unpublish';
	                            $this->content->text .= print_single_button('/blocks/publishflow/publish.php', $options, $btn, 'get', '_self', true, $tooltip, false, $confirm);
	
	                            $btn = get_string('republish', 'block_publishflow');
	                            $confirm = get_string('republishconfirm', 'block_publishflow');
	                            $tooltip = get_string('republishtooltip', 'block_publishflow');
	                            $options['what'] = 'publish';
	                            $options['forcerepublish'] = 1;
	                        }
	                        break;
	                        case PUBLISHED_HIDDEN : {
	                            $this->content->text .= get_string('publishedhidden','block_publishflow');
	                            $btn = get_string('publish', 'block_publishflow');
	                            $confirm = get_string('publishconfirm', 'block_publishflow');
	                            $tooltip = get_string('publishtooltip', 'block_publishflow');
	                            $options['what'] = 'publish';
	                            $options['forcerepublish'] = 0;
	                        }
	                        break ;
	                        default : {
	                            // if course is not published, publish it                                
	                            $this->content->text .= get_string('notpublishedyet','block_publishflow');
	                            $btn = get_string('publish', 'block_publishflow');
	                            $confirm = get_string('publishconfirm', 'block_publishflow');
	                            $tooltip = get_string('publishtooltip', 'block_publishflow');
	                            $options['what'] = 'publish';
	                        }
	                    }
	
	                    // make publish form
	                    $options['fromcourse'] = $COURSE->id;
	                    $options['where'] = $mainhost->id;
	                    $this->content->text .= print_single_button('/blocks/publishflow/publish.php', $options, $btn, 'get', '_self', true, $tooltip, false, $confirm);
						$this->content->text .= '<hr/></center>';
	                } else {
	                    if ($CFG->debug){
	                        notify("Error {$sessions->status} : {$sessions->error}");
	                    }
	                }
	            }
	
		        // Add the test deployment target	
		        if (($USER->mnethostid != $mainhost->id && $USER->mnethostid != $CFG->mnet_localhost_id) || has_capability('block/publishflow:deployeverywhere', $context_system)){
	
			    	if(has_capability('block/publishflow:deploy', $context_course) || has_capability('block/publishflow:deployeverywhere', $context_system)){
						require_js (array('yui_yahoo','yui_event','yui_connection'));
						require_js ($CFG->wwwroot.'/blocks/publishflow/js/block_js.js');
	
						$hostsavailable = get_records('block_publishflow_catalog','type','learningarea');
						$fieldsavailable = get_records_select('user_info_field','shortname like \'access%\'');
	                    $userhost = get_record('mnet_host', 'id', $USER->mnethostid);
	                    $deploy_btn = get_string('deploy', 'block_publishflow');
						$this->content->text .= '<h3>'.get_string('deployfortest', 'block_publishflow').' '.helpbutton('deployfortest', get_string('deployfortest', 'block_publishflow'), 'block_publishflow', true, false, '', true).'</h3>';
						$this->content->text .= '<form name="deployform" method="post" action="/blocks/publishflow/deploy.php">';
						$this->content->text .= '<div class="selector" align="center">';
	                    $this->content->text .= "<input type=\"hidden\" name=\"id\" value=\"{$this->instance->id}\" />";
	                    $this->content->text .= "<input type=\"hidden\" name=\"fromcourse\" value=\"{$COURSE->id}\" />";
	                    $this->content->text .= "<input type=\"hidden\" name=\"what\" value=\"deploy\" />";
						$this->content->text .= "<select id=\"publishflow-target-select\" name=\"where\" size=\"1\" onchange=\"doStuff(this, '{$CFG->wwwroot}');\">";
						$this->content->text .= "<option value='0' selected='selected'>".get_string('defaultplatform', 'block_publishflow')."</option>";
	
						$accessfields = get_records_select('user_info_field', ' shortname LIKE "access%" ');
				
						foreach($hostsavailable as $host){
							$platform = get_record('mnet_host', 'id', $host->platformid);
				      		//If we cant deploy everywhere, we see if we are Remote Course Creator. Then, the access fields are use for further checking
				      		if (!has_capability('block/publishflow:deployeverywhere', $context_system)){
					  			if(has_capability('block/publishflow:deploy', $context_system)){
					      			if($accessfields){
						      			foreach($accessfields as $field){
	
											//We don't need to check if the user doesn't have the required field
											if($userallowedfields = get_record('user_info_data', 'userid', $USER->id, 'fieldid', $field->id)){
	
								    			//We get the host prefix corresponding to the host
								    			preg_match('/http:\/\/([^.]*)/', $platform->wwwroot, $matches);
								    			$hostprefix = $matches[1];
								    			$hostprefix = strtoupper($hostprefix);
								    
											    //We try to match it to the field
											    if (preg_match('/access'.$hostprefix.'/', $field->shortname)){							    
													$this->content->text .='<option value='.$host->platformid.' > '.$platform->name.' </option>';
								    			}
											}
						      			}
									}
					  			}
				      		} else {
					  			$this->content->text .='<option value='.$host->platformid.' > '.$platform->name.' </option>';
					  		}
				    	}
				  		$this->content->text .= "</select>";
				  		$this->content->text .= "</div>";
	
				  		//Creating the second list that will be populated by the Ajax Script
				  		$this->content->text .= '<div class="selector" id="category" align="center"></div>';
				  		$this->content->text .= "<input type=\"button\" name=\"deploy\" value=\"$deploy_btn\" onclick=\"document.forms['deployform'].submit();\" align=\"center\" />";
				  		$this->content->text .= '</form>';
				  		$this->content->text .= "<script type=\"text/javascript\">window.onload=doStuff(0, '{$CFG->wwwroot}');</script>";
	                }
			    }
	        } 
	        
	        /*** CATALOG OR CATALOG & FACTORY ****/

	        elseif (preg_match('/\\bcatalog\\b/', $CFG->moodlenodetype)) {
	
	            /// propose deployment on authorized satellites
	            // first check we have backup                    
	            $realpath = delivery_check_available_backup($COURSE->id);
	                
	            if (empty($realpath)){
	                $dobackupstr = get_string('dobackup', 'block_publishflow');
	                $this->content->text .= notify(get_string('unavailable','block_publishflow'), 'notifyproblem', 'center', true);
	                $this->content->text .= '<center>';
	                $this->content->text .= "<form name=\"makebackup\" action=\"{$CFG->wwwroot}/blocks/publishflow/backup.php\" method=\"GET\">";
	                $this->content->text .= "<input type=\"hidden\" name=\"id\" value=\"{$COURSE->id}\" />";
	                $this->content->text .= "<input type=\"submit\" name=\"go_btn\" value=\"$dobackupstr\" />";
	                $this->content->text .= '</form>';
	                $this->content->text .= '</center>';
	                $this->content->footer = '';
	                return;
	            }
	
				if(has_capability('block/publishflow:deploy', $context_course)||has_capability('block/publishflow:deployeverywhere', $context_system)||block_publishflow_extra_deploy_check()){
					require_js (array('yui_yahoo', 'yui_event', 'yui_connection'));
					require_js ($CFG->wwwroot.'/blocks/publishflow/js/block_js.js');
	
					$hostsavailable = get_records('block_publishflow_catalog', 'type', 'learningarea');
					$fieldsavailable = get_records_select('user_info_field', 'shortname like \'access%\'');
	                $userhost = get_record('mnet_host', 'id', $USER->mnethostid);
				
					if(has_capability('block/publishflow:deploy',$context_course)||block_publishflow_extra_deploy_check()){
						$deployoptions['0'] = get_string('defaultplatform', 'block_publishflow');
					}
	
					$userhostroot = get_field('mnet_host', 'wwwroot', 'id', $USER->mnethostid);
				
					if (!empty($hostsavailable)){
						foreach($hostsavailable as $host){
							$platform = get_record('mnet_host', 'id', $host->platformid);
					      	//If we cant deploy everywhere, we see if we are Remote Course Creator. Then, the access fields are used for further checking
					      	if (!has_capability('block/publishflow:deployeverywhere', $context_system)){
						  		if(has_capability('block/publishflow:deploy', $context_course) || block_publishflow_extra_deploy_check()){
						  			// check remotely for each host
								  	$rpcclient = new mnet_xmlrpc_client();
								    $rpcclient->set_method('blocks/publishflow/rpclib.php/publishflow_rpc_check_user');
								    $user->username = $USER->username;
								    $user->remoteuserhostroot = $userhostroot;
								    $user->remotehostroot = $CFG->wwwroot;
								    $rpcclient->add_param($user, 'struct');
								    $rpcclient->add_param('block/publishflow:deploy', 'string');
								    
								    $mnet_host = new mnet_peer();
								    $mnet_host->set_wwwroot($platform->wwwroot);
								    if (!$rpcclient->send($mnet_host)){
								        print_object($rpcclient);
								        error(get_string('failed', 'block_publishflow').'<br/>'.implode("<br/>\n", $rpcclient->error));
								    }
								
								    $response = json_decode($rpcclient->response);
								    if ($response->status = RPC_SUCCESS);
									$deployoptions[$host->platformid] = $platform->name;
						  		}
					      	} else {
								$deployoptions[$host->platformid] = $platform->name;
						  	}
					    }
					}

					// make the form and the list
					$this->content->text .= '<form name="deployform" method="post" action="/blocks/publishflow/deploy.php">';
					$this->content->text .= '<div class="selector" align="center">';
	                $this->content->text .= "<input type=\"hidden\" name=\"id\" value=\"{$this->instance->id}\" />";
	                $this->content->text .= "<input type=\"hidden\" name=\"fromcourse\" value=\"{$COURSE->id}\" />";
	                $this->content->text .= "<input type=\"hidden\" name=\"what\" value=\"deploy\" />";

					$this->content->text .= get_string('choosetarget', 'block_publishflow');

					if (!empty($deployoptions)){
						$this->content->text .= "<select id=\"publishflow-target-select\" name=\"where\" size=\"1\" onchange=\"doStuff(this, '{$CFG->wwwroot}');\">";
						foreach($deployoptions as $key => $option){
							$this->content->text .= "<option value='{$key}'>$option</option>";
						}
					  	$this->content->text .= '</select>';

					  	//Creating the second list that will be populated by the Ajax Script
					  	if (@$this->config->allowfreecategoryselection){
							$this->content->text .= get_string('choosecat', 'block_publishflow');
						  	$this->content->text .= '<div class="selector" id="category" align="center"></div>';
					  		$this->content->text .= "<script type=\"text/javascript\">window.onload=doStuff(0, '{$CFG->wwwroot}');</script>";
						}
	
					  	if (!empty($this->config->deploymentkey)){
							$this->content->text .= '<br/>';
							$this->content->text .= get_string('enterkey', 'block_publishflow');
					  		$this->content->text .= "<input type=\"password\" name=\"deploykey\" value=\"\" size=\"8\" maxlength=\"10\" />";
					  	}

					  	$this->content->text .= "<input type=\"checkbox\" name=\"force\" value=\"1\" checked=\"checked\" /> ";
					  	$this->content->text .=  get_string('forcecache', 'block_publishflow');
					  	$this->content->text .=  helpbutton('forcecache', get_string('forcecache', 'block_publishflow'), 'block_publishflow', 1, 0, '', 1);
					  	
		                $deploy_btn = get_string('deploy', 'block_publishflow');
					  	$this->content->text .= "<input type=\"button\" name=\"deploy\" value=\"$deploy_btn\" onclick=\"document.forms['deployform'].submit();\" align=\"center\"/>";
					  	$this->content->text .= '</div>';
					  	$this->content->text .= '</form>';
					} else {
						$this->content->text .= '<div>'.get_string('nodeploytargets', 'block_publishflow').'</div>';
					}
				}

	        /*** TRAINING CENTER ****/

	        } else {

				// students usually do not see this block
				if (!has_capability('block/publishflow:retrofit', $context_course) && !has_capability('block/publishflow:manage', $context_course) && !block_publishflow_extra_retrofit_check()){
	            	$this->content->text = '';
	            	$this->content->footer = '';
	            	return $this->content;
				}

	            // in a learning area, we are refeeding the factory and propose to close the training session
	            if (!empty($CFG->enableretrofit)){                    

					$retrofitstr = get_string('retrofitting', 'block_publishflow');
					$retrofithelpstr = helpbutton('retrofit', get_string('retrofit', 'block_publishflow'), 'block_publishflow', true, false, '', true);
					$this->content->text .= "<b>$retrofitstr</b>$retrofithelpstr<br/>";
	            	$this->content->text .= '<center>';
					// try both strategies, using the prefix directly in mnethosts or the catalog records
					// there should be only one factiry. The first in the way will be considered
					// further records will be ignored
					$factoriesavailable = get_records_select('block_publishflow_catalog'," type LIKE '%factory%' ");
					
					// alternative strategy
					if (!$factoriesavailable){
		                $select = (!empty($CFG->factoryprefix)) ? " wwwroot LIKE 'http://{$CFG->factoryprefix}%' " : '' ;
		                $factoryhost = get_record_select('mnet_host', $select);
					} else {
						$factory = array_pop($factoriesavailable);
		                $factoryhost = get_record('mnet_host', 'id', $factory->platformid);
					}

	                if (!$factoryhost){
	                    $this->content->text .= notify(get_string('nofactory', 'block_publishflow'), 'notifyproblem', 'center', true);
	                } else {
		                $realpath = delivery_check_available_backup($COURSE->id);

			            if (empty($realpath)){
			                $dobackupstr = get_string('dobackup', 'block_publishflow');
			                $this->content->text .= notify(get_string('unavailable','block_publishflow'), 'notifyproblem', 'center', true);
			                $this->content->text .= "<center>";
			                $this->content->text .= "<form name=\"makebackup\" action=\"{$CFG->wwwroot}/blocks/publishflow/backup.php\" method=\"GET\">";
			                $this->content->text .= "<input type=\"hidden\" name=\"id\" value=\"{$COURSE->id}\" />";
			                $this->content->text .= "<input type=\"submit\" name=\"go_btn\" value=\"$dobackupstr\" />";
			                $this->content->text .= "</form>";
			                $this->content->text .= "</center>";
			                $this->content->footer = '';
			                return;		                
		                } else {
			                $strretrofit = get_string('retrofit', 'block_publishflow');
			                // should be given to entity author marked users
			                if (has_capability('block/publishflow:retrofit', $context_course) || block_publishflow_extra_retrofit_check()){
			                    $this->content->text .= "<a href=\"{$CFG->wwwroot}/blocks/publishflow/retrofit.php?fromcourse={$COURSE->id}&amp;what=retrofit&amp;where={$factoryhost->id}\">$strretrofit</a><br/><br/>";
			                }
			            }
		            }
	            }

				if (($COURSE->category == $CFG->coursedelivery_runningcategory) || ($COURSE->category == $CFG->coursedelivery_deploycategory) || ($COURSE->category == $CFG->coursedelivery_closedcategory)){
		            $strclose = get_string('close', 'block_publishflow');
		            $stropen = get_string('open', 'block_publishflow');
		            $strreopen = get_string('reopen', 'block_publishflow');
		            $coursecontrolstr =  get_string('coursecontrol', 'block_publishflow');
		            // should be given to entity trainers (mts)
		            // we need also fix the case where all categories are the same
		            if (has_capability('block/publishflow:manage', $context_course)){
						$this->content->text .= '<div class="block_publishflow_coursecontrol">';
						$this->content->text .= "<b>$coursecontrolstr</b><br/>";
		                if ($COURSE->category == $CFG->coursedelivery_runningcategory && $COURSE->visible){
		                    $this->content->text .= "<a href=\"{$CFG->wwwroot}/blocks/publishflow/close.php?fromcourse={$COURSE->id}&amp;what=close\">$strclose</a>";
		                } else if ($COURSE->category == $CFG->coursedelivery_deploycategory) {
		                    $this->content->text .= "<a href=\"{$CFG->wwwroot}/blocks/publishflow/open.php?fromcourse={$COURSE->id}&amp;what=open\">$stropen</a>";
		                } else if ($COURSE->category == $CFG->coursedelivery_closedcategory) {
		                    $this->content->text .= "<a href=\"{$CFG->wwwroot}/blocks/publishflow/open.php?fromcourse={$COURSE->id}&amp;what=open\">$strreopen</a>";
		                }
						$this->content->text .= '</div>';
		            }
		        }

	            $this->content->text .= '</center>';
	        }
	    } else {            
	        // this is an unregistered course that has no IDNumber reference. This causes a problem for instance identification            
	        $this->content->text .= notify(get_string('unregistered','block_publishflow'), 'notifyproblem', 'center', true);
            $qoptions['fromcourse'] = $COURSE->id;
            $qoptions['what'] = 'submit';
            $qoptions['id'] = $this->instance->id;
            $this->content->text .= print_single_button($CFG->wwwroot.'/blocks/publishflow/submit.php', $qoptions, get_string('reference', 'block_publishflow'), 'post', '_self', true);
	    } 

	    // If you like, you can specify a "footer" text that will be printed at the bottom of your block.
	    // If you don't want a footer, set this variable to an empty string. DO NOT delete the line entirely!
	    $this->content->footer = '';
	
	    // And that's all! :)
	    return $this->content;
	}


    
    /*
    * sets up moodle custom roles
    */
    function after_install(){
        global $USER;
        // We need add a custom role here : disabledstudent
        // A disabled student still is enrolled within a course, but cannot interfere anymore with content 
        if ($newroleid = create_role(get_string('disabledstudentrole', 'block_publishflow'), 'disabledstudent', get_string('disabledstudentdesc', 'block_publishflow'))){
        
            $standardwritecapsforstudents = array("moodle/calendar:manageownentries",
                    "moodle/calendar:managegroupentries",
                    "moodle/calendar:manageentries",
                    "mod/assignment:submit",
                    "mod/chat:chat",
                    "mod/chat:deletelog",
                    "mod/choice:choose",
                    "mod/choice:deleteresponses",
                    "mod/data:writeentry",
                    "mod/data:comment",
                    "mod/data:rate",
                    "mod/data:approve",
                    "mod/data:manageentries",
                    "mod/data:managecomments",
                    "mod/data:managetemplates",
                    "mod/data:manageuserpresets",
                    "mod/forum:startdiscussion",
                    "mod/forum:replypost",
                    "mod/forum:addnews",
                    "mod/forum:replynews",
                    "mod/forum:rate",
                    "mod/forum:createattachment",
                    "mod/forum:editanypost",
                    "mod/forum:throttlingapplies",
                    "mod/glossary:write",
                    "mod/glossary:manageentries",
                    "mod/glossary:managecategories",
                    "mod/glossary:comment",
                    "mod/glossary:managecomments",
                    "mod/glossary:import",
                    "mod/glossary:approve",
                    "mod/glossary:rate",
                    "mod/lams:participate",
                    "mod/lams:manage",
                    "mod/lesson:edit",
                    "mod/lesson:manage",
                    "mod/quiz:attempt",
                    "mod/quiz:manage",
                    "mod/quiz:preview",
                    "mod/quiz:grade",
                    "mod/quiz:deleteattempts",
                    "mod/scorm:skipview",
                    "mod/scorm:savetrack",
                    "mod/wiki:participate",
                    "mod/wiki:manage",
                    "mod/wiki:overridelock",
                    "mod/workshop:participate",
                    "mod/workshop:manage",
                    "block/rss_client:createprivatefeeds",
                    "block/rss_client:createsharedfeeds",
                    "block/rss_client:manageownfeeds",
                    "block/rss_client:manageanyfeeds");
    
            foreach($standardwritecapsforstudents as $writecap){
                $rolecap = new StdClass;
                $rolecap->roleid = $newroleid;
                $rolecap->context = 1;
                $rolecap->capability = $writecap;
                $rolecap->timemodified = time();
                $rolecap->permission = CAP_PREVENT;
                $rolecap->modifierid = $USER->id;
                insert_record('role_capabilities', $rolecap);
            }
        }
        
        // install MNET functions
      	publishflow_install();
    }
    
    /*
    * cleans up moodle custom roles
    */
    function before_delete(){

        if ($disabledstudentrole = get_record('role', 'shortname', 'disabledstudent')){
            delete_records('role_capabilities', 'roleid', $disabledstudentrole->id);
            delete_records('role', 'id', $disabledstudentrole->id);
            delete_records('role_assignments', 'roleid', $disabledstudentrole->id);
            delete_records('role_allow_assign', 'roleid', $disabledstudentrole->id);
            delete_records('role_allow_override', 'roleid', $disabledstudentrole->id);
            delete_records('role_allow_assign', 'allowassign', $disabledstudentrole->id);
            delete_records('role_allow_override', 'allowoverride', $disabledstudentrole->id);
        }
      	publishflow_uninstall();
    }
    
    function makebackupform(){
    	global $COURSE, $CFG;
    	
    	if (has_capability('moodle/course:backup', get_context_instance(CONTEXT_COURSE, $COURSE->id))){
            $dobackupstr = get_string('dobackup', 'block_publishflow');
	        $this->content->text .= "<center>";
	        $this->content->text .= "<form name=\"makebackup\" action=\"{$CFG->wwwroot}/blocks/publishflow/backup.php\" method=\"GET\">";
	        $this->content->text .= "<input type=\"hidden\" name=\"id\" value=\"{$COURSE->id}\" />";
	        $this->content->text .= "<input type=\"submit\" name=\"go_btn\" value=\"$dobackupstr\" />";
	        $this->content->text .= "</form>";
	        $this->content->text .= "</center>";
	    }
    }
}

?>
