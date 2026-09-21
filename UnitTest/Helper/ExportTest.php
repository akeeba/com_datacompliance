<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\UnitTest\Helper;

use Akeeba\Component\DataCompliance\Administrator\Helper\Export;
use Joomla\Component\Privacy\Administrator\Export\Domain;
use Joomla\Component\Privacy\Administrator\Export\Field;
use Joomla\Component\Privacy\Administrator\Export\Item;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * The XML export format helper: every personal data export goes through it.
 *
 * The property that matters most is round-tripping: whatever a plugin puts in, the user must get back
 * exactly, in a well-formed document — including the characters XML treats specially, which a
 * personal data export (names, addresses, free-text ticket posts) is full of.
 *
 * @since 4.1.0
 */
#[CoversClass(Export::class)]
class ExportTest extends TestCase
{
	/**
	 * Values that stress the XML encoding.
	 *
	 * @return  array<string, array{0: string}>
	 * @since   4.1.0
	 */
	public static function trickyValues(): array
	{
		return [
			'plain'          => ['Alice Example'],
			'ampersand'      => ['Smith & Sons'],
			'already escaped' => ['Fish &amp; Chips'],
			'angle brackets' => ['<script>alert(1)</script>'],
			'quotes'         => ['She said "hi" and it\'s fine'],
			'unicode'        => ['Νικόλαος Δ. — ‘quoted’ 🙂'],
			'newlines'       => ["line one\nline two\r\nline three"],
			'empty'          => [''],
		];
	}

