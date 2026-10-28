<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Connected healthcare for everyone';
require __DIR__ . '/../includes/header.php';
?>
<section class="hero hero-modern">
  <div class="hero-copy">
    <span class="eyebrow"><span class="eyebrow-dot"></span> ONE PLATFORM · CONNECTED CARE</span>
    <h1>Better healthcare.<br><span>For every journey.</span></h1>
    <p class="hero-text">Find trusted hospitals, book appointments, and keep your health history together. CareBridge also gives verified healthcare providers a smarter way to coordinate care.</p>
    <div class="hero-actions"><a class="button" href="<?= e(url('register.php')) ?>">Register as patient / user <span aria-hidden="true">→</span></a><a class="button button-secondary" href="<?= e(url('hospital-register.php')) ?>">Register as doctor / service provider</a><a class="button button-secondary" href="<?= e(url('patient-record-access.php')) ?>">View Medical History</a><a class="text-link" href="<?= e(url('login-patient.php')) ?>">Login as user / patient</a><a class="text-link" href="<?= e(url('login-provider.php')) ?>">Login as provider</a></div>
    <div class="trust-row"><span><span class="check-dot">✓</span> Verified providers</span><span><span class="check-dot">✓</span> Appointment tracking</span><span><span class="check-dot">✓</span> Patient-first access</span></div>
  </div>
  <div class="hero-photo-wrap"><img class="hero-photo" src="https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=1100&q=85" alt="Healthcare professional ready to support a patient" fetchpriority="high"></div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
