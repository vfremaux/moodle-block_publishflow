<?php // $Id: index.php,v 1.1 2010/12/18 16:10:55 vf Exp $

    require_once("../../config.php");
    require_once("lib.php");

    $id = required_param('id',PARAM_INT);   // course

    redirect("$CFG->wwwroot/course/view.php?id=$id");

?>
