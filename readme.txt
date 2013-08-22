=== Plugin Name ===
Contributors: dlgoodchild, paultgoodchild
Donate link: http://icwp.io/q
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
Tags: WordPress Firewall, protection, whitelist, blacklist, two-factor login, GASP
Requires at least: 3.2.0
Tested up to: 3.6
Stable tag: 1.5.5

WordPress Simple Firewall and Login Protection.

== Description ==

Protects your WordPress site in 2 main ways:

1.	A simple, easily configured WordPress Firewall.
1.	Ultimate WordPress Login Protection against Bots and Brute Force Attacks (no ban lists and horrible configuration pages!)

= Firewall =

The WordPress Simple Firewall is built to be reliable, and easy to use by **anyone**.  Seriously, the interface is simple! :)

It adds extra features over WordPress Firewall 2, including:

*	7 Simple, clear, Firewall blocking options - pick and choose for ultimate protection and compatibility.
*	Option: Ignore already logged-in Administrators so you don't firewall yourself as you work on the site.
*	Option: IP Address Whitelist. So you can vet your own IP addresses and 3rd Party Services.
*	Option: Developer option for 3rd Party Services to dynamically add IP Addresses to whitelist
	(our plugin is built to work with others!) E.g. [iControlWP](http://www.icontrolwp.com/).
*	Option: IP Address Blacklist so you can completely block sites/services based on their IP address.
*	Option: to easily turn on / off the whole firewall without disabling the whole plugin! (so simple, but important)
*	Recovery Option: You can use FTP to manually turn ON/OFF the Firewall. This means if you accidentally lock yourself out, you can forcefully turn off the firewall using FTP. You can also
  turn back on the firewall using the same method.
*	Performance: When the firewall is running it is processing EVERY page load. So your firewall checking needs to be fast.
	This plugin is written to cache settings and minimize database access: 1-3 database calls per page load.
*	Logging: Full logging of Firewall (and other options) to analyse and debug your traffic and settings.
*	Option: Email when firewall blocks a page access - with option to specify recipient.
*	Option: Email throttling. If you get hit by a bot you wont get 1000s of email... you can throttle how many emails are sent.
  useful for 3rd party services that connect to the site using other plugins. 
 
Basic functionality is based on the principles employed by the [WordPress Firewall 2 plugin](http://wordpress.org/plugins/wordpress-firewall-2/).

= Login and Identity Protection - Stops Brute Force Attacks =

Note: Login Protection is a completely independent feature to the Firewall. IP Address whitelists are not shared.

With our Login Protection features this plugin will single-handling prevent brute force login attack on all your WordPress sites.

It doesn't need IP Address Ban Lists (which are actually useless anyway), and instead puts hard limits on your WordPress site,
and force users to verify themselves when they login.

As of version 1.2.0+ you now have several ways to add simple protection to your WordPress Login system.

1.	[Email-based 2-Factor Login Authentication](http://www.icontrolwp.com/2013/07/add-two-factor-authentication-login-wordpress-sites/) based on IP address! (prevents brute force login attacks)
1.	[Login Cooldown Interval](http://www.icontrolwp.com/2013/08/wordpress-login-cool-down-stops-brute-force-attacks-wordpress/) - WordPress will only process 1 login per interval in seconds (prevents brute force login attacks)
1.	[GASP Anti-Bot Login Form Protection](http://www.icontrolwp.com/2013/07/how-to-growmap-anti-spam-protection-wordpress-login-form/) - Adds 2 protection checks for all WordPress login attempts (prevents brute force login attacks using Bots)

These options alone will protect your WordPress sites from nearly all forms of Brute Force
login attacks.

And you hardly need to configure anything! Simply check the options to turn them on, set a cooldown interval and you're instantly protected.

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

= TODO =

*	ADD:		Add various WordPress security features dynamically that would otherwise require wp-config.php editing.
*	ADD:		Limit login attempts functionality.
*	CHANGE:		Interface to give a better "At-A-Glance" Dashboard summary view, that also allows you to turn on/off core features.

= 1.5.5 =

*	FIXED:		Quite a few bugs fixed.

= 1.5.4 =

*	FIXED:		Typo error.

= 1.5.3 =

*	FIXED:		Some of the firewall processors were saving unnecessary data.

= 1.5.2 =

*	CHANGED:	The method for finding the client IP address is more thorough, in a bid to work with Proxy servers etc.
*	FIXED:		PHP notice reported here: http://wordpress.org/support/topic/getting-errors-when-logged-in

= 1.5.1 =

*	FIXED:		Bug fix where IP address didn't show in email.
*	FIXED:		Attempt to fix problem where update message never hides.

= 1.5.0 =

*	ADDED:		A new IP whitelist on the Login Protect that lets you by-pass login protect rules for given IP addresses.
*	REMOVED:	Firewall rule for wp-login.php and whitelisted IPs.

= 1.4.2 =

*	ADDED:		The plugin now has an option to automatically upgrade itself when an update is detected - enabled by default.

= 1.4.1 =

*	ADDED:		The plugin will now displays an admin notice when a plugin upgrade is available with a link to immediately update.
*	ADDED:		Plugin collision: removes the main hook by 'All In One WordPress Security'. No need to have both plugins running.
*	ADDED:		Improved Login Cooldown Feature- works more like email throttling as it now uses an extra filesystem-based level of protection.
*	FIXED:		Login Cooldown Feature didn't take effect in certain circumstances.

= 1.4.0 =

*	ADDED:		All-new plugin options handling making them more efficient, easier to manage/update, using far fewer WordPress database options.
*	CHANGED:	Huge improvements on database calls and efficiency in loading plugin options.
*	FIXED:		Nonce implementation.

= 1.3.2 =

*	FIXED:		Small compatibility issue with Quick Cache menu not showing.

= 1.3.0 =

*	ADDED:		Email Throttle Feature - this will prevent you getting bombarded by 1000s of emails in case you're hit by a bot.
*	ADDED:		Another Firewall die() option. New option will print a message and uses the wp_die() function instead.
*	ADDED:		Refactored and improved the logging system (upgrading will delete your current logs!).
*	ADDED:		Option to separately log Login Protect features.
*	ADDED:		Option to by-pass 2-factor authentication in the case sending the verification email fails
				(so you don't get locked out if your hosting doesn't support email!).
*	CHANGED:	Login Protect checking now better logs out users immediately with a redirect.
*	CHANGED:	We now escape the log data being printed - just in case there's any HTML/JS etc in there we don't want.
*	CHANGED:	Optimized and cleaned a lot of the option caching code to improve reliability and performance (more to come).

= 1.2.7 =

*	FIX:		Bug where the GASP Login protection was only working when you had 2-factor authentication enabled.

= 1.2.6 =

*	ADDED:		Ability to import settings from WordPress Firewall 2 plugin options - note, doesn't import page and variables whitelisting.
*	FIX:		A reported bug - parameter values could also be arrays.

= 1.2.5 =

*	ADDED:		New Feature - Option to add a checkbox that blocks automated SPAM Bots trying to log into your site.
*	ADDED:		Added a clear user message when they verify their 2-factor authentication.
*	FIX:		A few bugfixes and logic corrections.

= 1.2.4 =

*	CHANGED:	Documentation on the dashboard, and the message after installing the firewall have been updated to be clearer and more informative.
*	FIX:		A few bugfixes and logic corrections.

= 1.2.3 =

*	FIX:		bugfix.

= 1.2.2 =

*	FIX:		Some warnings and display bugs.

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
