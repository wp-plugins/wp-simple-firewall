=== Plugin Name ===
Contributors: paultgoodchild, dlgoodchild 
Donate link: http://icwp.io/q
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
Tags: WordPress Firewall, protection, whitelist, blacklist, two-factor authentication, GASP, comment spam, automatic updates
Requires at least: 3.2.0
Tested up to: 3.8
Stable tag: 2.4.2

Complete and Simple WordPress Security. Unrestricted, with no premium features.

== Description ==

The WordPress Simple Firewall is the only WordPress security plugin that *protects itself* - this plugin
will prevent access to itself so that unauthorized users can't deactivate or screw with your security settings.

An intro to the features and why you should use the Simple Firewall before getting all complicated with bulky
security plugins.

[youtube http://www.youtube.com/watch?v=r307fu3Eqbo]

Protects your WordPress site in 5 main ways:

= Plugin Self-Protection =

This plugins locks itself down - you can add access restriction to the plugin itself!

= A Simple, Effective Firewall =

Builds upon the simplicity and effectiveness of the WordPress Firewall 2 plugin.

= WordPress Login Protection =

Adds several layers of protection to the WordPress login screen through identity verification and Brute Force Login hacking prevention.

= Comments and SPAM Protection =

Uses and builds upon tried and tested SPAM prevention and filtering techniques with some unique approaches found only in this plugin.

= WordPress Lockdown =

Provides options for locking down your WordPress site from both legitimate users and people who may have gained unauthorized access.

Read more on each section below...

= A Simple Firewall =

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

1.	[Email-based 2-Factor Login Authentication](http://icwp.io/2v) based on IP address! (prevents brute force login attacks)
1.	[Login Cooldown Interval](http://icwp.io/2t) - WordPress will only process 1 login per interval in seconds (prevents brute force login attacks)
1.	[GASP Anti-Bot Login Form Protection](http://icwp.io/2u) - Adds 2 protection checks for all WordPress login attempts (prevents brute force login attacks using Bots)

These options alone will protect your WordPress sites from nearly all forms of Brute Force
login attacks.

And you hardly need to configure anything! Simply check the options to turn them on, set a cooldown interval and you're instantly protected.

= SPAM and Comments Filtering =

As of version 1.6, this plugin integrates [GASP Spambot Protection](http://wordpress.org/plugins/growmap-anti-spambot-plugin/).

We have taken this functionality a level further and added the concept of unique, per-page visit, Comment Tokens.

**Comment Tokens** are unique keys that are created every time a page loads and they are uniquely generated based on 3 factors:

1.	The visitors IP address.
1.	The Page they are viewing
1.	A unique, random number, generated at the time the page is loaded.

This is all handle automatically and your users will not be affected - they'll still just have a checkbox like the original GASP plugin.

These comment tokens are then embedded in the comment form and must be presented to your WordPress site when a comment is posted.  The plugin
will then examine the token, the IP address from which the comment is coming, and page upon which the comment is being posted.  They must
all match before the comment is accepted.

Furthermore, we place a cooldown (i.e. you must wait X seconds before you can post using that token) and an expiration on these comment tokens.
The reasons for this are:

1.	Cooldown means that a spambot cannot load a page, read the unique comment token and immediately re-post a comment to that page. It must wait
a while.  This has the effect of slowing down the spambots, and, if the spambots get it wrong, they've wasted that token - as tokens can only
be used once.
1.	Expirations mean that a spambot cannot get the token and use it whenever it likes, it must use it within the specfied time.

This all combines to make it much more difficult for spambots (and also human spammers as they have to now wait) to work their dirty magic :)

== Installation ==

See FAQs.

== Installation ==

Note: When you enable the plugin, the firewall is not automatically turned on. This plugin contains various different sections of
protection for your site and you should choose which you need based on your own requirements.

Why do we do this?  Simple, performance and optimization - there is no reason to automatically turn on features for people that don't
need it as each site and set of requirements is different.

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

== Frequently Asked Questions ==

Please see the new [help centre](http://icwp.io/firewallhelp) for details on features and some FAQs.

= My server has a firewall, why do I need this plugin? =

This plugin is more of an application firewall, rather than a server/network firewall.  It is designed to interpret web calls to your site to
look for and find attempts to circumvent it and gain unauthorized access or cause damage.

Your network firewall is designed to restrict access to your server based on certain types of network traffic.  The WordPress Simple Firewall
is designed to restrict access to your site, based on certain type of web calls.

= How does the IP Whitelist work? =

Any IP address that is on the whitelist will not be subject to any of the firewall scanning/processing.  This setting takes priority over all other settings.

= Does the IP Whitelist/Blacklist support IP ranges? =

Yes. To specify a range you do something like:  192.168.1.10-192.168.1.20

= I've locked myself out from my own site! =

This happens when any the following 3 conditions are met:

*	you have added your IP address to the firewall blacklist,
*	you have enabled 2 factor authentication and email doesn't work on your site (and you haven't chosen the override option)

You can completely turn OFF (and ON) the WordPress Simple Firewall by creating a special file in the plugin folder.

Here's how:

1.	Open up an FTP connection to your site, browse to the plugin folder <your site WordPress root>/wp-content/plugins/wp-simple-firewall/
1.	Create a new file in here called: "forceOff".
1.	Load any page on your WordPress site.
1.	After this, you'll find your WordPress Simple Firewall has been switched off.

If you want to turn the firewall on in the same way, create a file called "forceOn".

Remember: If you leave one of these files on the server, it will override your on/off settings, so you should delete it when you no longer need it.

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

= How does the login cooldown work? =

When enabled the plugin will prevent more than 1 login attempt to your site every "so-many" seconds.  So if you enable a login cooldown
of 60 seconds, only 1 login attempt will be processed every 60 seconds.  If you login incorrectly, you wont be able to attempt another
login for a further 60 seconds.

More Info: http://icwp.io/2t

= How does the GASP login protection work? =

This is best described on the blog: http://icwp.io/2u

= How does the 2-factor authentication work? =

Best described here: http://icwp.io/2v

= I'm getting an update message although I have auto update enabled? =

The Automatic (Background) WordPress updates happens on a WordPress schedule - it doesn't happen immediately when an update is detected.
You can either manually upgrade, or WordPress will handle it in due course.

== Screenshots ==

== Changelog ==

= TODO =

*	ADD:		Add various WordPress security features dynamically that would otherwise require wp-config.php editing.
*	CHANGE:		Interface to give a better "At-A-Glance" Dashboard summary view, that also allows you to turn on/off core features.

= 2.4.2 =

*	ADDED:      Contextual help links for many options.  More to come...
*	ADDED:      More Portuguese (Brazil) translations (~80%)

= 2.4.1 =

*	ADDED:      More strings to the translation set for better multilingual support
*	ADDED:      Portuguese (Brazil) translations (~40%)
*	UPDATED:    Hebrew Translations
*	FIXED:      Automatic cleaning of database logs wasn't actually working as expected. Should now be fixed.

= 2.4.0 =

*	NEW:        Option to enable Two-Factor Authentication based on Cookie. In this way you can tie a user session to a single browser.
*	FIX:        Better WordPress Multisite (WPMS) Support.

= 2.3.4 =

*   FIX:        Automatic updating of itself.

= 2.3.3 =

*	ADDED:      Hebrew Translations. Thanks [Ahrale](http://atar4u.com)!
*	ADDED:      Automatic trimming of the Firewall access log to 7 days - it just grows too large otherwise.
*   FIX:        The previously added automatic clean up of old comments and login protect database entries was wiping out the valid login protect
                entries and was forcing users to re-login every 24hrs.
*   FIX:        Some small bugs, errors, and PHPDoc Comments.

= 2.3.2 =

*	ADDED:		Automatic cleaning of GASP Comments Filter and Login Protection database entries (older than 24hrs) using WordPress Cron (everyday @ 6am)
*	CHANGED:	Huge code refactoring to allow for more easily use with other WordPress plugins.

= 2.2.5 =

*	ADDED:		Email sending options for automatic update notifications - options to change the notification email address, or turn it off completely.

= 2.2.4 =

*	FIX:		Small bug fix.
*	CHANGED:	When running a force automatic updates process, tries to remove influence from other plugins and uses only this plugin's automatic updates settings.
*	CHANGED:	A bit of automatic updates code refactoring.

= 2.2.2 =

*	CHANGED:	Changed all options to be disabled by default.
*	CHANGED:	The option for admin notices will turn off all main admin notices except after you update options.

= 2.2.1 =

*	ADDED:		Verified compatibility with WordPress 3.8

= 2.2.0 =

*	CHANGED:	Certain filesystem calls are more compatible with restrictive hosting environments.
*	CHANGED:	Plugin is now ready to integate with [iControlWP automatic background updates system](http://www.icontrolwp.com/2013/11/manage-wordpress-automatic-background-updates-icontrolwp/).
*	FIX:		Login Protection Cooldown feature may not operate properly in certain scenarios.

= 2.1.5 =

*	IMPROVED:	Improved logic for Firewall whitelisting for pages and parameters to ensure whitelisting rules are followed.
*	CHANGED:	The whitelisting rule for posting pages/posts is only for the "content" and the firewall checking will apply to all other page parameters.

= 2.1.4 =

*	FIX:		When you run the Force Automatic Background Updates, it disables the plugins.  This problem is now fixed.

= 2.1.2 =

*	FIX:		A bug that prevented auto-updates of this plugin.
*	FIX:		Not being able to hide translations and upgrade notices.
*	ADDED:		Tweaks to auto-update feature to allow interfacing with the iControlWP service to customize the auto update system.

= 2.1.0 =

*	ADDED:		A button that lets you run the WordPress Automatic Updates process on-demand (so you don't have to wait for WordPress cron).
*	CHANGED:	The plugin now sets more options to be turned on by default when the plugin is first activated.
*	CHANGED:	A lot of optimizations and code refactoring.

= 2.0.3 =

*	FIX:		Whoops, sorry, accidentally removed the option to toggle "disable file editing".  It's back now.

= 2.0.2 =

*	CHANGED:	WordPress filters used to programmatically update whitelists now update the Login Protection IP whitelist

= 2.0.1 =

*	ADDED:		Localization capabilities. All we need now are translators! [Go here to get started](http://translate.icontrolwp.com/).
*	ADDED:		Option to mask the WordPress version so the real version is never publicly visible.

= 1.9.2 =

*	CHANGED:	Simplified the automatic WordPress Plugin updates into 1 filter for consistency

= 1.9.1 =

*	ADDED:		Increased admin access security features - blocks the deactivation of itself if you're not authenticated fully with the plugin.
*	ADDED:		If you're not authenticated with the plugin, the plugin listing view wont have 'Deactivate' or 'Edit' links.

= 1.9.0 =

*	ADDED:		New WordPress Automatic Updates Configuration settings

= 1.8.2 =

*	ADDED:		Notification of available plugin upgrade is now an option under the 'Dashboard'
*	CHANGED:	Certain admin and upgrade notices now only appear when you're authenticated with the plugin (if this is enabled)
*	FIXED:		PHP Notice with undefined index.

= 1.8.1 =

*	ADDED:		Feature- Access Key Restriction [more info](http://icwp.io/2s).
*	ADDED:		Feature- WordPress Lockdown. Currently only provides 1 option, but more to come.

= 1.7.3 =

*	CHANGED:	Reworked a lot of the plugin to optimize for further performance.
*	FIX:		Potential infinite loop in processing firewall.

= 1.7.1 =

*	ADDED:		Much more efficiency yet again in the loading/saving of the plugin options.

= 1.7.0 =

*	ADDED:		Preliminary WordPress Multisite (WPMS/WPMU) Support.
*	CHANGED:	The Firewall now kicks in on the 'plugins_loaded' hook instead of as the actual firewall plugin is initialized (as a result
				of WP Multisite support).

= 1.6.2 =

*	REMOVED:	Automatic upgrade option until I can ascertain what caused the plugin to auto-disable.

= 1.6.1 =

*	ADDED:		Options to fully customize the text displayed by the GASP comments section.
*	ADDED:		Option to include logged-in users in the GASP Comments Filter.

= 1.6.0 =

*	ADDED:		A new section - 'Comments Filtering' that will form the basis for filtering comments with SPAM etc.
*	ADDED:		Option to add enhanced GASP based comments filtering to prevent SPAM bots posting comments to your site.

= 1.5.6 =

*	IMPROVED:	Whitelist/Blacklist IP range processing to better cater for ranges when saving, with more thorough checking.
*	IMPROVED:	Whitelist/Blacklist IP range processing for 32-bit systems.
*	FIXED:		A bug with Whitelist/Blacklist IP checking.

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
