{{--
    The numbered "what happens next" list.

    Each step is a table row with the number in its own fixed-width cell. The
    old version used `display:flex; gap:12px` with the number as a flex item —
    Outlook supports neither property, so the numbers and their text ran
    together into a single unbroken paragraph. This is the specific breakage
    emails/verify.blade.php was already rebuilt to avoid.

    Props:
      title — optional uppercase lead-in
      steps — [['title' => …, 'body' => …], …]; 'title' optional per step
--}}
@props(['title' => null, 'steps'])
@aware(['locale' => 'fr'])

@php
    $theme = config('mail_theme');
    $rtl = $locale === 'ar';
    $font = $rtl ? $theme['font_rtl'] : $theme['font'];
    $align = $rtl ? 'right' : 'left';
    // Bottom gap only; the horizontal offset comes from the number cell's
    // own width, so it is the same on both sides.
    $cellPad = 'padding:0 0 16px 0;';
@endphp

<tr>
    <td align="{{ $align }}" style="padding:26px 40px 0 40px;">
        @if ($title)
            <p style="margin:0 0 18px 0; font-family:{{ $font }}; font-size:11px; letter-spacing:0.18em; text-transform:uppercase; color:{{ $theme['accent'] }};">
                {{ $title }}
            </p>
        @endif

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            @foreach ($steps as $i => $step)
                <tr>
                    @if ($rtl)
                        <td valign="top" align="right" style="{{ $cellPad }}">
                            <p style="margin:0; font-family:{{ $font }}; font-size:14px; line-height:1.6; color:{{ $theme['soft'] }};">
                                @if (! empty($step['title']))
                                    <strong style="color:{{ $theme['ink'] }};">{{ $step['title'] }}</strong><br>
                                @endif
                                {{ $step['body'] }}
                            </p>
                        </td>
                        <td width="34" valign="top" style="width:34px; {{ $cellPad }} padding-left:12px;">
                            @include('components.mail.partials.step-number', ['n' => $i + 1])
                        </td>
                    @else
                        <td width="34" valign="top" style="width:34px; {{ $cellPad }}">
                            @include('components.mail.partials.step-number', ['n' => $i + 1])
                        </td>
                        <td valign="top" style="{{ $cellPad }}">
                            <p style="margin:0; font-family:{{ $font }}; font-size:14px; line-height:1.6; color:{{ $theme['soft'] }};">
                                @if (! empty($step['title']))
                                    <strong style="color:{{ $theme['ink'] }};">{{ $step['title'] }}</strong><br>
                                @endif
                                {{ $step['body'] }}
                            </p>
                        </td>
                    @endif
                </tr>
            @endforeach
        </table>
    </td>
</tr>
