<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$myUser = $this->getContainer()->platform->getUser();
$token  = Factory::getApplication()->getFormToken();
?>

<?php if($this->type == 'user'): ?>
<div class="card mb-3 border-primary">
	<h3 class="h1 card-header bg-primary text-white">
		<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_CONSENT_HEADER') ?>
	</h3>

	<div class="card-body">
		<p>
			<?= Text::sprintf('COM_DATACOMPLIANCE_OPTIONS_CONSENT_INFOBLOCK', $this->siteName) ?>
		</p>
		<p>
			<a class="akeebaDataComplianceArticleToggle">
				<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_CONSENT_CLICKTOREAD') ?>
			</a>
		</p>
		<div class="m-2 p-2 border border-2 d-none" id="datacompliance-article">
			<h4 class="h2">
				<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_CONSENT_POLICYHEADER') ?>
			</h4>
			<?= $this->article ?>
		</div>
		<p>
			<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_CONSENT_CURRENTPREFERENCE') ?>
			<span class="badge bg-<?= $this->preference ? 'success' : 'failure' ?>">
				<strong>
					<?= Text::_($this->preference ? 'JYES' : 'JNO') ?>
				</strong>
			</span>
		</p>
		<form
				method="post"
				action="<?= Route::_('index.php?option=com_datacompliance&view=Options&task=consent') ?>">

			<div class="row mb-3">
				<label for="enabled" class="col-sm-3 col-form-label">
					<?= Text::sprintf('COM_DATACOMPLIANCE_OPTIONS_CONSENT_PREFERENCELABEL', $this->siteName) ?>
				</label>
				<div class="col-sm-9">
					<?= HTMLHelper::_('select.booleanlist', 'enabled', [
						'class' => 'form-control',
						'list.select' => 0
					]) ?>
				</div>
			</div>

			<div class="row mb-3">
				<div class="col-sm-9 offset-sm-3">
					<button type="submit" class="btn btn-primary">
						<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_CONSENT_PREFERENCEBUTTON') ?>
					</button>
				</div>
			</div>

			<?= HTMLHelper::_('form.token') ?>
		</form>
		<p>
			<span class="fa fa-info-circle" aria-hidden="true"></span>
			<a href="https://ec.europa.eu/info/law/law-topic/data-protection_en" target="_blank">
				<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_CONSENT_PREFERENCELINK') ?>
			</a>
		</p>
	</div>
</div>
<?php else: ?>
    <div class="card mb-3 border-primary">
		<h3 class="h1 card-header bg-primary text-white">
			<?= Text::sprintf('COM_DATACOMPLIANCE_OPTIONS_CONSENT_MANAGE_HEADER', $this->user->username) ?>
		</h3>
		<div class="card-body">
			<p class="alert alert-info">
				<span class="fa fa-info-circle" aria-hidden="true"></span>
				<?= Text::sprintf('COM_DATACOMPLIANCE_OPTIONS_MANAGE_CONSENT_WARN', $this->user->username) ?>
			</p>
			<p>
				<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_MANAGE_CONSENT_CURRENTPREFERENCE') ?>
				<span class="badge bg-<?= $this->preference ? 'success' : 'failure' ?>">
					<strong>
						<?= Text::_($this->preference ? 'JYES' : 'JNO') ?>
					</strong>
				</span>
			</p>
		</div>
    </div>
<?php endif; ?>

<?php if (($this->type == 'user') && ($this->showExport || $this->showWipe)): ?>
    <div class="card mb-3 border-warning">
		<h3 class="h1 card-header bg-warning">
		    <?= Text::_('COM_DATACOMPLIANCE_OPTIONS_DATARIGHTS_HEADER') ?>
		</h3>

		<div class="card-body">
			<?php if($this->showExport): ?>
				<p>
					<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_DATARIGHTS_INFOBLOCK') ?>
				</p>
			<?php endif ?>

			<?php if($this->showWipe): ?>
				<p class="alert alert-warning">
					<span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
					<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_DATARIGHTS_WARNING') ?>
				</p>
			<?php endif ?>

			<div class="row mb-3">
				<?php if($this->showExport && (($this->type == 'user') || $myUser->authorise('export', 'com_datacompliance'))): ?>
					<div class="col-sm-6">
						<a href="<?= Route::_('index.php?option=com_datacompliance&view=Options&task=export&format=raw&' . $token . '=1') ?>"
						   class="btn btn-success w-100">
							<span class="fa fa-file-download" aria-hidden="true"></span>
							<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_DATARIGHTS_BTN_EXPORT') ?>
						</a>
					</div>
				<?php endif ?>

				<?php if($this->showWipe && (($this->type == 'user') || $myUser->authorise('wipe', 'com_datacompliance'))): ?>
					<div class="col-sm-6">
						<a href="<?= Route::_('index.php?option=com_datacompliance&view=Options&task=wipe&' . $token . '=1') ?>"
						   class="btn btn-danger w-100">
							<span class="fa fa-user-times" aria-hidden="true"></span>
							<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_DATARIGHTS_BTN_WIPE') ?>
						</a>
					</div>
				<?php endif ?>
			</div>
		</div>
    </div>
<?php endif ?>

<?php if($this->type !== 'user'): ?>
	<div class="card mb-3 border-warning">
		<h3 class="h1 card-header bg-warning">
			<?= Text::sprintf('COM_DATACOMPLIANCE_OPTIONS_MANAGE_DATARIGHTS_HEADER', $this->user->username) ?>
		</h3>

		<div class="card-body">
			<p>
				<?= Text::sprintf('COM_DATACOMPLIANCE_OPTIONS_MANAGE_DATARIGHTS_INFOBLOCK', $this->user->username) ?>
			</p>
			<p class="alert alert-warning">
				<span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
				<?= Text::sprintf('COM_DATACOMPLIANCE_OPTIONS_MANAGE_DATARIGHTS_WARNING', $this->user->username) ?>
			</p>
			<div class="row mb-3">
				<?php if($myUser->authorise('export', 'com_datacompliance')): ?>
					<div class="col-sm-6">
						<a href="<?= Route::_('index.php?option=com_datacompliance&view=Options&task=export&user_id=' . $this->user->id . '&format=raw&' . $token . '=1') ?>"
						   class="btn btn-success w-100">
							<span class="fa fa-file-download" aria-hidden="true"></span>
							<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_DATARIGHTS_BTN_EXPORT_ADMIN') ?>
						</a>
					</div>
				<?php endif ?>
				<?php if($myUser->authorise('wipe', 'com_datacompliance')): ?>
					<div class="col-sm-6">
						<a href="<?= Route::_('index.php?option=com_datacompliance&view=Options&task=wipe&user_id=' . $this->user->id . '&' . $token . '=1') ?>"
						   class="btn btn-danger w-100">
							<span class="fa fa-user-times" aria-hidden="true"></span>
							<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_DATARIGHTS_BTN_WIPE_ADMIN') ?>
						</a>
					</div>
				<?php endif ?>
			</div>
		</div>
    </div>
<?php endif ?>