<?php
/**
 * Outbound email helpers for NUPS-G.
 *
 * Transport selection:
 *   1. SMTP - when SMTP_HOST is set in .env / environment (recommended; e.g. a
 *      Gmail App Password, Zoho, or the church hosting provider's SMTP).
 *   2. PHP mail() - fallback used on shared hosts where mail() is configured.
 *
 * Nothing here throws: every send is reported via the returned result so the
 * admin flow is never broken by a delivery problem.
 */

function mailer_config($key, $default = null)
{
    return env_value('MAIL_' . $key, env_value($key, $default));
}

/** True when real outbound mail is configured (SMTP or a usable mail()). */
function mailer_is_configured()
{
    if (mailer_config('HOST')) {
        return true;
    }
    return function_exists('mail');
}

/**
 * Send one HTML email. Returns ['sent' => bool, 'error' => string|null].
 */
function send_mail($to, $subject, $html, $text = '')
{
    $from      = mailer_config('FROM', ADMIN_EMAIL);
    $from_name = SITE_NAME;
    $text      = $text !== '' ? $text : strip_tags(str_replace(['<br>', '</p>'], "\n", $html));

    if (mailer_config('HOST')) {
        return smtp_send($to, $subject, $html, $text, $from, $from_name);
    }

    // Fallback: PHP mail()
    $headers  = "From: {$from_name} <{$from}>\r\n";
    $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    try {
        $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers);
        return $ok ? ['sent' => true, 'error' => null]
                   : ['sent' => false, 'error' => 'PHP mail() returned false (no local mail server configured).'];
    } catch (Exception $e) {
        return ['sent' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Minimal SMTP client (AUTH LOGIN over STARTTLS or implicit SSL).
 * Covers the common cases (Gmail/Outlook/Zoho/hosting SMTP) without any
 * third-party library.
 */
function smtp_send($to, $subject, $html, $text, $from, $from_name)
{
    $host = mailer_config('HOST');
    $port = (int)mailer_config('PORT', 587);
    $user = mailer_config('USER');
    $pass = mailer_config('PASS');
    $secure = strtolower(mailer_config('SECURE', '')); // '' | 'tls' | 'ssl'

    if (!$user || !$pass) {
        return ['sent' => false, 'error' => 'SMTP_HOST is set but MAIL_USER/MAIL_PASS are missing.'];
    }

    $remote = $host . ':' . $port;
    if ($secure === 'ssl') {
        $remote = 'ssl://' . $remote;
    }

    $errno = 0;
    $errstr = '';
    $ssl_context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ]
    ]);
    $conn = @stream_socket_client($remote, $errno, $errstr, 15,
        STREAM_CLIENT_CONNECT, $ssl_context);
    if (!$conn && APP_ENV === 'development') {
        $ssl_context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        $conn = @stream_socket_client($remote, $errno, $errstr, 15,
            STREAM_CLIENT_CONNECT, $ssl_context);
    }
    if (!$conn) {
        return ['sent' => false, 'error' => "SMTP connect failed: $errstr"];
    }

    stream_set_timeout($conn, 15);
    $read = function () use ($conn) {
        $resp = '';
        while ($line = fgets($conn, 515)) {
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $resp;
    };
    $cmd = function ($expect, $name, $c = null) use ($conn, $read) {
        if ($c !== null) {
            fwrite($conn, $c . "\r\n");
        }
        $resp = $read();
        if (!preg_match('/^' . $expect . '/', $resp)) {
            return $resp;
        }
        return true;
    };

    $fail = function ($msg) use ($conn, $cmd) {
        @$cmd(2, 'quit', 'QUIT');
        @fclose($conn);
        return ['sent' => false, 'error' => $msg];
    };

    if (($r = $cmd(2, 'greeting')) !== true) return $fail("SMTP greeting failed: " . trim($r));
    if (($r = $cmd(2, 'ehlo', "EHLO " . (gethostname() ?: 'localhost'))) !== true) return $fail("EHLO failed: " . trim($r));

    if ($secure === 'tls') {
        if (($r = $cmd(2, 'starttls', 'STARTTLS')) !== true) return $fail("STARTTLS rejected: " . trim($r));
        if (!@stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            return $fail('TLS negotiation failed.');
        }
        if (($r = $cmd(2, 'ehlo2', "EHLO " . (gethostname() ?: 'localhost'))) !== true) return $fail("EHLO (TLS) failed: " . trim($r));
    }

    if (($r = $cmd('334', 'auth', 'AUTH LOGIN')) !== true) return $fail("AUTH not accepted: " . trim($r));
    if (($r = $cmd('334', 'user', base64_encode($user))) !== true) return $fail("SMTP username rejected: " . trim($r));
    if (($r = $cmd('235', 'pass', base64_encode($pass))) !== true) return $fail("SMTP password rejected: " . trim($r));

    if (($r = $cmd(2, 'mailfrom', "MAIL FROM:<{$from}>")) !== true) return $fail("MAIL FROM rejected: " . trim($r));
    if (($r = $cmd(2, 'rcptto', "RCPT TO:<{$to}>")) !== true) return $fail("RCPT TO rejected: " . trim($r));

    if (($r = $cmd(3, 'data', 'DATA')) !== true) return $fail("DATA rejected: " . trim($r));

    $boundary = 'np_' . md5(uniqid('', true));
    $headers  = "From: {$from_name} <{$from}>\r\n";
    $headers .= "To: <{$to}>\r\n";
    $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n{$text}\r\n\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n\r\n";
    $body .= "--{$boundary}--\r\n";

    fwrite($conn, $headers . "\r\n" . $body . "\r\n.\r\n");
    $resp = $read();
    if (!preg_match('/^250/', $resp)) {
        return $fail("Message not accepted: " . trim($resp));
    }
    @$cmd(2, 'quit', 'QUIT');
    @fclose($conn);
    return ['sent' => true, 'error' => null];
}

