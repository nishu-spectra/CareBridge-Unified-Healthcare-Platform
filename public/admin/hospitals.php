<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$admin = require_role('platform_admin');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $orgId = filter_input(INPUT_POST, 'organization_id', FILTER_VALIDATE_INT);
    $decision = (string)($_POST['decision'] ?? '');
    $note = trim((string)($_POST['note'] ?? ''));
    if (!$orgId || !in_array($decision, ['approved', 'rejected', 'suspended'], true)) {
        $errors[] = 'Invalid application action.';
    } else {
        $stmt = db()->prepare('SELECT o.id, o.owner_user_id, o.name, u.email AS owner_email, u.full_name AS owner_name, (SELECT COUNT(*) FROM organization_verification_documents vd WHERE vd.organization_id=o.id) AS has_verification_document FROM organizations o JOIN users u ON u.id=o.owner_user_id WHERE o.id = ?');
        $stmt->execute([$orgId]);
        $org = $stmt->fetch();
        if (!$org) {
            $errors[] = 'Organization was not found.';
        } elseif ($decision === 'approved' && (int)$org['has_verification_document'] < 1) {
            $errors[] = 'Cannot approve this provider: the required license/registration document is missing.';
        } else {
            db()->beginTransaction();
            try {
                $stmt = db()->prepare('UPDATE organizations SET approval_status=?, application_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?');
                $stmt->execute([$decision, $note ?: null, $admin['id'], $orgId]);
                if ($decision === 'approved') {
                    db()->prepare("UPDATE users SET role='hospital_admin', status='active' WHERE id=?")->execute([$org['owner_user_id']]);
                }
                if ($decision === 'suspended') {
                    db()->prepare("UPDATE users SET status='suspended' WHERE id=?")->execute([$org['owner_user_id']]);
                }
                db()->prepare('INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$admin['id'], 'provider_' . $decision, 'organization', $orgId, $note ?: null]);
                db()->commit();
                if (in_array($decision, ['approved', 'rejected', 'suspended'], true)) {
                    $mailHeading = $decision === 'approved' ? 'Your provider application is approved' : ($decision === 'rejected' ? 'An update about your provider application' : 'Your provider workspace has been suspended');
                    $mailMessage = $decision === 'approved' ? 'Hello ' . $org['owner_name'] . ', your application for ' . $org['name'] . ' has been approved. You can now sign in using the Healthcare Provider login page and manage your organization.' : 'Hello ' . $org['owner_name'] . ', the status of your application for ' . $org['name'] . ' is now ' . $decision . '. ' . ($note ? 'Administrator note: ' . $note : 'Please contact the platform administrator if you need clarification.');
                    send_carebridge_email($org['owner_email'], 'CareBridge provider application: ' . ucfirst($decision), $mailHeading, $mailMessage, ['Organization' => $org['name'], 'Decision' => ucfirst($decision), 'Provider login' => 'Healthcare Provider portal']);
                }
                flash('success', $org['name'] . ' marked ' . $decision . '. Email delivery was attempted.');
                redirect('admin/hospitals.php');
            } catch (Throwable $e) {
                db()->rollBack();
                error_log((string)$e);
                $errors[] = 'Unable to update this application. Check server logs.';
            }
        }
    }
}
$filter = (string)($_GET['status'] ?? 'pending');
$allowed = ['pending', 'approved', 'rejected', 'suspended', 'all'];
if (!in_array($filter, $allowed, true)) $filter = 'pending';
$sql = "SELECT o.*, u.full_name AS owner_name, u.email AS owner_email, vd.id AS verification_document_id, vd.original_filename AS verification_document_name FROM organizations o JOIN users u ON u.id=o.owner_user_id LEFT JOIN organization_verification_documents vd ON vd.organization_id=o.id";
$params = [];
if ($filter !== 'all') { $sql .= ' WHERE o.approval_status=?'; $params[] = $filter; }
$sql .= " ORDER BY CASE o.approval_status WHEN 'pending' THEN 0 ELSE 1 END, o.created_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$organizations = $stmt->fetchAll();
$pageTitle = 'Provider applications';
require __DIR__ . '/../../includes/header.php';
?>
<section class="dashboard-welcome"><div><span class="eyebrow">PLATFORM ADMINISTRATION</span><h1>Provider applications</h1><p>Review organization applications and control provider workspace access.</p></div><span class="admin-stamp">ADMIN</span></section>
<?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="filter-row">
<?php foreach (['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','suspended'=>'Suspended','all'=>'All'] as $key=>$label): ?><a class="filter-pill <?= $filter===$key?'filter-active':'' ?>" href="<?= e(url('admin/hospitals.php?status='.$key)) ?>"><?= e($label) ?></a><?php endforeach; ?>
</div>
<div class="table-card">
  <?php if (!$organizations): ?><div class="empty-state"><span>✧</span><h3>No applications here</h3><p>Applications matching this filter will appear here.</p></div>
  <?php else: ?>
  <div class="table-scroll"><table><thead><tr><th>Organization</th><th>Owner</th><th>Type / license</th><th>Status</th><th>Review action</th></tr></thead><tbody>
  <?php foreach ($organizations as $org): ?>
  <tr>
    <td><strong><?= e($org['name']) ?></strong><small><?= e($org['address']) ?></small><small>Submitted <?= e(date('M j, Y', strtotime($org['created_at']))) ?></small></td>
    <td><?= e($org['owner_name']) ?><small><?= e($org['owner_email']) ?></small></td>
    <td><?= e(ucwords(str_replace('_', ' ', $org['organization_type']))) ?><small><?= e($org['license_number']) ?></small><?php if (!empty($org['verification_document_id'])): ?><small><a class="text-link" href="<?= e(url('admin/provider-document.php?id='.(int)$org['id'])) ?>">View verification document</a></small><?php else: ?><small>Verification document missing</small><?php endif; ?></td>
    <td><span class="<?= e(status_class($org['approval_status'])) ?>"><?= e(ucfirst($org['approval_status'])) ?></span><?php if ($org['application_note']): ?><small><?= e($org['application_note']) ?></small><?php endif; ?></td>
    <td>
      <?php if ($org['approval_status'] !== 'approved'): ?>
      <form method="post" class="review-form">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="organization_id" value="<?= (int)$org['id'] ?>">
        <input name="note" maxlength="500" placeholder="Decision note (optional)" aria-label="Decision note">
        <button class="button button-small" name="decision" value="approved">Approve</button>
        <button class="button button-small button-danger" name="decision" value="rejected">Reject</button>
      </form>
      <?php else: ?>
      <form method="post" class="review-form">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="organization_id" value="<?= (int)$org['id'] ?>">
        <input name="note" maxlength="500" placeholder="Reason for suspension" aria-label="Suspension note">
        <button class="button button-small button-danger" name="decision" value="suspended">Suspend</button>
      </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
