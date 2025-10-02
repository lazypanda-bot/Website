document.addEventListener('DOMContentLoaded', function() {
  // Add click functionality to contact methods
  const methods = document.querySelectorAll('.method');
  
  methods.forEach(method => {
    method.addEventListener('click', function() {
      const methodType = this.querySelector('h3').textContent.toLowerCase();
      const content = this.querySelector('p').textContent;
      
      switch(methodType) {
        case 'chat with us':
          // Add chat functionality here
          console.log('Opening chat...');
          break;
          
        case 'call us':
          const phoneNumber = content.replace(/\s/g, '');
          window.open(`tel:${phoneNumber}`, '_self');
          break;
          
        case 'find our store':
          const address = encodeURIComponent(content);
          window.open(`https://maps.google.com/?q=${address}`, '_blank');
          break;
          
        case 'facebook':
          window.open(content, '_blank');
          break;
          
        case 'linktree':
          window.open(content, '_blank');
          break;
          
        case 'email':
          window.open(`mailto:${content}`, '_self');
          break;
      }
    });
    
    // Add hover effect with JavaScript for better control
    method.addEventListener('mouseenter', function() {
      this.style.transform = 'translateY(-2px)';
    });
    
    method.addEventListener('mouseleave', function() {
      this.style.transform = 'translateY(0)';
    });
  });
  
  // Add smooth scrolling for better UX
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    });
  });
  
  // Add loading animation
  const contactSection = document.querySelector('.contact-section');
  contactSection.style.opacity = '0';
  contactSection.style.transform = 'translateY(20px)';
  
  setTimeout(() => {
    contactSection.style.transition = 'all 0.6s ease';
    contactSection.style.opacity = '1';
    contactSection.style.transform = 'translateY(0)';
  }, 100);
  
  // Add stagger animation to methods
  const allMethods = document.querySelectorAll('.method');
  allMethods.forEach((method, index) => {
    method.style.opacity = '0';
    method.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
      method.style.transition = 'all 0.4s ease';
      method.style.opacity = '1';
      method.style.transform = 'translateY(0)';
    }, 200 + (index * 100));
  });
});
