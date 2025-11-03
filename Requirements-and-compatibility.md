Requirements for the Acumulus web shop extensions
=================================================

Note that this information will be updated regularly but will be out-of-date
even faster. So the quick changelog here should give you an idea of the
recentness of this information.

Updates:
- System requirements: valid for our version 8.7
- Supported shops and versions: October 2025
- Supported PHP versions: October 2025.

System Requirements
-------------------

- PHP:
    * Minimum version: 8.1, older versions WILL fail.
    * Recommended version: 8.3, soon, probably plugin version 8.8, this will become the
      minimally required version, and older versions WILL fail.
    * See the PHP versions table further below for which PHP versions can be used for
      which shop (version).
- Database:
    * MySql: 5.6 or later.
    * MariaDb: 10.0 or later.

Supported shops and versions
----------------------------
The tags below are also used further on in this overview to indicate to which
shop(s) a change applies (if no tag is mentioned, ALL may be assumed).

Notes:

- The supported versions column is tricky. We only use the latest (locally installed)
  version of a shop for testing. New features of our module may use features of the shop
  as were available when that new feature of our module was developed. As it is often not
  clear when a new API feature became available, this may unknowingly lead to our module
  failing on older versions of the shop.
- VirtueMart 4.6.0 has not yet been tested but will be in the (near) future.
- Joomla 6 has not yet been tested but will be in the (near) future.

| Tag | Shop             | Version used for testing | Supported versions                  |
|-----|------------------|--------------------------|-------------------------------------|
| ALL | All webshops.    |                          |                                     |
| HS  | HikaShop (JOO)   | 6.1.0 (starter)          | >= 4.0.0                            |
| JOO | Joomla (+ HS)    | 5.4.0                    | >= 4.2.5                            | 
| MA  | Magento          | 2.4.8 (community)        | >= 2.4, 2.3 might still work        |
| OC  | OpenCart         | 3.0.3.9                  | >= 3.0.3.9                          |
|     |                  | 4.0.2.3                  | >= 4.0                              |
| PS  | PrestaShop       | 9.0.0                    | >= 8.1                              |
| VM  | VirtueMart (JOO) | 4.4.4                    | >= 4.0.5                            |
| JOO | Joomla (+ VM)    | 5.4.0                    | >= 4.2.5                            |
| WC  | WooCommerce      | 10.3.3                   | >= 5.0 (9.1 for stock management!)  |
|     | WordPress        | 6.8.3                    | >= 5.9 (earlier versions WILL fail) |

Supported PHP versions
----------------------
This is an overview of which PHP versions can be used with the supported shops.

Notes:

- Last, or almost last, point releases are used of the listed PHP versions.
- WooCommerce is listed in combination with the WordPress version used at that moment.
  Note that most warnings on PHP 8.4 came from other (popular) plugins, though WooCommerce
  emitted also some warnings.
- Other installed plugins/modules may not have been updated to the latest PHP version as
  supported by the shop or our plugin, or may already require a newer version than the
  shop does. This table cannot keep track of that.
- HikaShop forum: "4.4.3 fully supports PHP 8.0"
- HikaShop changelog: 4.6.0 first fix mentioning 8.1; 5.0.1 last fix mentioning 8.1
- HikaShop changelog: 4.7.2 first fix mentioning 8.2; 5.1.0 last fix mentioning 8.2
- HikaShop changelog: 5.1.1 first fix mentioning 8.3; 6.0.0 last fix mentioning 8.3
- HikaShop changelog: 6.0.0 first fix mentioning 8.4;
- Joomla 5.4 still emits quite some warnings on 8.4

| Tag | Shop Version      | 8.1 | 8.2 | 8.3 | 8.4 | Remarks                                       |
|-----|-------------------|-----|-----|-----|-----|-----------------------------------------------|
| JOO | 4.4.6             | ✅   | ❓   | ❓   | ❓   |                                               |
| JOO | 5.4               | ✅   | ✅   | ✅   | ❌   | Warnings on PHP 8.4 (Implicit nullable types) |
| HS  | 6.1.0 (starter)   | ✅   | ✅   | ✅   | ❌   |                                               |
| MA  | 2.4.6 (community) | ✅   | ✅   | ✅   | ✅   |                                               |
| OC  | 3.0.3.9           | ✅   | ❌   | ❌   | ❌   | 3.0.3.9 is said to be compatible with PHP 8.1 |
|     | 4.0.2.3           | ✅   | ❓   | ❓   | ❓   |                                               |
|     | 4.1.0.3           | ✅   | ✅   | ✅   | ❓   |                                               |
| PS  | 9.0               | ✅   | ✅   | ✅   | ✅   | Warnings on PHP 8.4 (Implicit nullable types) |
| VM  | 4.4.8             | ✅   | ✅   | ✅   | ✅   | Warnings on PHP 8.4 (Implicit nullable types) |
| WC  | 10.3.3            | ✅   | ✅   | ✅   | ✅   | Warnings on PHP 8.4 (Implicit nullable types) |
