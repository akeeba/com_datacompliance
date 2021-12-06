/*
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

CREATE TABLE IF NOT EXISTS `#__datacompliance_exporttrails` (
    `datacompliance_exporttrail_id` bigint(20)          NOT NULL AUTO_INCREMENT,
    `user_id`                       bigint(20) unsigned NOT NULL,
    `created_on`                    datetime            NOT NULL,
    `created_by`                    bigint(20)          NOT NULL,
    `requester_ip`                  varchar(255)        NOT NULL,
    PRIMARY KEY (`datacompliance_exporttrail_id`),
    KEY `#__datacompliance_exporttrail_user` (`user_id`)
) DEFAULT COLLATE utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `#__datacompliance_wipetrails` (
    `datacompliance_wipetrail_id` BIGINT(20)                        NOT NULL AUTO_INCREMENT,
    `user_id`                     bigint(20)                        NOT NULL,
    `type`                        enum ('lifecycle','user','admin') NOT NULL DEFAULT 'user',
    `created_on`                  datetime                          NOT NULL,
    `created_by`                  bigint(20)                        NOT NULL,
    `requester_ip`                varchar(255)                      NOT NULL,
    `items`                       longtext,
    PRIMARY KEY (`datacompliance_wipetrail_id`)
) DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__datacompliance_consenttrails` (
    `created_on`   datetime     NOT NULL,
    `created_by`   bigint(20)   NOT NULL,
    `requester_ip` varchar(255) NOT NULL,
    `enabled`      int(1)       NOT NULL DEFAULT 0,
    PRIMARY KEY (`created_by`)
) DEFAULT COLLATE utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `#__datacompliance_usertrails` (
    `datacompliance_usertrail_id` BIGINT(20)   NOT NULL AUTO_INCREMENT,
    `user_id`                     bigint(20)   NOT NULL,
    `created_on`                  datetime     NOT NULL,
    `created_by`                  bigint(20)   NOT NULL,
    `requester_ip`                varchar(255) NOT NULL,
    `items`                       longtext,
    PRIMARY KEY (`datacompliance_usertrail_id`)
) DEFAULT COLLATE utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__datacompliance_cookietrails`;

DROP TABLE IF EXISTS `#__datacompliance_emailtemplates`;