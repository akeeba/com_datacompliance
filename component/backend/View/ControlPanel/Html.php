<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\View\ControlPanel;

defined('_JEXEC') or die;

use FOF30\View\DataView\Html as BaseView;

class Html extends BaseView
{
	/** @var  string	The fancy formatted changelog of the component */
	public $formattedChangelog = '';

	/**
	 * Main Control Panel task
	 *
	 * @return  void
	 */
	protected function onBeforeMain()
	{
		$this->formattedChangelog    = $this->formatChangelog();

		$this->addJavascriptFile('admin://components/com_admintools/media/js/Modal.min.js');
		$this->addJavascriptFile('admin://components/com_admintools/media/js/ControlPanel.min.js');

	}

	protected function formatChangelog($onlyLast = false)
	{
		$ret   = '';
		$file  = $this->container->backEndPath . '/CHANGELOG.php';
		$lines = @file($file);

		if (empty($lines))
		{
			return $ret;
		}

		array_shift($lines);

		foreach ($lines as $line)
		{
			$line = trim($line);

			if (empty($line))
			{
				continue;
			}

			$type = substr($line, 0, 1);

			switch ($type)
			{
				case '=':
					continue;
					break;

				case '+':
					$ret .= "\t" . '<li class="akeeba-changelog-added"><span></span>' . htmlentities(trim(substr($line, 2))) . "</li>\n";
					break;

				case '-':
					$ret .= "\t" . '<li class="akeeba-changelog-removed"><span></span>' . htmlentities(trim(substr($line, 2))) . "</li>\n";
					break;

				case '~':
					$ret .= "\t" . '<li class="akeeba-changelog-changed"><span></span>' . htmlentities(trim(substr($line, 2))) . "</li>\n";
					break;

				case '!':
					$ret .= "\t" . '<li class="akeeba-changelog-important"><span></span>' . htmlentities(trim(substr($line, 2))) . "</li>\n";
					break;

				case '#':
					$ret .= "\t" . '<li class="akeeba-changelog-fixed"><span></span>' . htmlentities(trim(substr($line, 2))) . "</li>\n";
					break;

				default:
					if (!empty($ret))
					{
						$ret .= "</ul>";
						if ($onlyLast)
						{
							return $ret;
						}
					}

					if (!$onlyLast)
					{
						$ret .= "<h3 class=\"akeeba-changelog\">$line</h3>\n";
					}
					$ret .= "<ul class=\"akeeba-changelog\">\n";

					break;
			}
		}

		return $ret;
	}
}
