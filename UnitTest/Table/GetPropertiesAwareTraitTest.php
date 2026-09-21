<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\UnitTest\Table;

use Akeeba\Component\DataCompliance\Administrator\Table\GetPropertiesAwareTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;

/**
 * GetPropertiesAwareTrait replaces CMSObject::getProperties() for the component's tables.
 *
 * What it returns decides what the S3 audit trail upload and the wipe audit record contain, so it must
 * return the table's columns and nothing private: protected and private members (the table's database
 * driver, dispatcher and so on) must never leak into it.
 *
 * @since 4.1.0
 */
#[CoversTrait(GetPropertiesAwareTrait::class)]
class GetPropertiesAwareTraitTest extends TestCase
{
	/**
	 * Only public properties, by default; everything, when asked.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testPublicPropertiesOnly(): void
	{
		$object = new class {
			use GetPropertiesAwareTrait;

			public $user_id = 42;

			public $type = 'admin';

			public $items = null;

			protected $_db = 'secret-driver';

			private $_secret = 'hidden';
		};

		$public = $object->getProperties();

		$this->assertSame(['user_id' => 42, 'type' => 'admin', 'items' => null], $public);
		$this->assertArrayNotHasKey('_db', $public);

		$all = $object->getProperties(false);

		$this->assertCount(5, $all, 'getProperties(false) must include protected and private members.');
	}
}
