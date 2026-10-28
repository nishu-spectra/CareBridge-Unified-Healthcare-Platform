<?php
require_once __DIR__.'/../../includes/bootstrap.php';
$user=require_role('hospital_admin');
$q=db()->prepare("SELECT * FROM organizations WHERE owner_user_id=? AND approval_status='approved' ORDER BY id DESC LIMIT 1");
$q->execute([$user['id']]); $org=$q->fetch();
if(!$org){flash('error','Only approved providers can access clinical records.');redirect('dashboard.php');}
$oid=(int)$org['id']; $code=trim((string)($_REQUEST['patient_code']??'')); $errors=[]; $patient=null; $visits=[]; $history=[]; $hasConsent=true; // Cross-provider history is available to authenticated, approved providers without a separate consent grant.
if($code!==''){
  $q=db()->prepare("SELECT u.id,u.full_name,p.patient_code FROM users u JOIN patient_profiles p ON p.user_id=u.id WHERE UPPER(p.patient_code)=UPPER(?) AND u.role='patient' AND u.status='active' LIMIT 1");
  $q->execute([$code]); $patient=$q->fetch();
  if(!$patient) $errors[]='No active patient account matches that Patient ID.';
  else {
    $hasConsent=true;
    $q=db()->prepare("SELECT a.id,a.starts_at,a.reason,a.status,d.name AS department_name FROM appointments a LEFT JOIN departments d ON d.id=a.department_id WHERE a.organization_id=? AND a.patient_user_id=? ORDER BY a.starts_at DESC");
    $q->execute([$oid,$patient['id']]); $visits=$q->fetchAll();
    if($hasConsent){
      $q=db()->prepare("SELECT e.id,e.occurred_at,e.encounter_type,e.summary,e.clinician_name,e.clinician_license,o.name AS provider_name FROM encounters e JOIN organizations o ON o.id=e.organization_id WHERE e.patient_user_id=? ORDER BY e.occurred_at DESC");
      $q->execute([$patient['id']]); $history=$q->fetchAll();
      // Notify the patient after an authorized history lookup actually loads.
      // Store only access metadata in the audit log, not medical details.
      $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
      $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown browser/device'), 0, 350);
      $viewedAt = date('Y-m-d H:i:s');
      $detail = 'Provider organization: '.$org['name'].'; viewed_at: '.$viewedAt.'; IP: '.($ip !== '' ? $ip : 'Unavailable').'; device/browser: '.$ua;
      try {
        db()->prepare("INSERT INTO audit_logs(actor_user_id,action,entity_type,entity_id,details,ip_address) VALUES(?, 'medical_history_viewed', 'patient', ?, ?, ?)")->execute([$user['id'], $patient['id'], $detail, $ip !== '' ? $ip : null]);
        db()->prepare('INSERT INTO notifications(user_id,title,message) VALUES(?,?,?)')->execute([$patient['id'], 'Your medical history was viewed', 'An authorized provider at '.$org['name'].' viewed your CareBridge medical history at '.$viewedAt.'. IP address: '.($ip !== '' ? $ip : 'unavailable').'. Browser/device: '.$ua.'.']);
        $mail = db()->prepare('SELECT email FROM users WHERE id=? LIMIT 1');
        $mail->execute([$patient['id']]);
        $patientEmail = (string)$mail->fetchColumn();
        if ($patientEmail !== '') {
          send_carebridge_email($patientEmail, 'CareBridge: your medical history was viewed', 'Your medical history was viewed', 'An authorized healthcare provider accessed your CareBridge medical history. This is a notification only; no approval is required for this access event.', ['Provider organization'=>$org['name'], 'Access time'=>$viewedAt, 'IP address'=>$ip !== '' ? $ip : 'Unavailable', 'Browser / device'=>$ua, 'Location'=>'Not available (no IP-geolocation service configured)']);
        }
      } catch (Throwable $notifyError) {
        error_log('CareBridge medical history access notification failed: '.$notifyError->getMessage());
      }
    }
  }
}
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['save_record'])){
  verify_csrf(); $code=trim((string)($_POST['patient_code']??'')); $appointmentId=(int)($_POST['appointment_id']??0); $clinicianName=trim((string)($_POST['clinician_name']??'')); $clinicianLicense=trim((string)($_POST['clinician_license']??'')); $diagnosis=trim((string)($_POST['diagnosis']??'')); $notes=trim((string)($_POST['notes']??'')); $medicineLines=trim((string)($_POST['medicines']??'')); $type=(string)($_POST['encounter_type']??'outpatient');
  if(!in_array($type,['outpatient','emergency','inpatient','telehealth','other'],true))$type='outpatient';
  $q=db()->prepare("SELECT u.id,u.full_name,p.patient_code FROM users u JOIN patient_profiles p ON p.user_id=u.id JOIN appointments a ON a.patient_user_id=u.id WHERE UPPER(p.patient_code)=UPPER(?) AND a.organization_id=? AND a.id=? LIMIT 1");$q->execute([$code,$oid,$appointmentId]);$patient=$q->fetch();
  if(!$patient)$errors[]='Choose an appointment belonging to this patient and your organization.';
  if(mb_strlen($clinicianName)<2)$errors[]='Enter the treating clinician name.';
  if($clinicianLicense==='')$errors[]='Enter the treating clinician registration / license number.';
  if($diagnosis==='')$errors[]='Diagnosis / assessment is required.'; if($notes==='')$errors[]='Consultation notes are required.';
  $meds=[]; foreach(preg_split('/\r\n|\r|\n/',$medicineLines) as $line){$line=trim($line);if($line==='')continue;$parts=array_map('trim',explode('|',$line));if(count($parts)<2||$parts[0]===''||$parts[1]===''){$errors[]='Each medicine line must be: Medicine name | dosage | instructions (instructions optional).';break;}$meds[]=['name'=>$parts[0],'dosage'=>$parts[1],'instructions'=>$parts[2]??''];}
  if(!$errors){try{db()->beginTransaction();$q=db()->prepare("INSERT INTO encounters(organization_id,patient_user_id,doctor_user_id,clinician_name,clinician_license,appointment_id,encounter_type,summary,status) VALUES(?,?,?,?,?,?,?,?, 'closed')");$q->execute([$oid,$patient['id'],$user['id'],$clinicianName,$clinicianLicense,$appointmentId,$type,$diagnosis]);$encounterId=(int)db()->lastInsertId();db()->prepare("INSERT INTO medical_records(encounter_id,record_type,title,body,created_by) VALUES(?, 'diagnosis','Diagnosis / assessment',?,?)")->execute([$encounterId,$diagnosis,$user['id']]);db()->prepare("INSERT INTO medical_records(encounter_id,record_type,title,body,created_by) VALUES(?, 'clinical_note','Consultation notes',?,?)")->execute([$encounterId,$notes,$user['id']]);$ps=db()->prepare('INSERT INTO prescriptions(encounter_id,prescribed_by,medicine_name,dosage,instructions,start_date,end_date) VALUES(?,?,?,?,?,NULL,NULL)');foreach($meds as $m)$ps->execute([$encounterId,$user['id'],$m['name'],$m['dosage'],$m['instructions']?:null]);db()->prepare("UPDATE appointments SET status='completed' WHERE id=? AND organization_id=?")->execute([$appointmentId,$oid]);db()->commit();flash('success','Consultation and prescriptions saved to the patient timeline.');redirect('hospital/records.php?patient_code='.rawurlencode($code));}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();error_log((string)$e);$errors[]='The record could not be saved. Check the database schema and try again.';}}
  // Reload the matched patient, consent, visits and history after a validation error.
  $code=trim((string)($_POST['patient_code']??'')); $q=db()->prepare("SELECT u.id,u.full_name,p.patient_code FROM users u JOIN patient_profiles p ON p.user_id=u.id WHERE UPPER(p.patient_code)=UPPER(?) AND u.role='patient' LIMIT 1");$q->execute([$code]);$patient=$q->fetch();
  if($patient){$hasConsent=true;$q=db()->prepare("SELECT a.id,a.starts_at,a.reason,a.status,d.name AS department_name FROM appointments a LEFT JOIN departments d ON d.id=a.department_id WHERE a.organization_id=? AND a.patient_user_id=? ORDER BY a.starts_at DESC");$q->execute([$oid,$patient['id']]);$visits=$q->fetchAll();if($hasConsent){$q=db()->prepare("SELECT e.id,e.occurred_at,e.encounter_type,e.summary,e.clinician_name,e.clinician_license,o.name AS provider_name FROM encounters e JOIN organizations o ON o.id=e.organization_id WHERE e.patient_user_id=? ORDER BY e.occurred_at DESC");$q->execute([$patient['id']]);$history=$q->fetchAll();}}
}
$pageTitle='Clinical records'; require __DIR__.'/../../includes/header.php';
?>
<section class="page-intro"><span class="eyebrow">AUTHORIZED CLINICAL WORKSPACE</span><h1>Consultation & <span>prescription records.</span></h1><p>Search a CareBridge Patient ID. The patient is notified automatically when cross-provider history is viewed. Only authenticated, approved providers can access this workspace.</p></section>
<div class="form-panel form-panel-wide"><form method="get" class="stack-form"><label>Patient ID<input name="patient_code" required value="<?=e($code)?>" placeholder="CB-XXXXXXXX"></label><button class="button" type="submit">Find patient →</button></form>
<?php if($errors):?><div class="alert alert-error"><ul><?php foreach($errors as $error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?>
<?php if($patient):?><hr><h2><?=e($patient['full_name'])?> <small>(<?=e($patient['patient_code'])?>)</small></h2>
<?php if($hasConsent):?><div class="alert alert-success">Read-only medical history from all CareBridge providers is available below. The patient is notified automatically.</div>
<?php if(!$history):?><p class="source-note">No prior clinical encounters are recorded.</p><?php else:?>
<h3>Medical history across providers</h3>
<?php foreach($history as $enc):?><article class="medical-record-card"><h3><?=e($enc['summary']?:'Clinical encounter')?></h3><p><strong><?=e($enc['provider_name'])?></strong> · <?=e($enc['clinician_name'])?> (License: <?=e($enc['clinician_license'])?>) · <?=e(date('M j, Y g:i A',strtotime($enc['occurred_at'])))?></p>
<?php $rr=db()->prepare('SELECT title,body FROM medical_records WHERE encounter_id=? ORDER BY created_at');$rr->execute([$enc['id']]);foreach($rr->fetchAll() as $rec):?><p><strong><?=e($rec['title'])?>:</strong> <?=nl2br(e($rec['body']))?></p><?php endforeach;?>
<h4>Prescribed medicines</h4><?php $pp=db()->prepare('SELECT medicine_name,dosage,instructions,start_date,end_date,status FROM prescriptions WHERE encounter_id=? ORDER BY created_at');$pp->execute([$enc['id']]);$meds=$pp->fetchAll();if(!$meds):?><p>No medicines recorded for this visit.</p><?php else:foreach($meds as $med):?><div class="medicine-row"><strong><?=e($med['medicine_name'])?></strong> — <?=e($med['dosage'])?><p><?=e($med['instructions']?:'No additional instructions recorded')?></p><small>Status: <?=e($med['status'])?> · Dates: <?=e($med['start_date']?:'Not specified')?> to <?=e($med['end_date']?:'Not specified')?></small></div><?php endforeach;endif;?></article><?php endforeach;endif;?>
<?php else:?><div class="alert alert-warning">Medical history is available to authenticated, approved providers. You can still add a record for a visit at your organization.</div><?php endif;?>
<?php if($visits):?><hr><h3>Record a consultation at <?=e($org['name'])?></h3><p>Only visits booked with your organization can be selected for a new record.</p><form method="post" class="stack-form"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="save_record" value="1"><input type="hidden" name="patient_code" value="<?=e($patient['patient_code'])?>"><label>Appointment / visit<select name="appointment_id" required><option value="">Choose a visit</option><?php foreach($visits as $v):?><option value="<?=(int)$v['id']?>">CB-T<?=str_pad((string)$v['id'],6,'0',STR_PAD_LEFT)?> · <?=e(date('M j, Y g:i A',strtotime($v['starts_at'])))?> · <?=e($v['reason']?:$v['department_name']?:'General check-up')?></option><?php endforeach;?></select></label><label>Encounter type<select name="encounter_type"><option value="outpatient">Outpatient</option><option value="emergency">Emergency</option><option value="inpatient">Inpatient</option><option value="telehealth">Telehealth</option><option value="other">Other</option></select></label><label>Treating clinician full name<input name="clinician_name" required maxlength="180"></label><label>Clinician registration / license number<input name="clinician_license" required maxlength="120"></label><label>Diagnosis / assessment<textarea name="diagnosis" required rows="2" maxlength="1000"></textarea></label><label>Consultation notes<textarea name="notes" required rows="4" maxlength="10000"></textarea></label><label>Medicines prescribed (one per line)<textarea name="medicines" rows="4" placeholder="Medicine name | dosage | instructions"></textarea><small class="form-hint">Enter only medicines actually prescribed by the treating clinician.</small></label><button class="button" type="submit">Save consultation & prescriptions →</button></form><?php else:?><p class="source-note">No appointments at your organization are linked to this patient yet, so a new consultation cannot be attached here.</p><?php endif;?>
<?php endif;?></div><?php require __DIR__.'/../../includes/footer.php';?>