/**
 * Build the HTML newsletter body used for event / blog notifications.
 */
function mail_template($heading, $preview, $cta_label, $cta_url, $subscriber_email, $token)
{
    $unsub = SITE_URL . '/subscribe.php?unsubscribe=1&email=' . urlencode($subscriber_email) . '&token=' . urlencode($token);
    $logo = '';
    if (file_exists(__DIR__ . '/nupsglogo.png')) {
        $logo = SITE_URL . '/nupsglogo.png';
    }
    $esc = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

    return '
<div style="background:#f2f4f9; padding:24px 12px; font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
    <table role="presentation" width="100%" style="max-width:560px; background:#ffffff; border-radius:14px; overflow:hidden; border:1px solid #e3e7f0;">
      <tr><td style="background:linear-gradient(135deg,#101b3d,#1e3a8a); padding:26px 28px; text-align:center;">
        ' . ($logo ? '<img src="' . $esc($logo) . '" alt="" width="52" height="52" style="border-radius:12px; display:inline-block;"><br>' : '') . '
        <span style="color:#ffffff; font-size:18px; font-weight:bold; letter-spacing:.5px;">' . $esc(SITE_NAME) . '</span>
      </td></tr>
      <tr><td style="padding:30px 28px;">
        <h1 style="margin:0 0 12px; font-size:22px; color:#101b3d; line-height:1.35;">' . $esc($heading) . '</h1>
        <p style="margin:0 0 20px; font-size:15px; line-height:1.75; color:#3c465c;">' . $esc($preview) . '</p>
        <table role="presentation" cellpadding="0" cellspacing="0"><tr><td>
          <a href="' . $esc($cta_url) . '" style="display:inline-block; background:#f2a900; color:#101b3d; font-weight:bold; font-size:14px; text-decoration:none; padding:12px 26px; border-radius:999px;">' . $esc($cta_label) . '</a>
        </td></tr></table>
      </td></tr>
      <tr><td style="padding:0 28px 26px; text-align:center;">
        <p style="margin:0; font-size:12px; color:#8a94a8; line-height:1.7;">
          You are receiving this because you subscribed on ' . $esc(SITE_NAME) . '.<br>
          <a href="' . $esc($unsub) . '" style="color:#8a94a8;">Unsubscribe</a> · ' . $esc(SITE_URL) . '
        </p>
      </td></tr>
    </table>
  </td></tr></table>
</div>';
}

