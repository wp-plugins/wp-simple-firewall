=== Plugin Name ===
Contributors: dlgoodchild, paultgoodchild
Donate link: http://icwp.io/q
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
Tags: WordPress Firewall, protection, whitelist
Requires at least: 3.2.0
Tested up to: 3.6
Stable tag: 1.1.4

WordPress Simple Firewall

== Description ==

Principally based on functionality provided by the [WordPress Firewall 2 plugin](http://wordpress.org/plugins/wordpress-firewall-2/).

The WordPress Simple Firewall is built to be principally easy to use. It takes a lead from the WordPress Firewall 2 plugin that is already quite old and
that hasn't been updated for more than 2 years.

This plugin is built for reliability of operation, ease of extension, and overall... simplicity.

It adds extra features over WordPress Firewall 2, including:

*	Option to completely block access to wp-login.php based on IP Address whitelist
*	Added a Blacklist option so you can completely block based on IP address.
*	Option to easily turn on / off the whole firewall. This means you don't have to disable certain settings or even disable the plugin to temporarily turn it off.
  To debug the plugin, just turn off the firewall in the Firewall Options screen and all settings are ignored.
*	Filesystem based plugin override. This means if you accidentally lock yourself out, you can forcefully turn off the firewall using FTP. You can also
  turn back on the firewall using the same method.
*	Automatic caching to reduce database calls when determining Firewall settings: 1-3 database calls per page load.
*	Ability to easily turn on and off firewall logging.
*	Ability to view the complete log of the firewall and all its messages.
*	Ability to clear the whole log.
*	For developers - ability to programmatically add to the IP address whitelist/blacklist - this is
  useful for 3rd party services that connect to the site using other plugins. E.g. [iControlWP](http://www.icontrolwp.com/).

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

== Screenshots ==

== Changelog ==

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
