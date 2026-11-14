<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light">
<title>Got it. I'll read this myself. | The Shark Web</title>
{{--
    The owner's own design (auto-responder-v2.html), wired to the lead it is about.

    WHAT CAME FROM THE FILE AND WHAT DID NOT. The layout, the copy, the type scale and the
    colours are his, unchanged. Three things were placeholders in the source and are now real:

      {{first_name}}  -> the lead's first name, with a neutral fallback
      {{engagement}}  -> the form's `engagement` field, stored on the lead as `goal`
      {{timeline}}    -> parsed out of `message`, where the frontend parks the door-specific
                         answers as "Label: value" lines (see app/actions/submit-lead.ts)

    The email address and the WhatsApp number were hard-coded in the file. They now come from
    the panel's Contact tab, so changing a number is a settings save and not an edit here.

    FONTS. The Google Fonts <link> and @import are kept from the original: Apple Mail and a
    few desktop clients honour them, Gmail strips them. The family stacks below are what
    actually renders in Gmail, and they are the same fallbacks the site uses.

    Every claim traces to existingwebsitecontents.md. Do not add a turnaround guarantee, a
    price, or a deliverable that is not in the source.
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
    .stack { display:block !important; width:100% !important; padding:0 0 10px 0 !important; }
    .btn a { display:block !important; text-align:center !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background:#F4F4F2;">

<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#F4F4F2;font-size:1px;line-height:1px;">
  I read every enquiry myself and reply within 24 hours.&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;
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
          <td class="jb" align="right" style="font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:11px;line-height:18px;letter-spacing:1.2px;color:#2F4BFF;">[ ENQUIRY RECEIVED ]</td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Headline -->
  <tr>
    <td class="pad" style="padding:44px 40px 8px 40px;">
      <h1 class="h1 sg" style="margin:0;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:40px;line-height:44px;font-weight:700;letter-spacing:-1.4px;color:#0F1011;">Got it, {{ $firstName }}.<br>I will read this myself.</h1>
    </td>
  </tr>

  <!-- Intro -->
  <tr>
    <td class="pad" style="padding:20px 40px 8px 40px;">
      <p class="sg" style="margin:0 0 14px 0;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:17px;line-height:27px;color:#3D4046;">I'm Zonaed, founder of The Shark Web. Your enquiry is in my inbox, and I personally read every one before it goes anywhere else.</p>
      <p class="sg" style="margin:0;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:17px;line-height:27px;color:#3D4046;">Nothing else is needed from you right now.</p>
    </td>
  </tr>

  {{-- Only rendered when the form actually captured these. An empty "TIMELINE" row would
       read as if we had lost the answer. --}}
  @if ($engagement || $timeline)
  <!-- Enquiry summary -->
  <tr>
    <td class="pad" style="padding:32px 40px 8px 40px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #DEDED9;">
        @if ($engagement)
        <tr>
          <td class="jb" width="110" style="padding:14px 18px;{{ $timeline ? 'border-bottom:1px solid #DEDED9;' : '' }}font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:12px;line-height:18px;color:#7A7D84;">[D] engagement</td>
          <td class="jb" style="padding:14px 18px;{{ $timeline ? 'border-bottom:1px solid #DEDED9;' : '' }}font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:12px;line-height:18px;letter-spacing:1px;color:#0F1011;text-transform:uppercase;">{{ $engagement }}</td>
        </tr>
        @endif
        @if ($timeline)
        <tr>
          <td class="jb" width="110" style="padding:14px 18px;font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:12px;line-height:18px;color:#7A7D84;">[E] timeline</td>
          <td class="jb" style="padding:14px 18px;font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:12px;line-height:18px;letter-spacing:1px;color:#0F1011;text-transform:uppercase;">{{ $timeline }}</td>
        </tr>
        @endif
      </table>
    </td>
  </tr>
  @endif

  <!-- Steps -->
  <tr>
    <td class="pad" style="padding:36px 40px 8px 40px;">
      <p class="jb" style="margin:0 0 4px 0;font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:11px;line-height:18px;letter-spacing:1.4px;color:#7A7D84;">WHAT HAPPENS NEXT</p>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td class="jb" valign="top" width="52" style="padding:20px 0;border-top:1px solid #DEDED9;font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:12px;line-height:24px;font-weight:700;color:#2F4BFF;">[01]</td>
          <td class="sg" valign="top" style="padding:20px 0;border-top:1px solid #DEDED9;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:16px;line-height:24px;color:#3D4046;"><strong style="color:#0F1011;font-weight:700;">I read your brief.</strong> If it needs another set of eyes, it goes to the senior person who would actually do the work, never a sales rep.</td>
        </tr>
        <tr>
          <td class="jb" valign="top" width="52" style="padding:20px 0;border-top:1px solid #DEDED9;font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:12px;line-height:24px;font-weight:700;color:#2F4BFF;">[02]</td>
          <td class="sg" valign="top" style="padding:20px 0;border-top:1px solid #DEDED9;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:16px;line-height:24px;color:#3D4046;"><strong style="color:#0F1011;font-weight:700;">You hear back within 24 hours.</strong> Directly from me or the lead on your project, with no handoff in between.</td>
        </tr>
        <tr>
          <td class="jb" valign="top" width="52" style="padding:20px 0;border-top:1px solid #DEDED9;border-bottom:1px solid #DEDED9;font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:12px;line-height:24px;font-weight:700;color:#2F4BFF;">[03]</td>
          <td class="sg" valign="top" style="padding:20px 0;border-top:1px solid #DEDED9;border-bottom:1px solid #DEDED9;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:16px;line-height:24px;color:#3D4046;"><strong style="color:#0F1011;font-weight:700;">If it looks like a fit, we get on a free 30-minute call.</strong> We map your funnel, name the biggest leak, and outline the highest-leverage fix.</td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Free consultancy card (echoes the site chat widget) -->
  <tr>
    <td class="pad" style="padding:32px 40px 8px 40px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0F1011;">
        <tr>
          <td style="background:#2F4BFF;padding:16px 24px;">
            <p class="jb" style="margin:0 0 2px 0;font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:10px;line-height:14px;font-weight:700;letter-spacing:1.6px;color:#C9D1FF;">INCLUDED</p>
            <p class="sg" style="margin:0;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:21px;line-height:27px;font-weight:700;letter-spacing:-0.4px;color:#FFFFFF;">1:1 Free Consultancy</p>
          </td>
        </tr>
        <tr>
          <td style="padding:24px 24px 26px 24px;">
            <p class="sg" style="margin:0 0 20px 0;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:16px;line-height:25px;color:#D5D6DA;">Every engagement starts with a free audit. Ask me anything.</p>
            <table role="presentation" class="btn" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td class="stack" width="50%" style="padding-right:6px;">
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" bgcolor="#0B7A55" style="background:#0B7A55;">
                    <a href="{{ $whatsappLink }}" class="sg" style="display:block;padding:14px 12px;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:15px;line-height:20px;font-weight:700;color:#FFFFFF;text-decoration:none;">WhatsApp me</a>
                  </td></tr></table>
                </td>
                <td class="stack" width="50%" style="padding-left:6px;">
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" style="border:1px solid #3A3C41;">
                    <a href="mailto:{{ $contactEmail }}" class="sg" style="display:block;padding:13px 12px;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:15px;line-height:20px;font-weight:700;color:#FFFFFF;text-decoration:none;">Email me</a>
                  </td></tr></table>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Signature -->
  <tr>
    <td class="pad" style="padding:40px 40px 40px 40px;">
      <p class="sg" style="margin:0 0 20px 0;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:17px;line-height:27px;color:#3D4046;">Talk soon,</p>
      <p class="sg" style="margin:0;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:20px;line-height:26px;font-weight:700;letter-spacing:-0.4px;color:#0F1011;">Zonaed Hossain</p>
      <p class="jb" style="margin:4px 0 0 0;font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:11px;line-height:18px;letter-spacing:1.4px;color:#7A7D84;">FOUNDER &amp; CMO, THE SHARK WEB</p>
      <p class="sg" style="margin:12px 0 0 0;font-family:'Space Grotesk','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:15px;line-height:22px;color:#3D4046;"><a href="mailto:{{ $contactEmail }}" style="color:#2F4BFF;text-decoration:none;font-weight:700;">{{ $contactEmail }}</a>&nbsp;&nbsp;/&nbsp;&nbsp;<a href="{{ $whatsappLink }}" style="color:#2F4BFF;text-decoration:none;font-weight:700;">WhatsApp {{ $whatsappDisplay }}</a></p>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td class="pad" style="background:#F4F4F2;border-top:1px solid #DEDED9;padding:20px 40px;">
      <p class="jb" style="margin:0;font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;font-size:11px;line-height:18px;color:#7A7D84;">You received this because you sent an enquiry through <a href="https://thesharkweb.com" style="color:#0F1011;text-decoration:none;">thesharkweb.com</a>. Encrypted in transit, never shared.</p>
    </td>
  </tr>

</table>

</td></tr>
</table>
</body>
</html>
