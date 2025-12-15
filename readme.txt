=== Niyago Postcodes ===
Contributors: gengbiz
Tags: woocommerce, postcode, checkout, autofill, malaysia
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Auto-fill city and state when postcode is entered in WooCommerce checkout.

== Description ==

Niyago Postcodes automatically fills in the city and state fields when a customer enters their postcode during WooCommerce checkout. This speeds up the checkout process and reduces errors.

**Features:**

* Auto-fill city and state based on postcode
* Works with WooCommerce classic checkout
* Works with WooCommerce Blocks checkout
* Option to reorder fields (Postcode -> City -> State)
* Currently supports Malaysia postcodes
* Lightweight and fast - uses local data, no external API calls

**How it works:**

1. Customer enters their postcode
2. Plugin looks up the postcode in local database
3. City and state fields are automatically filled
4. Green highlight shows which fields were auto-filled

== Installation ==

1. Upload the `niyago-postcodes` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Postcodes to configure settings

== Frequently Asked Questions ==

= Which countries are supported? =

Currently Malaysia postcodes are included. More countries may be added in future updates.

= Does this plugin make external API calls? =

No. All postcode data is stored locally within the plugin. No external services are used.

= Does it work with WooCommerce Blocks checkout? =

Yes, the plugin supports both the classic WooCommerce checkout and the newer Blocks-based checkout.

= Can I change the field order? =

Yes. Go to WooCommerce > Postcodes and enable the field reorder option to show Postcode before City and State.

== Screenshots ==

1. Settings page under WooCommerce menu
2. Auto-fill in action on checkout page

== Changelog ==

= 1.0.4 =
* Security improvements
* Code cleanup

= 1.0.3 =
* Added WooCommerce Blocks checkout support
* Improved field detection

= 1.0.2 =
* Added field reorder option
* Bug fixes

= 1.0.1 =
* Improved state matching
* Performance improvements

= 1.0.0 =
* Initial release
* Malaysia postcode support

== Upgrade Notice ==

= 1.0.4 =
Security improvements and code cleanup. Recommended update for all users.
