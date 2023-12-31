/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

# Drop old tables on update
DROP TABLE IF EXISTS `#__datacompliance_emailtemplates`;
DROP TABLE IF EXISTS `#__datacompliance_cookietrails`;

# Convert all tables to InnoDB
ALTER TABLE `#__datacompliance_exporttrails` ENGINE InnoDB;
ALTER TABLE `#__datacompliance_wipetrails` ENGINE InnoDB;
ALTER TABLE `#__datacompliance_consenttrails` ENGINE InnoDB;
ALTER TABLE `#__datacompliance_usertrails` ENGINE InnoDB;