# Data sources configuration (`datacompliance` plugins)

Akeeba Data Compliance, the component, does not know what personally identifiable information looks like, where to find
it and where to handle it. This is the job of the plugins of the `datacompliance` type. These provide the interface to
the personally identifiable data sources and take actions on that data.

## <a id="source-joomla"></a>Joomla! Core User Data

Plugin: Data Compliance - Joomla! Core User Data

It allows Data Compliance to handle information stored by Joomla! itself, as well as any profile fields managed by third
party components and plugins.

## Options

**Exempt user groups**. The users who belong in these user groups cannot be deleted, either manually or automatically
(lifecycle policies). Please note that users who belong in any group which has the Super User privilege are always
exempt, regardless of this setting.

**User account lifecycle**. Do you want this plugin to tell Akeeba Data Compliance which user records are considered
expired according to the options below? If you set this to No the following options are ignored.

**Last login threshold (months)**. User accounts which have not logged into your site for at least this many months are
considered expired.

**Never visited**. User accounts who have never visited your site after the user account creation are considered
expired.

**Blocked accounts**. User accounts marked as Blocked are considered expired. **THIS IS DANGEROUS**! Joomla will also
mark user accounts as blocked when the user tries to reset their password and until they do so. This may result in
active user accounts being deleted because they were trying to reset their password. We recommend AGAINST using this
option.
