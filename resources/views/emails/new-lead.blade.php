<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New enquiry</title>
</head>
{{--
    Inline styles only, and a single column — this has to survive Gmail, Outlook and
    Apple Mail without a stylesheet. Colours follow DESIGNINSTRUCTION.md: obsidian
    surfaces with the Shark Web accent orange.
--}}
<body style="margin:0;padding:0;background:#08090A;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Inter,Helvetica,Arial,sans-serif;color:#F5F5F5;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#08090A;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#121315;border:1px solid rgba(255,255,255,0.08);border-radius:12px;overflow:hidden;">

                    {{-- Header --}}
                    <tr>
                        <td style="padding:24px 28px;border-bottom:1px solid rgba(255,255,255,0.08);">
                            <div style="font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#FF4D0A;font-weight:600;">
                                New enquiry
                            </div>
                            <div style="font-size:22px;font-weight:700;color:#FFFFFF;margin-top:6px;">
                                {{ $lead->displayName() }}
                            </div>
                            <div style="margin-top:10px;">
                                <span style="display:inline-block;padding:4px 10px;border-radius:999px;background:rgba(255,77,10,0.15);color:#FF4D0A;font-size:12px;font-weight:600;">
                                    {{ $lead->lead_score?->getLabel() ?? 'Unscored' }}
                                </span>
                                <span style="display:inline-block;padding:4px 10px;border-radius:999px;background:rgba(255,255,255,0.08);color:#A1A1A1;font-size:12px;margin-left:6px;">
                                    {{ $lead->status?->getLabel() ?? 'New' }}
                                </span>
                            </div>
                        </td>
                    </tr>

                    {{-- Details --}}
                    <tr>
                        <td style="padding:24px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding:6px 0;color:#737373;font-size:13px;width:120px;">Email</td>
                                    <td style="padding:6px 0;color:#F5F5F5;font-size:14px;">
                                        <a href="mailto:{{ $lead->email }}" style="color:#FF4D0A;text-decoration:none;">{{ $lead->email }}</a>
                                    </td>
                                </tr>
                                @if (filled($lead->phone))
                                    <tr>
                                        <td style="padding:6px 0;color:#737373;font-size:13px;">Phone</td>
                                        <td style="padding:6px 0;color:#F5F5F5;font-size:14px;">
                                            <a href="tel:{{ $lead->phone }}" style="color:#FF4D0A;text-decoration:none;">{{ $lead->phone }}</a>
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:6px 0;color:#737373;font-size:13px;">Goal</td>
                                    <td style="padding:6px 0;color:#F5F5F5;font-size:14px;">{{ $lead->goal ?: 'Growth Audit' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;color:#737373;font-size:13px;">Received</td>
                                    <td style="padding:6px 0;color:#F5F5F5;font-size:14px;">{{ $lead->created_at?->format('j M Y, H:i') }}</td>
                                </tr>
                                @if ($attribution)
                                    <tr>
                                        <td style="padding:6px 0;color:#737373;font-size:13px;">Campaign</td>
                                        <td style="padding:6px 0;color:#F5F5F5;font-size:14px;">{{ $attribution }}</td>
                                    </tr>
                                @endif
                            </table>

                            @if (filled($lead->message))
                                <div style="margin-top:20px;padding:16px;background:#0D0E10;border-left:3px solid #FF4D0A;border-radius:6px;">
                                    <div style="font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#737373;margin-bottom:8px;">Message</div>
                                    <div style="color:#F5F5F5;font-size:14px;line-height:1.6;white-space:pre-wrap;">{{ $lead->message }}</div>
                                </div>
                            @endif
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:18px 28px;border-top:1px solid rgba(255,255,255,0.08);background:#0D0E10;">
                            <div style="color:#555555;font-size:12px;line-height:1.6;">
                                Reply within 24 hours. This lead is in the pipeline as
                                <strong style="color:#A1A1A1;">#{{ $lead->id }}</strong>.
                            </div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
