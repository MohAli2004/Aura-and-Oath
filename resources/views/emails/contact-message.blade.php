<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact form message</title>
</head>
<body style="margin:0;padding:0;background:#F7F3EE;font-family:Georgia,'Times New Roman',serif;color:#2C2A28;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#F7F3EE;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #E8DFD4;">
                    <tr>
                        <td style="padding:28px 28px 8px;font-size:22px;letter-spacing:0.02em;">
                            New contact message
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 24px;font-size:14px;line-height:1.6;color:#8B7E74;">
                            Submitted via the {{ config('aura.name') }} website contact form.
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 8px;font-size:13px;text-transform:uppercase;letter-spacing:0.08em;color:#8B7E74;">
                            From
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 20px;font-size:16px;line-height:1.5;">
                            {{ $senderName }} &lt;{{ $senderEmail }}&gt;
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 8px;font-size:13px;text-transform:uppercase;letter-spacing:0.08em;color:#8B7E74;">
                            Message
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 28px;font-size:16px;line-height:1.7;white-space:pre-wrap;">{{ $contactMessage }}</td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px;border-top:1px solid #E8DFD4;font-size:12px;color:#8B7E74;">
                            Reply to this email to respond directly to the visitor.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
