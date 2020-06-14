## Joomla and PHP Compatibility

We are developing, testing and using Akeeba Data Compliance using the latest version of Joomla! and a popular and actively maintained branch of PHP 7. At the time of this writing this is:

* Joomla! 3.9
* PHP 7.3

Akeeba Subscriptions should be compatible with:

* Joomla! 3.9, 4.0
* PHP 7.1, 7.2, 7.3, 7.4.

## Changelog

**New features**

* Joomla 4 compatibility (pre-release beta 1)

**Miscellaneous changes**

* Use JAccess instead of DB queries [gh-29]

**Bug fixes**
* (LOW) Public site pages flash when the cookie plugin is enabled
* (LOW) Empty box when viewing the Options page of a different user as an admin
* (MEDIUM) Cookie popup shows up in Off-line mode but cannot be acted upon
* (HIGH) Cookies plugin would fail when using path-style URLs to access articles (thank you @brianteeman for reporting this)
* (HIGH) When an admin viewed the Options page for a different user the displayed current consent preference was wrong
* (HIGH) When an admin deleted a user's profile the audit trail showed the user deleting themselves instead of the admin
* (HIGH) Export may fail if the Akeeba Subscriptions plugin is enabled and the user was created after AS7 was installed