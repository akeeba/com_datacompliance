<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

/**
 * Defaults for the end-to-end suite.
 *
 * docker/run.sh writes a config.php next to this file with the values it actually provisioned; this
 * template is what the suite falls back to when that file is absent. Keep it in step with
 * docker/env.dist — between the two, a fresh clone can run the suite without configuring anything.
 *
 * To point the suite at a site you provisioned some other way, copy this file to config.php (which
 * is git-ignored) and edit it.
 */
return [
	'site'          => [
		// The Apache front-end, as seen from the host running PHPUnit.
		'url'  => 'http://localhost:8120',
		'root' => __DIR__ . '/docker/www',
	],
	'db'            => [
		'host'   => '127.0.0.1',
		'port'   => 33310,
		'name'   => 'dce2e',
		'user'   => 'dce2e',
		'pass'   => 'dce2e',
		'prefix' => 'e2e_',
	],
	'mailpit'       => [
		'url' => 'http://localhost:8145',
	],
	's3'            => [
		// As the SITE sees it, from inside the compose network.
		'endpoint' => 'minio:9000',
		'access'   => 'dce2eaccess',
		'secret'   => 'dce2esecret',
		'bucket'   => 'dc-audit',
	],
	'docker'        => [
		'composeBin'  => 'docker compose',
		'composeFile' => __DIR__ . '/docker/docker-compose.yml',
		'phpService'  => 'php',
	],
	'users'         => [
		'adminUsername' => 'admin',
		'adminPassword' => 'test',
		'adminEmail'    => 'admin@example.test',
		'password'      => 'test',
	],
	'mail'          => [
		'from'     => 'privacy@example.test',
		'fromName' => 'Data Compliance E2E',
	],
	'siblings'      => [
		'ats' => true,
		'ars' => true,
	],
	// Overwritten by run.sh with the versions it actually resolved and installed.
	'joomlaVersion' => '0.0.0',
	'phpVersion'    => '0.0',
];
