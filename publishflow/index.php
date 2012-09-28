<?php // $Id: index.php,v 1.1 2012-06-24 14:07:14 vf Exp $

    require_once("../../config.php");
    require_once("lib.php");

    $id = required_param('id',PARAM_INT);   // course

    redirect("$CFG->wwwroot/course/view.php?id=$id");

?>
