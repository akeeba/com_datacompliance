## Joomla and PHP Compatibility

We are developing, testing and using Akeeba Data Compliance using the latest version of Joomla! and a popular and actively maintained branch of PHP 7. At the time of this writing this is:

* Joomla! 3.9
* PHP 7.2

Akeeba Subscriptions should be compatible with:

* Joomla! 3.8, 3.9
* PHP 7.0, 7.1, 7.2, 7.3.

## Changelog

New features

* Protection of all component and plugin folders against direct web access
* Export data: support for Joomla “privacy” group plugins

Bug fixes

* [VERY HIGH] Immediate failure accessing the backend of the component [gh-27]
* [HIGH] ATS export, causing fatal error
* [LOW] Cookie compliance fails with a fatal error when the server does not set the HTTP_HOST environment variable
* [LOW] PHP 7.3 warning in the Control Panel page
* [LOW] The cookie consent code was outside the BODY tag
