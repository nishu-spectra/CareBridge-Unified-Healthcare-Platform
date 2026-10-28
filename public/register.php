<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (current_user()) redirect('dashboard.php');
$errors=[]; $fullName=$email=$phone=''; $method='email';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf(); $fullName=trim((string)($_POST['full_name']??'')); $email=strtolower(trim((string)($_POST['email']??''))); $phone=trim((string)($_POST['phone']??'')); $password=(string)($_POST['password']??''); $confirm=(string)($_POST['confirm_password']??'');
 if(mb_strlen($fullName)<2||mb_strlen($fullName)>120)$errors[]='Enter your full name (2–120 characters).';
 if(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Enter a valid email address.';
 if(!preg_match('/^\+?[0-9 ()-]{7,20}$/',$phone))$errors[]='Enter a valid phone number, including country code when possible.';

 $errors=array_merge($errors,password_policy_errors($password)); if($password!==$confirm)$errors[]='Passwords do not match.';
 if(!$errors){$q=db()->prepare('SELECT id FROM users WHERE email=? OR phone=?');$q->execute([$email,$phone]);if($q->fetch())$errors[]='That email or phone number is already registered.';}
 if(!$errors){$code=(string)random_int(100000,999999);$payload=['type'=>'patient','full_name'=>$fullName,'email'=>$email,'phone'=>$phone,'password_hash'=>password_hash($password,PASSWORD_DEFAULT)];
  db()->prepare('DELETE FROM pending_registrations WHERE email=? OR expires_at < NOW()')->execute([$email]);
  $q=db()->prepare('INSERT INTO pending_registrations(email,phone,verification_method,code_hash,payload_json,expires_at) VALUES(?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))');$q->execute([$email,$phone,'email',password_hash($code,PASSWORD_DEFAULT),json_encode($payload,JSON_THROW_ON_ERROR)]);
  if(send_verification_code('email',$email,$code,'CareBridge patient registration')){$_SESSION['pending_registration_email']=$email;flash('success','A verification code was sent to your email. Enter it to finish registration.');redirect('verify-registration.php');}
  db()->prepare('DELETE FROM pending_registrations WHERE email=?')->execute([$email]);$errors[]='We could not send the verification email. Please check the email configuration and try again.';
 }
}
$pageTitle='Register as a patient';require __DIR__.'/../includes/header.php';
?>
<section class="auth-layout"><div class="auth-aside"><span class="eyebrow">YOUR HEALTH, YOUR SPACE</span><h1>A personal space for your health journey.</h1><p>Register with verified contact details to create your CareBridge Patient ID.</p></div><div class="form-panel"><div class="form-heading"><h2>Register as patient / user</h2><p>Already registered? <a href="<?=e(url('login-patient.php'))?>">Login</a></p></div>
<?php if($errors):?><div class="alert alert-error"><ul><?php foreach($errors as $error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?>
<form method="post" class="stack-form" data-password-policy><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><label>Full name<input name="full_name" required maxlength="120" autocomplete="name" value="<?=e($fullName)?>"></label><label>Email address<input type="email" name="email" required autocomplete="email" value="<?=e($email)?>"></label><label>Phone number<input type="tel" name="phone" required autocomplete="tel" placeholder="+977 98XXXXXXXX" value="<?=e($phone)?>"></label><label>Password<span class="password-wrap"><input type="password" name="password" required minlength="10" autocomplete="new-password" data-password-main><button class="password-toggle" type="button" data-password-toggle>Show</button></span><small class="password-rules" data-password-rules>At least 10 characters, uppercase, lowercase, digit and symbol.</small></label><label>Confirm password<span class="password-wrap"><input type="password" name="confirm_password" required minlength="10" autocomplete="new-password" data-password-confirm><button class="password-toggle" type="button" data-password-toggle>Show</button></span><small data-confirm-hint class="password-rules"></small></label><button class="button button-full" type="submit">Verify email & register →</button></form></div></section>
<?php require __DIR__.'/../includes/footer.php'; ?>