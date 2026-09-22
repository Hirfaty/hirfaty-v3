{{--
    A hairline between sections.

    A filled 1px table cell rather than <hr>, which mail clients style
    inconsistently — several render it with their own margin and a 2px
    inset bevel.
--}}
@php
    $theme = config('mail_theme');
@endphp

<tr>
    <td style="padding:30px 40px 0 40px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td style="height:1px; line-height:1px; font-size:0; background-color:{{ $theme['line'] }};">&nbsp;</td>
            </tr>
        </table>
    </td>
</tr>
