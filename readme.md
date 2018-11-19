Acumulus web service API library
================================

@author: Buro RaDer http://www.burorader.com/  
@copyright: SIEL BV https://www.siel.nl/acumulus/  
@license: GPLv3  
@support: https://forum.acumulus.nl/index.php?board=17.0  

Introduction
------------
This Acumulus web service API library was written to simplify development of
client side code that communicates with the Acumulus web service. It therefore
specifically aims at a Dutch based public.

It is currently used by the extensions for the webshop software of HikaShop,
Magento, PrestaShop, OpenCart, VirtueMart and WooCommerce, but was built to be
easily usable with other webshop/CMS software systems as well.

Note to extension/plugin/module reviewers
-----------------------------------------
This is a cross webshop/CMS library and can therefore not comply with often
conflicting coding standards and guidelines for a specific webshop or CMS
package. We ask for your understanding in these.

This library uses:

 - The PSR-2 coding standards.
 - Phpdoc to fully document each and every part of the code.
 - When needed, its own PSR-4 autoloader to circumvent the fact that many
   webshop/CMS systems still live in the pre PSR4 autoloading era, meaning that
   if an autoloader already exists it often won't work with the PSR4 standard.
 - Its own translation system to present this library in English and Dutch.
 - Its own form renderer/builder.
 
This library may deviate from specific coding standards in a.o. these ways:

 - Classes are placed in namespaces following the PSR4 structure and may
   therefore need to register its own autoloader function and not use the
   webshop/CMS specific one.
 - Class constants are in StudlyCaps.
 - Class properties are in camelCase.
 - Form definitions are placed in the library and are also rendered/built in
   the library, but do follow form guidelines specific to the webshop/CMS
   package.
 - Translation is done using an own included translation system and is only
   done in Dutch and English. As the online SIEL Acumulus service only targets
   people running a small business in the Netherlands this is not seen as a
   limiting factor.
   
License
-------
This library is licensed under the GNU GPLv3 Open Source license. The English
and only official text can be found on: http://www.gnu.org/licenses/gpl.html.
A non-binding Dutch version can be found on:
http://bartbeuving.files.wordpress.com/2008/07/gpl-v3-nl-101.pdf.
Both texts are also delivered as part of this library.

Development
------------
This library is still under development and will:

 - where necessary be adapted to work with even more webshop packages.
 - be extended with new features.
 - where possible and useful be further abstracted.

In doing so, backwards compatibility is a consideration but not a must. Because
the webshop plugins are delivered with this library this is no problem for the
users, However, developers that are developing their own software on top of this
library should carefully check new releases for backwards incompatibilities.
