# Other CLI tools

Akeeba Data Compliance offers some optional command line interface (CLI) tools. CLI tools are meant for advanced users
only who manage their sites using the command line, e.g. through an SSH console. Most people should use the
administrator web interface in Joomla! instead.

## <a id="delete-user-cli"></a>Delete a user from the CLI

Script location: `JOOMLA_ROOT/cli/datacompliance_account_delete.php`

This script deletes any user account. It's equivalent to using the Delete option from the self-service page of that
account.

> [!WARNING]
> THIS IS IRREVERSIBLE. Once you delete it with this script, the user's personal information is gone forever.

You can call this script using the following command line:

```
/usr/local/bin/php JOOMLA_ROOT/cli/datacompliance_account_lifecycle.php --username=foobar
```

where _/usr/local/bin/php_ is the path to your PHP CLI executable and _JOOMLA_ROOT_ is the absolute path to your web
site's root. You can get this information from your host.

The script accepts the following parameters

* `--debug` When present it turns on debug mode. Only for developers. Do not use on live sites.
* `--username` The username you want to delete, e.g. `--username=foobar`
* `--id` The numeric user ID you want to delete, e.g. `--id=123`
* `--force` Do not ask for confirmation, delete the user anyway. DANGEROUS!!!
* `--dry-run` No deletions will take place. The script will run normally and output all actions it would be taking
  without really deleting any user account. Useful to test what will happen.
