<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\CMS\MVC\Model\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;


class Router extends RouterView
{
	use MVCFactoryAwareTrait;
	use DatabaseAwareTrait;

	public function __construct(SiteApplication $app = null, AbstractMenu $menu = null, DatabaseInterface $db, MVCFactory $factory)
	{
		$this->setDbo($db);
		$this->setMVCFactory($factory);

		$this->registerView(new RouterViewConfiguration('options'));

		parent::__construct($app, $menu);

		$this->attachRule(new MenuRules($this));
		$this->attachRule(new StandardRules($this));
		$this->attachRule(new NomenuRules($this));
	}

	public function build(&$query)
	{
		$query['view'] = strtolower($query['view'] ?? 'options');

		$segments = parent::build($query);

		$task = strtolower($query['task'] ?? 'options');

		if (in_array($task, ['export', 'wipe']))
		{
			$segments[] = $task;
			unset($query['task']);
		}

		return $segments;
	}

	public function parse(&$segments)
	{
		$query = parent::parse($segments);

		$lastSegment = count($segments) ? array_pop($segments) : null;

		if (empty($lastSegment))
		{
			return $query;
		}

		if (in_array($lastSegment, ['export', 'wipe']))
		{
			$query['view'] = 'options';
			$query['task'] = $lastSegment;

			return $query;
		}

		$segments[] = $lastSegment;

		return $query;
	}


}