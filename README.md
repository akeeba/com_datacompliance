# Data Compliance

A tool to help comply with the European Union's General Data Protection Directive 

## What does it do?

The component allows the site's visitors to:

- give or revoke their consent for personal data processing (and prevent the user from using the site if they have not provided consent).
- export all data we have on them to a commonly machine readable format (XML).
- exercise their right to be forgotten (account removal) with a concrete audit trail.

The component also keeps an audit log of all the user profile changes, data exports and account removal.

The account removal audit log can be automatically exported to S3 (in a JSON format) or a filesystem location or email and replayed at a later time. This lets you comply with the GDPR when restoring older backups. Note that this audit log does NOT include any personally identifiable information, just the anonymous IDs of the information deleted.  

There are CLI plugins included to schedule periodic removal of stale accounts (lifecycle management).

## License 

This component is distributed under the GNU General Public License version 3 or, at your option, any later version published by the Free Software Foundation.

## Download

Stable releases are available from [our site's Downloads page](https://www.akeebabackup.com/download/official/datacompliance.html).

More current features and bug fixes may be found in the development branch. However, you will need to build the package yourself to access them.

## Support policy

We do not provide any end user support.

If you are a developer and want to contribute a bug fix or small feature please send a Pull Request. If it's a more significant feature you want to contribute please file an issue first, explaining your use case, how you propose to address it and what is your timeline for writing the code. We will get back to you within a week at most. 

## Prerequisites

In order to build the installation packages of this component you will need to have the following tools:

* A command line environment. Using Bash under Linux / Mac OS X works best. On Windows you will need to run most tools through an elevated privileges (administrator) command prompt on an NTFS filesystem due to the use of symlinks. Press WIN-X and click on "Command Prompt (Admin)" to launch an elevated command prompt.
* A PHP CLI binary in your path
* Command line Git executables
* Phing

You will also need the following path structure inside a folder on your system

* **com_datacompliance** This repository
* **buildfiles** [Akeeba Build Tools](https://github.com/akeeba/buildfiles)
* **fof** [Framework on Framework](https://github.com/akeeba/fof)
* **fef** [Akeeba Front-end Framework](https://github.com/akeeba/fef)
* **translations** [Akeeba Translations](https://github.com/akeeba/translations)

You will need to use the exact folder names specified here.

## Building a dev release

Go inside `com_datacompliance/build` and run `phing git -Dversion=0.0.1.a1` to create a development release. The installable Joomla! ZIP package file is output in the `com_datacompliance/release` directory.