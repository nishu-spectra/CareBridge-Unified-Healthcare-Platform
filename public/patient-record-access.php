<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$errors = [];
$patientCode = trim((string)($_GET['patient_code'] ?? $_POST['patient_code'] ?? ''));
$user = current_user();
if ($user) {
    refresh_session_user();
    $user = current_user();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $patientCode = strtoupper($patientCode);
    if (!preg_match('/^CB-[A-F0-9]{8}$/', $patientCode)) {
        $errors[] = 'Enter a valid Patient ID (for example, CB-1A2B3C4D).';
    } elseif (!$user) {
        flash('error', 'Please sign in with an approved healthcare provider account to view medical history.');
        redirect('login-provider.php?next=' . rawurlencode('hospital/patient-history.php?patient_code=' . $patientCode));
    } elseif (($user['role'] ?? '') !== 'hospital_admin') {
        http_response_code(403);
        $errors[] = 'Medical history search is available to signed-in healthcare providers only.';
    } else {
        redirect('hospital/patient-history.php?patient_code=' . rawurlencode($patientCode));
    }
}

$pageTitle = 'View medical history';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-intro">
  <span class="eyebrow">CAREBRIDGE MEDICAL HISTORY</span>
  <h1>View a patient's <span>medical history.</span></h1>
  <p>Enter a Patient ID to view the patient's read-only medical history across CareBridge providers. Approved healthcare providers must sign in. Each successful lookup is logged and the patient is automatically notified; no separate approval request is required.</p>
</section>
<div class="form-panel form-panel-wide">
  <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" class="stack-form">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <label>Patient ID<input name="patient_code" required maxlength="32" pattern="CB-[A-Fa-f0-9]{8}" placeholder="CB-1A2B3C4D" value="<?= e($patientCode) ?>"></label>
    <p class="form-hint">Only signed-in, approved healthcare providers can view records. The patient is notified automatically; no per-view approval is requested.</p>
    <button class="button" type="submit">View Medical History →</button>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
