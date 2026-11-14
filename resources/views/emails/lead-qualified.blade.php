<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light">
<title>You're a fit — book your 30 minutes | The Shark Web</title>
{{--
    The second email: the booking invitation, sent only when the owner approves a lead.

    SAME DESIGN SYSTEM AS THE ACKNOWLEDGEMENT. Same paper ground, same mono meta labels, same
    sharp corners, same accent blue. A lead should not be able to tell that two different
    screens wrote the two messages.

    WHAT CHANGED FROM THE ACKNOWLEDGEMENT IS THE JOB. That one says "we have it". This one
    says "we looked, and we want to talk", so the only thing on the page that matters is the
    booking button — it gets the accent band, the only saturated block in the email.

    NO UNSUBSCRIBE LINK. This is a direct reply to someone who asked to hear from us, not a
    list. An unsubscribe here would be meaningless, and a broken one is worse than none.

    Every claim traces to existingwebsitecontents.md: the free 30-minute consultation, what it
    covers, and nothing else.
--}}
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Space+Grotesk:wght@400;500;700&display=swap');
  body { margin:0; padding:0; width:100% !important; background:#F4F4F2; -webkit-text-size-adjust:100%; }
  table { border-collapse:collapse; }
  img { border:0; }
  a { color:#2F4BFF; }
  .sg { font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif; }
  .jb { font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace; }
  @media only screen and (max-width:620px) {
    .wrap { width:100% !important; }
    .pad { padding-left:22px !important; padding-right:22px !important; }
    .h1 { font-size:30px !important; line-height:35px !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background:#F4F4F2;">

<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#F4F4F2;font-size:1px;line-height:1px;">
  Pick a time that suits you — 30 minutes, free, straight in my calendar.&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F4F4F2;">
<tr><td align="center" style="padding:40px 16px 48px 16px;">

<table role="presentation" class="wrap" width="620" cellpadding="0" cellspacing="0" border="0" style="width:620px;max-width:620px;background:#FFFFFF;border:1px solid #DEDED9;">

  <!-- Top bar -->
  <tr>
    <td class="pad" style="padding:20px 40px;border-bottom:1px solid #DEDED9;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td class="jb" style="font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:12px;line-height:18px;font-weight:700;letter-spacing:1px;color:#0F1011;">THE SHARK WEB</td>
          <td class="jb" align="right" style="font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:11px;line-height:18px;letter-spacing:1.2px;color:#2F4BFF;">[ INVITATION ]</td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Headline -->
  <tr>
    <td class="pad" style="padding:44px 40px 8px 40px;">
      <h1 class="h1 sg" style="margin:0;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:40px;line-height:44px;font-weight:700;letter-spacing:-1.4px;color:#0F1011;">Let's talk, {{ $firstName }}.<br>30 minutes, free.</h1>
    </td>
  </tr>

  <!-- Intro -->
  <tr>
    <td class="pad" style="padding:20px 40px 8px 40px;">
      <p class="sg" style="margin:0 0 14px 0;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:17px;line-height:27px;color:#3D4046;">I read your enquiry myself. It looks like a good fit for what we do, so the next step is a proper conversation rather than more email.</p>
      <p class="sg" style="margin:0;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:17px;line-height:27px;color:#3D4046;">Pick a slot that suits you and it goes straight into my calendar. No pitch deck, no account manager.</p>
    </td>
  </tr>

  <!-- What the 30 minutes covers -->
  <tr>
    <td class="pad" style="padding:36px 40px 8px 40px;">
      <p class="jb" style="margin:0 0 4px 0;font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:11px;line-height:18px;letter-spacing:1.4px;color:#7A7D84;">WHAT THE 30 MINUTES COVER</p>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td class="jb" valign="top" width="52" style="padding:20px 0;border-top:1px solid #DEDED9;font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:12px;line-height:24px;font-weight:700;color:#2F4BFF;">[01]</td>
          <td class="sg" valign="top" style="padding:20px 0;border-top:1px solid #DEDED9;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:16px;line-height:24px;color:#3D4046;"><strong style="color:#0F1011;font-weight:700;">We map your funnel.</strong> Where the traffic comes from, where it stops, and what it costs you today.</td>
        </tr>
        <tr>
          <td class="jb" valign="top" width="52" style="padding:20px 0;border-top:1px solid #DEDED9;font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:12px;line-height:24px;font-weight:700;color:#2F4BFF;">[02]</td>
          <td class="sg" valign="top" style="padding:20px 0;border-top:1px solid #DEDED9;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:16px;line-height:24px;color:#3D4046;"><strong style="color:#0F1011;font-weight:700;">We name the biggest leak.</strong> The one fix worth doing first, not a list of twenty.</td>
        </tr>
        <tr>
          <td class="jb" valign="top" width="52" style="padding:20px 0;border-top:1px solid #DEDED9;border-bottom:1px solid #DEDED9;font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:12px;line-height:24px;font-weight:700;color:#2F4BFF;">[03]</td>
          <td class="sg" valign="top" style="padding:20px 0;border-top:1px solid #DEDED9;border-bottom:1px solid #DEDED9;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:16px;line-height:24px;color:#3D4046;"><strong style="color:#0F1011;font-weight:700;">You leave with a plan.</strong> The highest-leverage fix, in writing, whether or not you work with us.</td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Booking card -->
  <tr>
    <td class="pad" style="padding:32px 40px 8px 40px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0F1011;">
        <tr>
          <td style="background:#2F4BFF;padding:16px 24px;">
            <p class="jb" style="margin:0 0 2px 0;font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:10px;line-height:14px;font-weight:700;letter-spacing:1.6px;color:#C9D1FF;">30 MINUTES · NO CHARGE</p>
            <p class="sg" style="margin:0;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:21px;line-height:27px;font-weight:700;letter-spacing:-0.4px;color:#FFFFFF;">1:1 with Zonaed Hossain</p>
          </td>
        </tr>
        <tr>
          <td style="padding:24px 24px 26px 24px;">
            <p class="sg" style="margin:0 0 20px 0;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:16px;line-height:25px;color:#D5D6DA;">Choose a time from the calendar. You get a confirmation and a reminder, and I get your details already filled in.</p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" bgcolor="#2F4BFF" style="background:#2F4BFF;">
              <a href="{{ $bookingUrl }}" class="sg" style="display:block;padding:16px 12px;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:16px;line-height:20px;font-weight:700;letter-spacing:0.2px;color:#FFFFFF;text-decoration:none;">Book your 30-minute session &rarr;</a>
            </td></tr></table>
            <p class="jb" style="margin:16px 0 0 0;font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:10px;line-height:16px;letter-spacing:0.6px;color:#8A8D94;">OPENS MY CALENDAR — ALL TIME ZONES SHOWN IN YOURS</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Signature -->
  <tr>
    <td class="pad" style="padding:40px 40px 40px 40px;">
      <p class="sg" style="margin:0 0 20px 0;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:17px;line-height:27px;color:#3D4046;">If none of the slots work, reply to this email and we will find one.</p>
      <p class="sg" style="margin:0;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:20px;line-height:26px;font-weight:700;letter-spacing:-0.4px;color:#0F1011;">Zonaed Hossain</p>
      <p class="jb" style="margin:4px 0 0 0;font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:11px;line-height:18px;letter-spacing:1.4px;color:#7A7D84;">FOUNDER &amp; CMO, THE SHARK WEB</p>
      <p class="sg" style="margin:12px 0 0 0;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:15px;line-height:22px;color:#3D4046;"><a href="mailto:{{ $contactEmail }}" style="color:#2F4BFF;text-decoration:none;font-weight:700;">{{ $contactEmail }}</a>&nbsp;&nbsp;/&nbsp;&nbsp;<a href="{{ $whatsappLink }}" style="color:#2F4BFF;text-decoration:none;font-weight:700;">WhatsApp {{ $whatsappDisplay }}</a></p>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td class="pad" style="background:#F4F4F2;border-top:1px solid #DEDED9;padding:20px 40px;">
      <p class="jb" style="margin:0;font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:11px;line-height:18px;color:#7A7D84;">You received this because you sent an enquiry through <a href="https://thesharkweb.com" style="color:#0F1011;text-decoration:none;">thesharkweb.com</a> and we replied to it. Encrypted in transit, never shared.</p>
    </td>
  </tr>

</table>

</td></tr>
</table>
</body>
</html>
