<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$next = (string)($_GET['next'] ?? $_POST['next'] ?? '');
$allowedNext = '';
if (preg_match('#^hospital/patient-history\.php(?:\?patient_code=CB-[A-Fa-f0-9]{8})?$#', $next)) {
    $allowedNext = $next;
}
if (current_user()) redirect($allowedNext !== '' ? $allowedNext : 'dashboard.php');
$portal = 'provider';
$errors = [];
$identity = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $identity = trim((string)($_POST['identity'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($portal === 'patient') {
        $stmt = db()->prepare("SELECT u.id,u.full_name,u.email,u.password_hash,u.role,u.status,u.email_verified_at,u.phone_verified_at FROM users u LEFT JOIN patient_profiles p ON p.user_id=u.id WHERE (LOWER(u.email)=LOWER(?) OR UPPER(p.patient_code)=UPPER(?)) AND u.role='patient' LIMIT 1");
        $stmt->execute([$identity, $identity]);
    } else {
        $stmt = db()->prepare("SELECT id,full_name,email,password_hash,role,status,email_verified_at,phone_verified_at FROM users WHERE LOWER(email)=LOWER(?) AND role='hospital_admin' LIMIT 1");
        $stmt->execute([$identity]);
    }
    $account = $stmt->fetch();
    if (!$account || !password_verify($password, $account['password_hash'])) $errors[] = 'The login details are incorrect for this portal.';
    elseif (empty($account['email_verified_at'])) $errors[] = 'Please verify your email before logging in.';
    elseif ($account['status'] !== 'active') $errors[] = 'Your account is not active. Hospital accounts can sign in after government/platform approval.';
    else { session_regenerate_id(true); unset($account['password_hash']); $_SESSION['user']=$account; redirect($allowedNext !== '' ? $allowedNext : 'dashboard.php'); }
}
$isPatient = $portal === 'patient';
$pageTitle = $isPatient ? 'Patient login' : 'Healthcare provider login';
require __DIR__ . '/../includes/header.php';
?>
<section class="auth-layout auth-layout-login"><div class="auth-aside"><span class="eyebrow"><?= $isPatient ? 'YOUR HEALTH, YOUR SPACE' : 'HEALTHCARE ORGANIZATION ACCESS' ?></span><h1><?= $isPatient ? 'Your health journey, connected.' : 'Your care team, connected.' ?></h1><p><?= $isPatient ? 'Book appointments, keep your Patient ID handy, and follow your care history in one place.' : 'Access your approved organization workspace to coordinate departments, appointments, patients, and queue tickets.' ?></p><div class="aside-note"><span><?= $isPatient ? '♡' : '✚' ?></span><div><strong><?= $isPatient ? 'Patient & family portal' : 'Verified provider portal' ?></strong><small><?= $isPatient ? 'Sign in using your email or Patient ID.' : 'Only approved healthcare organizations can access this portal.' ?></small></div></div></div>
<div class="form-panel"><div class="form-heading"><span class="eyebrow">CAREBRIDGE SECURE ACCESS</span><h2><?= $isPatient ? 'Patient / User login' : 'Hospital / Provider login' ?></h2><p><?= $isPatient ? 'New here? <a href="' . e(url('register.php')) . '">Create a patient account</a>' : 'Not registered yet? <a href="' . e(url('hospital-register.php')) . '">Apply as a healthcare provider</a>' ?></p></div>
<?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="stack-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="next" value="<?= e($allowedNext) ?>"><label><?= $isPatient ? 'Email address or Patient ID' : 'Registered organization email' ?><input name="identity" autocomplete="username" required value="<?= e($identity) ?>" placeholder="<?= $isPatient ? 'you@example.com or CB-XXXXXXXX' : 'admin@yourhospital.com' ?>"></label><label>Password<span class="password-wrap"><input type="password" name="password" autocomplete="current-password" required placeholder="Enter your password"><button class="password-toggle" type="button" data-password-toggle aria-label="Show password">Show</button></span></label><button class="button button-full" type="submit">Sign in to <?= $isPatient ? 'patient portal' : 'provider workspace' ?> <span>→</span></button></form><p class="form-hint"><a href="<?= e(url('forgot-password.php')) ?>">Forgot your password?</a></p>
<div class="portal-switch"><span><?= $isPatient ? 'Managing a hospital?' : 'Looking for patient access?' ?></span> <a href="<?= e(url($isPatient ? 'login-provider.php' : 'login-patient.php')) ?>"><?= $isPatient ? 'Hospital login' : 'Patient login' ?></a></div><p class="form-hint">For safety, use a unique password. Email delivery and account recovery must be configured before production launch.</p></div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
