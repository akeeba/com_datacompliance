<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\View\Exporttrails;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Mixin\ViewLoadAnyTemplateTrait;
use Akeeba\Component\DataCompliance\Administrator\Model\ConsenttrailsModel;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;

class HtmlView extends BaseHtmlView
{
	use ViewLoadAnyTemplateTrait;

	/**
	 * The active search filters
	 *
	 * @var    array
	 * @since  3.0.0
	 */
	public $activeFilters = [];

	/**
	 * The search tools form
	 *
	 * @var    Form
	 * @since  3.0.0
	 */
	public $filterForm;

	/**
	 * An array of items
	 *
	 * @var    array
	 * @since  3.0.0
	 */
	protected $items = [];

	/**
	 * The pagination object
	 *
	 * @var    Pagination
	 * @since  3.0.0
	 */
	protected $pagination;

	/**
	 * The model state
	 *
	 * @var    Registry
	 * @since  3.0.0
	 */
	protected $state;

	/**
	 * Is this view an Empty State
	 *
	 * @var   boolean
	 * @since 3.0.0
	 */
	private $isEmptyState = false;

	public function display($tpl = null)
	{
		/** @var ConsenttrailsModel $model */
		$model               = $this->getModel();
		$this->items         = $model->getItems();
		$this->pagination    = $model->getPagination();
		$this->state         = $model->getState();
		$this->filterForm    = $model->getFilterForm();
		$this->activeFilters = $model->getActiveFilters();
		$this->isEmptyState  = $this->get('IsEmptyState');

		// Check for errors.
		if (count($errors = $this->get('Errors')))
		{
			throw new GenericDataException(implode("\n", $errors), 500);
		}

		if (!\count($this->items) && $this->isEmptyState)
		{
			$this->setLayout('emptystate');
		}

		ToolbarHelper::title(Text::_('COM_DATACOMPLIANCE_EXPORTTRAILS'), 'datacompliance');
		ToolbarHelper::back('COM_DATACOMPLIANCE_TITLE_DASHBOARD_SHORT', 'index.php?option=com_datacompliance');

		parent::display($tpl);
	}


}