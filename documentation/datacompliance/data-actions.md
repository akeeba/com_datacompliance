# Managing data actions

As [already explained](backend-pii-management.md), exporting and deleting users is possible through the Joomla! User
Manager. However, this is not a convenient interface to find out which profiles are expired and so on. This is why Data
Compliance comes with its own pages to manage data actions.

## <a id="expired-profiles"></a>Expired user profiles

This page displays all users whose profiles have expired. Expired profiles are determined by your Data Compliance plugin
settings. Some of the profiles cannot be deleted even though they are expired, e.g. when they belong to a privileged
user group. These profiles appear in pink background colour.

> [!NOTE]
> Why not hide the profiles which cannot be deleted? For performance reasons.
>
> Identifying expired profiles is a relatively fast query with the same criteria for all users. This means that we can
> get thousands of users matching these criteria and paginate the results for display.
>
> Finding the exceptions, which expired profiles cannot be deleted, is a set of slow queries which have to be run per
> user. On sites with thousands of users, like our own business site, executing these queries would take a lot of time
> and memory. In fact, it would end up crashing either PHP or the server itself.
>
> When we display a page of a few dozen expired profiles we can run these slow queries. Even though the page takes a
> while to display it doesn't crash PHP or the server. The difference is that in this case we run the slow queries to a
> fraction of all the expired profiles at a time, therefore taking a fraction of the processing power required. The only
> drawback is that we cannot exclude the exceptions from the display.

Each profile has a Manage Data Options button at the right-hand corner. This takes you to the page to manage data
options, i.e. export the user's profile or delete it.

You can search by the maximum expiration date and by user. The user search box allows you to type a numeric user ID or a
partial username, full name, or email address.
