/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

CREATE TABLE IF NOT EXISTS "#__datacompliance_exporttrails"
(
    "datacompliance_exporttrail_id" SERIAL       NOT NULL,
    "user_id"                       BIGINT       NOT NULL,
    "created_on"                    TIMESTAMP    NOT NULL,
    "created_by"                    BIGINT       NOT NULL,
    "requester_ip"                  VARCHAR(255) NOT NULL,
    PRIMARY KEY ("datacompliance_exporttrail_id")
);

CREATE INDEX IF NOT EXISTS "#__datacompliance_exporttrail_user" ON "#__datacompliance_exporttrails" ("user_id");

CREATE TABLE IF NOT EXISTS "#__datacompliance_wipetrails"
(
    "datacompliance_wipetrail_id" SERIAL       NOT NULL,
    "user_id"                     BIGINT       NOT NULL,
    "type"                        VARCHAR(9)   NOT NULL DEFAULT 'user' CHECK ("type" IN ('lifecycle', 'user', 'admin')),
    "created_on"                  TIMESTAMP    NOT NULL,
    "created_by"                  BIGINT       NOT NULL,
    "requester_ip"                VARCHAR(255) NOT NULL,
    "items"                       TEXT,
    PRIMARY KEY ("datacompliance_wipetrail_id")
);

CREATE TABLE IF NOT EXISTS "#__datacompliance_consenttrails"
(
    "created_on"   TIMESTAMP    NOT NULL,
    "created_by"   BIGINT       NOT NULL,
    "requester_ip" VARCHAR(255) NOT NULL,
    "enabled"      SMALLINT     NOT NULL DEFAULT 0,
    PRIMARY KEY ("created_by")
);

CREATE TABLE IF NOT EXISTS "#__datacompliance_usertrails"
(
    "datacompliance_usertrail_id" SERIAL       NOT NULL,
    "user_id"                     BIGINT       NOT NULL,
    "created_on"                  TIMESTAMP    NOT NULL,
    "created_by"                  BIGINT       NOT NULL,
    "requester_ip"                VARCHAR(255) NOT NULL,
    "items"                       TEXT,
    PRIMARY KEY ("datacompliance_usertrail_id")
);

DROP TABLE IF EXISTS "#__datacompliance_cookietrails";

DROP TABLE IF EXISTS "#__datacompliance_emailtemplates";