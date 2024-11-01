=== Shipi ===
Contributors: aarsiv
Tags: shipping, woocommerce, label, rates, tracking
Requires at least: 4.0.1
Tested up to: 6.7
Stable tag: 1.0.1
Requires PHP: 5.6
License: GPLv3 or later License
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Integrate 3+ Shipping carriers with Woocommerce Store.

== Description ==
The Shipi plugin provides a seamless integration with the shipping service, allowing you to create and manage shipping labels directly from your WordPress site. The plugin facilitates easy access to shipping API for fetching shipping rates and generating labels.

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/shipi` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the Shipi menu to configure the plugin settings.


== Third-Party Services ==
This plugin uses external services provided by [Shipi](https://app.myshipi.com/).

The plugin interacts with the following Shipi API endpoints:

- `https://app.myshipi.com/api/link-site.php` for linking the site.
- `https://app.myshipi.com/embed/label.php` for embedding labels.
- `https://app.myshipi.com/label_api/entry.php` for label entry.
- `https://app.myshipi.com/assets/img/brand/` for loading brand images.
- `https://app.myshipi.com/carriers.php` for loading carrier details.
- `https://app.myshipi.com/rates_api/shipi_rates.php` for retrieving shipping rates.

For more information on the services provided by Shipi, you can refer to their [Terms of Use](https://myshipi.com/terms) and [Privacy Policy](https://myshipi.com/privacy).

== Frequently Asked Questions ==

= How do I create a shipping label? =
To create a shipping label, navigate to the Shipi menu in your WordPress dashboard and follow the instructions provided.

== Changelog ==
= 1.0.1 =
* New Wordpress version tested
= 1.0.0 =
* Initial release.