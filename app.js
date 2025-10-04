document.addEventListener('DOMContentLoaded', function () {
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

searchBtn.addEventListener('click', () => {
  searchInput.classList.toggle('hidden');
  searchInput.classList.toggle('visible');
  searchInput.focus();
});

searchInput.addEventListener('blur', () => {
  if (searchInput.value.trim() === '') {
    searchInput.classList.remove('visible');
    searchInput.classList.add('hidden');
  }
});

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