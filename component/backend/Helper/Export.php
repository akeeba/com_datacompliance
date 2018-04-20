<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Helper;

use FOF30\Model\DataModel;
use Joomla\CMS\Table\Table;
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
	 */
	public static function adoptChild(SimpleXMLElement &$root, SimpleXMLElement $child)
	{
		$node = $root->addChild($child->getName(), (string) $child);

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
			$elCol = $elItem->addChild('column', $v);
			$elCol->addAttribute('name', $k);
		}

		return $elItem;
	}

	/**
	 * Create an XML export item from generic PHP objects
	 *
	 * @param   stdClass  $item   The PHP object to convert
	 * @param   string    $idCol  The object property containing the unique ID of this item
	 *
	 * @return  SimpleXMLElement  The XML export item
	 */
	public static function exportItemFromObject(stdClass $item, string $idCol = ''): SimpleXMLElement
	{
		$data = (array)$item;

		return self::exportItemFromArray($data, $idCol);
	}

	/**
	 * Create an XML export item from a FOF DataModel object
	 *
	 * @param   DataModel  $model  The model object to convert
	 *
	 * @return  SimpleXMLElement  The XML export item
	 */
	public static function exportItemFromDataModel(DataModel $model): SimpleXMLElement
	{
		$data  = $model->toArray();
		$idCol = $model->getKeyName();

		return self::exportItemFromArray($data, $idCol);
	}

	/**
	 * @param   Table|\JTable  $table  The JTable object to convert
	 *
	 * @return  SimpleXMLElement  The XML export item
	 */
	public static function exportItemFromJTable($table): SimpleXMLElement
	{
		$data        = [];
		$idCol       = $table->getPrimaryKey();
		$tableFields = $table->getFields();

		foreach ($tableFields as $fieldName => $fieldDefinition)
		{
			$data[$fieldName] = $table->get($fieldName, null);
		}

		return self::exportItemFromArray($data, $idCol);
	}
}