<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 *
 * @var  \Akeeba\DataCompliance\Site\View\Options\Html $this
 */

$otherInfo = JText::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_OTHERINFO');
$otherInfo = ($otherInfo == 'COM_DATACOMPLIANCE_OPTIONS_WIPE_OTHERINFO') ? '' : $otherInfo;

?>
<div class="akeeba-panel--red">
    <header class="akeeba-block-header">
        <h1>
            @lang('COM_DATACOMPLIANCE_OPTIONS_WIPE_HEADER')
        </h1>
    </header>
    <div class="akeeba-block--warning">
        <h1>@lang('COM_DATACOMPLIANCE_OPTIONS_WIPE_SCARY_1')</h1>
        <h2>@lang('COM_DATACOMPLIANCE_OPTIONS_WIPE_SCARY_2')</h2>
        <h3>@lang('COM_DATACOMPLIANCE_OPTIONS_WIPE_SCARY_3')</h3>
    </div>

    <p>
        @lang('COM_DATACOMPLIANCE_OPTIONS_WIPE_ACTIONSTOBETAKEN')
    </p>
    <ul>
        @foreach ($this->bulletPoints as $bullet)
        <li>{{{ $bullet }}}</li>
        @endforeach
    </ul>
    <p>
        @lang('COM_DATACOMPLIANCE_OPTIONS_WIPE_AUDITTRAILNOTICE')
    </p>
    <p>
        @lang('COM_DATACOMPLIANCE_OPTIONS_WIPE_EXPORTBEFORE')
    </p>
    @if(!empty($otherInfo))
    <p class="akeeba-block--info">
        @lang('COM_DATACOMPLIANCE_OPTIONS_WIPE_OTHERINFO')
    </p>
    @endif
    <p>
        @sprintf('COM_DATACOMPLIANCE_OPTIONS_WIPE_ASKFORPHRASE', JText::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_CONFIRMPHRASE'));
    </p>
    <p class="akeeba-block--warning">
        @sprintf('COM_DATACOMPLIANCE_OPTIONS_WIPE_YOURUSER', 'akeeba-label--grey', $this->getContainer()->platform->getUser()->username)
    </p>

    <form method="post"
          action="@route('index.php?option=com_datacompliance&view=Options&task=wipe')"
          class="akeeba-form--stretch"
    >
        <div class="akeeba-form-group">
            <label for="datacompliance-phrase">
                @lang('COM_DATACOMPLIANCE_OPTIONS_WIPE_PHRASELABEL')
            </label>
            <input id="datacompliance-phrase" type="text" name="phrase" value="" />
        </div>

        <div class="akeeba-form-group--pull-right">
            <div class="akeeba-form-group--actions">
                <button type="submit" class="akeeba-btn--red">
                    @lang('COM_DATACOMPLIANCE_OPTIONS_WIPE_BTN_PROCEED')
                </button>
            </div>
        </div>

        <input type="hidden" name="{{ $this->getContainer()->platform->getToken(true) }}" value="1" />
    </form>
</div>
