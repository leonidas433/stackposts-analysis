// Search overlay behaviour (open triggers, close buttons, backdrop, Escape).
// Side-effect module with no CSS coupling so child themes can import it
// from their own entry point.
document.addEventListener('DOMContentLoaded', function() {
  const searchTriggers = document.querySelectorAll('.search-trigger');
  const searchOverlay = document.querySelector('.search-overlay');
  const closeSearchBtns = document.querySelectorAll('.close-search');

  searchTriggers.forEach(trigger => {
    trigger.addEventListener('click', function() {
      if (searchOverlay) {
        searchOverlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
      }
    });
  });

  closeSearchBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      if (searchOverlay) {
        searchOverlay.classList.add('hidden');
        document.body.style.overflow = 'auto';
      }
    });
  });

  if (searchOverlay) {
    searchOverlay.addEventListener('click', function(e) {
      if (e.target === searchOverlay) {
        searchOverlay.classList.add('hidden');
        document.body.style.overflow = 'auto';
      }
    });

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && !searchOverlay.classList.contains('hidden')) {
        searchOverlay.classList.add('hidden');
        document.body.style.overflow = 'auto';
      }
    });
  }
});
