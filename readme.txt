=== Check Amazon Links ===
Author URI: http://www.linsoftware.com
Plugin URI: http://www.linsoftware.com/amazon-link-checker/
Contributors: LinSoftware
Donate link: http://www.linsoftware.com/support-free-plugin-development/
Tags: amazon, amazon associate, amazon affiliate, affiliate links, link checker
Requires at least: 3.9
Tested up to: 4.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin automatically checks all Amazon links to see if the products are in stock.  It sends daily alerts when products become unavailable.

== Description ==

[Click here to watch a video about this plugin.](https://youtu.be/68AwqGhD6E4)

Check Amazon Links helps you fix Amazon affiliate links by notifying you when Amazon products are out-of-stock.  It
also displays a table of your Amazon links so you can spot other errors too, like missing affiliate tags.

It's designed to use low resources and look up products in the background.  It will find and look-up all Amazon links in your posts and pages that have the ASIN as part of the URL.  It will not work with shortened links or dynamically generated links.

Whether your site has only a few products
 or thousands, this plugin can help keep your site up-to-date and increase your affiliate sales revenue.

Features:

* Parses your posts and pages for Amazon links, including text, image and iframe links.
* Creates a database of all of your Amazon links and displays this information in a table.
* Displays link information in a table under the Tools section of Wordpress Admin.
* Table displays product availability information.
* Affiliate tags are shown for each link, so you can spot missing or incorrect tags.
* Notifies you daily about out-of-stock items.
* Uses the Amazon Product Advertising API to request current inventory status from Amazon.
* Supports Wordpress Multisite

== Installation ==

1. Install the plugin in wp-content\plugins
2. Activate the Plugin
3. Go to Settings->Check Amazon Links
4. Enter your Amazon credentials. (This plugin needs them to function.)
5. Scroll down and click Save Changes.
6. Keep your blog open (any page is fine) so that this plugin can look up Amazon links in the background.
7. Wait awhile.  After 10 minutes or more, go to Tools->Amazon Links
8. A table should show your Amazon links.  See the FAQ if you have problems.

== Frequently Asked Questions ==

= Why are my links not showing up? =

If you followed all of the [installation steps](https://wordpress.org/plugins/check-amazon-link/installation/), but
are not seeing a table of your Amazon products in
Tools->Amazon Links, then there might be a problem that we have to fix.
 Please email us with a URL to one of your blog's posts that feature Amazon products.  You can use this contact form: http://www.linsoftware.com/contact/

= How often are Amazon links checked? =

This depends on how many links you have and how often your website is loaded.  Every time your site it loaded, Amazon links will be checked in the background.  The default setting is that one link is checked every 30 seconds while your website is being viewed.  If you have 120 unique links, all links should be checked about once an hour if you keep your website open continually.

You can speed up the frequency by changing the setting "Minimum Sleep Time Between Amazon Requests."  However, you should be cautious about lowering the sleep time because it may use too many resources and slow your site or exceed allowed CPU cycles.

= What links can this plugin check? =

This plugin is designed to check standard Amazon url links (in &lt;a&gt; tags) and iframe links.  

= Does this plugin work with short Amazon links? =

No, Amazon links that do not contain the ASIN can not be parsed.  For example, it will not find this link: http://amzn.to/1K9bfhx

= Does this plugin work with the "Amazon Link" Wordpress plugin? =

This has not been tested yet.  Plugins that dynamically generate Amazon links may not work correctly with this
plugin.  Testing compatibility is on the to-do list.  Your feedback is appreciated.

= What Amazon sites does this plugin work with? =

It has been tested with Amazon.com, Amazon UK, Amazon Japan, Amazon Canada, Amazon France, and Amazon Mexico.

= What features are coming soon? =

We intend to add more customizations and better options to view and search your Amazon links.

We also are working on a plugin to display similar Amazon items and a plugin to display Amazon price graphs.

Automation options like updating all of your links at once are also in the works.

= Does this plugin work with Wordpress Multisite? =

Yes, version 1.0.3 and above work with Multisite.

== Screenshots ==

1. This plugin creates a table of all your Amazon links.
It is sortable so that you can easily find out-of-stock products, links with no affiliate tag, and more.
To view this table, log into Wordpress Admin, click on "Tools" and "Amazon Links."

2. This is a screenshot of plugin settings.

== Changelog ==

= 1.2.0 =

* Fixed HTML Parsing Bug

= 1.1.9 =

* Fixed Error on Settings Page

= 1.1.8 =

* Feature added: Support for single product links generated with the Wordpress Plugin EasyAzon.
* Feature added: Support for older style Amazon links (those using the obidos system).
* Bug Fix: Parser was finding Amazon links that don't point to a specific product.

= 1.1.7 =

* Fixed security vulnerability.

= 1.1.6 =

* Removed support for Amazon short links (amzn.to). 

= 1.1.5 =

* Minor change to options page.

= 1.1.4 =

* Feature added: Now fully compatible with Paul Stuttard's Amazon Link plugin.

= 1.1.3 =

* Improved Loading Speed of the Amazon Links Table

= 1.1.2 =

* Improved Lookup Speed by up to 10x

= 1.1.1 =

* Fixed issue of slow speed for admin dashboard.

= 1.1.0 =

* Added support for Amazon short links (amzn.to).  This function requires Curl.

= 1.0.9 =

* Improved Error Reporting

= 1.0.8 =

* Fixed error caused by empty posts

= 1.0.7 =

* Added Quality Control Module, which makes reporting errors easy.  

= 1.0.6 = 

* Fixed ajax issue

= 1.0.5 =

* Fixed issues of compatibility with other Amazon plugins

= 1.0.4 =

* Fixed Bug related to Amazon iFrame Links

= 1.0.3 =

* Support for Multisite
* Fixed bugs
* Changed Name to "Check Amazon Links"

= 1.0.2 =

* Fixed error in Javascript file
* Improved option to join mailing list

= 1.0.1 =

* Added option to join mailing list

= 1.0.0: Early September 2015 =

* First official release!

== Upgrade Notice ==

= 1.2.0 =

* Fixed HTML Parsing Bug

= 1.1.9 =

* Fixed Error on Settings Page

= 1.1.8 =

* Feature added: Support for single product links generated with the Wordpress Plugin EasyAzon.
* Feature added: Support for older style Amazon links (those using the obidos system).
* Bug Fix: Parser was finding Amazon links that don't point to a specific product.

= 1.1.7 =

* Fixed security vulnerability.

= 1.1.6 =

* Removed support for Amazon short links (amzn.to). 

= 1.1.5 =

* Minor change to options page.

= 1.1.4 =

* Feature added: Now fully compatible with Paul Stuttard's Amazon Link plugin.
* Bug Fix: Issue with extracting affiliate tag correctly

= 1.1.3 =

* Improved Loading Speed of the Amazon Links Table

= 1.1.2 =

* Improved Lookup Speed by up to 10x

= 1.1.1 =

* Fixed issue of slow speed for admin dashboard.

= 1.1.0 =

* Added support for Amazon short links (amzn.to).  This function requires Curl.

= 1.0.9 =

* Improved Error Reporting

= 1.0.8 =

* Fixed error caused by empty posts

= 1.0.7 =

* Added Quality Control Module, which makes reporting errors easy.

= 1.0.6 = 

* Fixed ajax issue

= 1.0.5 =

* Fixed issues of compatibility with other Amazon plugins

= 1.0.4 =

* Fixed Bug related to Amazon iFrame Links

= 1.0.3 =

* Support for Multisite
* Fixed bugs

= 1.0.2 =

* Fixed error in Javascript file
* Improved option to join mailing list

= 1.0.1 =

* Added option to join mailing list

= 1.0.0 =

* First official release!

