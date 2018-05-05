<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Toolbar;

defined('_JEXEC') or die;

use JFactory;
use JText;
use JToolbar;
use JToolbarHelper;

class Toolbar extends \FOF30\Toolbar\Toolbar
{
	/**
	 * Renders the submenu (toolbar links) for all defined views of this component
	 *
	 * @return  void
	 */
	public function renderSubmenu()
	{
		$views = array(
			'ControlPanel',
			'COM_DATACOMPLIANCE_MAINMENU_SETUP'    => array(
				'EmailTemplates',
			),
		);

		foreach ($views as $label => $view)
		{
			if (!is_array($view))
			{
				$this->addSubmenuLink($view);
				continue;
			}

			$label = \JText::_($label);
			$this->appendLink($label, '', false);

			foreach ($view as $v)
			{
				$this->addSubmenuLink($v, $label);
			}
		}
	}

	/**
	 * Disable rendering a toolbar.
	 *
	 * @return array
	 */
	protected function getMyViews()
	{
		return array();
	}

	/**
	 * Renders the toolbar for the component's Browse pages (the plural views)
	 *
	 * @return  void
	 */
	public function onBrowse()
	{
		// On frontend, buttons must be added specifically
		if ($this->container->platform->isBackend() || $this->renderFrontendSubmenu)
		{
			$this->renderSubmenu();
		}

		if (!$this->container->platform->isBackend() && !$this->renderFrontendButtons)
		{
			return;
		}

		// Setup
		$option = $this->container->componentName;
		$view   = $this->container->input->getCmd('view', 'cpanel');

		// Set toolbar title
		$subtitle_key = strtoupper($option . '_TITLE_' . $view);
		JToolBarHelper::title(JText::_(strtoupper($option)) . ': ' . JText::_($subtitle_key), str_replace('com_', '', $option));

		if (!$this->isDataView())
		{
			return;
		}

		// Add toolbar buttons
		if ($this->perms->create)
		{
			JToolBarHelper::addNew();
			JToolBarHelper::custom('copy', 'copy.png', 'copy_f2.png', 'JTOOLBAR_DUPLICATE', false);
		}

		if ($this->perms->edit)
		{
			JToolBarHelper::editList();
		}

		if ($this->perms->create || $this->perms->edit)
		{
			JToolBarHelper::divider();
		}

		// Published buttons are only added if there is a enabled field in the table
		try
		{
			$model = $this->container->factory->model($view);

			if ($model->hasField('enabled') && $this->perms->editstate)
			{
				JToolBarHelper::publishList();
				JToolBarHelper::unpublishList();
				JToolBarHelper::divider();
			}
		}
		catch (\Exception $e)
		{
			// Yeah. Let's not add the buttons if we can't load the model...
		}

		if ($this->perms->delete)
		{
			$msg = JText::_($option . '_CONFIRM_DELETE');
			JToolBarHelper::deleteList(strtoupper($msg));
		}

		// A Check-In button is only added if there is a locked_on field in the table
		try
		{
			$model = $this->container->factory->model($view);

			if ($model->hasField('locked_on') && $this->perms->edit)
			{
				JToolBarHelper::checkin();
			}

		}
		catch (\Exception $e)
		{
			// Yeah. Let's not add the button if we can't load the model...
		}
	}

	/**
	 * Renders the toolbar for the component's Add pages
	 *
	 * @return  void
	 */
	public function onAdd()
	{
		// On frontend, buttons must be added specifically
		if (!$this->container->platform->isBackend() && !$this->renderFrontendButtons)
		{
			return;
		}

		$option = $this->container->componentName;
		$componentName = str_replace('com_', '', $option);
		$view = $this->container->input->getCmd('view', 'cpanel');

		// Set toolbar title
		$subtitle_key = strtoupper($option . '_TITLE_' . $this->container->inflector->pluralize($view)) . '_EDIT';
		JToolBarHelper::title(JText::_(strtoupper($option)) . ': ' . JText::_($subtitle_key), $componentName);

		if (!$this->isDataView())
		{
			return;
		}

		// Set toolbar icons
		if ($this->perms->edit || $this->perms->editown)
		{
			// Show the apply button only if I can edit the record, otherwise I'll return to the edit form and get a
			// 403 error since I can't do that
			JToolBarHelper::apply();
		}

		JToolBarHelper::save();

		if ($this->perms->create)
		{
			JToolBarHelper::custom('savenew', 'save-new.png', 'save-new_f2.png', 'JTOOLBAR_SAVE_AND_NEW', false);
		}

		JToolBarHelper::cancel();
	}


	public function onControlPanels()
	{
		$this->renderSubmenu();

		JToolbarHelper::title(JText::_('COM_DATACOMPLIANCE_TITLE_DASHBOARD') . ' <small>' . DATACOMPLIANCE_DATE . '</small>', 'vcard');

		JToolbarHelper::preferences('com_datacompliance');
	}

	public function onOptions()
	{
		JToolbarHelper::title(JText::_('COM_DATACOMPLIANCE_OPTIONS_CONSENT_HEADER'), 'vcard');
	}

	/**
	 * Adds a link to the submenu (toolbar links)
	 *
	 * @param string $view   The view we're linking to
	 * @param array  $parent The parent view
	 */
	private function addSubmenuLink($view, $parent = null)
	{
		static $activeView = null;

		if (empty($activeView))
		{
			$activeView = $this->container->input->getCmd('view', 'cpanel');
		}

		if ($activeView == 'cpanels')
		{
			$activeView = 'cpanel';
		}

		$key = $this->container->componentName . '_TITLE_' . $view;

		// Exceptions to avoid introduction of a new language string
		if ($view == 'ControlPanel')
		{
			$key = $this->container->componentName . '_TITLE_CPANEL';
		}

		if (strtoupper(\JText::_($key)) == strtoupper($key))
		{
			$altView = $this->container->inflector->isPlural($view) ? $this->container->inflector->singularize($view) : $this->container->inflector->pluralize($view);
			$key2    = strtoupper($this->container->componentName) . '_TITLE_' . strtoupper($altView);

			if (strtoupper(\JText::_($key2)) == $key2)
			{
				$name = ucfirst($view);
			}
			else
			{
				$name = \JText::_($key2);
			}
		}
		else
		{
			$name = \JText::_($key);
		}

		$link = 'index.php?option=' . $this->container->componentName . '&view=' . $view;

		$active = $view == $activeView;

		$this->appendLink($name, $link, $active, null, $parent);
	}

}
