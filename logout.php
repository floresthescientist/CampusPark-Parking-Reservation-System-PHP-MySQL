<?php
/*
 * logout.php - Ends the session and goes back to the home page
 */
require 'inc/helpers.php';

$_SESSION = [];                 // forget everything about this visitor
session_regenerate_id(true);    // throw away the old session ID

set_notice('ok', 'You are logged out. See you next time!');
go('index.php');
