<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

$otherInfo = JText::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_OTHERINFO');
$otherInfo = ($otherInfo == 'COM_DATACOMPLIANCE_OPTIONS_WIPE_OTHERINFO') ? '' : $otherInfo;

?>
<div class="akeeba-panel--red">
    <header class="akeeba-block-header">
        <h1>
            @if($this->type == 'user')
            @lang('COM_DATACOMPLIANCE_OPTIONS_WIPE_HEADER')
            @else
            @sprintf('COM_DATACOMPLIANCE_OPTIONS_WIPE_ADMIN_HEADER', $this->user->username)
            @endif
        </h1>
    </header>
    <div class="akeeba-block--warning">
        <h1>@lang('COM_DATACOMPLIANCE_OPTIONS_WIPE_SCARY_1')</h1>
        <h2>@lang('COM_DATACOMPLIANCE_OPTIONS_WIPE_SCARY_2')</h2>
        @if($this->type == 'user')
            <h3>@lang('COM_DATACOMPLIANCE_OPTIONS_WIPE_SCARY_3')</h3>
        @else
            <h3>@lang('COM_DATACOMPLIANCE_OPTIONS_WIPE_SCARY_3_ADMIN')</h3>
        @endif
    </div>

    <p>
        @if($this->type == 'user')
        @lang('COM_DATACOMPLIANCE_OPTIONS_WIPE_ACTIONSTOBETAKEN')
        @else
        @sprintf('COM_DATACOMPLIANCE_OPTIONS_WIPE_ACTIONSTOBETAKEN_ADMIN', $this->user->username)
        @endif
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
    @if(!empty($otherInfo) && ($this->type == 'user'))
    <p class="akeeba-block--info">
        {{ $otherInfo }}
    </p>
    @endif
    <p>
        @sprintf(($this->type == 'user') ? 'COM_DATACOMPLIANCE_OPTIONS_WIPE_ASKFORPHRASE' : 'COM_DATACOMPLIANCE_OPTIONS_WIPE_ASKFORPHRASE_ADMIN', JText::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_CONFIRMPHRASE'));
    </p>
    <p class="akeeba-block--warning">
        @sprintf('COM_DATACOMPLIANCE_OPTIONS_WIPE_YOURUSER', 'akeeba-label--grey', $this->user->username)
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
                    @lang(($this->type == 'user') ? 'COM_DATACOMPLIANCE_OPTIONS_WIPE_BTN_PROCEED' : 'COM_DATACOMPLIANCE_OPTIONS_WIPE_BTN_PROCEED_ADMIN')
                </button>
            </div>
        </div>

        <input type="hidden" name="{{ $this->getContainer()->platform->getToken(true) }}" value="1" />
        <input type="hidden" name="user_id" value="{{ $this->user->id }}" />
    </form>
</div>
