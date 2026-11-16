# Workarounds for core Joomla bugs

Re-check on every Joomla release.

- **None at the moment.** There used to be one for HTML mail escaping (`Helper\TemplateEmails` pre-escaped the
  values itself because core Joomla's `MailTemplate::send()` did not escape `addUnsafeTags()` tags inside
  com_mails' HTML layout, up to at least 6.1.3). Current core does (5.4.10 and 6.2.0 pass `$isHtml = true`
  there), so the workaround was removed — keeping it escapes every value twice. `sendMail()` now only
  calls `addUnsafeTags()`. The integration test `WipeNotificationTest::testUserIsNotifiedAtTheirOriginalAddress`
  catches a regression in either direction. If a Joomla release inside the supported range stops
  escaping again, that test fails on that release.
