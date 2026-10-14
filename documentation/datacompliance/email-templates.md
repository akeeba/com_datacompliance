# Setting up the email templates

![Email templates](images/control_panel.png)

_Email templates_

Akeeba Data Compliance can send out emails to the user or the administrator on different occasions. More specifically,
it can send out emails when a user profile is deleted by the user themselves, when an administrator deletes a user
profile, when the profile is deleted by the [lifecycle policy CRON job](data-minimization.md) or as a notification
before the profile is deleted by the lifecycle policy.

> [!IMPORTANT]
> The Data Compliance - Send emails on account deletion plugin must be activated and have the access level Public for
> emails to work.

Each template can be customised _per language_. Akeeba Data Compliance will determine the correct language to use by
looking at the user profile. If no language preference is set for the user's site (frontend) language, it will use the
site's default language as defined in Joomla's Languages page. Then it will try to find the email template in this
language. If this is not possible, it will try to fall back to the en-GB (English, Great Britain) email template. If
that's not found either it will try to fall back to the email template marked as "(All languages)". If none is found
still no email will be sent. Only enabled email templates are used.

While Data Compliance comes with built in email templates we strongly advise you to customise them to match your site's
branding and the tone of language you commonly use. All email templates fully support HTML.

> [!WARNING]
> All URLs in your emails, be it links to your site or static media files such as images, _must_ be absolute – they must
> contain the protocol and domain name to your site. Some Joomla editors will convert absolute links to relative on
> saving and convert them back to absolute when you try to edit the content. For example, JCE (JoomlaContentEditor.net)
> does this. This will NOT work with emails.
>
> We recommend that you use CodeMirror, the HTML source code editor shipped with Joomla, to edit email templates.

The Subject and Email body of each email template can contain the following variables, in square brackets and all
capital letters. They are expanded to the correct text right before the email is sent.

* \[NAME\] The full name of the user on whom the action is taken
* \[EMAIL\] The email address of the user
* \[USERNAME\] The Joomla username of the user
* \[REGISTERDATE\] The date the user account was registered on the site
* \[LASTVISITDATE\] The last time the user logged into their user account on the site
* \[REQUIRERESET\] Is a password reset required (0/1)
* \[RESETCOUNT\] How many times the password has been reset in the past
* \[LASTRESETTIME\] Last date and time (GMT) the password was reset
* \[ACTIVATION\] Activation code (there's no point using it, it's hashed)
* \[BLOCK\] Is the user account blocked? (Yes/No)
* \[ID\] The user's numeric account ID
* \[PARAM:_param_name_\] The value of the user profile parameter _param_name_. For advanced users.
* \[SITENAME\] The name of your site, as configured in Joomla
* \[SITEURL\] The URL to your site
* \[ACTIONS\] A bullet list of the actions which will be taken when the account is wiped
* \[ADMIN:NAME\] The administrator's full name, when sending emails to an administrator
* \[ADMIN:USERNAME\] The administrator's username, when sending emails to an administrator
* \[ADMIN:EMAIL\] The administrator's email address, when sending emails to an administrator

You can take a look at the default email templates to see how these variables can be used inside a template.
