# Other plugins

Akeeba Data Compliance comes with optional plugins. They add functionality to Akeeba Data Compliance.

## <a id="user-plugin"></a>The User plugin

Plugin: User - Data Compliance

> [!WARNING]
> If you disable this plugin logging into the site may result in user accounts being deleted by the Lifecycle Policy
> feature _WITHOUT_ being sent a warning email first! If unsure, publish the plugin.

This plugin is responsible for the following features of Akeeba Data Compliance:

* Add the **Manage your personal data options** link to the user profile edit page.
* Log any changes to the user's personal information (Joomla! core user profile information, user group changes, user
  profile field changes, user custom field changes).
* When a user logs in remove the information from the user profile which indicates that the user has already received
  warning about their account being ready to be removed due to the Lifecycle Policy. This means that next time their
  account is eligible for Lifecycle Policy deletion they will receive a new email. If this information is not removed
  they will not receive any further email in the future if their user account becomes expired again BUT their account
  will be deleted by Lifecycle Policy!

It allows Data Compliance to handle information stored by Joomla! itself, as well as any profile fields managed by third
party components and plugins.
