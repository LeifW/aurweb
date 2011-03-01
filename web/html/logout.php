<?php

set_include_path(get_include_path() . PATH_SEPARATOR . '../lib');

include_once("aur.inc");         # access AUR common functions
include_once("acctfuncs.inc");         # access AUR common functions


# if they've got a cookie, log them out - need to do this before
# sending any HTML output.
#
if (isset($_COOKIE["AURSID"])) {
	$dbh = db_connect();
	$q = "DELETE FROM Sessions WHERE SessionID = '";
	$q.= mysql_real_escape_string($_COOKIE["AURSID"]) . "'";
	db_query($q, $dbh);
	# setting expiration to 1 means '1 second after midnight January 1, 1970'
	setcookie("AURSID", "", 1, "/");
	unset($_COOKIE['AURSID']);
}

clear_expired_sessions();

header('Location: index.php');

