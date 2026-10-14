# Managing personal information

The whole point of Data Compliance is to allow users to manage their Personally Identifiable Information (PII) they have
stored with you. This means that they can:

* give or withdraw their explicit consent to your processing of their PII;
* export the PII you have on file for them;
* request that you remove or anonymize their PII profile (user profile) from your site.

This allows your site to meet the GDPR requirements without taxing the site's administrators with the laborious tasks
associated with users exercising their data rights.

### Frequently asked questions

Please read these questions before filing GitHub issues or feature requests. There's a reason why we have implemented
certain things in a certain way.

## Can I change the default PII processing preference to be "consent has been given"?

No. This is illegal. The GDPR requires you to obtain explicit consent and also record the date, time, and IP address of
the user giving or revoking their explicit consent. Implied consent is no longer admissible.

## Can I change the default option of the slider to Yes instead of No?

No. The GDPR requires the users to take explicit action to provide their consent. According to most analysts this means
that the default state of a slider / radio selection must be No and the default state of a checkbox must be unchecked.

## Can I limit the consent only to people coming from the EU (GeoIP detection)?

No. The GDPR applies to natural persons based on their _nationality_, not their current location. For example, a German
citizen currently in the United States of America enjoys the same protection under the GDPR as a German citizen
currently in Berlin. This makes using GeoIP detection to determine the nationality of an individual legally unsound.

Things are even more interesting with individuals having dual (or multiple) citizenship. A person who's both a US
citizen and a Czech citizen enjoys the protections of the GDPR regardless of whether they have ever set foot in the
Czech Republic because they are EU citizens (the Czech Republic is in the EU).

## Can an administrator change the PII processing consent of a user?

No. This is illegal. The GDPR requires you to obtain explicit consent and also record the date, time, and IP address of
the user giving or revoking their explicit consent. As a result you cannot give consent on behalf of a user.

While in theory you could be given written permissions or otherwise indisputable evidence of being given the power to
change the user's consent setting we consider it beyond the scope of Data Compliance. These are very rare cases and they
hardly make sense for a web site. Data Compliance is meant to be used by online-only businesses.

## Can an administrator export the PII of a user or delete their profile?

Yes, they can. Their actions are also stored in the audit trail. The rationale behind this decision is that a user may
have lost access to their email address and no longer remember their username or password. They can, however, contact
you and provide evidence of their identity (e.g. a government issued photo ID) along with their request that you
exercise their data rights on their behalf. In this case you are allowed to export the PII or delete the user profile of
a user per their request.

After reading both this and the previous question you might wonder why this rationale doesn't apply to the processing
consent. Instead of applying their consent preference on their behalf you can simply change their email address,
username and / or password so they can log back into your site and apply their consent preference. This means that you
are legally covered: you only changed the user's data on their behalf, something which is also stored in the audit log,
and they get to apply their processing preference -after logging in- per the GDPR requirements.

## Can you create a plugin for a third party extension? It shouldn't be too hard...

No. We do not have the necessary time to analyse how a third party extension works, figure out what PII it stores and
how. Then we would have to create and test the plugin. And then we would have to maintain it forever, even when the
third party extension changes substantially. This is a cost of several thousand Euros per year. It's not easy and it's
not a cost we can afford for an extensions that makes us no money. Selling integrations would require putting a price of
several hundred Euros per year on each to break even, a price everyone would complain about and few would pay.

We made this mistake with Akeeba Subscriptions and its payment plugins. We would create new plugins for just the initial
cost of development. However, the maintenance cost started racking up. We ultimately had to discontinue Akeeba
Subscriptions as a paid product and remove most of the payment plugins since we could no longer afford maintaining them.
Instead of going through the same agony all over again we prefer to be honest and tell you upfront that we can't do
that. No, we cannot make an exception for you. No, really, even if you pay us.

## <a id="captive-login"></a>Captive login (consent page) in the front-end

> [!IMPORTANT]
> This feature requires that the System and User plugins of Data Compliance are published on your site and their access
> level is set to Public.

Akeeba Data Compliance allows users to give or withdraw their explicit consent to your processing of their Personally
Identifiable Information. Per the GDPR, the default state is that no consent is given.

When the user has not provided their consent for their PII to be processed by you they cannot use the logged in section
of the site. The reasoning is that being logged in inherently means that they have a cookie in their browser (Joomla's
session cookie) which identifies them as a specific person. Therefore any action they take on the site can be attributed
to them, the individual, which requires processing of their PII. The only action allowed in these circumstances is for
them to exercise their data rights. That's why Data Compliance implements a “captive login” in this case.

Explicitly: the “captive login” kicks in when the user has not yet consented to you processing their PII _or_ if they
have explicitly withdrawn their consent.

The captive login takes the user to [the Self-Service page](self-service.md) where they can exercise their data rights
(review their consent, export their PII or delete their user profile). They will not be able to visit any other page
unless they either log out first or consent to processing their PII.
