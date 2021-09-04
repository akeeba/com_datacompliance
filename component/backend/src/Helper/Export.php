<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Helper;

use Joomla\CMS\Table\Table;
use Joomla\Component\Privacy\Administrator\Export\Domain as ExportDomain;
use SimpleXMLElement;
use stdClass;

defined('_JEXEC') or die;

/**
 * A helper class to convert structured data into the XML export format
 */
abstract class Export
{
	/**
	 * Essentially a deep addChild for SimpleXMLElement. It appends the $child node as a child of $root.
	 *
	 * @param   SimpleXMLElement  $root   The XML node to append children to
	 * @param   SimpleXMLElement  $child  The XML child node to append to the $root
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public static function adoptChild(SimpleXMLElement $root, SimpleXMLElement $child)
	{
		$v    = (string) $child;
		$v    = htmlspecialchars($v, ENT_QUOTES);
		$node = $root->addChild($child->getName(), $v);

		foreach ($child->attributes() as $attr => $value)
		{
			$node->addAttribute($attr, $value);
		}

		foreach ($child->children() as $ch)
		{
			self::adoptChild($node, $ch);
		}
	}

	/**
	 * Create an XML export item from array data
	 *
	 * @param   array   $data   The array data to convert into an <item> document
	 * @param   string  $idCol  The array key containing the unique ID of this item
	 *
	 * @return  SimpleXMLElement  The XML export item
	 * @since   1.0.0
	 */
	public static function exportItemFromArray(array $data, string $idCol = ''): SimpleXMLElement
	{
		$elItem = new SimpleXMLElement("<item></item>");

		if (!empty($idCol) && isset($data[$idCol]))
		{
			$elItem->addAttribute('id', $data[$idCol]);
		}

		foreach ($data as $k => $v)
		{
			if (is_array($v))
			{
				$v = print_r($v, true);
			}
			elseif (is_object($v))
			{
				$v = (array) $v;
			}

			$v     = htmlspecialchars($v, ENT_QUOTES);
			$elCol = $elItem->addChild('column', $v ?? '');
			$elCol->addAttribute('name', $k ?? '');
		}

		return $elItem;
	}

	/**
	 * @param   Table  $table  The Joomla Table object to convert
	 *
	 * @return  SimpleXMLElement  The XML export item
	 * @since        1.0.0
	 * @noinspection PhpUnused
	 *
	 */
	public static function exportItemFromJTable(Table $table): SimpleXMLElement
	{
		$data        = [];
		$idCol       = $table->getKeyName(false);
		$tableFields = $table->getFields();

		foreach ($tableFields as $fieldName => $fieldDefinition)
		{
			$data[$fieldName] = $table->get($fieldName);
		}

		return self::exportItemFromArray($data, $idCol);
	}

	/**
	 * Create an XML export item from generic PHP objects
	 *
	 * @param   stdClass  $item   The PHP object to convert
	 * @param   string    $idCol  The object property containing the unique ID of this item
	 *
	 * @return  SimpleXMLElement  The XML export item
	 * @since        1.0.0
	 * @noinspection PhpUnused
	 */
	public static function exportItemFromObject(stdClass $item, string $idCol = ''): SimpleXMLElement
	{
		$data = (array) $item;

		return self::exportItemFromArray($data, $idCol);
	}

	/**
	 * Converts a PrivacyExportDomain object, returned by Joomla privacy plugins, into an export format compatible with
	 * Akeeba DataCompliance.
	 *
	 * @param   ExportDomain  $joomlaDomain  The Joomla! export domain object
	 *
	 * @return  SimpleXMLElement  The DataCompliance export object
	 * @since   1.0.0
	 * @noinspection PhpUnused
	 */
	public static function mapJoomlaPrivacyExportDomain(ExportDomain $joomlaDomain): SimpleXMLElement
	{
		$export = new SimpleXMLElement("<root></root>");
		$domain = $export->addChild('domain');
		$domain->addAttribute('name', $joomlaDomain->name);
		$domain->addAttribute('description', $joomlaDomain->description);

		$items = $joomlaDomain->getItems();

		foreach ($items as $item)
		{
			$itemArray = [
				'id' => $item->id,
			];
			$fields    = $item->getFields();

			foreach ($fields as $field)
			{
				$itemArray[$field->name] = $field->value;
			}

			self::adoptChild($domain, self::exportItemFromArray($itemArray));
		}

		return $export;
	}

	/**
	 * Merge two SimpleXMLElement documents into a new one. The root element of the first document is kept. The top
	 * level children of $second become top level children of $first, appended after $first's existing children. This is
	 * used to merge multiple export documents, containing one or more domains, into one big file.
	 *
	 * @param   SimpleXMLElement  $first
	 * @param   SimpleXMLElement  $second
	 *
	 * @return  SimpleXMLElement
	 * @since   1.0.0
	 */
	public static function merge(SimpleXMLElement $first, SimpleXMLElement $second): SimpleXMLElement
	{
		$ret = clone $first;

		foreach ($second->children() as $child)
		{
			self::adoptChild($ret, $child);
		}

		return $ret;
	}
}
