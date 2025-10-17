document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('signinForm');
  if (!form) return;
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    // send to admin-only endpoint
    fetch('admin-login.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        if (d && d.status === 'ok') {
          // redirect to admin dashboard (relative path)
          window.location.href = d.redirect || 'admin.html';
        } else {
          alert(d.message || 'Login failed');
        }
      })
      .catch(err => { console.error(err); alert('Login error'); });
  });
});
