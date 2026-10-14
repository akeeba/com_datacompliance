# Automatically delete expired user profiles

Script location: `JOOMLA_ROOT/cli/datacompliance_account_lifecycle.php`

This script deletes expired user accounts as long as they have already been notified. The Akeeba Data Compliance - Email
plugin must be enabled for emails to be sent out to users when their accounts are being deleted. In this case your site
must be configured to be able to send out email. Check your Joomla Global Configuration if you are not sure.

You can schedule this script using the following command line to your host's CRON interface:

```
/usr/local/bin/php JOOMLA_ROOT/cli/datacompliance_account_lifecycle.php --confirm=0
```

where _/usr/local/bin/php_ is the path to your PHP CLI executable and _JOOMLA_ROOT_ is the absolute path to your web
site's root. You can get this information from your host.

The script accepts the following parameters

* `--debug` When present it turns on debug mode. Only for developers. Do not use on live sites.
* `--memdebug` Displays additional information about memory consumption. For developers and power users.
* `--force` Delete expired user accounts even if they have not been notified. This is useful for the first run on a site
  with thousands of expired accounts you do not want to notify before deleting. **THIS IS EXTREMELY DANGEROUS!** Use it
  with --dry-run first to make sure that it does what you think it should be doing.
* `--confirm` Should I ask for confirmation before deleting each user? Default is 1 (ask for confirmation, expecting
  input from the keyboard). When using in CRON scripts use `--confirm=0` to prevent this behaviour.
* `--dry-run` No deletions will take place. The script will run normally and output all actions it would be taking
  without really deleting any user account. Useful for the first few runs while you're getting things set up.
