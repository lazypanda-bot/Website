document.addEventListener('DOMContentLoaded', () => {
  const profileIcon = document.getElementById('profile-icon');
  const mobileProfileIcon = document.getElementById('mobile-profile-icon');
  const cartIcon = document.getElementById('cart-icon');
  let modal;

  function openLoginModal(redirectPath) {
    // Always use the current page as the redirect path
    const path = redirectPath || window.location.href;
    sessionStorage.setItem('redirect_after_auth', path);
    if (modal) {
      modal.classList.add('active');
      setRedirectInput();
    }
  }
  window.openLoginModal = openLoginModal;

  function setRedirectInput() {
    const loginRedirectInput = document.getElementById('login-redirect-after-auth');
    const registerRedirectInput = document.getElementById('register-redirect-after-auth');
    // Always set to the last stored path (where icon was clicked)
    const redirectPath = sessionStorage.getItem('redirect_after_auth') || window.location.href;
    if (loginRedirectInput) loginRedirectInput.value = redirectPath;
    if (registerRedirectInput) registerRedirectInput.value = redirectPath;
  }

  function handleProfileClick(e) {
    e.preventDefault();
    if (window.isAuthenticated) {
      window.location.href = 'profile.php';
    } else {
      openLoginModal(window.location.href);
    }
  }

  function handleCartClick(e) {
    e.preventDefault();
    if (window.isAuthenticated) {
      window.location.href = 'cart.php';
    } else {
      openLoginModal('cart.php');
    }
  }

  if (profileIcon) profileIcon.addEventListener('click', handleProfileClick);
  if (mobileProfileIcon) mobileProfileIcon.addEventListener('click', handleProfileClick);
  if (cartIcon) cartIcon.addEventListener('click', handleCartClick);

  fetch('login.php?nocache=' + Date.now())
    .then(res => res.text())
    .then(html => {
      document.getElementById('login-container').innerHTML = html;

      modal = document.getElementById('auth-modal');
      const container = document.getElementById('auth-box');
      const closeBtn = document.getElementById('modal-close');
      const signUpBtn = document.getElementById('sign-up-btn');
      const signInBtn = document.getElementById('sign-in-btn');

      if (closeBtn && modal) {
        closeBtn.addEventListener('click', () => modal.classList.remove('active'));
      }

      if (signUpBtn && container) {
        signUpBtn.addEventListener('click', () => container.classList.add('sign-up-mode'));
      }

      if (signInBtn && container) {
        signInBtn.addEventListener('click', () => container.classList.remove('sign-up-mode'));
      }

      if (modal) {
        modal.addEventListener('click', (e) => {
          if (e.target === modal) modal.classList.remove('active');
        });
      }

      requestAnimationFrame(() => {
        // Password toggles
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

        setRedirectInput();

        // Login social toggles
        const loginInput = document.getElementById('login-identifier-input');
        const loginIcon = document.getElementById('login-identifier-icon');
        const loginSocial = document.getElementById('login-social');
        const loginUsePhone = document.getElementById('login-use-phone');
        const loginUseFacebook = document.getElementById('login-use-facebook');

        function addLoginEmailIcon() {
          if (!document.getElementById('login-use-email') && loginSocial && loginInput && loginIcon) {
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

        if (loginUsePhone && loginInput && loginIcon && loginSocial) {
          loginUsePhone.addEventListener('click', (e) => {
            e.preventDefault();
            loginInput.placeholder = 'Phone Number';
            loginIcon.className = 'fas fa-phone';
            loginUsePhone.remove();
            if (!document.getElementById('login-use-facebook')) loginSocial.prepend(loginUseFacebook);
            addLoginEmailIcon();
          });
        }

        if (loginUseFacebook && loginInput && loginIcon && loginSocial) {
          loginUseFacebook.addEventListener('click', (e) => {
            e.preventDefault();
            loginInput.placeholder = 'Facebook Email';
            loginIcon.className = 'fab fa-facebook-f';
            loginUseFacebook.remove();
            if (!document.getElementById('login-use-phone')) loginSocial.prepend(loginUsePhone);
            addLoginEmailIcon();
          });
        }

        // Register social toggles
        const regInput = document.getElementById('reg-identifier-input');
        const regIcon = document.getElementById('reg-identifier-icon');
        const regSocial = document.getElementById('reg-social');
        const regUsePhone = document.getElementById('reg-use-phone');
        const regUseFacebook = document.getElementById('reg-use-facebook');

        function addRegEmailIcon() {
          if (!document.getElementById('reg-use-email') && regSocial && regInput && regIcon) {
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

        if (regUsePhone && regInput && regIcon && regSocial) {
          regUsePhone.addEventListener('click', (e) => {
            e.preventDefault();
            regInput.placeholder = 'Phone Number';
            regIcon.className = 'fas fa-phone';
            regUsePhone.remove();
            if (!document.getElementById('reg-use-facebook')) regSocial.prepend(regUseFacebook);
            addRegEmailIcon();
          });
        }

        if (regUseFacebook && regInput && regIcon && regSocial) {
          regUseFacebook.addEventListener('click', (e) => {
            e.preventDefault();
            regInput.placeholder = 'Facebook Email';
            regIcon.className = 'fab fa-facebook-f';
            regUseFacebook.remove();
            if (!document.getElementById('reg-use-phone')) regSocial.prepend(regUsePhone);
            addRegEmailIcon();
          });
        }
      });
    });
});
