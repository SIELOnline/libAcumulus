Requirements for the Acumulus web shop extensions
=================================================

Note that this information will be updated regularly but will be out-of-date
even faster. So the quick changelog here should give you an idea of the
recentness of this information.

Updates:
- System requirements: valid for our version 8.2.0.
- Supported shops and versions: july 2024.
- Supported PHP versions: july 2024.

System Requirements
-------------------

- PHP:
    * Minimum version: 8.0, older versions WILL fail.
    * Recommended version: 8.1, soon - probably plugin version 8.3, this will become the
      minimally required version, and older versions WILL fail.
    * See the PHP versions table further below for which PHP versions can be used for
      which shop.
- Database:
    * MySql: 5.6 or later.
    * MariaDb: 10.0 or later.

Supported shops and versions
----------------------------
The tags below are also used further on in the changelog to indicate to which
shop(s) a change applies (if no tag is mentioned, ALL may be assumed).

Notes:

- The supported versions column is tricky. We only use the latest (locally
  installed) version of a shop for testing. New features of our module may use
  features of the shop as were available when our new feature was developed.
  As it is often not clear when a new API feature became available, this may
  unknowingly lead to our module failing on older versions of the shop.
- Joomla 5(.1) has not yet been tested but will be in the near future.

| Tag | Shop             | Version used for testing | Supported versions                  |
|-----|------------------|--------------------------|-------------------------------------|
| ALL | All web shops.   |                          |                                     |
| HS  | HikaShop (JOO)   | 5.1.0 (starter)          | >= 4.0.0                            |
| JOO | Joomla (+ HS)    | 4.4.6                    | >= 4.2.5                            | 
| MA  | Magento          | 2.4.6 (community)        | >= 2.4, 2.3 might still work        |
| OC  | OpenCart         | 3.0.3.9                  | >= 3.0                              |
|     |                  | 4.0.2.3                  | >= 4.0                              |
| PS  | PrestaShop       | 8.1.7                    | >= 8.1                              |
| VM  | VirtueMart (JOO) | 4.2.0                    | >= 4.0.5                            |
| JOO | Joomla (+ VM)    | 4.4.6                    | >= 4.2.5                            |
| WC  | WooCommerce      | 9.1.0                    | >= 5.0 (3 and 4 may work)           |
|     | WordPress        | 6.6                      | >= 5.9 (earlier versions WILL fail) |

Supported PHP versions
----------------------
This is an overview of which PHP versions can be used with the supported shops.

Notes:
- Latest, or almost latest, point releases are used of the listed PHP versions.
- WooCommerce is listed in combination with WordPress 6.6 Note that other plugins gave
  warnings in my test environment, but those were paid plugins that I cannot upgrade, and
  thus for which I have an outdated version.
- Other installed plugins/modules may not have been updated to the latest
  PHP version as supported by the shop or our plugin, or may, on the contrary,
  already require a newer version than the shop does. This table cannot keep
  track of that.
- HikaShop forum: "4.4.3 fully supports PHP 8.0"
- HikaShop changelog: 4.6.0 first fix mentioning 8.1; 5.0.1 latest fix mentioning 8.1
- HikaShop changelog: 4.7.2 first fix mentioning 8.2; 5.1.0 latest fix mentioning 8.2

| Tag | Shop Version      | 8.0 | 8.1 | 8.2 | 8.3 | Remarks                                        |
|-----|-------------------|-----|-----|-----|-----|------------------------------------------------|
| JOO | 4.4.6             | ❓  | ✅  | ❓  | ❓  | JOO 4 on PHP 8.0 warns abut EOL               |
| JOO | 5.1               | ❓  | ✅  | ❓  | ❓  | JOO 5 on PHP 8.0 warns abut EOL               |
| HS  | 5.1.0 (starter)   | ❓  | ✅  | ❓  | ❓  |                                               |
| MA  | 2.4.6 (community) | ❌  | ✅  | ✅  | ❓  |                                               |
| OC  | 3.0.3.9           | ✅  | ✅  | ❌  | ❓  | 3.0.3.9 is said to be compatible with PHP 8.1 |
|     | 4.0.2.3           | ✅  | ✅  | ❓  | ❓  |                                               |
| PS  | 8.1               | ✅  | ✅  | ❓  | ❓  | Warnings get logged on PHP 8.1                |
| VM  | 4.2.0             | ❓  | ✅  | ❓  | ❓  | VM 4.2 still produces warnings on 8.1         |
| WC  | 9.1.0             | ✅  | ✅  | ❓  | ❓  | Many warnings from other plugins on 8.1       |
