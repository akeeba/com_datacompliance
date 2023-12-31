<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

/** @var \Akeeba\Component\DataCompliance\Administrator\View\Controlpanel\HtmlView $this */

if (version_compare(JVERSION, '4.999.999', 'lt'))
{
	$this->loadAnyTemplate('Controlpanel/joomla_eol');
}

?>

<div class="container-fluid">
	<div class="row">
		<div class="col-lg">
			<?= $this->loadTemplate('icons') ?>
		</div>
		<div class="col-lg">
			<?= $this->loadTemplate('stats') ?>
		</div>
	</div>
</div>