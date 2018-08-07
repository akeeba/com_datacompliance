<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @var  \Akeeba\DataCompliance\Admin\View\ControlPanel\Html $this For type hinting in the IDE */

// Protect from unauthorized access
defined('_JEXEC') or die;

?>
<div class="akeeba-panel--teal">
    <header class="akeeba-block-header">
        <h3>@lang('COM_DATACOMPLIANCE_CONTROLPANEL_HEADER_AUDIT')</h3>
    </header>

    <div class="akeeba-grid">

        <a class="akeeba-action--teal"
           href="index.php?option=com_datacompliance&view=Consenttrails" >
            <span class="akion-ios-checkmark"></span>
            @lang('COM_DATACOMPLIANCE_CONSENTTRAILS')
        </a>

        <a class="akeeba-action--teal"
           href="index.php?option=com_datacompliance&view=Cookietrails" >
            <span class="akion-android-checkbox"></span>
            @lang('COM_DATACOMPLIANCE_COOKIETRAILS')
        </a>


        <a class="akeeba-action--teal"
           href="index.php?option=com_datacompliance&view=Usertrails" >
            <span class="akion-person"></span>
            @lang('COM_DATACOMPLIANCE_USERTRAILS')
        </a>

        <a class="akeeba-action--teal"
           href="index.php?option=com_datacompliance&view=Exporttrails" >
            <span class="akion-share"></span>
            @lang('COM_DATACOMPLIANCE_EXPORTTRAILS')
        </a>

        <a class="akeeba-action--teal"
           href="index.php?option=com_datacompliance&view=Wipetrails" >
            <span class="akion-android-delete"></span>
            @lang('COM_DATACOMPLIANCE_WIPETRAILS')
        </a>

    </div>
</div>

<div class="akeeba-panel--info">
    <header class="akeeba-block-header">
        <h3>@lang('COM_DATACOMPLIANCE_CONTROLPANEL_HEADER_ACTIONS')</h3>
    </header>

    <div class="akeeba-grid">

        <a class="akeeba-action--teal"
           href="index.php?option=com_datacompliance&view=Lifecycle" >
            <span class="akion-clock"></span>
            @lang('COM_DATACOMPLIANCE_LIFECYCLE')
        </a>

    </div>
</div>