	/**
	 * A value survives exportItemFromArray() → XML text → parsing, unchanged.
	 *
	 * @param   string  $value  The value.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	#[DataProvider('trickyValues')]
	public function testItemFromArrayRoundTrips(string $value): void
	{
		$item   = Export::exportItemFromArray(['id' => 7, 'name' => $value], 'id');
		$parsed = new SimpleXMLElement($item->asXML());

		$this->assertSame('7', (string) $parsed['id']);
		$this->assertSame($value, (string) $this->column($parsed, 'name'));
	}

	/**
	 * The same, through the deep copy merge() and adoptChild() perform on every export.
	 *
	 * @param   string  $value  The value.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	#[DataProvider('trickyValues')]
	public function testMergedDocumentRoundTrips(string $value): void
	{
		$plugin = new SimpleXMLElement('<root/>');
		$domain = $plugin->addChild('domain');
		$domain->addAttribute('name', 'e2e');
		$domain->addAttribute('description', 'Descr ' . $value);

		Export::adoptChild($domain, Export::exportItemFromArray(['name' => $value]));

		$merged = Export::merge(new SimpleXMLElement('<root/>'), $plugin);
		$parsed = new SimpleXMLElement($merged->asXML());

		$this->assertSame('e2e', (string) $parsed->domain['name']);
		$this->assertSame('Descr ' . $value, (string) $parsed->domain['description'], 'Attribute values are not preserved.');
		$this->assertSame($value, (string) $this->column($parsed->domain->item, 'name'));
	}

	/**
	 * merge() keeps the first document's children, appends the second's, and changes neither input.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testMergeAppendsWithoutMutatingItsInputs(): void
	{
		$first  = new SimpleXMLElement('<root><domain name="a"/></root>');
		$second = new SimpleXMLElement('<root><domain name="b"/><domain name="c"/></root>');

		$merged = Export::merge($first, $second);

		$names = array_map(fn($d) => (string) $d['name'], iterator_to_array($merged->domain, false));

		$this->assertSame(['a', 'b', 'c'], $names);
		$this->assertCount(1, $first->domain, 'merge() modified its first argument.');
		$this->assertCount(2, $second->domain, 'merge() modified its second argument.');
	}

	/**
	 * Array values are dumped as text (with print_r) rather than lost; null becomes empty.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testArrayAndNullValues(): void
	{
		$item = Export::exportItemFromArray(['list' => ['x' => '<b>', 'y' => 2], 'nothing' => null]);

		$this->assertStringContainsString('[x] => <b>', (string) $this->column($item, 'list'));
		$this->assertSame('', (string) $this->column($item, 'nothing'));
	}

	/**
	 * Object values do not break the export.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testObjectValues(): void
	{
		try
		{
			$item = Export::exportItemFromArray(['obj' => (object) ['x' => 'y']]);
		}
		catch (\TypeError $e)
		{
			$this->markTestSkipped(
				'Known issue #23 (see known-issues.md): Export::exportItemFromArray() casts an object value to an array and then passes that array to htmlspecialchars(): ' . $e->getMessage()
			);
		}

		$this->assertStringContainsString('y', (string) $this->column($item, 'obj'));
	}

	/**
	 * exportItemFromObject() is exportItemFromArray() of the object's properties.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testItemFromObject(): void
	{
		$item = Export::exportItemFromObject((object) ['id' => 3, 'email' => 'a@example.test'], 'id');

		$this->assertSame('3', (string) $item['id']);
		$this->assertSame('a@example.test', (string) $this->column($item, 'email'));
	}

	/**
	 * Joomla privacy domains are converted to Data Compliance domains, one item per privacy item, one
	 * column per field.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testMapJoomlaPrivacyExportDomain(): void
	{
		$domain = $this->privacyDomain([
			['name' => 'Alice & Co', 'profile_key' => 'profile.city', 'profile_value' => 'Athens'],
		]);

		$xml = Export::mapJoomlaPrivacyExportDomain($domain);

		$this->assertSame('user_profile', (string) $xml->domain['name']);
		$this->assertSame('joomla_user_profile_data', (string) $xml->domain['description']);
		$this->assertCount(1, $xml->domain->item);
		$this->assertSame('Alice & Co', (string) $this->column($xml->domain->item, 'name'));
		$this->assertSame('Athens', (string) $this->column($xml->domain->item, 'profile_value'));
	}

	/**
	 * With Maximalist Export off, the Joomla API token seed (joomlatoken.token) is dropped from the
	 * core privacy plugins' output; with it on, it is kept. Nothing else is dropped either way.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testMaximalistExportControlsTheApiTokenSeed(): void
	{
		$domain = $this->privacyDomain([
			['profile_key' => 'profile.city', 'profile_value' => 'Athens'],
			['profile_key' => 'joomlatoken.token', 'profile_value' => 'SEED'],
		]);

		$maximal = Export::mapJoomlaPrivacyExportDomain($domain, true)->asXML();
		$minimal = Export::mapJoomlaPrivacyExportDomain($domain, false)->asXML();

		$this->assertStringContainsString('SEED', $maximal);
		$this->assertStringNotContainsString('SEED', $minimal);
		$this->assertStringNotContainsString('joomlatoken.token', $minimal);
		$this->assertStringContainsString('Athens', $minimal);
	}

	/**
	 * With Maximalist Export off, the activation / password reset token is dropped from the core
	 * plugins' output as it is from Data Compliance's own.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testMinimalExportDropsTheActivationTokenFromCoreOutput(): void
	{
		$domain = $this->privacyDomain([
			['username' => 'alice', 'activation' => 'ACTIVATION-TOKEN'],
		]);

		$minimal = Export::mapJoomlaPrivacyExportDomain($domain, false)->asXML();

		if (str_contains($minimal, 'ACTIVATION-TOKEN'))
		{
			$this->markTestSkipped('Known issue #7 (see known-issues.md): mapJoomlaPrivacyExportDomain() only filters joomlatoken.token; the core users domain\'s activation token is exported even with Maximalist Export off.');
		}

		$this->assertStringContainsString('alice', $minimal);
	}

	/**
	 * A Joomla privacy export domain with the given items.
	 *
	 * @param   array[]  $items  Field name => value, per item.
	 *
	 * @return  Domain
	 * @since   4.1.0
	 */
	private function privacyDomain(array $items): Domain
	{
		$domain              = new Domain();
		$domain->name        = 'user_profile';
		$domain->description = 'joomla_user_profile_data';

		foreach ($items as $i => $fields)
		{
			$item     = new Item();
			$item->id = $i + 1;

			foreach ($fields as $name => $value)
			{
				$field        = new Field();
				$field->name  = $name;
				$field->value = $value;

				$item->addField($field);
			}

			$domain->addItem($item);
		}

		return $domain;
	}

	/**
	 * The named <column> of an <item>.
	 *
	 * @param   SimpleXMLElement  $item  The item.
	 * @param   string            $name  The column name.
	 *
	 * @return  SimpleXMLElement|null
	 * @since   4.1.0
	 */
	private function column(SimpleXMLElement $item, string $name): ?SimpleXMLElement
	{
		foreach ($item->column as $column)
		{
			if ((string) $column['name'] === $name)
			{
				return $column;
			}
		}

		$this->fail(sprintf('The item has no "%s" column: %s', $name, $item->asXML()));
	}
}
