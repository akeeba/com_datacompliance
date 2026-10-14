# Installation, updates, and upgrades

## <a id="installing"></a>Installing Akeeba Data Compliance

Installing Akeeba Data Compliance is no different than installing any other Joomla!™ extension on your site. You can
read the complete instructions for installing Joomla!™ extensions on the [official help
page](https://docs.joomla.org/Installing_an_extension). Throughout this chapter we assume that you are familiar with
these instructions and we will try not to duplicate them.

### Installing or manually updating the extension

Just like with most Joomla! extensions there are two ways to install or manually update Akeeba Data Compliance on your
site:

* Install from URL. It is the easiest and fastest one, if your server supports it. Most servers do support this method.
* Upload and install. That's the typical extension installation method for Joomla! extensions. It rarely fails.

Please note that installing and updating Akeeba Data Compliance (and almost all Joomla! extensions) is actually the same
thing. If you want to update Akeeba Data Compliance please remember that you **MUST NOT** uninstall it before installing
the new version! When you uninstall Akeeba Data Compliance you will lose all your settings. This is definitely something
you do not want to happen! Instead, simply install the new version on top of the old one. Joomla! will figure out that
you are doing an update and will treat it as such, automatically.

> [!TIP]
> If you find that after installing or updating Akeeba Data Compliance it is missing some features or doesn't work,
> please try installing the same version a second time, without uninstalling the component. The reason is that very few
> times the Joomla! extensions installer infrastructure gets confused and fails to copy some files or entire folders. By
> repeating the installation you force it to copy the missing files and folders, solving the problem.

#### <a id="installing-from-url"></a>Install from URL

The easiest way to install Akeeba Data Compliance is using the Install from URL feature in Joomla!.

> [!IMPORTANT]
> This Joomla! feature requires that your server supports fopen() URL wrappers (`allow_url_fopen` is set to 1 in your
> server's `php.ini` file) or has the PHP cURL extension enabled. Moreover, if your server has a firewall, it has to
> allow TCP connections over ports 80 and 443 to GitHub's servers where the file is stored. If you don't see any updates
> or if they fail to download please ask your host to check that these conditions are met. If they are met but you still
> do not see the updates please file a bug report in the [official Joomla! forum](http://forum.joomla.org/).

First, go to [the download page for Akeeba Data
Compliance](https://www.akeeba.com/download/official/datacompliance.html). Find the latest version (at the top of the
page). Right click the ZIP file and copy the URL.

Now go to your site's administator page and click on **Extensions**, **Manage**. Click on the **Install from URL** tab.
Clear the contents of the **Install URL** field and paste the URL you copied from our site's download page. Then click
on the **Install** button. Joomla! will download and install the Akeeba Data Compliance update.

If Joomla! cannot download the package, please use one of the methods described in this section of the documentation.
If, however, you get an error about copying files, folder not found, or a cryptic "-1" error please follow [our
installation troubleshooting instructions](https://www.akeeba.com/documentation/troubleshooter/abinstallation.html).

#### <a id="installing-upload"></a>Upload and install

You can download the latest installation packages from [the download page for Akeeba Data
Compliance](https://www.akeeba.com/download/official/datacompliance.html). Please note that the latest version is always
on top. Click on the ZIP file version you want to download and install.

All Akeeba Data Compliance installation packages contain the component and all of its associated extensions. Installing
it will install all of these items automatically. It can also be used to upgrade Akeeba Data Compliance; just install it
_without_ uninstalling the previous release.

In any case, do not extract the ZIP files yet!

> [!WARNING]
> **Attention Mac OS X users**! Safari, the default web server provided to you by Apple, is automatically extracting the
> ZIP file into a directory and removes the ZIP file. In order to install the extension through Joomla!'s extensions
> installer you must select that directory, right-click on it and select Compress to get a ZIP file of its contents.
> This behaviour was changed in Mac OS X Mountain Lion, but people upgrading from older versions of Mac OS X (Mac OS X
> Lion and earlier) will witness the old, automatic ZIP extraction, behaviour.

Log in to your site's administrator section. Click on **Extensions**, **Manage** link on the top menu. Please click on
the **Upload Package File** tab. Drag and drop the installation ZIP file you had previously downloaded to start the
upload and the installation. After a short while, Joomla!™ will tell you that the component has been installed.

> [!WARNING]
> Akeeba Data Compliance is a big extension. Some servers do not allow you to upload files that big. If this is the case
> you can ask your host to follow [our installation troubleshooting
> instructions](https://www.akeeba.com/documentation/troubleshooter/abinstallation.html) under "You get an error about
> the package not being uploaded to the server".
>
> If you have WAMPServer (or any other prepackaged local server), please note that its default configuration does not
> allow files over 2Mb to be uploaded. To work around that you will need to modify your php.ini and restart the server.
> On WAMPserver left-click on the WAMP icon (the green W), click on **PHP**, **php.ini**. Find the line beginning with
> `upload_max_filesize`. Change it so that it reads:
>
> ```
> upload_max_filesize = 6M
> ```
>
> Save this file. Now, left-click on the WAMP icon, click on **Apache**, **Service**, **Restart Service** and you can
> now install the component. Editing the `php.ini` file should also work on all other servers, local and live alike.

If the installation did not work, please take a look at [our installation troubleshooting
instructions](https://www.akeeba.com/documentation/troubleshooter/abinstallation.html).
