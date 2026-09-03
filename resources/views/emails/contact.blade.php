<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Neue Kontaktanfrage</title>
</head>
<body style="margin:0; padding:0; background:#eef2f6; color:#1f2937; font-family:Arial, Helvetica, sans-serif;">
<div style="display:none; max-height:0; overflow:hidden; opacity:0;">
    Neue Anfrage von {{ $name }} über {{ $brand['siteName'] }}.
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; background:#eef2f6;">
    <tr>
        <td align="center" style="padding:36px 16px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; max-width:680px;">
                <tr>
                    <td align="center" style="padding:0 20px 24px;">
                        @if ($brand['logoUrl'])
                            <img src="{{ $brand['logoUrl'] }}" alt="{{ $brand['siteName'] }}" style="display:block; max-width:190px; max-height:64px; width:auto; height:auto; border:0;">
                        @else
                            <div style="font-size:24px; line-height:30px; font-weight:700; color:#172033;">{{ $brand['siteName'] }}</div>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="overflow:hidden; border-radius:18px; background:#ffffff; box-shadow:0 12px 36px rgba(23, 32, 51, .10);">
                        <div style="height:7px; background:{{ $brand['primaryColor'] }};"></div>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding:38px 44px 18px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td width="48" height="48" align="center" valign="middle" style="width:48px; height:48px; border-radius:14px; background:{{ $brand['primaryColor'] }}; color:#ffffff; font-size:23px; font-weight:700;">@</td>
                                            <td style="padding-left:16px;">
                                                <div style="margin:0 0 4px; color:{{ $brand['accentColor'] }}; font-size:12px; line-height:16px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase;">Kontaktformular</div>
                                                <h1 style="margin:0; color:#172033; font-size:27px; line-height:34px; font-weight:700;">Neue Kontaktanfrage</h1>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="margin:22px 0 0; color:#667085; font-size:15px; line-height:24px;">Über die Website ist eine neue Nachricht eingegangen. Alle Angaben finden Sie übersichtlich zusammengefasst.</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:14px 44px 8px;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; border:1px solid #e7ebf0; border-radius:12px; background:#f8fafc;">
                                        <tr>
                                            <td width="50%" valign="top" style="padding:20px 22px; border-bottom:1px solid #e7ebf0;">
                                                <div style="margin-bottom:6px; color:#98a2b3; font-size:11px; line-height:15px; font-weight:700; letter-spacing:.8px; text-transform:uppercase;">Name</div>
                                                <div style="color:#172033; font-size:15px; line-height:22px; font-weight:700;">{{ $name }}</div>
                                            </td>
                                            <td width="50%" valign="top" style="padding:20px 22px; border-bottom:1px solid #e7ebf0; border-left:1px solid #e7ebf0;">
                                                <div style="margin-bottom:6px; color:#98a2b3; font-size:11px; line-height:15px; font-weight:700; letter-spacing:.8px; text-transform:uppercase;">Unternehmen</div>
                                                <div style="color:#172033; font-size:15px; line-height:22px;">{{ $company ?: 'Nicht angegeben' }}</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td width="50%" valign="top" style="padding:20px 22px; border-bottom:1px solid #e7ebf0;">
                                                <div style="margin-bottom:6px; color:#98a2b3; font-size:11px; line-height:15px; font-weight:700; letter-spacing:.8px; text-transform:uppercase;">E-Mail</div>
                                                <a href="mailto:{{ $email }}" style="color:{{ $brand['primaryColor'] }}; font-size:15px; line-height:22px; font-weight:700; text-decoration:none;">{{ $email }}</a>
                                            </td>
                                            <td width="50%" valign="top" style="padding:20px 22px; border-bottom:1px solid #e7ebf0; border-left:1px solid #e7ebf0;">
                                                <div style="margin-bottom:6px; color:#98a2b3; font-size:11px; line-height:15px; font-weight:700; letter-spacing:.8px; text-transform:uppercase;">Telefon</div>
                                                @if ($phone)
                                                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" style="color:#172033; font-size:15px; line-height:22px; text-decoration:none;">{{ $phone }}</a>
                                                @else
                                                    <div style="color:#172033; font-size:15px; line-height:22px;">Nicht angegeben</div>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" valign="top" style="padding:20px 22px;">
                                                <div style="margin-bottom:6px; color:#98a2b3; font-size:11px; line-height:15px; font-weight:700; letter-spacing:.8px; text-transform:uppercase;">Gewünschte Dienstleistung</div>
                                                <div style="color:#172033; font-size:15px; line-height:22px;">{{ $serviceType ?: 'Nicht angegeben' }}</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:20px 44px 10px;">
                                    <div style="margin-bottom:9px; color:#667085; font-size:12px; line-height:16px; font-weight:700; letter-spacing:.8px; text-transform:uppercase;">Nachricht</div>
                                    <div style="padding:22px; border-left:4px solid {{ $brand['accentColor'] }}; border-radius:4px 12px 12px 4px; background:#f8fafc; color:#344054; font-size:15px; line-height:25px; white-space:pre-wrap;">{{ $messageText }}</div>
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding:24px 44px 42px;">
                                    <a href="mailto:{{ $email }}" style="display:inline-block; padding:14px 24px; border-radius:9px; background:{{ $brand['accentColor'] }}; color:{{ $brand['buttonTextColor'] }}; font-size:14px; line-height:18px; font-weight:700; text-decoration:none;">Direkt antworten</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:22px 24px 0; color:#98a2b3; font-size:12px; line-height:19px;">
                        Diese E-Mail wurde automatisch über das Kontaktformular von
                        <a href="{{ $brand['websiteUrl'] }}" style="color:#667085; text-decoration:none;">{{ $brand['siteName'] }}</a> erstellt.
                        @if ($brand['contactEmail'])
                            <br>{{ $brand['contactEmail'] }}
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
