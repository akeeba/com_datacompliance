<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

/** @var \Akeeba\Component\DataCompliance\Administrator\View\Controlpanel\HtmlView $this */

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