/**
 * Email every active subscriber about a new piece of content.
 *
 * $type: 'event' | 'blog'
 * $info: ['title' =>, 'link' =>, 'preview' =>, 'date' =>, 'location' =>]
 *
 * Returns ['sent' => int, 'failed' => int, 'subscribers' => int, 'error' => string|null]
 */
function notify_subscribers($type, array $info)
{
    global $conn;
    $result = ['sent' => 0, 'failed' => 0, 'subscribers' => 0, 'error' => null];

    try {
        $stmt = $conn->query("SELECT email, token FROM subscribers WHERE is_active = TRUE");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return ['sent' => 0, 'failed' => 0, 'subscribers' => 0, 'error' => 'subscribers table not available: ' . $e->getMessage()];
    }

    $result['subscribers'] = count($rows);
    if (empty($rows)) {
        return $result; // nothing to do - no subscribers yet
    }

    $title = $info['title'] ?? 'New update';
    $is_event = ($type === 'event');
    $is_sermon = !$is_event && (($info['post_type'] ?? 'article') === 'sermon');
    $subject = ($is_event ? 'New Event: ' : ($is_sermon ? 'New Sermon: ' : 'New Article: ')) . $title;
    $link = $info['link'] ?? SITE_URL;
    $preview = $info['preview'] ?? ($is_event
        ? 'A new event has been added to the ' . SITE_NAME . ' calendar.'
        : ($is_sermon
            ? 'A new sermon has been published on the ' . SITE_NAME . ' website.'
            : 'A new article has been published on the ' . SITE_NAME . ' website.'));
    if ($is_sermon && !empty($info['preacher'])) {
        $preview = 'Preacher: ' . $info['preacher']
            . (!empty($info['scripture']) ? ' · Scripture: ' . $info['scripture'] : '')
            . '. ' . $preview;
    }

    if ($is_event) {
        $meta = trim(($info['date'] ?? '') . ($info['location'] ? ' - ' . $info['location'] : ''));
        if ($meta !== '') {
            $preview = $meta . ". " . $preview;
        }
    }

    foreach ($rows as $sub) {
        $html = mail_template($subject, $preview, $is_event ? 'View Event' : ($is_sermon ? 'Listen / Read Sermon' : 'Read Article'), $link, $sub['email'], $sub['token']);
        $res = send_mail($sub['email'], $subject, $html);
        if (!empty($res['sent'])) {
            $result['sent']++;
        } else {
            $result['failed']++;
            error_log('notify_subscribers: failed to ' . $sub['email'] . ' - ' . ($res['error'] ?? 'unknown'));
        }
    }

    if ($result['failed'] > 0 && $result['sent'] === 0) {
        $result['error'] = 'Could not send email. Check the SMTP / mail settings (see .env: MAIL_HOST, MAIL_USER, MAIL_PASS).';
    }
    return $result;
}

/** Human-readable suffix for the admin success message. */
function notify_summary(array $r)
{
    if (!empty($r['error'])) {
        return ' (the email to subscribers could not be sent: ' . $r['error'] . ')';
    }
    if ($r['subscribers'] === 0) {
        return ' (no subscribers to notify yet)';
    }
    if ($r['sent'] === $r['subscribers']) {
        return ' (notification sent to ' . $r['sent'] . ' subscriber' . ($r['sent'] === 1 ? '' : 's') . ')';
    }
    return ' (notification sent to ' . $r['sent'] . ' of ' . $r['subscribers'] . ' subscribers)';
}
