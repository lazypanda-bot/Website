document.addEventListener('DOMContentLoaded', function () {
  // Attach #order-now-btn click event (moved from home.php inline script)
  var orderBtn = document.getElementById('order-now-btn');
  if (orderBtn) {
    orderBtn.addEventListener('click', function() {
      window.location.href = 'products.php';
    });
  }

  const toggleBtn = document.getElementById('menu-toggle');
  const navbar = document.getElementById('navbar');
  const closeBtn = document.getElementById('close-menu');

  const links = document.querySelectorAll('.nav-link');
const currentPage = window.location.pathname.split('/').pop();

links.forEach(link => {
  if (link.getAttribute('href') === currentPage) {
    link.classList.add('active');
  }
});

  toggleBtn.addEventListener('click', () => {
    navbar.classList.add('active');
  });
  closeBtn.addEventListener('click', () => {
    navbar.classList.remove('active');
  });
});

// document.querySelector('.search-btn').addEventListener('click', function () {
//   const input = document.querySelector('.search-bar input');
//   input.classList.toggle('active');
//   input.focus();
// });


const searchBtn = document.querySelector('.search-btn');
const searchInput = document.querySelector('.search-input');

function performSearch() {
  if (!searchInput) return;
  const val = searchInput.value.trim();
  if (val === '') return;
  window.location.href = 'products.php?q=' + encodeURIComponent(val);
}

if (searchBtn && searchInput) {
  // Preload existing query if on products page with ?q=
  const params = new URLSearchParams(window.location.search);
  const existingQ = params.get('q');
  if (existingQ) {
    searchInput.value = existingQ;
    searchInput.classList.remove('hidden');
    searchInput.classList.add('visible');
  }

  searchBtn.addEventListener('click', () => {
    // If there is text, treat click as submit regardless of visibility state
    if (searchInput.value.trim() !== '') {
      performSearch();
      return;
    }
    // No text yet → toggle visibility & focus
    searchInput.classList.toggle('hidden');
    searchInput.classList.toggle('visible');
    searchInput.focus();
  });

  // Submit on Enter key
  searchInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      performSearch();
    }
  });

  // Hide if empty on blur
  searchInput.addEventListener('blur', () => {
    if (searchInput.value.trim() === '') {
      searchInput.classList.remove('visible');
      searchInput.classList.add('hidden');
    }
  });
}

// outer carousel
const whySwiper = new Swiper('.why-carousel', {
  pagination: {
    el: '.why-pagination',
    clickable: true,
  },
  navigation: {
    nextEl: '.swiper-button-next',
    prevEl: '.swiper-button-prev',
  },
  spaceBetween: 30,
  loop: true,
  autoplay: {
    delay: 3000,
    disableOnInteraction: false,
  },
});

// inner carousel
const reasonSwiper = new Swiper('.reason-carousel', {
  pagination: {
    el: '.reason-pagination',
    clickable: true,
  },
  spaceBetween: 20,
  loop: false,
  autoplay: {
    delay: 2000,
    disableOnInteraction: false,
  },
});

// pause outer carousel when "Why choose" slide is active
whySwiper.on('slideChange', function () {
  const activeIndex = whySwiper.activeIndex;
  if (activeIndex === 1) {
    whySwiper.autoplay.stop();
    reasonSwiper.slideTo(0, 0);
    reasonSwiper.autoplay.start();
  } else {
    whySwiper.autoplay.start();
  }
});
// resume outer carousel after inner carousel finishes
reasonSwiper.on('reachEnd', function () {
  reasonSwiper.autoplay.stop();
  setTimeout(() => {
    whySwiper.slideNext();  
    whySwiper.autoplay.start(); 
  }, 3000);
});

function hideSpinner(video) {
  const spinner = video.closest('.swiper-slide').querySelector('.spinner');
  if (spinner) spinner.style.display = 'none';
}

//reels
// function attachVideoEndListener() {
//   const activeSlide = document.querySelector('.reels-carousel .swiper-slide-active video');
//   if (activeSlide) {
//     activeSlide.currentTime = 0;
//     activeSlide.play();

//     activeSlide.onended = () => {
//       reelsSwiper.slideNext();
//     };
//   }
// }

// window.addEventListener('load', () => {
//   document.querySelectorAll('.reels-carousel video').forEach(video => {
//     video.pause();
//     video.currentTime = 0;
//     video.onended = null;
//   });
//   attachVideoEndListener();
// });

// const reelsSwiper = new Swiper('.reels-carousel', {
//   loop: true,
//   centeredSlides: true,
//   slidesPerView: 3,
//   spaceBetween: -30,
//   grabCursor: true,
//   navigation: {
//     nextEl: '.reels-carousel .swiper-button-next',
//     prevEl: '.reels-carousel .swiper-button-prev',
//   },
//   on: {
//     slideChangeTransitionStart: () => {
//       document.querySelectorAll('.reels-carousel video').forEach(video => {
//         video.pause();
//         video.currentTime = 0;
//         video.onended = null;
//       });
//     },
//     slideChangeTransitionEnd: () => {
//       attachVideoEndListener();
//     }
//   }
// });

// Global welcome toast (login / registration)
document.addEventListener('DOMContentLoaded', () => {
  function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
  }
  const raw = getCookie('welcome_toast');
  if (raw) {
    // Decode any percent-encoding artifacts (%20, %2C, UTF-8 sequences) safely
    let message = raw.replace(/\+/g, ' ');
    try {
      // First pass decode
      message = decodeURIComponent(message);
      // If decoding introduced new % sequences (double-encoded), attempt one more time
      if (/%[0-9A-Fa-f]{2}/.test(message)) {
        try { message = decodeURIComponent(message); } catch(_){}
      }
    } catch (e) {
      // fallback: replace common encodings manually
      message = message
        .replace(/%20/g,' ')
        .replace(/%2C/gi,',')
        .replace(/%21/g,'!')
        .replace(/%3A/gi,':')
        .replace(/%3B/gi,';')
        .replace(/%2D/gi,'-');
    }
    message = message.trim();
    const toast = document.createElement('div');
    toast.className = 'site-toast';
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    toast.textContent = message;
    document.body.appendChild(toast);
    requestAnimationFrame(() => { toast.classList.add('show'); });
    setTimeout(() => { toast.classList.remove('show'); }, 3200);
    setTimeout(() => { toast.remove(); }, 3800);
    document.cookie = 'welcome_toast=; Max-Age=0; path=/';
  }
});