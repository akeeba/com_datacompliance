# Enforced PHP and Joomla version limits

Every version of Akeeba Data Compliance is only tested against, and only supports, a specific range of PHP and Joomla
versions, as published on its download page. As of Akeeba Data Compliance 4.1, these version limits are _actively
enforced_ by the software itself, not just documented. This is part of our compliance with the European Union Cyber
Resilience Act (EU CRA): we can only vouch for the security of our software, and honour our security risk assessment,
within the specific PHP and Joomla versions it was designed for.

Depending on _how_ you are using Akeeba Data Compliance when the incompatibility is hit, you will see one of the
following symptoms.

* **Using the component's backend interface.** Akeeba Data Compliance will refuse to run and will display a clear error
  message telling you whether you need to upgrade PHP, upgrade Joomla, or upgrade Akeeba Data Compliance itself, and
  which versions are supported.
* **Using Joomla Scheduled Tasks, Joomla CLI, or CRON.** If the site is running an incompatible version of PHP or
  Joomla, the Console - Akeeba Data Compliance plugin will not load at all. Joomla itself has no way to tell you _why_ a
  plugin did not load; instead it will report that the command you are trying to run — typically
  `datacompliance:lifecycle:notify`, `datacompliance:lifecycle:delete`, or `datacompliance:account:delete` — does not
  exist. Whenever you see this message you should treat it as a _possible_ PHP or Joomla version incompatibility and
  investigate as follows:
  1. Log into your site's backend and go to **Components**, **Data Compliance**. If PHP or Joomla on your site is
     incompatible with this version of Akeeba Data Compliance you will see the error message described above, telling
     you exactly what needs to be upgraded.

  1. The PHP version used to run a CLI command can be different than the one your site runs under on the web server,
     e.g. because your host provides several PHP CLI binaries and your CRON job uses the wrong one, or an environment
     default was changed. To find out which PHP version is actually used when your CRON job runs, create a file called
     `test.php` inside your site's `cli` directory with this content:

     ```
     <?php echo PHP_VERSION;
     ```

     Then run it with the very same PHP binary and path you use in your CRON job, replacing `joomla.php arguments` with
     `test.php`, for example:

     ```
     /usr/local/bin/php /home/USER/webroot/cli/test.php
     ```

     This will print the PHP version your CRON job actually runs under. If it is lower than the minimum, or equal to or
     higher than the maximum, PHP version supported by your version of Akeeba Data Compliance you will need to adjust
     your CRON job to use a compatible PHP CLI binary. Remember to delete `test.php` once you are done; it is not part
     of Akeeba Data Compliance and will not be removed automatically.

> [!NOTE]
> In every one of the above cases the underlying issue is the same: the site's PHP or Joomla version falls outside the
> range supported by the installed version of Akeeba Data Compliance. The fix is always one of upgrading PHP, upgrading
> Joomla, or upgrading (or, rarely, downgrading) Akeeba Data Compliance to a version whose supported range matches your
> server's PHP and Joomla versions — consult [our Compatibility page](https://www.akeeba.com/compatibility.html) to find
> a compatible combination.
