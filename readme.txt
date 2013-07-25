=== Plugin Name ===
Contributors: dlgoodchild, paultgoodchild
Donate link: http://icwp.io/q
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
Tags: WordPress Firewall, protection, whitelist
Requires at least: 3.2.0
Tested up to: 3.6
Stable tag: 1.2.1

WordPress Simple Firewall and Login Protection.

== Description ==

Protects your WordPress site in 2 main ways:

1.	A simple, easily configured Firewall.
1.	WordPress 2-Factor Authentication Login.

= Firewall =

The firewall is based on functionality provided by the [WordPress Firewall 2 plugin](http://wordpress.org/plugins/wordpress-firewall-2/).

The WordPress Simple Firewall is built to be easy to use by anyone, reliable and overall... simple.

It adds extra features over WordPress Firewall 2, including:

*	Option to completely block access to wp-login.php based on an IP Address whitelist
*	Added a Blacklist option so you can completely block based on IP address.
*	Option to easily turn on / off the whole firewall. This means you don't have to disable certain settings or even disable the plugin to temporarily turn it off.
  To debug the plugin, just turn off the firewall in the Firewall Options screen and all settings are ignored.
*	Filesystem based plugin override. This means if you accidentally lock yourself out, you can forcefully turn off the firewall using FTP. You can also
  turn back on the firewall using the same method.
*	Automatic caching to reduce database calls when determining Firewall settings: 1-3 database calls per page load.
*	Ability to view the complete log of the firewall and all its messages.
*	Ability to easily turn on and off firewall logging.
*	Ability to clear the whole log.
*	For developers - ability to programmatically add to the IP address whitelist/blacklist - this is
  useful for 3rd party services that connect to the site using other plugins. E.g. [iControlWP](http://www.icontrolwp.com/).
  
= Login Protection =

Note: Login Protection is a completely independent feature to the Firewall. IP Address whitelists are not shared.

There are many way to protect your WordPress site from attacks on your user login. This part of the plugin is design to implement some of the most simplest
and thereby effective forms of protection.

As of version 1.2.0 you now have the option to add simple, email-based 2-Factor Login Authentication based on IP address.

What does this mean?

Once this feature is activated, every user login must have a matching IP address, and they can only have ONE.  If they are not logged in, or attempt to login
to the site from an IP address that is different to their verified IP address, they will receive an email with a verification link.

They must click this link to verify their IP address.  Then, and only then, will they be permitted to log into the site.

How does this protect your site?

1.	You are protected against brute force login attacks against your site.
1.	If you leave your WordPress account logged in, simply login from another location and your previous session will be automatically invalidated.
1.	You reduce the risk that accounts will be shared and re-used with 3rd parties.

There are many more login protection features coming...

== Installation ==

See FAQs.

== Frequently Asked Questions ==

= How can I install the plugin? =

This plugin should install as any other WordPress.org respository plugin.

1.	Browse to Plugins -> Add Plugin
1.	Search: WordPress Simple Firewall
1.	Click Install
1.	Click to Activate.

Alternatively using FTP:

1.	Download the zip file using the download link to the right.
1.	Extract the contents of the file and locate the folder called 'wp-simple-firewall' containing the plugin files.
1.	Upload this whole folder to your '/wp-content/plugins/' directory
1.	From the plugins page within Wordpress locate the plugin 'WordPress Simple Firewall' and click Activate

A new menu item will appear on the left-hand side called 'Simple Firewall'.

= How does the IP Whitelist work? =

Any IP address that is on the whitelist will not be subject to any of the firewall scanning/processing.  This setting takes priority over all other settings.

= How does the wp-login.php block work? =

If the IP whitelist is empty, this setting is ignored. This stops you from easily locking yourself out.

When enabled, and there are valid IP addresses in the whitelist, any access to wp-login.php will be blocked from a visitor
who's IP address is not on the whitelist.

= I've locked myself out from the WordPress login screen! =

This happens when ALL the following 3 conditions are all met:

*	you turn on the option to restrict access to wp-login.php,
*	you have at least 1 IP address in the IP Whitelist, and
*	your current IP address is not on the IP Whitelist.

This plugin offers the ability to completely turn off (and on) the WordPress Simple Firewall by creating a specific file in the plugin folder.

Here's how:

1.	Open up an FTP connection to your site, browse to the plugin folder <your site WordPress root>/wp-content/plugins/wp-simple-firewall/
1.	Create a new file in here called: "forceOff".
1.	Load any page on your WordPress site.
1.	After this, you'll find your WordPress Simple Firewall has been switched off.

If you want to turn the firewall on in the same way, create a file called "forceOn".

Remember: If you leave one of these files on the server, it will override your on/off settings, so you should delete it when you're done.

= Which takes precedence... whitelist or blacklist? =

Whitelist. So if you have the same address in both lists, it'll be whitelisted and allowed to pass before the blacklist comes into effect.

= How does the pages/parameters whitelist work? =

It is a comma-separated list of pages and parameters. A NEW LINE should be taken for each new page name and its associated parameters.

The first entry on each line (before the first comma) is the page name. The rest of the items on the line are the parameters.

The following are some simple examples to illustrate:

**edit.php, featured**

On the edit.php page, the parameter with the name 'featured' will be ignored.

**admin.php, url, param01, password**

Any parameters that are passed to the page ending in 'admin.php' with the names 'url', 'param01' and 'password' will
be excluded from the firewall processing.

*, url, param, password

Putting a star first means that these exclusions apply to all pages.  So for every page that is accessed, all the parameters
that are url, param and password will be ignored by the firewall.

== Screenshots ==

== Changelog ==

= 1.2.1 =

*	ADDED:		New Feature - Login Wait Interval. To reduce the effectiveness of brute force login attacks, you can add an interval by
				which WordPress will wait before processing any more login attempts on a site.
*	CHANGED:	Optimized some settings for performance.
*	CHANGED:	Cleaned up the UI when the Firewall / Login Protect features are disabled (more to come).
*	CHANGED:	Further code improvements (more to come).

= 1.2.0 =

*	ADDED:		New Feature - **Login Protect**. Added 2-Factor Login Authentication for all users and their associated IP addresses.
*	CHANGED:	The method for processing the IP address lists is improved.
*	CHANGED:	Improved .htaccess rules (thanks MickeyRoush)
*	CHANGED:	Mailing method now uses WP_MAIL
*	CHANGED:	Lot's of code improvements.

= 1.1.6 =

*	ADDED:		Option to include Cookies in the firewall checking.

= 1.1.5 =

*	ADDED: Ability to whitelist particular pages and their parameters (see FAQ)
*	CHANGED: Quite a few improvements made to the reliability of the firewall processing.

= 1.1.4 =

*	FIX: Left test path in plugin.

= 1.1.3 =

*	ADDED: Option to completely ignore logged-in Administrators from the Firewall processing (they wont even trigger logging etc).
*	ADDED: Ability to (un)blacklist and (un)whitelist IP addresses directly from within the log.
*	ADDED: helpful link to IP WHOIS from within the log.

= 1.1.2 =

*	CHANGED: Logging now has its own dedicated database table.

= 1.1.1 =

*	Fix: Block notification emails weren't showing the user-friendly IP Address format.

= 1.1.0 =

*	You can now specify IP ranges in whitelists and blacklists. To do this separate the start and end address with a hypen (-)	E.g. For everything between 1.2.3.4 and 1.2.3.10, you would do: 1.2.3.4-1.2.3.10
*	You can now specify which email address to send the notification emails.
*	You can now add a comment to IP addresses in the whitelist/blacklist. To do this, write your IP address then type a SPACE and write whatever you want (don't take a new line).
*	You can now set to delete ALL firewall settings when you deactivate the plugin.
*	Improved formatting of the firewall log.

= 1.0.2 =
*	First Release

== Upgrade Notice ==

= 1.1.2 =

*	CHANGED: Logging now has its own dedicated database table.
*	Fix: Block notification emails weren't showing the user-friendly IP Address format.
*	You can now specify IP ranges in whitelists and blacklists. To do this separate the start and end address with a hypen (-)	E.g. For everything between 1.2.3.4 and 1.2.3.10, you would do: 1.2.3.4-1.2.3.10
*	You can now specify which email address to send the notification emails.
*	You can now add a comment to IP addresses in the whitelist/blacklist. To do this, write your IP address then type a SPACE and write whatever you want (don't take a new line).
*	You can now set to delete ALL firewall settings when you deactivate the plugin.
*	Improved formatting of the firewall log.
