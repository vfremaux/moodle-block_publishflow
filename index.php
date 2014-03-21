<?php // $Id: index.php,v 1.1.1.1 2013-02-13 08:42:03 mo2dlemaster Exp $

    require_once("../../config.php");
    require_once("lib.php");

    $id = required_param('id',PARAM_INT);   // course

    redirect("$CFG->wwwroot/course/view.php?id=$id");

?>
