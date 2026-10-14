# Configuration

GDPR compliance is most definitely not a one-size-fits-all affair. It is tied to the way your site is collecting and
using personally identifiable information. Decisions about how to delete this data or when a profile is expired, or when
it shouldn't be deleted, may also vary depending on your business needs and what your lawyer has advised you.

Akeeba Data Compliance gives you plenty of options to tailor it to your needs. These options can be found either at the
component itself or its plugins.

## <a id="component-config"></a>Component configuration

Component configuration is accessible from the backend of your site in the same way that's customary for all Joomla!
components. The easiest way is going to **Components**, **Akeeba Data Compliance** and clicking the **Options** button
in the toolbar.

Alternatively, you can go to **System**, **Global Configuration** and click on **Akeeba Data Compliance** from the
left-hand list of Components.

## Consent Page Options

The first tab is called **Consent Page Options** and it controls the way the self-service page of Data Compliance works
on your site.

The **Policy article** option lets you choose a Joomla article which contains your site's Privacy Statement per the GDPR
requirements. Remember that when the self-service page is shown to a user who has not yet provided their consent they
cannot navigate outside of that page. Therefore it's advisable to not include any links to other pages on your site in
this article as they will be inaccessible to the user.

The **Show export button** and **Show delete button** options control whether the button to export the personal
information to XML and to delete the user profile, respectively, will be shown to the user. If they are not shown you
will have to take and fulfil these requests manually.

The **Maximalist export** option controls what the personal information export includes. When it's set to Yes (default)
the export includes everything stored about the user, including authentication material such as their Multi-factor
Authentication configuration and backup codes, “remember me” tokens, their Joomla! API token, and their download IDs.
This mirrors what Joomla's own privacy export does. When it's set to No this authentication material is removed from the
export, and download IDs are masked so that only their last four characters are shown. The user's password hash is never
included in the export, regardless of this option.

## Permissions

This is the standard Joomla! ACL configuration page for the component. Apart from Joomla's typical permissions, Akeeba
Data Compliance comes with its own, special permissions.

**Audit trails** controls whether the backend user can view any of the audit trails kept by Akeeba Data Compliance.
Please note that this is a unique case of a permission in that it controls _viewing information_, not taking an action
on it.

**Delete profiles** controls whether the user is allowed to delete the profiles of other users.

**Export profiles** controls whether the user is allowed to export the personal information of other users. Only Super
Users can export the personal information of other Super Users.

Due to the way Joomla! works, Super Users (that is: any user belonging to a group with the Super User privilege) has all
of these privileges and _they can not be taken away from them_. For better GDPR compliance it's advisable to create a
new user group with more permissions than Administrator but less than Super User for those administrators who need
extended privileges on the site but who should not be able to take actions or view personally identifiable information
of other users. Doing that is outside the scope of Akeeba Data Compliance: the tools to do that (Permissions) are given
to you by Joomla itself!
