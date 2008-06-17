<?php

set_include_path(get_include_path() . PATH_SEPARATOR . '../lib' . PATH_SEPARATOR . '../lang');

include("timeout_po.inc");
include("aur.inc");
set_lang();
html_header();

print __("Your session has timed out.  You must log in again.");
print "<p>\n";
print __("Click on the Home link above to log in.");
print "</p>\n";

html_footer(AUR_VERSION);

?>
