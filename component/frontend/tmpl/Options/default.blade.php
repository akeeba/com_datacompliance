<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

$myUser = $this->getContainer()->platform->getUser();
?>
@js('media://com_datacompliance/js/Options.min.js')

@if($this->type == 'user')
<div class="akeeba-panel--teal">
    <header class="akeeba-block-header">
        <h1>
            @lang('COM_DATACOMPLIANCE_OPTIONS_CONSENT_HEADER')
        </h1>
    </header>
    <p>
        @sprintf('COM_DATACOMPLIANCE_OPTIONS_CONSENT_INFOBLOCK', $this->siteName)
    </p>
    <p>
        <a class="akeebaDataComplianceArticleToggle">
            @Lang('COM_DATACOMPLIANCE_OPTIONS_CONSENT_CLICKTOREAD')
        </a>
    </p>
    <div class="akeeba-panel--info" style="display: none;" id="datacompliance-article">
        <header class="akeeba-block-header">
            <h3>
                @lang('COM_DATACOMPLIANCE_OPTIONS_CONSENT_POLICYHEADER')
            </h3>
        </header>
        {{ $this->article }}
    </div>
    <p>
        @lang('COM_DATACOMPLIANCE_OPTIONS_CONSENT_CURRENTPREFERENCE') <span class="akeeba-label--{{ $this->preference ? 'green' : 'red' }}"><strong>
                @lang($this->preference ? 'JYES' : 'JNO')
            </strong></span>
    </p>
    <form
            method="post"
            action="@route('index.php?option=com_datacompliance&view=Options&task=consent')"
            class="akeeba-form--stretch akeeba-panel--info">

        <div class="akeeba-form-group">
            <label for="enabled">
                @sprintf('COM_DATACOMPLIANCE_OPTIONS_CONSENT_PREFERENCELABEL', $this->siteName)
            </label>
            @jhtml('FEFHelp.select.booleanswitch', 'enabled', 0)
        </div>

        <div class="akeeba-form-group--pull-right">
            <div class="akeeba-form-group--actions">
                <button type="submit" class="akeeba-btn">
                    @lang('COM_DATACOMPLIANCE_OPTIONS_CONSENT_PREFERENCEBUTTON')
                </button>
            </div>
        </div>

        <input type="hidden" name="{{ $this->getContainer()->platform->getToken(true) }}" value="1" />
    </form>
    <p>
        <span class="akion-ios-information"></span>
        <a href="https://ec.europa.eu/info/law/law-topic/data-protection_en" target="_blank">
            @lang('COM_DATACOMPLIANCE_OPTIONS_CONSENT_PREFERENCELINK')
        </a>
    </p>
</div>
@else
    <div class="akeeba-panel--teal">
        <header class="akeeba-block-header">
            <h1>
                @sprintf('COM_DATACOMPLIANCE_OPTIONS_CONSENT_MANAGE_HEADER', $this->user->username))
            </h1>
        </header>
        <p class="akeeba-block--info">
            @sprintf('COM_DATACOMPLIANCE_OPTIONS_MANAGE_CONSENT_WARN', $this->user->username)
        </p>
        <p>
            @lang('COM_DATACOMPLIANCE_OPTIONS_MANAGE_CONSENT_CURRENTPREFERENCE') <span class="akeeba-label--{{ $this->preference ? 'green' : 'red' }}"><strong>
                @lang($this->preference ? 'JYES' : 'JNO')
            </strong></span>
        </p>
    </div>
@endif

@if (($this->type == 'user') && ($this->showExport || $this->showWipe))
    <div class="akeeba-panel--orange">
        <header class="akeeba-block-header">
            <h1>
                @lang('COM_DATACOMPLIANCE_OPTIONS_DATARIGHTS_HEADER')
            </h1>
        </header>

        @if($this->showExport)
            <p>
                @lang('COM_DATACOMPLIANCE_OPTIONS_DATARIGHTS_INFOBLOCK')
            </p>
        @endif

        @if($this->showWipe)
            <p class="akeeba-block--warning">
                @lang('COM_DATACOMPLIANCE_OPTIONS_DATARIGHTS_WARNING')
            </p>
        @endif

        <div class="akeeba-container--50-50">
            @if($this->showExport && (($this->type == 'user') || $myUser->authorise('export', 'com_datacompliance')))
                <div>
                    <a href="@route('index.php?option=com_datacompliance&view=Options&task=export&format=raw&' . $this->getContainer()->platform->getToken() . '=1')"
                       class="akeeba-btn--success--block">
                        <span class="akion-android-download"></span>
                        @lang('COM_DATACOMPLIANCE_OPTIONS_DATARIGHTS_BTN_EXPORT')
                    </a>
                </div>
            @endif

            @if($this->showWipe && (($this->type == 'user') || $myUser->authorise('wipe', 'com_datacompliance')))
                <div>
                    <a href="@route('index.php?option=com_datacompliance&view=Options&task=wipe&' . $this->getContainer()->platform->getToken() . '=1')"
                       class="akeeba-btn--red--block">
                        <span class="akion-nuclear"></span>
                        @lang('COM_DATACOMPLIANCE_OPTIONS_DATARIGHTS_BTN_WIPE')
                    </a>
                </div>
            @endif
        </div>
    </div>
@endif

@if($this->type !== 'user')
    <div class="akeeba-panel--orange">
        <header class="akeeba-block-header">
            <h1>
                @sprintf('COM_DATACOMPLIANCE_OPTIONS_MANAGE_DATARIGHTS_HEADER', $this->user->username)
            </h1>
        </header>
        <p>
            @sprintf('COM_DATACOMPLIANCE_OPTIONS_MANAGE_DATARIGHTS_INFOBLOCK', $this->user->username)
        </p>
        <p class="akeeba-block--warning">
            @sprintf('COM_DATACOMPLIANCE_OPTIONS_MANAGE_DATARIGHTS_WARNING', $this->user->username)
        </p>
        <div class="akeeba-container--50-50">
            @if($myUser->authorise('export', 'com_datacompliance'))
                <div>
                    <a href="@route('index.php?option=com_datacompliance&view=Options&task=export&user_id=' . $this->user->id . '&format=raw&' . $this->getContainer()->platform->getToken() . '=1')"
                       class="akeeba-btn--success--block">
                        <span class="akion-android-download"></span>
                        @lang('COM_DATACOMPLIANCE_OPTIONS_DATARIGHTS_BTN_EXPORT_ADMIN')
                    </a>
                </div>
            @endif
            @if($myUser->authorise('wipe', 'com_datacompliance'))
                <div>
                    <a href="@route('index.php?option=com_datacompliance&view=Options&task=wipe&user_id=' . $this->user->id . '&' . $this->getContainer()->platform->getToken() . '=1')"
                       class="akeeba-btn--red--block">
                        <span class="akion-nuclear"></span>
                        @lang('COM_DATACOMPLIANCE_OPTIONS_DATARIGHTS_BTN_WIPE_ADMIN')
                    </a>
                </div>
            @endif
        </div>
    </div>
@endif
