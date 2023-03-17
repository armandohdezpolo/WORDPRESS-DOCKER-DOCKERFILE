=== Purge Varnish Cache ===
Contributors: devavi
Donate link: http://avantikayadav.com/donate.html
Tags: varnish, purge, cache, caching, flush, plugin, wp-cache, performance, fast, automatic
Requires at least: 4.0
Tested up to: 6.0.2
Stable tag: 1.1.3
License: GPLv2 or later

Clean clear VARNISH cache automatically when content on your site is created or modified, also allow you to purge VARNISH cache manually.


== Description ==

Purge Varnish Cache provides integration between your WordPress site and multiple Varnish Cache servers. Purge Varnish Cache sends a PURGE request to the URL of a page or post every time based on configured actions and trigger by site administrator. Varnish is a web application accelerator also known as a caching HTTP reverse proxy.

<strong>Features:</strong>
*   Support on all varnish versions of 3.x, 4.x, 5.x and 6.x
*   One time configuration.
*   admin-socket integration and Varnish admin interface for status.
*   unlimited number of Varnish Cache servers.
*   Custom URLs purge.
*   User interface for manual purge.
*   Single click entire cache purge.
*   Debugging.
*   Actively maintained.

<strong>Requirements:</strong> Apache sockets module/extention should be enabled.

<strong>Purpose:</strong> The main purpose of developing this plugin is to deliver updated copy of content to end user without any delay.

<strong>Enhancement Request:</strong> For any further enhancement, please mail me at <a href="mailto:dev.firoza@gmail.com"><strong>dev.firoza@gmail.com</strong></a>

== Installation ==

<strong>How to install Purge Varnish?</strong>

*   Go to your admin area and select Plugins -> Add new from the menu.
*   Search for "Purge Varnish" or download
*   Click install and then click on activate link.

<strong>How to configure settings?</strong>

*   Access the link DOMAIN_NAME/wp-admin/admin.php?page=purge-varnish-settings and configure terminal settings.
*   Access the link DOMAIN_NAME/wp-admin/admin.php?page=purge-varnish-expire and configure required actions and events.
*   Access the link DOMAIN_NAME/wp-admin/admin.php?page=purge-varnish-urls for purge urls from varnish cache.
*   Access the link DOMAIN_NAME/wp-admin/admin.php?page=purge-varnish-all to purge all varnish cache.

== Frequently Asked Questions ==

<strong>How can I check everything's working?</strong>

It is not difficult. Install this plugin and configure Terminal settings using below link.
DOMAIN_NAME/wp-admin/admin.php?page=purge-varnish-settings. 

If status is 'Varnish running' means everything is working perfectly!

<strong>What versions of Varnish is supported?</strong>

Currently it is supporting all varnish versions of 3.x, 4.x, 5.x and 6.x

<strong>How do I manually purge a single URL from varnish cache?</strong>

Click on 'Purge URLs' link or access below link.
DOMAIN_NAME/wp-admin/admin.php?page=purge-varnish-urls.

<strong>What if I have multiple varnish Servers/IPs?</strong>

You need to configure multiple IPs in Varnish Control Terminal textfield in 'Terminal' screen like 127.0.0.1:6082 127.0.0.2:6082 127.0.0.3:6082

<strong>How can I debug?</strong>

Add below constant in wp-config.php file.
<strong>define('WP_VARNISH_PURGE_DEBUG', true);</strong>

It will generate a log file 'purge_varnish_log.txt' inside uploads directory.

<strong>How do I manually purge the whole site cache?</strong>

Clicking on link 'Purge all' or access below link: 
DOMAIN_NAME/wp-admin/admin.php?page=purge-varnish-all.

<strong>What it purge?</strong>

It allow you to configure purge settings.
Please configure by clicking on Expire link or accessing below link.
DOMAIN_NAME/wp-admin/admin.php?page=purge-varnish-expire to configure purge expire setting.Clicking on Expire link 



== Screenshots ==

1. Terminal settings screen for test connectivity from varnish server. 

2. Action trigger configuration screen to make automate purge varnish cache for post expiration.

3. Action trigger configuration screen to make automate purge varnish cache for comment expiration.

4. Action trigger configuration screen to make automate purge varnish cache on menu update.

5. Action trigger configuration screen to make automate purge varnish cache on theme change.

6. Purge whole site cache.

7. Purge URLs screen to purge URLs manually from varnish cache. 


== ChangeLog ==
= 1.1.3 =

Fix: 
1. Make it Comptable with WP 6.0.2


= 1.1.2 =

Fix: 
1. Update Icon

= 1.1.1 =

Fix: 
1. Resolve css conflicts occur with wp-admin elements

= 1.1.0 =

Fix: 
1.  Fix Notice: Undefined index.
2.  AH01071: Got error ‘PHP message: Recieved status code 106

= 1.0.9 =

Fix: 
1.  Fix minor warning issues.
2.  Fix css issues.
3. Re-test on php7.2

= 1.0.8 =

Fix: 
1.  Wrong number of arguments’ errors filling up logs.
2.  Purge Varnish cache on comment post/update on published post.

= 1.0.7 =

Fix: 

1.  Plugin shows white screen after setup.
2.  Multiple Varnish terminals connect message is wrong.


= 1.0.6 =


Fix: 
1.  Plugin shows white screen after setup.
1.  Multiple Varnish terminals connect message is wrong.

Implement Trigger to purge post on comment approved/unapproved
Fix: Wrong number of arguments
Update respected screens.


= 1.0.5 =

Purge Custom URLs
Update screens.

= 1.0.4 =

Enable expire configuration automatically when plug in enabled.
Add more tags.
Update screens.

= Version 2.x =

* PHP 4.x/5.x/6.x/7.x compatibility.

== Upgrade notice ==
....
