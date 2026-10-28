<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = current_user();
if (!$user) {
    $next = 'hospital/patient-history.php';
    redirect('login-provider.php?next=' . rawurlencode($next));
}
refresh_session_user();
$user = current_user();
if (!$user || ($user['role'] ?? '') !== 'hospital_admin') {
    http_response_code(403);
    exit('Only healthcare providers can view patient medical history.');
}
$q = db()->prepare("SELECT * FROM organizations WHERE owner_user_id=? AND approval_status='approved' ORDER BY id DESC LIMIT 1");
$q->execute([$user['id']]);
$org = $q->fetch();
if (!$org) {
    flash('error', 'Only approved providers can access medical records.');
    redirect('hospital/dashboard.php');
}
$code = strtoupper(trim((string)($_GET['patient_code'] ?? $_POST['patient_code'] ?? '')));
$errors = [];
$patient = null;
$history = [];
$hasConsent = true; // Separate cross-provider consent is not required in this workflow; provider authentication/approval remains required.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}
if ($code !== '') {
    if (!preg_match('/^CB-[A-F0-9]{8}$/', $code)) {
        $errors[] = 'Enter a valid Patient ID (for example, CB-1A2B3C4D).';
    } else {
        $q = db()->prepare("SELECT u.id,u.full_name,p.patient_code FROM users u JOIN patient_profiles p ON p.user_id=u.id WHERE UPPER(p.patient_code)=UPPER(?) AND u.role='patient' AND u.status='active' LIMIT 1");
        $q->execute([$code]);
        $patient = $q->fetch();
        if (!$patient) {
            $errors[] = 'No active patient account matches that Patient ID.';
        } else {
            // Show cross-provider records to signed-in, approved providers without a separate consent grant.
            $q = db()->prepare("SELECT e.id,e.occurred_at,e.encounter_type,e.summary,e.clinician_name,e.clinician_license,o.name AS provider_name FROM encounters e JOIN organizations o ON o.id=e.organization_id WHERE e.patient_user_id=? ORDER BY e.occurred_at DESC");
            $q->execute([$patient['id']]);
            $history = $q->fetchAll();

            // Log and notify on each successful patient-history lookup, including when no encounters exist.
            $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
            $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown browser/device'), 0, 350);
            $viewedAt = date('Y-m-d H:i:s');
            $detail = 'Provider organization: '.$org['name'].'; viewed_at: '.$viewedAt.'; IP: '.($ip !== '' ? $ip : 'Unavailable').'; device/browser: '.$ua;
            try {
                db()->prepare("INSERT INTO audit_logs(actor_user_id,action,entity_type,entity_id,details,ip_address) VALUES(?, 'medical_history_viewed', 'patient', ?, ?, ?)")->execute([$user['id'], $patient['id'], $detail, $ip !== '' ? $ip : null]);
            } catch (Throwable $auditError) {
                error_log('CareBridge medical history audit log failed: '.$auditError->getMessage());
            }
            try {
                db()->prepare('INSERT INTO notifications(user_id,title,message) VALUES(?,?,?)')->execute([$patient['id'], 'Your medical history was viewed', 'An authorized provider at '.$org['name'].' viewed your CareBridge medical history at '.$viewedAt.'. IP address: '.($ip !== '' ? $ip : 'unavailable').'. Browser/device: '.$ua.'.']);
                $mail = db()->prepare('SELECT email FROM users WHERE id=? LIMIT 1');
                $mail->execute([$patient['id']]);
                $patientEmail = (string)$mail->fetchColumn();
                if ($patientEmail !== '') {
                    send_carebridge_email($patientEmail, 'CareBridge: your medical history was viewed', 'Your medical history was viewed', 'An authorized healthcare provider accessed your CareBridge medical history. This is a notification only; no approval is required for this access event.', ['Provider organization'=>$org['name'], 'Access time'=>$viewedAt, 'IP address'=>$ip !== '' ? $ip : 'Unavailable', 'Browser / device'=>$ua, 'Location'=>'Not available (no IP-geolocation service configured)']);
                }
            } catch (Throwable $notifyError) {
                error_log('CareBridge medical history notification failed: '.$notifyError->getMessage());
            }
        }
    }
}
$pageTitle = 'Patient medical records';
require __DIR__ . '/../../includes/header.php';
?>
<section class="page-intro">
  <span class="eyebrow">AUTHORIZED CLINICAL RECORDS</span>
  <h1>Patient <span>medical history.</span></h1>
  <p>Search by Patient ID to view medical records only. This page does not create consultations or prescriptions.</p>
</section>
<div class="form-panel form-panel-wide">
  <form method="post" class="stack-form">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <label>Patient ID<input name="patient_code" required maxlength="32" pattern="CB-[A-Fa-f0-9]{8}" placeholder="CB-1A2B3C4D" value="<?= e($code) ?>"></label>
    <button class="button" type="submit">Find patient →</button>
  </form>
  <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <?php if ($patient): ?>
    <hr>
    <h2><?= e($patient['full_name']) ?> <small>(<?= e($patient['patient_code']) ?>)</small></h2>
    <?php if (!$history): ?>
      <div class="alert alert-success">Patient found. The lookup has been logged and the patient has been notified.</div>
      <p class="source-note">No prior clinical encounters are recorded for this patient.</p>
    <?php else: ?>
      <div class="alert alert-success">Read-only medical history displayed. This access has been logged and the patient has been notified.</div>
      <h3>Medical history</h3>
      <?php foreach ($history as $enc): ?>
        <article class="medical-record-card">
          <h3><?= e($enc['summary'] ?: 'Clinical encounter') ?></h3>
          <p><strong><?= e($enc['provider_name']) ?></strong> · <?= e($enc['clinician_name']) ?> (License: <?= e($enc['clinician_license']) ?>) · <?= e(date('M j, Y g:i A', strtotime($enc['occurred_at']))) ?></p>
          <?php $rr = db()->prepare('SELECT title,body FROM medical_records WHERE encounter_id=? ORDER BY created_at'); $rr->execute([$enc['id']]); $records = $rr->fetchAll(); ?>
          <?php foreach ($records as $rec): ?><p><strong><?= e($rec['title']) ?>:</strong> <?= nl2br(e($rec['body'])) ?></p><?php endforeach; ?>
          <h4>Prescribed medicines</h4>
          <?php $pp = db()->prepare('SELECT medicine_name,dosage,instructions,start_date,end_date,status FROM prescriptions WHERE encounter_id=? ORDER BY created_at'); $pp->execute([$enc['id']]); $meds = $pp->fetchAll(); ?>
          <?php if (!$meds): ?><p>No medicines recorded for this visit.</p><?php else: ?>
            <?php foreach ($meds as $med): ?><div class="medicine-row"><strong><?= e($med['medicine_name']) ?></strong> — <?= e($med['dosage']) ?><p><?= e($med['instructions'] ?: 'No additional instructions recorded') ?></p><small>Status: <?= e($med['status']) ?> · Dates: <?= e($med['start_date'] ?: 'Not specified') ?> to <?= e($med['end_date'] ?: 'Not specified') ?></small></div><?php endforeach; ?>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
