/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2025-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

-- Sites freshly installed on 4.0.3 or later never got the "reason" column: the install SQL lacked it, and
-- Joomla does not run update SQL on a fresh install.
ALTER TABLE "#__datacompliance_consenttrails" ADD COLUMN IF NOT EXISTS "reason" TEXT NULL;
