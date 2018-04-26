<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 *
 * @var  \Akeeba\DataCompliance\Site\View\Options\Html $this
 */

$js = <<< JS
function datacompliance_toggle_article()
{
    var elArticle = window.jQuery('#datacompliance-article');

    if (elArticle.css('display') === 'none')
    {
        elArticle.show();
        return;
    }

    elArticle.hide();
}

JS;

$this->addJavascriptInline($js);
?>

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
        <a onclick="datacompliance_toggle_article();">
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
            @jhtml('FEFHelper.select.booleanswitch', 'enabled', 0)
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

<div class="akeeba-panel--orange">
    <header class="akeeba-block-header">
        <h1>
            @lang('COM_DATACOMPLIANCE_OPTIONS_DATARIGHTS_HEADER')
        </h1>
    </header>
    <p>
        @lang('COM_DATACOMPLIANCE_OPTIONS_DATARIGHTS_INFOBLOCK')
    </p>
    <p class="akeeba-block--warning">
        @lang('COM_DATACOMPLIANCE_OPTIONS_DATARIGHTS_WARNING')
    </p>
    <div class="akeeba-container--50-50">
        <div>
            <a href="@route('index.php?option=com_datacompliance&view=Options&task=export&format=raw&' . $this->getContainer()->platform->getToken() . '=1')"
               class="akeeba-btn--success--block">
                <span class="akion-android-download"></span>
                @lang('COM_DATACOMPLIANCE_OPTIONS_DATARIGHTS_BTN_EXPORT')
            </a>
        </div>
        <div>
            <a href="@route('index.php?option=com_datacompliance&view=Options&task=wipe&' . $this->getContainer()->platform->getToken() . '=1')"
               class="akeeba-btn--red--block">
                <span class="akion-nuclear"></span>
                @lang('COM_DATACOMPLIANCE_OPTIONS_DATARIGHTS_BTN_WIPE')
            </a>
        </div>
    </div>
</div>