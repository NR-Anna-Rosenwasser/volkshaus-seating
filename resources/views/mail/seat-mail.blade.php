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

                                <p style="margin: 20px 0;">
                                    <a href="{{ $buttonUrl }}" style="display: inline-block; padding: 12px 20px; border-radius: 12px; background: #181818; color: #ffffff; font-weight: bold; text-decoration: none;">{{ $buttonLabel }}</a>
                                </p>

                                @foreach ($closing as $line)
                                    <p style="margin: 0 0 12px; font-size: 14px;">{{ $line }}</p>
                                @endforeach

                                <p style="margin: 16px 0 0; font-size: 12px; color: #6b6b6b;">Dieser Link ist persönlich. Bitte leitet ihn nicht weiter.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
