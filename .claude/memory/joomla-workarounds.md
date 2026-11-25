# Workarounds for core Joomla bugs

Re-check on every Joomla release.

- **HTML mail escaping** (`Helper\TemplateEmails::coreEscapesHtmlLayout()`, `usesHtmlLayout()`, `escapeForHtml()`):
  some releases' `MailTemplate::send()` put the tags into com_mails' HTML layout without escaping the
  `addUnsafeTags()` ones. On those, and only when the layout is used, `sendMail()` passes pre-escaped values for the
  HTML part and raw values for the plain text part. Escaping on any other release shows entities to the user.
  From core's source (5.4.0 – 6.2.1): 5.4 fixed in 5.4.9; 6.0 never (up to 6.0.4); 6.1 fixed in 6.1.4; 6.2 fixed in
  6.2.0-rc1. The 5.4.9-rc1 and 6.1.4-rc1 pre-releases do not have the fix.
  **Why:** the supported range includes releases on both sides, so neither core's escaping nor ours is enough alone.
  The workaround was removed once, after testing only fixed releases.
  **How to apply:** do not remove it while the supported range includes an affected release. On every Joomla release,
  check the `replaceTags()` call on the rendered layout, and its layout decision against `usesHtmlLayout()`. Test with
  `run.sh --joomla=X.Y.Z --filter=WipeNotificationTest` on both sides of each boundary: the default run only tests the
  newest Joomla, which hides a regression on older ones.
