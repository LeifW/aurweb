<?php

set_include_path(get_include_path() . PATH_SEPARATOR . '../lib' . PATH_SEPARATOR . '../lang');

include("aur.inc");         # access AUR common functions
include("template_po.inc"); # use some form of this for i18n support
set_lang();                 # this sets up the visitor's language
check_sid();                # see if they're still logged in
html_header();              # print out the HTML header


# Any text you print out to the visitor, use the __() function
# for i18n support.  See 'testpo.php' for more details.
#
print __("Hi, this is worth reading!")."<br />\n";


html_footer(AUR_VERSION);

?>
