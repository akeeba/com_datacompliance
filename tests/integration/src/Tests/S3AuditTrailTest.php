<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\IntegrationTest\Tests;

defined('_JEXEC') or die;

use Akeeba\DataCompliance\IntegrationTest\AbstractE2ETestCase;

/**
 * plg_datacompliance_s3: every wipe audit record is also uploaded to an S3 bucket, so the audit trail
 * survives the site.
 *
 * The bucket is a real S3 API (MinIO, inside the compose network), so the plugin's connector, request
 * signing and upload are exercised for real.
 *
 * @since 4.1.0
 */
class S3AuditTrailTest extends AbstractE2ETestCase
{
	/**
	 * Set up: the plugin pointed at the stack's MinIO; no administrator notifications (they are tested
	 * in WipeNotificationTest).
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$s3 = static::$config->getS3();

		static::$fixtures->setPluginParams('datacompliance', 's3', [
			'access'          => $s3['access'],
			'secret'          => $s3['secret'],
			'bucket'          => $s3['bucket'],
			'path'            => 'e2e-audit',
			'method'          => 'v4',
			'region'          => 'us-east-1',
			'ssl'             => '0',
			'pathaccess'      => '1',
			'class'           => 'STANDARD',
			'custom_endpoint' => $s3['endpoint'],
		], true);
		static::$fixtures->setPluginParams('datacompliance', 'email', ['admins' => 0]);
	}

	/**
	 * Tear down: plugin off again.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function tearDown(): void
	{
		static::$fixtures->setPluginParams('datacompliance', 's3', [], false);
		static::$fixtures->setPluginParams('datacompliance', 'email', ['admins' => 1]);

		parent::tearDown();
	}

	/**
	 * A wipe uploads its audit record, named after the user id, and without personal data.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testWipeUploadsTheAuditRecord(): void
	{
		$id     = static::$fixtures->createUser();
		$before = $this->userRow($id);
		$wiper  = $this->loggedIn('wiper');

		$response = $this->requestWipe($wiper, $id);

		$this->assertNotServerError($response);
		$this->assertCount(1, $this->wipeTrails($id), 'The wipe did not happen; the test would prove nothing.');

		$bucket = static::$config->getS3()['bucket'];

		[$exitCode, $listing] = $this->cli()->mc(['ls', '--recursive', 'e2e/' . $bucket . '/e2e-audit/']);

		$this->assertSame(0, $exitCode, $listing);

		$uploaded = (bool) preg_match('/\b' . $id . '_[0-9a-f]{40}\.json\b/', $listing, $match);

		$this->assertTrue(
			$uploaded,
			'The wipe audit record is never uploaded to S3. S3::getS3Connector() checks class_exists() for Akeeba Backup\'s engine class name (Akeeba\\Engine\\Postproc\\Connector\\S3v4\\Connector) instead of the bundled Akeeba\\S3\\Connector, so it always throws "Could not get the Composer autoloader."; and even with that fixed, it calls setUseLegacyPathStyle() BEFORE setEndpoint(), which resets path-style access for any custom (S3-compatible) endpoint. Failures are logged, then reported as success.'
		);

		[, $object] = $this->cli()->mc(['cat', 'e2e/' . $bucket . '/e2e-audit/' . $match[0]]);

		// `docker compose run` may print its own progress lines around the object.
		$start  = strpos($object, '{');
		$record = $start === false ? null : json_decode(substr($object, $start, strrpos($object, '}') - $start + 1), true);

		$this->assertIsArray($record, "The uploaded audit record is not JSON:\n" . $object);
		$this->assertSame($id, (int) $record['user_id']);
		$this->assertSame('admin', $record['type']);
		$this->assertStringNotContainsString($before['email'], $object, 'The uploaded audit record holds personal data.');
		$this->assertStringNotContainsString($before['username'], $object, 'The uploaded audit record holds personal data.');
	}
}
