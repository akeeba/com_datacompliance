# Automating data minimisation actions

The GDPR calls for data minimisation, i.e. removing the personally identifiable information of users when it's no longer
needed for business purposes. While there are no hard limits in the law, it's generally agreed that a reasonable amount
of time for needing PII for business purposes is around 6 months after the client last had an active business
relationship with you. On most sites this means about 6 months after the last time the user logged in, had an active
subscription and so on.

Akeeba Data Compliance lets you automatically notify users with expired profiles that their profile will be deleted
after a specific date unless they log in or take another action to affirm a business relation with you before that date.
After that date and only if the notification email has been sent these expired profiles will be deleted in the same way
as if the user chose to delete their profile. This is called a “lifecycle policy” deletion. These actions take place
through command line scripts which can be automated with CRON jobs.

> [!IMPORTANT]
> Your host must support real CRON jobs which lets you run PHP scripts on the command line. If you can only set up a URL
> in your “CRON jobs” it will NOT work with Akeeba Data Compliance. We are not trying to be difficult about this. We
> know how to write a web interface alternative, as evidenced by doing the same with Akeeba Backup, it's just that we
> haven't found the time yet to do so.

The conditions to consider a profile “expired”, i.e. the user account lifecycle policies for your site, are controlled
by the options of the Data Compliance plugins.

## <a id="cron-notify"></a>Notify users before their profile is automatically deleted

Script location: `JOOMLA_ROOT/cli/datacompliance_lifecycle_notify.php`

This script notifies users that their account is going to be deleted due to user account lifecycle policies on your site
and gives them information to prevent that. The Akeeba Data Compliance - Email plugin must be enabled for this to work
correctly. Your site must be configured to be able to send out email. Check your Joomla Global Configuration if you are
not sure.

You can schedule this script using the following command line to your host's CRON interface:

```
/usr/local/bin/php JOOMLA_ROOT/cli/datacompliance_lifecycle_notify.php --confirm=0
```

where _/usr/local/bin/php_ is the path to your PHP CLI executable and _JOOMLA_ROOT_ is the absolute path to your web
site's root. You can get this information from your host.

The script accepts the following parameters

* `--debug` When present it turns on debug mode. Only for developers. Do not use on live sites.
* `--period interval_spec` How long before the account expiration date should users be notified? The interval_spec is a
  [PHP DateInterval interval specification](http://php.net/manual/en/dateinterval.construct.php). The default value is
  `P1M` which means one month.
* `--confirm` Should I ask for confirmation before sending out each email? Default is 1 (ask for confirmation, expecting
  input from the keyboard). When using in CRON scripts use `--confirm=0` to prevent this behaviour.
* `--dry-run` No emails will be sent. The script will run normally and output all actions it would be taking without
  really sending out the emails. Useful for the first few runs while you're getting things set up.
