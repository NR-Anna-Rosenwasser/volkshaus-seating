<!DOCTYPE html>
<html lang="de-CH">
    <body style="margin: 0; padding: 24px; background: #fac9c9; font-family: Helvetica, Arial, sans-serif; color: #3c3c3c; line-height: 1.5;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px; background: #ffffff; border-radius: 16px;">
                        <tr>
                            <td style="padding: 32px;">
                                <p style="margin: 0 0 16px; font-size: 18px; font-weight: bold; color: #181818;">{{ $greeting }}</p>

                                @foreach ($lines as $line)
                                    <p style="margin: 0 0 12px;">{{ $line }}</p>
                                @endforeach

                                @if ($seats !== [])
                                    <ul style="margin: 0 0 16px; padding: 0; list-style: none;">
                                        @foreach ($seats as $seat)
                                            <li style="margin: 0 0 4px; font-size: 18px; font-weight: bold; color: #181818;">{{ $seat }}</li>
                                        @endforeach
                                    </ul>
                                @endif

                                @if ($planImage)
                                    <img src="{{ $message->embedData($planImage, 'saalplan.png', 'image/png') }}" width="496" alt="Saalplan: {{ implode(', ', $seats) }}" style="display: block; width: 100%; max-width: 496px; height: auto; margin: 0 0 6px; border: 1px solid #e8e8e8; border-radius: 12px;">
                                    <p style="margin: 0 0 16px; font-size: 12px; color: #6b6b6b;">{{ $planCaption }}</p>
                                @endif

                                <p style="margin: 20px 0;">
                                    <a href="{{ $buttonUrl }}" style="display: inline-block; padding: 12px 20px; border-radius: 12px; background: #181818; color: #ffffff; font-weight: bold; text-decoration: none;">{{ $buttonLabel }}</a>
                                </p>

                                @if ($summaryUrl)
                                    <p style="margin: 0 0 20px; font-size: 14px;">
                                        <a href="{{ $summaryUrl }}" style="color: #a05c50; font-weight: bold;">Bestellung bei Hi.Events ansehen</a>
                                    </p>
                                @endif

                                @foreach ($closing as $line)
                                    <p style="margin: 0 0 12px; font-size: 14px;">{{ $line }}</p>
                                @endforeach

                                <p style="margin: 16px 0 0; font-size: 12px; color: #6b6b6b;">{{ $personalNote }}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
