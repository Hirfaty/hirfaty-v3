{{--
    The boxed digit beside a step. Its own file only so <x-mail.steps> does not
    have to carry two copies of it for the LTR and RTL column orders.

    A fixed-size table cell with width/height as *attributes* as well as
    styles — Outlook ignores the CSS ones.

    Expects: $n
--}}
@php
    $theme = config('mail_theme');
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td align="center" valign="middle" width="24" height="24"
            style="width:24px; height:24px; border:1px solid {{ $theme['accent'] }}; font-family:{{ $theme['font'] }}; font-size:11px; color:{{ $theme['accent'] }};">
            {{ $n }}
        </td>
    </tr>
</table>
