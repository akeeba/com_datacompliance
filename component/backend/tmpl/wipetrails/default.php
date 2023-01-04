<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Akeeba\Component\DataCompliance\Administrator\View\Wipetrails\HtmlView $this */

HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('bootstrap.modal', '.comDatacomplianceModal', [
	'backdrop' => 'static',
	'keyboard' => true,
	'focus'    => true,
]);

$user      = Factory::getApplication()->getIdentity();
$userId    = $user->get('id');
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$nullDate  = Factory::getDbo()->getNullDate();
$lang      = Factory::getApplication()->getLanguage();

$i = 0;

$userLayout = new FileLayout('akeeba.datacompliance.common.user', JPATH_ADMINISTRATOR . '/components/com_datacompliance/layout');

?>
<form action="<?= Route::_('index.php?option=com_datacompliance&view=wipetrails'); ?>"
      method="post" name="adminForm" id="adminForm">
	<div class="row">
		<div class="col-md-12">
			<div id="j-main-container" class="j-main-container">
				<?= LayoutHelper::render('joomla.searchtools.default', ['view' => $this]) ?>
				<?php if (empty($this->items)) : ?>
					<div class="alert alert-info">
						<span class="icon-info-circle" aria-hidden="true"></span>
						<span class="visually-hidden"><?= Text::_('INFO'); ?></span>
						<?= Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
					</div>
				<?php else : ?>
				<table class="table" id="articleList">
					<caption class="visually-hidden">
						<?= Text::_('COM_DATACOMPLIANCE_WIPETRAILS_TABLE_CAPTION'); ?>, <span
							id="orderedBy"><?= Text::_('JGLOBAL_SORTED_BY'); ?> </span>, <span
							id="filteredBy"><?= Text::_('JGLOBAL_FILTERED_BY'); ?></span>
					</caption>
					<thead>
					<tr>
						<th scope="col">
							<?= Text::_('COM_DATACOMPLIANCE_WIPETRAIL_FIELD_USER_ID') ?>
						</th>
						<th scope="col">
							<?= Text::_('COM_DATACOMPLIANCE_WIPETRAIL_FIELD_CREATED_BY') ?>
						</th>
						<th scope="col">
							<?= HTMLHelper::_('searchtools.sort', 'COM_DATACOMPLIANCE_WIPETRAIL_FIELD_CREATED_ON', 'created_on', $listDirn, $listOrder); ?>
						</th>
						<th scope="col">
							<?= Text::_('COM_DATACOMPLIANCE_WIPETRAIL_FIELD_REQUESTER_IP') ?>
						</th>
						<th scope="col">
							<?= Text::_('COM_DATACOMPLIANCE_WIPETRAIL_FIELD_ITEMS') ?>
						</th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($this->items as $item) :
						$item->items = @json_decode($item->items ?? '{}', true) ?: [];
						?>
					<tr class="row<?= $i++ % 2; ?>" data-draggable-group="0">
						<td>
							<?= $userLayout->render([
								'user_id'    => $item->user_id,
								'showUserId' => true,
								'username'   => $item->user_username,
								'name'       => $item->user_name,
								'email'      => $item->user_email,
								'showLink'   => true,
							]) ?>
						</td>
						<td>
							<?= $userLayout->render([
								'user_id'    => $item->created_by,
								'showUserId' => true,
								'username'   => $item->creator_username,
								'name'       => $item->creator_name,
								'email'      => $item->creator_email,
								'showLink'   => false,
							]) ?>
						</td>
						<td>
							<?= HTMLHelper::_('datacompliance.formatDate', $item->created_on) ?>
						</td>
						<td>
							<?= $this->escape($item->requester_ip) ?>
						</td>
						<td class="text-center">
							<button type="button"
									data-bs-toggle="modal" data-bs-target="#comDatacomplianceModal-<?= $i ?>"
									class="btn btn-sm btn-outline-primary">
								<span class="fa fa-eye" aria-hidden="true"></span>
								<?= Text::_('COM_DATACOMPLIANCE_WIPETRAIL_FIELD_ITEMS_BTN') ?>
							</button>
							<div id="comDatacomplianceModal-<?= $i ?>"
								 class="modal"
								 role="dialog"
								 tabindex="-1"
								 aria-labelledby="comDatacomplianceModal-<?= $i ?>-title"
								 aria-hidden="true"
							>
								<div class="modal-dialog">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-title" id="comDatacomplianceModal-<?= $i ?>-title">
												<?= Text::_('COM_DATACOMPLIANCE_USERTRAIL_HEAD_CHANGES') ?>
											</h3>
											<button type="button" class="btn-close"
													data-bs-dismiss="modal"
													aria-label="<?= Text::_('JCLOSE') ?>"></button>

										</div>
										<div class="modal-body p-5 text-start">
											<table class="table table-striped table-sm w-100 align-topk">
												<thead class="border-bottom border-dark">
												<tr>
													<th scope="col">
														<?= Text::_('COM_DATACOMPLIANCE_WIPETRAIL_HEAD_WHATDELETED') ?>
													</th>
													<th scope="col">
														<?= Text::_('COM_DATACOMPLIANCE_WIPETRAIL_HEAD_IDS') ?>
													</th>
												</tr>
												</thead>
												<tbody>
												<?php foreach ($item->items as $domain => $domainItems):
													$extension = 'plg_datacompliance_' . strtolower($domain);
													$lang->load($extension);
													?>
												<tr>
													<th scope="rowgroup" colspan="2">
														<h4>
															<?= Text::_($extension . '_DOMAINNAME') ?>
														</h4>
													</th>
												</tr>
												<?php foreach($domainItems as $what => $ids): ?>
												<tr class="border-bottom">
													<th scope="row">
														<?= $this->escape($what) ?>
													</th>
													<td>
														<?= implode(', ', array_map([$this, 'escape'], $ids)) ?>
													</td>
												</tr>
												<?php endforeach ?>
												<?php endforeach ?>
												</tbody>
											</table>
										</div>
										<div class="modal-footer">
											<button type="button" class="btn btn-secondary"
													data-bs-dismiss="modal">
												<span class="fa fa-times" aria-hidden="true"></span>
												<?= Text::_('JCLOSE') ?>
											</button>
										</div>
									</div>
								</div>
							</div>
						</td>
					</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<?php // Load the pagination. ?>
				<?= $this->pagination->getListFooter(); ?>
				<?php endif; ?>

				<input type="hidden" name="task" value="">
				<input type="hidden" name="boxchecked" value="0">
				<?= HTMLHelper::_('form.token'); ?>
			</div>
		</div>
	</div>
</form>
