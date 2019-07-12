## Joomla and PHP Compatibility

We are developing, testing and using Akeeba Data Compliance using the latest version of Joomla! and a popular and actively maintained branch of PHP 7. At the time of this writing this is:

* Joomla! 3.9
* PHP 7.2

Akeeba Subscriptions should be compatible with:

* Joomla! 3.8, 3.9
* PHP 7.1, 7.2, 7.3.

## Changelog

* Rewritten cookie plugin to work with caching enabled
* Consent can be transcribed from Joomla's privacy consent user profile field (migration from Joomla to DataCompliance)
* Transcribing consent given during subscription only applies to Akeeba Subscriptions 5 and 6
* Compatibility with Akeeba Subscriptions 7 for data export and user anonymisation (Users model has been removed)
* User Profile fields not displayed correctly when using an Edit Profile menu item
