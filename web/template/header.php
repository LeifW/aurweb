<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
 "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"
	xml:lang="<?php print htmlspecialchars($LANG, ENT_QUOTES) ?>" lang="<?php print htmlspecialchars($LANG, ENT_QUOTES) ?>">
  <head>
    <title>AUR (<?php print htmlspecialchars($LANG); ?>)<?php if ($title != "") { print " - " . htmlspecialchars($title); } ?></title>
	<link rel='stylesheet' type='text/css' href='css/fonts.css' />
	<link rel='stylesheet' type='text/css' href='css/containers.css' />
	<link rel='stylesheet' type='text/css' href='css/arch.css' />
	<link rel='stylesheet' type='text/css' href='css/archweb.css' />
	<link rel='stylesheet' type='text/css' href='css/aur.css' />
	<link rel='shortcut icon' href='images/favicon.ico' />
	<link rel='alternate' type='application/rss+xml' title='Newest Packages RSS' href='rss.php' />
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  </head>
	<body>
		<div id="archnavbar" class="anb-aur">
			<div id="archnavbarlogo"><h1><a href="/" title="Return to the main page">Arch Linux</a></h1></div>
			<div id="archnavbarmenu">
				<ul id="archnavbarlist">
					<li id="anb-home"><a href="http://www.archlinux.org/" title="Arch news, packages, projects and more">Home</a></li>
					<li id="anb-packages"><a href="http://www.archlinux.org/packages/" title="Arch Package Database">Packages</a></li>
					<li id="anb-forums"><a href="https://bbs.archlinux.org/" title="Community forums">Forums</a></li>
					<li id="anb-wiki"><a href="https://wiki.archlinux.org/" title="Community documentation">Wiki</a></li>
					<li id="anb-bugs"><a href="https://bugs.archlinux.org/" title="Report and track bugs">Bugs</a></li>
					<li id="anb-aur"><a href="/" title="Arch Linux User Repository">AUR</a></li>
					<li id="anb-download"><a href="http://www.archlinux.org/download/" title="Get Arch Linux">Download</a></li>
				</ul>
			</div>
		</div><!-- #archnavbar -->

		<div id="content">
			<div id="lang_sub">
				<form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES) ?>">
					<fieldset>
						<div>
							<select name="setlang" id="id_setlang">
		<?php
		reset($SUPPORTED_LANGS);
		foreach ($SUPPORTED_LANGS as $lang => $lang_name) {

			print '<option value="' . strtolower($lang) . '"' .
				($lang == $LANG ? ' selected="selected"' : '') .
				'>' . strtolower($lang) . "</option>\n";
		}
		?>
							</select>
							<input type="submit" value="Go" />
						</div>
					</fieldset>
				</form>
			</div>
			<div id="archdev-navbar">
				<ul>
					<li><a href="index.php">AUR <?php print __("Home"); ?></a></li>
					<li><a href="packages.php"><?php print __("Packages"); ?></a></li>
					<?php if (isset($_COOKIE['AURSID'])): ?>
						<li><a href="packages.php?SeB=m&amp;K=<?php print username_from_sid($_COOKIE["AURSID"]); ?>"><?php print __("My Packages"); ?></a></li>
						<li><a href="pkgsubmit.php"><?php print __("Submit"); ?></a></li>
						<li><a href="account.php"><?php print __("Accounts"); ?></a></li>
						<?php if (check_user_privileges()): ?><li><a href="tu.php"><?php print __("Trusted User"); ?></a></li><?php endif; ?>
						<li><a href="logout.php"><?php print __("Logout"); ?></a></li>
					<?php else: ?>
						<li><a href="account.php"><?php print __("Register"); ?></a></li>
						<li><a href="login.php"><?php print __("Login"); ?></a></li>
					<?php endif; ?>
				</ul>
			</div><!-- #archdev-navbar -->
			<!-- Start of main content -->

