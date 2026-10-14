# Technology overview

Akeeba Data Compliance is more than just a component. In fact, its component is only used to render the interface and
handle some common work. Most of the features are implemented as plugins to ensure extensibility, even by third parties.

The System - Data Compliance plugin is used to implement the captive login page which requires your clients to give
their consent to data processing before continuing to use your site.

The User - Data Compliance plugin is used to add links to Data Compliance in user profiles and handle other aspects of
data processing consent.

There are a number of plugins of the `datacompliance` type. These are the integrations with personal information data
sources, i.e. Joomla itself and third party components. Each of these plugins informs Data Compliance of the personal
information kept for each user, lets it delete this information, informs Data Compliance whether a user profile can be
deleted and provides information about which profiles are to be considered expired. Data Compliance ships with a number
of plugins for core Joomla! and Akeeba extensions. The API is open and any third party developer can create their own
plugins for their software.
