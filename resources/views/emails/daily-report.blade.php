<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daily report</title>
</head>
{{--
    Inline styles, one column, same obsidian/accent palette as the lead alert. This has to
    survive Gmail and Outlook, which strip <style> blocks and ignore flexbox, so every
    layout below is a table and every number is its own cell.
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
                                Daily report
                            </div>
                            <div style="font-size:22px;font-weight:700;color:#FFFFFF;margin-top:6px;">
                                {{ $report['date'] }}
                            </div>
                        </td>
                    </tr>

                    {{-- The one number that matters --}}
                    <tr>
                        <td style="padding:28px 28px 8px;">
                            <div style="font-size:13px;color:#A1A1A1;letter-spacing:0.04em;text-transform:uppercase;">
                                Leads today
                            </div>
                            <div style="font-size:52px;line-height:1.1;font-weight:700;color:#FFFFFF;margin-top:4px;">
                                {{ $report['leads_today'] }}
                            </div>
                            <div style="font-size:14px;margin-top:6px;color:{{ $report['lead_delta'] > 0 ? '#4ADE80' : ($report['lead_delta'] < 0 ? '#F87171' : '#A1A1A1') }};">
                                @if ($report['lead_delta'] > 0)
                                    +{{ $report['lead_delta'] }} vs yesterday
                                @elseif ($report['lead_delta'] < 0)
                                    {{ $report['lead_delta'] }} vs yesterday
                                @else
                                    Level with yesterday
                                @endif
                                <span style="color:#6B6B6B;">({{ $report['leads_yesterday'] }} yesterday)</span>
                            </div>
                        </td>
                    </tr>

                    {{-- Three supporting numbers --}}
                    <tr>
                        <td style="padding:20px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="33%" style="padding:14px 10px 14px 0;border-top:1px solid rgba(255,255,255,0.08);">
                                        <div style="font-size:24px;font-weight:700;color:#FFFFFF;">{{ $report['hot_today'] }}</div>
                                        <div style="font-size:11px;color:#A1A1A1;text-transform:uppercase;letter-spacing:0.05em;margin-top:2px;">Hot today</div>
                                    </td>
                                    <td width="33%" style="padding:14px 10px;border-top:1px solid rgba(255,255,255,0.08);">
                                        <div style="font-size:24px;font-weight:700;color:#FFFFFF;">{{ $report['open_leads'] }}</div>
                                        <div style="font-size:11px;color:#A1A1A1;text-transform:uppercase;letter-spacing:0.05em;margin-top:2px;">Open pipeline</div>
                                    </td>
                                    <td width="33%" style="padding:14px 0 14px 10px;border-top:1px solid rgba(255,255,255,0.08);">
                                        <div style="font-size:24px;font-weight:700;color:#FFFFFF;">{{ $report['subscribers_today'] }}</div>
                                        <div style="font-size:11px;color:#A1A1A1;text-transform:uppercase;letter-spacing:0.05em;margin-top:2px;">New subscribers</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Status breakdown, only when there is something to break down --}}
                    @if (! empty($report['by_status']))
                        <tr>
                            <td style="padding:0 28px 20px;">
                                <div style="font-size:12px;color:#A1A1A1;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px;">
                                    By status
                                </div>
                                @foreach ($report['by_status'] as $status => $count)
                                    <div style="font-size:14px;color:#E5E5E5;padding:4px 0;">
                                        {{ $status }}
                                        <span style="float:right;color:#FFFFFF;font-weight:600;">{{ $count }}</span>
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @endif

                    {{-- Anything that needs a human --}}
                    @if ($report['failed_webhooks'] > 0 || ! $report['capi_enabled'])
                        <tr>
                            <td style="padding:0 28px 20px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:rgba(255,77,10,0.08);border-left:3px solid #FF4D0A;border-radius:6px;">
                                    <tr>
                                        <td style="padding:14px 16px;font-size:13px;color:#E5E5E5;line-height:1.6;">
                                            @if ($report['failed_webhooks'] > 0)
                                                <div>{{ $report['failed_webhooks'] }} webhook{{ $report['failed_webhooks'] === 1 ? '' : 's' }} failed in the last 24 hours.</div>
                                            @endif
                                            @if (! $report['capi_enabled'])
                                                <div>Server-side Meta events are off, so only the browser pixel is counting.</div>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:18px 28px;border-top:1px solid rgba(255,255,255,0.08);background:#0E0F11;">
                            <div style="font-size:13px;color:#A1A1A1;">
                                {{ $report['subscribers_total'] }} subscribers in total.
                            </div>
                            <a href="{{ $report['panel_url'] }}" style="display:inline-block;margin-top:10px;font-size:13px;color:#FF4D0A;text-decoration:none;font-weight:600;">
                                Open the panel →
                            </a>
                        </td>
                    </tr>
                </table>

                <div style="max-width:600px;font-size:11px;color:#6B6B6B;padding:14px 4px;text-align:left;">
                    Sent every night at 23:30 Asia/Dhaka. Turn it off in the panel under Settings → Daily report.
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
