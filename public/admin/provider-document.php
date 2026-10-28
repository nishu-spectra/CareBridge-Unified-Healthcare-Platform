<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('platform_admin');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); exit('Invalid organization.'); }
$stmt = db()->prepare('SELECT stored_filename, original_filename, mime_type FROM organization_verification_documents WHERE organization_id=? LIMIT 1');
$stmt->execute([$id]);
$doc = $stmt->fetch();
$allowed = ['image/jpeg', 'image/png', 'application/pdf'];
$base = dirname(__DIR__, 2) . '/storage/private/provider-docs/';
$path = $doc ? $base . basename((string)$doc['stored_filename']) : '';
if (!$doc || !in_array($doc['mime_type'], $allowed, true) || !is_file($path)) { http_response_code(404); exit('Verification document not found.'); }
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');
header('Content-Type: ' . $doc['mime_type']);
header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', basename((string)$doc['original_filename'])) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
