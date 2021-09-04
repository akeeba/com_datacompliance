# Data Compliance

A tool to facilitate GDPR conformance of your Joomla! sites

[Download](https://www.akeeba.com/download/official/datacompliance.html) • [Documentation](https://www.akeeba.com/documentation/data-compliance.html)

## What does it do?

The component allows the site's visitors to:

- give or revoke their consent for personal data processing (and prevent the user from using the site if they have not provided consent).
- export all data we have on them to a commonly machine readable format (XML).
- exercise their right to be forgotten (account removal) with a concrete audit trail.

The component also keeps an audit log of all the user profile changes, data exports and account removal.

The account removal audit log can be automatically exported to S3 (in a JSON format). This lets you comply with the GDPR requiring you to keep an audit trail of your compliance to personal data requests. Note that this audit log does NOT include any personally identifiable information, just the anonymous IDs of the information deleted.  

There is a Joomla CLI integration plugin. You can use the CLI commands to, among other things, schedule periodic removal of stale accounts. This lets you comply with the data minimisation requirement of the GDPR.

## Copyright notice and license 

Akeeba Data Compliance — A tool to facilitate GDPR conformance of your Joomla! sites
Copyright (C) 2018-2021  Nicholas K. Dionysopoulos / Akeeba Ltd

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the  GNU General Public License for more details.

You should have received a copy of the GNU General Public License  along with this program.  If not, see [https://www.gnu.org/licenses/](https://www.gnu.org/licenses/).

## Support policy

We do not provide any end user support.

If you are a developer and want to contribute a bug fix or small feature please send a Pull Request. 

If it's a more significant feature you want to contribute please file an issue first, explaining your use case, how you propose to address it and what is your timeline for writing the code.

## Building the component (for developers)

In order to build the installation packages of this component you will need to have the following tools:

* A command line environment. Using Bash under Linux / Mac OS X works best. On Windows you will need to run most tools through an elevated privileges (administrator) command prompt on an NTFS filesystem due to the use of symlinks. Press WIN-X and click on "Command Prompt (Admin)" to launch an elevated command prompt.
* A PHP CLI binary in your path
* Command line Git executables
* Phing

You will also need the following path structure inside a folder on your system

* **com_datacompliance** This repository
* **buildfiles** [Akeeba Build Tools](https://github.com/akeeba/buildfiles)

You will need to use the exact folder names specified here.

## Building a dev release

Go inside `com_datacompliance/build` and run `phing git -Dversion=0.0.1.a1` to create a development release. The installable Joomla! ZIP package file is output in the `com_datacompliance/release` directory.