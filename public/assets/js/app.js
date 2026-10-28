document.addEventListener('click', function (event) {
  const toggle = event.target.closest('[data-password-toggle]');
  if (!toggle) return;
  const wrapper = toggle.closest('.password-wrap');
  const input = wrapper ? wrapper.querySelector('input') : null;
  if (!input) return;
  const reveal = input.type === 'password';
  input.type = reveal ? 'text' : 'password';
  toggle.textContent = reveal ? 'Hide' : 'Show';
  toggle.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
});


document.addEventListener('input', function (event) {
  const form = event.target.closest('[data-password-policy]');
  if (!form) return;
  const password = form.querySelector('[data-password-main]');
  const confirm = form.querySelector('[data-password-confirm]');
  const rules = form.querySelector('[data-password-rules]');
  const confirmHint = form.querySelector('[data-confirm-hint]');
  if (password && rules) {
    const checks = [password.value.length >= 10, /[A-Z]/.test(password.value), /[a-z]/.test(password.value), /[0-9]/.test(password.value), /[^A-Za-z0-9]/.test(password.value)];
    const names = ['10+ characters', 'uppercase letter', 'lowercase letter', 'digit', 'symbol'];
    rules.textContent = checks.every(Boolean) ? 'Password meets all requirements.' : 'Required: ' + names.filter((_, i) => !checks[i]).join(', ') + '.';
    rules.classList.toggle('rules-valid', checks.every(Boolean));
    password.setCustomValidity(checks.every(Boolean) ? '' : 'Password must meet all listed requirements.');
  }
  if (password && confirm && confirmHint) {
    const match = confirm.value === password.value;
    confirmHint.textContent = confirm.value === '' ? '' : (match ? 'Passwords match.' : 'Passwords do not match.');
    confirmHint.classList.toggle('rules-valid', match && confirm.value !== '');
    confirm.setCustomValidity(match ? '' : 'Passwords do not match.');
  }
});
