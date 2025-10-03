document.addEventListener('DOMContentLoaded', () => {
  fetch('login.php')
    .then(res => res.text())
    .then(html => {
      document.getElementById('login-container').innerHTML = html;

      const modal = document.getElementById('auth-modal');
      const container = document.getElementById('auth-box');
      const openLogin = document.getElementById('open-login');
      const closeBtn = document.getElementById('modal-close');
      const signUpBtn = document.getElementById('sign-up-btn');
      const signInBtn = document.getElementById('sign-in-btn');

      // 🔄 Store current page for redirect
      openLogin.addEventListener('click', (e) => {
        e.preventDefault();
        sessionStorage.setItem('redirect_after_auth', window.location.href);
        modal.classList.add('active');
        container.classList.remove('sign-up-mode');
      });

      closeBtn.addEventListener('click', () => {
        modal.classList.remove('active');
      });

      signUpBtn.addEventListener('click', () => {
        container.classList.add('sign-up-mode');
      });

      signInBtn.addEventListener('click', () => {
        container.classList.remove('sign-up-mode');
      });

      modal.addEventListener('click', (e) => {
        if (e.target === modal) {
          modal.classList.remove('active');
        }
      });

      requestAnimationFrame(() => {
        // 🔐 Password toggles
        const toggleLoginPassword = document.getElementById('toggle-login-password');
        const loginPasswordInput = document.getElementById('login-password');
        if (toggleLoginPassword && loginPasswordInput) {
          toggleLoginPassword.addEventListener('change', function () {
            loginPasswordInput.type = this.checked ? 'text' : 'password';
          });
        }

        const toggleRegisterPassword = document.getElementById('toggle-register-password');
        const registerPasswordInput = document.getElementById('register-password');
        if (toggleRegisterPassword && registerPasswordInput) {
          toggleRegisterPassword.addEventListener('change', function () {
            registerPasswordInput.type = this.checked ? 'text' : 'password';
          });
        }

        // 🔁 Inject redirect path into both forms
        const redirectInputs = document.querySelectorAll('#redirect-after-auth');
        redirectInputs.forEach(input => {
          input.value = sessionStorage.getItem('redirect_after_auth') || window.location.href;
        });

        // 🔄 Sign In toggle
        const loginInput = document.getElementById('login-identifier-input');
        const loginIcon = document.getElementById('login-identifier-icon');
        const loginSocial = document.getElementById('login-social');
        const loginUsePhone = document.getElementById('login-use-phone');
        const loginUseFacebook = document.getElementById('login-use-facebook');

        function addLoginEmailIcon() {
          if (!document.getElementById('login-use-email')) {
            const emailIcon = document.createElement('a');
            emailIcon.href = '#';
            emailIcon.className = 'social-icon';
            emailIcon.id = 'login-use-email';
            emailIcon.innerHTML = '<i class="fas fa-envelope"></i>';
            loginSocial.prepend(emailIcon);

            emailIcon.addEventListener('click', (e) => {
              e.preventDefault();
              loginInput.placeholder = 'Email';
              loginIcon.className = 'fas fa-user';
              emailIcon.remove();
              if (!document.getElementById('login-use-phone')) loginSocial.prepend(loginUsePhone);
              if (!document.getElementById('login-use-facebook')) loginSocial.prepend(loginUseFacebook);
            });
          }
        }

        loginUsePhone.addEventListener('click', (e) => {
          e.preventDefault();
          loginInput.placeholder = 'Phone Number';
          loginIcon.className = 'fas fa-phone';
          loginUsePhone.remove();
          if (!document.getElementById('login-use-facebook')) loginSocial.prepend(loginUseFacebook);
          addLoginEmailIcon();
        });

        loginUseFacebook.addEventListener('click', (e) => {
          e.preventDefault();
          loginInput.placeholder = 'Facebook Email';
          loginIcon.className = 'fab fa-facebook-f';
          loginUseFacebook.remove();
          if (!document.getElementById('login-use-phone')) loginSocial.prepend(loginUsePhone);
          addLoginEmailIcon();
        });

        // 🔄 Sign Up toggle
        const regInput = document.getElementById('reg-identifier-input');
        const regIcon = document.getElementById('reg-identifier-icon');
        const regSocial = document.getElementById('reg-social');
        const regUsePhone = document.getElementById('reg-use-phone');
        const regUseFacebook = document.getElementById('reg-use-facebook');

        function addRegEmailIcon() {
          if (!document.getElementById('reg-use-email')) {
            const emailIcon = document.createElement('a');
            emailIcon.href = '#';
            emailIcon.className = 'social-icon';
            emailIcon.id = 'reg-use-email';
            emailIcon.innerHTML = '<i class="fas fa-envelope"></i>';
            regSocial.prepend(emailIcon);

            emailIcon.addEventListener('click', (e) => {
              e.preventDefault();
              regInput.placeholder = 'Email';
              regIcon.className = 'fas fa-envelope';
              emailIcon.remove();
              if (!document.getElementById('reg-use-phone')) regSocial.prepend(regUsePhone);
              if (!document.getElementById('reg-use-facebook')) regSocial.prepend(regUseFacebook);
            });
          }
        }

        regUsePhone.addEventListener('click', (e) => {
          e.preventDefault();
          regInput.placeholder = 'Phone Number';
          regIcon.className = 'fas fa-phone';
          regUsePhone.remove();
          if (!document.getElementById('reg-use-facebook')) regSocial.prepend(regUseFacebook);
          addRegEmailIcon();
        });

        regUseFacebook.addEventListener('click', (e) => {
          e.preventDefault();
          regInput.placeholder = 'Facebook Email';
          regIcon.className = 'fab fa-facebook-f';
          regUseFacebook.remove();
          if (!document.getElementById('reg-use-phone')) regSocial.prepend(regUsePhone);
          addRegEmailIcon();
        });
      });
    });
});
