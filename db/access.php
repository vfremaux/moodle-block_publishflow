<?php
//
// Capability definitions for the block vmoodle.
//
// The capabilities are loaded into the database table when the module is
// installed or updated. Whenever the capability definitions are updated,
// the module version number should be bumped up.
//
// The system has four possible values for a capability:
// CAP_ALLOW, CAP_PREVENT, CAP_PROHIBIT, and inherit (not set).
//
//
// CAPABILITY NAMING CONVENTION
//
// It is important that capability names are unique. The naming convention
// for capabilities that are specific to modules and blocks is as follows:
//   [mod/block]/<component_name>:<capabilityname>
//
// component_name should be the same as the directory name of the mod or block.
//
// Core moodle capabilities are defined thus:
//    moodle/<capabilityclass>:<capabilityname>
//
// Examples: mod/forum:viewpost
//           block/recent_activity:view
//           moodle/site:deleteuser
//
// The variable name for the capability definitions array follows the format
//   $capabilities
//

$capabilities = array(

    'block/publishflow:deployeverywhere' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),
    'block/publishflow:managepublishedfiles' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'block/publishflow:deploy' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'block/publishflow:publish' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'block/publishflow:addtocourse' => array(
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'riskbitmask'  => RISK_SPAM,
        'legacy' => array(
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'block/publishflow:retrofit' => array(
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'riskbitmask'  => RISK_SPAM,
        'legacy' => array(
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'block/publishflow:manage' => array(
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'riskbitmask'  => RISK_SPAM,
        'legacy' => array(
            'editingteacher' => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),
);
?>