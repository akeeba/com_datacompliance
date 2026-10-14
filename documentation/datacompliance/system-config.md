# System plugin configuration

The **System - Data Compliance** plugin makes it possible to capture the login of users who have not yet consented to
processing of their personal information. It's advisable to keep it published at all times.

The captive login works by means of redirections. If the current user has not provided their consent (or has withdrawn
their consent) they are immediately redirected to Data Compliance's self service page. Trying to navigate to another
page causes another redirection. This way the user is captured in the self-service page until they provide consent (they
can also log out of the site if they want to).

The same trick may be used by other components to provide a captive login. For example, Akeeba LoginGuard (our Two Step
Verification component for Joomla) does the same thing. To prevent a redirection loop you must let Data Compliance know
which third party components are allowed to be accessed without the user providing their consent. Moreover, you can
specify other components here which don't collect personal information and are therefore allowed (e.g. com_content which
is the Joomla articles component).

The Exempt components / views / tasks takes one specification on each line. Each specification consists of a component,
view and task separated by dots. When you want to match any value in a position using `*`. For example, to allow access
to single article views in com_content use the specification `com_content.article.*`

By default, only Akeeba LoginGuard is allowed to be accessed: `com_loginguard.*.*`
