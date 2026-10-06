<?php
// =====================================================================
// mailer.php - sends an email with one attachment through Gmail (SMTP).
// No extra library is needed: PHP talks to smtp.gmail.com directly.
//
// Gmail needs an "App Password" (not your normal Gmail password):
//   Google Account -> Security -> 2-Step Verification (turn on)
//   -> App passwords -> create one -> copy the 16 letters.
// =====================================================================

// Send an email. Throws RuntimeException with a simple message on error.
// $mail = ['gmail' => 'you@gmail.com', 'app_password' => '....']
// $options = ['host' => 'smtp.gmail.com', 'port' => 587]  (STARTTLS)
function smtp_send(array $mail, string $to, string $subject, string $text, ?string $filePath = null,
                   ?string $fileName = null, array $options = []): void
{
    if (!extension_loaded('openssl')) {
        throw new RuntimeException('The PHP "openssl" extension is not enabled. Enable it in php.ini and restart Apache.');
    }
    $host = $options['host'] ?? 'smtp.gmail.com';
    $port = (int)($options['port'] ?? 587);
    $user = (string)$mail['gmail'];
    $pass = str_replace(' ', '', (string)$mail['app_password']); // Google shows it as "abcd efgh ijkl mnop"

    // Always check the server's security certificate (protects the password)
    $ssl = ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false, 'peer_name' => $host];
    if (!ini_get('openssl.cafile') && is_file('C:/xampp/apache/bin/curl-ca-bundle.crt')) {
        $ssl['cafile'] = 'C:/xampp/apache/bin/curl-ca-bundle.crt';
    }
    $context = stream_context_create(['ssl' => $ssl]);

    // 1. Connect
    $fp = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        throw new RuntimeException('Could not connect to ' . $host . '. Check the internet connection. (' . $errstr . ')');
    }
    stream_set_timeout($fp, 60);

    try {
        smtp_expect($fp, 220);
        smtp_command($fp, 'EHLO nadiif-laundry', 250);

        // 2. Switch to an encrypted connection
        smtp_command($fp, 'STARTTLS', 220);
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
            throw new RuntimeException('Could not open a secure connection to Gmail (certificate problem).');
        }
        smtp_command($fp, 'EHLO nadiif-laundry', 250);

        // 3. Log in with the App Password
        smtp_command($fp, 'AUTH LOGIN', 334);
        smtp_command($fp, base64_encode($user), 334);
        try {
            smtp_command($fp, base64_encode($pass), 235);
        } catch (RuntimeException $e) {
            throw new RuntimeException('Gmail did not accept the login. Use a Gmail App Password (16 letters), not your normal password, '
                . 'and make sure 2-Step Verification is on.');
        }

        // 4. Send the message
        smtp_command($fp, 'MAIL FROM:<' . $user . '>', 250);
        smtp_command($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_command($fp, 'DATA', 354);
        $message = build_mime_message($user, $to, $subject, $text, $filePath, $fileName);
        // Lines starting with "." must be doubled (SMTP rule)
        $message = preg_replace('/^\./m', '..', $message);
        fwrite($fp, $message . "\r\n.\r\n");
        smtp_expect($fp, 250);
        smtp_command($fp, 'QUIT', 221);
    } finally {
        fclose($fp);
    }
}

// Send one SMTP command and check the answer code
function smtp_command($fp, string $command, $expected): string
{
    fwrite($fp, $command . "\r\n");
    return smtp_expect($fp, $expected);
}

// Read the server's answer and check the code (e.g. 250 = OK)
function smtp_expect($fp, $expected): string
{
    $response = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') {   // last line of the answer
            break;
        }
    }
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, (array)$expected, true)) {
        throw new RuntimeException('Gmail answered: ' . trim($response === '' ? 'no answer (timeout)' : $response));
    }
    return $response;
}

// Build the email text with the attachment (MIME format)
function build_mime_message(string $from, string $to, string $subject, string $text, ?string $filePath, ?string $fileName): string
{
    $boundary = 'nadiif_' . bin2hex(random_bytes(12));
    $headers = [
        'From: ' . mime_header(setting('business_name', 'NADIIF LAUNDRY')) . ' <' . $from . '>',
        'To: <' . $to . '>',
        'Subject: ' . mime_header($subject),
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@nadiif-laundry>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
    ];
    $body = '--' . $boundary . "\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($text), 76, "\r\n");
    if ($filePath !== null) {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName ?? basename($filePath));
        $body .= '--' . $boundary . "\r\n"
               . 'Content-Type: application/gzip; name="' . $name . "\"\r\n"
               . "Content-Transfer-Encoding: base64\r\n"
               . 'Content-Disposition: attachment; filename="' . $name . "\"\r\n\r\n"
               . chunk_split(base64_encode((string)file_get_contents($filePath)), 76, "\r\n");
    }
    $body .= '--' . $boundary . "--\r\n";
    return implode("\r\n", $headers) . "\r\n\r\n" . $body;
}

// Encode a header so names with special characters are safe
function mime_header(string $value): string
{
    $value = str_replace(["\r", "\n"], ' ', $value);
    return preg_match('/^[\x20-\x7E]*$/', $value) ? $value : '=?UTF-8?B?' . base64_encode($value) . '?=';
}
