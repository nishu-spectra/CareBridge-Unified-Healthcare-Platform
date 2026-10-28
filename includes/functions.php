<?php
declare(strict_types=1);

function app_config(): array
{
    static $config = null;
    return $config ??= require __DIR__ . '/../config/app.php';
}

function url(string $path = ''): string
{
    $base = rtrim((string)app_config()['base_url'], '/');
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '/');
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function verify_csrf(): void
{
    $sent = (string)($_POST['_csrf'] ?? '');
    $known = (string)($_SESSION['_csrf'] ?? '');
    if ($sent === '' || $known === '' || !hash_equals($known, $sent)) {
        http_response_code(419);
        exit('Your form session expired. Please go back, refresh the page, and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    $value = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return is_array($value) ? $value : null;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    refresh_session_user();
    $user = current_user();
    if (!$user) {
        flash('error', 'Please log in to continue.');
        header('Location: ' . url('login.php'));
        exit;
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    $currentRole = (string)($user['role'] ?? '');

    if (!in_array($currentRole, $roles, true)) {
        // Keep authorization strict, but send authenticated users back to the
        // correct role workspace instead of showing a confusing permission page.
        $home = match ($currentRole) {
            'patient' => 'dashboard.php',
            'hospital_admin' => 'hospital/dashboard.php',
            'platform_admin' => 'admin/hospitals.php',
            default => null,
        };
        if ($home !== null) {
            redirect($home);
        }
        http_response_code(403);
        exit('You do not have permission to view this page.');
    }
    return $user;
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function refresh_session_user(): void
{
    if (empty($_SESSION['user']['id'])) {
        return;
    }
    $stmt = db()->prepare('SELECT id, full_name, email, role, status FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user']['id']]);
    $user = $stmt->fetch();
    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['user']);
        return;
    }
    $_SESSION['user'] = $user;
}


function send_app_email(string $to, string $subject, string $html, string $text = ''): bool
{
    $cfg = app_config();
    $from = (string)($cfg['mail_from'] ?? getenv('MAIL_FROM') ?: 'no-reply@carebridge.local');
    $safeSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . (string)($cfg['mail_from_name'] ?? 'CareBridge') . ' <' . $from . '>',
        'X-Mailer: CareBridge PHP'
    ];
    $sent = false;
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    // If PHPMailer is installed with Composer, prefer authenticated SMTP for reliable delivery.
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $host = (string)(getenv('SMTP_HOST') ?: '');
            if ($host !== '') {
                $mail->isSMTP();
                $mail->Host = $host;
                $mail->Port = (int)(getenv('SMTP_PORT') ?: 587);
                $mail->SMTPAuth = (getenv('SMTP_AUTH') ?: 'true') !== 'false';
                $mail->Username = (string)(getenv('SMTP_USERNAME') ?: '');
                $mail->Password = (string)(getenv('SMTP_PASSWORD') ?: '');
                $encryption = strtolower((string)(getenv('SMTP_ENCRYPTION') ?: 'tls'));
                if ($encryption === 'ssl') $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                elseif ($encryption === 'tls') $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->isMail();
            }
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($from, (string)($cfg['mail_from_name'] ?? 'CareBridge'));
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = $text !== '' ? $text : strip_tags($html);
            $mail->send();
            $sent = true;
        } catch (Throwable $mailError) {
            error_log('CareBridge SMTP delivery failed: ' . $mailError->getMessage());
        }
    } else {
        $sent = @mail($to, $safeSubject, $html, implode("\r\n", $headers));
    }
    if (!$sent) {
        // Never log medical data or message bodies. This helps local development diagnose mail configuration.
        error_log('CareBridge email was not delivered to ' . $to . ' (subject: ' . $subject . '). Configure PHP mail or SMTP before production.');
    }
    return $sent;
}

function send_carebridge_email(string $to, string $subject, string $heading, string $message, array $details = []): bool
{
    $items = '';
    foreach ($details as $label => $value) {
        $items .= '<tr><td style="padding:10px 12px;color:#7b6258;border-bottom:1px solid #f0e4d8">' . e((string)$label) . '</td><td style="padding:10px 12px;font-weight:700;color:#6B352A;border-bottom:1px solid #f0e4d8">' . e((string)$value) . '</td></tr>';
    }
    $html = '<div style="margin:0;background:#fbf8f1;padding:28px;font-family:Arial,sans-serif;color:#352722"><div style="max-width:600px;margin:auto;background:#fffdf8;border:1px solid #f0e4d8;border-radius:18px;overflow:hidden"><div style="background:#6B352A;color:#FFF1A6;padding:22px 26px;font-size:22px;font-weight:bold">✚ CareBridge</div><div style="padding:28px"><h1 style="font-size:23px;color:#6B352A;margin-top:0">' . e($heading) . '</h1><p style="line-height:1.7">' . nl2br(e($message)) . '</p>' . ($items ? '<table style="width:100%;border-collapse:collapse;margin:20px 0">' . $items . '</table>' : '') . '<p style="font-size:12px;color:#82736C;line-height:1.7">Please keep your account details private. If you did not expect this message, contact the CareBridge support team.</p><p style="color:#6B352A;font-weight:bold">CareBridge · Connected care, with trust.</p></div></div></div>';
    $textDetails = $message;
    foreach ($details as $label => $value) { $textDetails .= "\n\n" . (string)$label . ': ' . (string)$value; }
    return send_app_email($to, $subject, $html, $textDetails);
}

function status_class(string $status): string
{
    return match ($status) {
        'approved', 'active' => 'status status-success',
        'pending' => 'status status-pending',
        'rejected', 'suspended' => 'status status-danger',
        default => 'status',
    };
}


function password_policy_errors(string $password): array
{
    $errors = [];
    if (strlen($password) < 10) $errors[] = 'Password must be at least 10 characters long.';
    if (!preg_match('/[A-Z]/', $password)) $errors[] = 'Add at least one uppercase letter (A–Z).';
    if (!preg_match('/[a-z]/', $password)) $errors[] = 'Add at least one lowercase letter (a–z).';
    if (!preg_match('/[0-9]/', $password)) $errors[] = 'Add at least one digit (0–9).';
    if (!preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = 'Add at least one symbol (for example !, @, #, or $).';
    return $errors;
}

function send_verification_code(string $method, string $destination, string $code, string $purpose = 'CareBridge verification'): bool
{
    $message = "Your CareBridge code is {$code}. It expires in 10 minutes. Do not share this code.";
    // CareBridge account and password-reset codes are delivered by email only.
    return send_carebridge_email($destination, $purpose . ' code', 'Verify your account', 'Use the code below to continue. It expires in 10 minutes. Do not share it with anyone.', ['Verification code'=>$code, 'Expiry'=>'10 minutes']);
}


