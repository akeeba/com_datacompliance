# Upload user deletion audit trail to S3-compatible storage

Plugin: Data Compliance - Upload user deletion audit trail to S3-compatible storage

Uploads the user deletion audit log records to Amazon S3 or another S3-compatible storage provider. One JSON file per
deleted user account is uploaded. You should configure it to use a bucket with a suitable policy which disallows
deletion of files. This helps you positively fulfil the recommendation of having an immutable audit log for data
deletion actions on your site.

## Options

**Access Key**

Your Amazon S3 Access Key

**Secret Key**

Your Amazon S3 Secret Key

**Use HTTPS**

If enabled, HTTPS will be used to connect to Amazon S3. Strongly recommended.

**Bucket**

The name of your Amazon S3 bucket where your files will be stored in. The bucket must be already created; the
application can not create buckets.

> [!WARNING]
> **DO NOT CREATE BUCKETS WITH NAMES CONTAINING UPPERCASE LETTERS. AMAZON CLEARLY WARNS AGAINST DOING THAT**. If you use
> a bucket with uppercase letters in its name it is very possible that the application will not be able to upload
> anything to it.
>
> Please note that this is a limitation imposed by Amazon itself. It is not something we can "fix" in the application.
> If this is the case with your site, please create a new bucket whose name only consists of lowercase unaccented latin
> characters (a-z), numbers (0-9), dashes and dots.
>
> Moreover, you cannot use a bucket name with a dot in its filename together with the Use HTTPS option. This is a
> limitation of the HTTPS setup in Amazon S3 servers and cannot be worked around.

**Amazon S3 Region**

Please select which S3 Region you have created your bucket in. This is MANDATORY for using the newer, more secure, v4
signature method. You can see the region of your bucket in your Amazon S3 management console. Right click on a bucket
and click on Properties. A new pane opens to the left. The second row is labelled Region. This is where your bucket was
created in. Go back to Akeeba Data Compliance and select the corresponding option from the drop-down.

> [!IMPORTANT]
> If you choose the wrong region the connection WILL fail.

**Custom region**

Only used when you select **Custom / None** in the **Amazon S3 Region** option above. Enter the region identifier to use
with the v4 signature method here. This is useful for third-party, S3-compatible storage providers which are not Amazon
S3 itself.

**Signature method**

This option determines the authentication API which will be used to "log in" the backup engine to your Amazon S3 bucket.
You have two options:

* **v4 (preferred for Amazon S3)**. Recommended. You MUST specify the Amazon S3 Region in the option above. This option
  implements the newer AWS4 (v4) authentication API. Buckets created in Amazon S3 regions brought online after January
  2014 (e.g. Frankfurt) will only accept this option. Older buckets will work with either option.
* **v2 (legacy mode, third party storage providers)**. Legacy option. We strongly advise against using it unless you
  have a specific reason we can't think of.

**Directory**

The directory inside your Amazon S3 bucket where your files will be stored in. If you want to use subdirectories, you
have to use a forward slash, e.g. `directory/subdirectory/subsubdirectory`.

**Bucket access**

How the plugin should access your bucket. If unsure, use **Virtual Hosting (recommended)**, the recommended and
supported method for Amazon S3, which accesses the bucket using a URL of the form `https://BUCKET.ENDPOINT`. **Path
Access (legacy)** is a deprecated, unsupported method which accesses the bucket using a URL of the form
`https://ENDPOINT/BUCKET`. Only use Path Access if a third party, S3-compatible storage provider specifically asks you
to.

**Storage Class**

This determines how Amazon will store your files. We recommend using Standard - Infrequent Access. This offers decent
data durability and results in smaller storage costs if you don't retrieve the files too often, as is the case with the
audit log files. Not all third-party, S3-compatible storage providers support every storage class listed here, or indeed
any storage class other than Standard; consult your provider's documentation.

**Custom endpoint**

For use with third party storage services which implement an S3-compatible API. Enter the endpoint (API URL) of the
third party storage service here. **If you are using Amazon S3 you MUST leave this blank.**

**Alternate date format**

Only shown when a **Custom endpoint** is set. Only disable this setting if Akeeba support asks you to.

**Use the HTTP header Date instead of X-Amz-Date**

Only enable this setting if Akeeba support asks you to.
