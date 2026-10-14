# Using Akeeba Data Compliance

In this chapter you are going to find detailed reference of all the pages, options, and features of the Akeeba Data
Compliance component.

## <a id="initial-setup"></a>Initial setup

After installing Akeeba Data Compliance there are a few things you need to do to get up to speed. In this section we are
going to set up the minimum required features for the component to make sense.

> [!TIP]
> We strongly recommend trying Data Compliance on a copy of your site first. If you do not know how to make a copy of
> your site we suggest that you take a look at our [Akeeba Backup](https://www.akeeba.com/products/akeeba-backup.html)
> software. The free of charge Akeeba Backup Core version can be easily used to create copies of your site, e.g. running
> them on a local server such as MAMP, XAMPP or WAMPserver.

Before you take any further steps, you need to have a Privacy Statement saved as a Joomla! article. Per the GDPR it has
to be written in plain and simple language, just like the rest of your site. If you are not sure what to write in there
you can search for "GDPR privacy statement template" in your favourite search engine. Here's [an
example](https://www.itgovernance.co.uk/blog/how-to-write-a-gdpr-privacy-notice-with-documentation-template-example/).
Or you could even take a look at what other companies have. For example, here is [our Privacy
Statement](https://www.akeeba.com/privacy). It's best to publish the article in the Uncategorized section of your site.

After doing that go to **Components**, **Akeeba Data Compliance** and click on the **Options** button. Find the **Policy
article** option and click **Select**. Choose the article you have just created. Finally, click on **Save & Close**
button in the toolbar.

Now go to Extensions, Plugins and publish the following plugins _in this order_:

* Data Compliance - Joomla! Core User Data
* User - Data Compliance
* System - Data Compliance

After publishing the last plugin you will immediately see a page asking you to consent to processing of your personal
data and / or exercise your data rights. This is Data Compliance's [self-service page](self-service.md). This is the
same page your site's users will see the first time they log into your site.

You need to select **Yes** and click on **Apply my preference** to continue using your site. You are taken back to the
Plugins page.

This is really all you need to do if the only personal information you are collecting is through Joomla! itself.

If you are collecting information through other components you will need to [activate and configure the relevant plugin
of the `datacompliance` type](data-sources.md). Please note that Data Compliance only ships with a small number of
plugins. You can always ask the developer of the third party extension you are using (e.g. VirtueMart, HikaShop,
AcyMailing etc) to make Data Compliance plugins using our [open, documented API](create-data-source-plugins).

While optional, we recommend that you [set up the email templates](email-templates.md). These are used to send out
emails to your users. The default template is intentionally very generic.

The next logical step is to [configure the component](configuration.md#component-config) to choose who can see sensitive
personal information in Data Compliance.

Finally, you may want to set up CRON jobs for [automating data minimisation](data-minimization.md) (deletion of expired
data profiles).
