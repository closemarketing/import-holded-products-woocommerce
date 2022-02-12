=== Sync Holded for WooCommerce or Easy Digital Downloads ===
Contributors: closemarketing, davidperez, sacrajaimez, freemius
Tags: holded, woocommerce
Donate link: https://close.marketing/go/donate/
Requires at least: 4.0
Requires PHP: 5.6
Tested up to: 5.9
Stable tag: 2.0
Version: 2.0

Syncs Products and data from Holded software to WooCommerce or Easy Digital Downloads.

== Description ==

This plugin allows you to import simple products from Holded to WooCommerce. 

It creates a new menu in WooCommerce > Products > Import products from Holded.

You can import simple products, and it will create new products if it does not find the SKU code from your WooCommerce. If the SKU exists, it will import all data except title and description from the product. The stock will be imported as well.

¡We have a PRO version!
These are the features:
- Import categories from Holded.
- Import attributes as brands or others.
- Import variable products.
- Automate the syncronization.
- Sync Orders to Holded.
- Import pack products from Holded.

[You could buy it here](https://en.close.technology/connect-woocommerce-holded/)

== Installation ==

Extract the zip file and just drop the contents in the wp-content/plugins/ directory of your
WordPress installation and then activate the Plugin from Plugins page.

== Developers ==
[Official Repository GitHub](https://github.com/closemarketing/import-holded-products-woocommerce)

== Changelog ==
= 2.0 =
*   Removed Freemius as engine sell.
*   Removed Support to Easy Digital Downloads.
*   Add Tags as list (separated with commas).
*   Add VAT Info in checkout.
*   Option to Company field in checkout.
*   PRO: Add PDF generated from Holded.
*   PRO: Better sync management WooCommerce Action Scheduler.
*   Refactoring code from free and fremium.
*   Select design in document holded.

= 1.4 =
*   Option to not create document if order is free.

= 1.3 =
*   Sync orders to Holded (PRO) automatically and force manually for past orders.
*   Sync Pack products to Holded (PRO).
*   Fix: Attributes duplicated in variation product not imported.
*   Fix: Categories not imported in simple products.

= 1.2 =
*   Automate your syncronization! (PRO).
*   Option email when is finished (PRO).
*   Fix sku saved for EDD.
*   Better metavalue search for SKU.
*   Fix Holded Pagination (thanks to itSerra).
*   Fix SKU variation (thanks to itSerra).

= Earlier versions =

For the changelog of earlier versions, please refer to the separate changelog.txt file.

== Links ==
*	[Closemarketing](https://close.marketing/)
*	[Closemarketing plugins](https://profiles.wordpress.org/closemarketing/#content-plugins)
