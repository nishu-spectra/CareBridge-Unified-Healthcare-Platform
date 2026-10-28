<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
if (!in_array($user['role'], ['patient', 'platform_admin', 'hospital_admin'], true)) {
    http_response_code(403); exit('You do not have permission to apply as a provider.');
}
$errors = [];
$name = $type = $license = $address = $phone = '';
$existingStmt = db()->prepare('SELECT id, name, approval_status, application_note FROM organizations WHERE owner_user_id = ? ORDER BY id DESC LIMIT 1');
$existingStmt->execute([$user['id']]);
$existing = $existingStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $type = (string)($_POST['organization_type'] ?? 'hospital');
    $license = trim((string)($_POST['license_number'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    if (mb_strlen($name) < 3 || mb_strlen($name) > 180) $errors[] = 'Organization name must be between 3 and 180 characters.';
    if (!in_array($type, ['hospital', 'clinic', 'laboratory', 'independent_practice'], true)) $errors[] = 'Choose a valid provider type.';
    if (mb_strlen($address) < 5) $errors[] = 'Enter the organization address.';
    if ($license === '') $errors[] = 'Enter a license or registration reference. Verification documents upload will be added in a later phase.';
    if (!$errors) {
        if ($existing && in_array($existing['approval_status'], ['pending', 'approved'], true)) {
            $errors[] = 'You already have an application that is pending or approved.';
        } else {
            if ($existing) {
                $stmt = db()->prepare("UPDATE organizations SET name=?, organization_type=?, license_number=?, address=?, phone=?, approval_status='pending', application_note=NULL, reviewed_by=NULL, reviewed_at=NULL WHERE id=? AND owner_user_id=?");
                $stmt->execute([$name, $type, $license, $address, $phone ?: null, $existing['id'], $user['id']]);
            } else {
                $stmt = db()->prepare("INSERT INTO organizations (owner_user_id, name, organization_type, license_number, address, phone, approval_status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$user['id'], $name, $type, $license, $address, $phone ?: null]);
            }
            flash('success', 'Your provider application has been submitted for review. Operational access remains disabled until approval.');
            redirect('dashboard.php');
        }
    }
}
$pageTitle = 'Register a healthcare provider';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-intro"><span class="eyebrow">FOR HEALTHCARE ORGANIZATIONS</span><h1>Bring your organization to <span>CareBridge.</span></h1><p>Submit your organization details for review. Provider workspaces are activated only after the application is approved.</p></section>
<?php if ($existing): ?>
<div class="notice-card"><span class="notice-icon">i</span><div><strong>Latest application: <?= e($existing['name']) ?></strong><p>Status: <span class="<?= e(status_class($existing['approval_status'])) ?>"><?= e(ucfirst($existing['approval_status'])) ?></span><?= $existing['application_note'] ? ' · ' . e($existing['application_note']) : '' ?></p></div></div>
<?php endif; ?>
<div class="form-panel form-panel-wide">
  <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" class="stack-form">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="form-grid">
      <label>Organization name<input name="name" required maxlength="180" value="<?= e($name) ?>" placeholder="e.g. Sunrise Community Hospital"></label>
      <label>Provider type<select name="organization_type" required><option value="hospital" <?= $type==='hospital'?'selected':'' ?>>Hospital</option><option value="clinic" <?= $type==='clinic'?'selected':'' ?>>Clinic</option><option value="laboratory" <?= $type==='laboratory'?'selected':'' ?>>Laboratory</option><option value="independent_practice" <?= $type==='independent_practice'?'selected':'' ?>>Independent practice</option></select></label>
      <label>License / registration reference<input name="license_number" required maxlength="120" value="<?= e($license) ?>" placeholder="Official registration reference"></label>
      <label>Contact phone (optional)<input name="phone" type="tel" maxlength="40" value="<?= e($phone) ?>" placeholder="+country code ..."></label>
      <label class="form-span">Organization address<textarea name="address" rows="3" required maxlength="500" placeholder="Street, city, region, country"><?= e($address) ?></textarea></label>
    </div>
    <div class="form-hint">This starter records a license reference only. Secure document upload, license validation, and jurisdiction-specific verification must be implemented before production use.</div>
    <button class="button" type="submit">Submit for approval <span>→</span></button>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
