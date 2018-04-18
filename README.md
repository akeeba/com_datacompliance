# Data Compliance (com_datacompliance)

A component to help us with the EU GDPR compliance.

The component allows the user to
- export all data we have on them to a commonly machine readable format (XML)
- exercise their right to be forgotten (account removal) with a concrete audit trail

If the user has purchased a subscription within the last 2 months we cannot comply with the account removal immediately BUT we can include it as a planned, non-revokable removal. CRON jobs will ensure compliance with the planned removal.

The audit trail records the user ID, IP address and timestamp the deletion took place. A copy of this information in JSON format is a. sent under a randomly generated filename to an S3 bucket and b. emailed to the admin for safekeeping. This allows replay of the audit log when restoring backups. 

Moreover we include CLI scripts to prepare us for data compliance and for automating tasks.