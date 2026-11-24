// User Page JavaScript

// Theme handling
function applyTheme(theme) {
  if (theme === 'dark') {
    document.body.classList.add('dark');
    document.documentElement.classList.add('dark');
  } else {
    document.body.classList.remove('dark');
    document.documentElement.classList.remove('dark');
  }
  
  // Update checkbox state
  const themeSwitch = document.getElementById('themeSwitch');
  if (themeSwitch) {
    themeSwitch.checked = (theme === 'dark');
  }
  
  // Save to localStorage
  try {
    localStorage.setItem('theme', theme);
  } catch (e) {
    console.error('Failed to save theme:', e);
  }
}

// Initialize on page load
window.addEventListener('DOMContentLoaded', () => {
  // Load saved theme
  const savedTheme = (typeof localStorage !== 'undefined' && localStorage.getItem('theme')) || 'light';
  applyTheme(savedTheme);
  
  // Header theme toggle button
  const headerThemeToggle = document.getElementById('headerThemeToggle');
  if (headerThemeToggle) {
    headerThemeToggle.addEventListener('click', () => {
      const currentTheme = document.body.classList.contains('dark') ? 'light' : 'dark';
      applyTheme(currentTheme);
    });
  }
  
  // Settings page theme switch
  const themeSwitch = document.getElementById('themeSwitch');
  if (themeSwitch) {
    themeSwitch.addEventListener('change', (e) => {
      applyTheme(e.target.checked ? 'dark' : 'light');
    });
  }
  
  // User menu dropdown toggle
  const userMenu = document.getElementById('userMenu');
  const userDropdown = document.getElementById('userDropdown');
  
  if (userMenu) {
    userMenu.addEventListener('click', (e) => {
      e.stopPropagation();
      userMenu.classList.toggle('open');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
      if (!userMenu.contains(e.target)) {
        userMenu.classList.remove('open');
      }
    });
  }
  
  // Sidebar navigation
  document.querySelectorAll('.side-menu a[data-target]').forEach(link => {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      const targetId = this.getAttribute('data-target');
      
      // Update active link
      document.querySelectorAll('.side-menu a').forEach(a => a.classList.remove('active'));
      this.classList.add('active');
      
      // Update active section
      document.querySelectorAll('.section').forEach(section => section.classList.remove('active'));
      const targetSection = document.getElementById(targetId);
      if (targetSection) {
        targetSection.classList.add('active');
      }
      
      // Update URL hash
      history.replaceState(null, '', '#' + targetId);
    });
  });
  
  // Handle initial hash
  const hash = location.hash.replace('#', '');
  if (hash) {
    const link = document.querySelector('.side-menu a[data-target="' + hash + '"]');
    if (link) {
      link.click();
    }
  }
  
  // Header profile link
  const headerGoProfile = document.getElementById('headerGoProfile');
  if (headerGoProfile) {
    headerGoProfile.addEventListener('click', (e) => {
      e.preventDefault();
      const profileLink = document.querySelector('.side-menu a[data-target="profile"]');
      if (profileLink) {
        profileLink.click();
      }
      // Close dropdown
      if (userMenu) {
        userMenu.classList.remove('open');
      }
    });
  }
});
