## Joomla and PHP Compatibility

We are developing, testing and using Akeeba Data Compliance using the latest version of Joomla! and a popular and actively maintained branch of PHP 7. At the time of this writing this is:

* Joomla! 3.9
* PHP 7.3

Akeeba Subscriptions should be compatible with:

* Joomla! 3.9, 4.0
* PHP 7.1, 7.2, 7.3, 7.4, 8.0.

At the time of this writing PHP 8.0 has not been officially released yet. Support for PHP 8 is, therefore, tentative at this point.

## Changelog

**New features**

* CLI tool to replay the audit log
* Custom error pages when FEF is not installed or an unhandled PHP exception occurred

**Miscellaneous changes**

* Replace zero datetime with nullable datetime (gh-32)
* **DEPRECATED**: The cookie plugin only works with Joomla 3 and will be removed in version 2.0. We strongly recommend not using services which set third party cookies. It not only respects your users' privacy, it also allows you to run a site _without_ a cookie banner! 
* Update path to cacert.pem
* Use Joomla's domain name when setting the cookie acceptance, er, cookie
* Banner links to EU sites should have `rel="noopener"`

**Bug fixes**

* `[LOW]` Minified JS / CSS was not being loaded by default