<?php
/**
 * @package   Akeeba Connection
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @var  \Akeeba\Connection\Admin\View\ControlPanel\Html $this For type hinting in the IDE */

// Protect from unauthorized access
defined('_JEXEC') or die;

?>
<?php echo $this->loadAnyTemplate('admin:com_connection/ControlPanel/warnings'); ?>

<div>
	<div class="akeeba-container--50-50">
		<div>

		</div>

		<div>
            <div class="akeeba-panel--default">
                <header class="akeeba-block-header">
				    <h3><?php echo \JText::_('COM_ADMINTOOLS_LBL_CONTROLPANEL_UPDATES'); ?></h3>
                </header>

				<div>
					<p>
						Admin Tools version <?php echo AKCONNECTION_VERSION; ?> &bull;
						<a href="#" id="btnAdminToolsChangelog" class="akeeba-btn--primary--small">CHANGELOG</a>
					</p>

					<p>Copyright &copy; 2018&ndash;<?php echo date('Y'); ?> Nicholas K. Dionysopoulos / <a
								href="https://www.akeebabackup.com">Akeeba Ltd</a></p>
				</div>

                <div id="akeeba-changelog" tabindex="-1" role="dialog" aria-hidden="true" style="display:none;">
                    <div class="akeeba-renderer-fof">
                        <div class="akeeba-panel--info">
                            <header class="akeeba-block-header">
                                <h3>
						            <?php echo \JText::_('CHANGELOG'); ?>
                                </h3>
                            </header>
                            <div id="DialogBody">
					            <?php echo $this->formattedChangelog; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

		</div>
	</div>
</div>
