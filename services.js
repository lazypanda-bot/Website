document.addEventListener('DOMContentLoaded', () => {
  const serviceItems = document.querySelectorAll('.service-item');

  serviceItems.forEach(item => {
    item.addEventListener('click', () => {
      const wrapper = item.closest('.service-item-wrapper');
      wrapper.classList.toggle('active');
    });
  });
});
