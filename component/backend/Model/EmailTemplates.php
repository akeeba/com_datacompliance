<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Model;

defined('_JEXEC') or die;

use FOF30\Container\Container;
use FOF30\Model\DataModel;

/**
 * Model Akeeba\DataCompliance\Admin\Model\EmailTemplates
 *
 * Fields:
 *
 * @property  int     $datacompliance_emailtemplate_id
 * @property  string  $key
 * @property  int     $subscription_level_id
 * @property  string  $subject
 * @property  string  $body
 * @property  string  $language
 *
 * Filters:
 *
 * @method  $this  datacompliance_emailtemplate_id()  datacompliance_emailtemplate_id(int $v)
 * @method  $this  key()                              key(string $v)
 * @method  $this  subject()                          subject(string $v)
 * @method  $this  body()                             body(string $v)
 * @method  $this  language()                         language(string $v)
 * @method  $this  enabled()                          enabled(bool $v)
 * @method  $this  ordering()                         ordering(int $v)
 * @method  $this  created_on()                       created_on(string $v)
 * @method  $this  created_by()                       created_by(int $v)
 * @method  $this  modified_on()                      modified_on(string $v)
 * @method  $this  modified_by()                      modified_by(int $v)
 * @method  $this  locked_on()                        locked_on(string $v)
 * @method  $this  locked_by()                        locked_by(int $v)
 *
 **/class EmailTemplates extends DataModel
{
	/**
	 * Overrides the constructor to add the Filters behaviour
	 *
	 * @param Container $container
	 * @param array     $config
	 */
	public function __construct(Container $container, array $config = array())
	{
		parent::__construct($container, $config);

		$this->addBehaviour('Filters');
	}

	/**
	 * Unpublish the newly copied item
	 *
	 * @param EmailTemplates $copy
	 */
	protected function onAfterCopy(EmailTemplates $copy)
	{
		// Unpublish the newly copied item
		if ($copy->enabled)
		{
			$this->publish(0);
		}
	}
}
