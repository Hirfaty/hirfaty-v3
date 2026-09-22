{{--
    A bordered box holding label/value rows — order reference, date, address.

    This is the direct replacement for the old `.order-meta` block, whose rows
    were `display:flex; justify-content:space-between`. Flexbox is unsupported
    in Outlook and Gmail, so every one of those rows rendered as the label and
    the value run together on one line. A two-column table is the only thing
    that reliably puts a value on the far side of a row in mail.

    Contains <x-mail.row> children.
--}}
@aware(['locale' => 'fr'])

@php
    $theme = config('mail_theme');
@endphp

<tr>
    <td style="padding:24px 40px 0 40px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="border:1px solid {{ $theme['line'] }}; background-color:{{ $theme['page'] }};">
            {{ $slot }}
        </table>
    </td>
</tr>
