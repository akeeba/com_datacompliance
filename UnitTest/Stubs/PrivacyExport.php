<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Test doubles for Joomla\Component\Privacy\Administrator\Export\{Domain,Item,Field}.
 *
 * Only declared when the real classes are not loadable, which, without a Joomla installation, is
 * always. They copy core's API (Joomla 5.4 – 6.1) verbatim.
 */

namespace Joomla\Component\Privacy\Administrator\Export;

if (!class_exists(Domain::class, false))
{
	class Domain
	{
		public $name;

		public $description;

		protected $items = [];

		public function addItem(Item $item)
		{
			$this->items[] = $item;
		}

		public function getItems()
		{
			return $this->items;
		}
	}
}

if (!class_exists(Item::class, false))
{
	class Item
	{
		public $id;

		protected $fields = [];

		public function addField(Field $field)
		{
			$this->fields[] = $field;
		}

		public function getFields()
		{
			return $this->fields;
		}
	}
}

if (!class_exists(Field::class, false))
{
	class Field
	{
		public $name;

		public $value;
	}
}
