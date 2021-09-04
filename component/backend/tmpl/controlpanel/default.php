<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

/** @var \Akeeba\Component\DataCompliance\Administrator\View\Controlpanel\HtmlView $this */

?>

<?= $this->loadAnyTemplate('common/phpversion_warning', false, [
	'softwareName'  => 'Akeeba Data Compliance',
	'minPHPVersion' => '7.2.0',
]); ?>

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