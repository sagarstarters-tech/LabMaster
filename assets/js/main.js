/**
 * LabMaster - Public Website Scripts
 */

document.addEventListener('DOMContentLoaded', function () {
  // 1. Interactive Test Filter & Search
  const testSearchInput = document.getElementById('testSearchInput');
  const categoryFilters = document.querySelectorAll('.category-filter-btn');
  const testCards = document.querySelectorAll('.test-item-card');

  function filterTests() {
    const query = (testSearchInput ? testSearchInput.value : '').toLowerCase().trim();
    const activeCategory = document.querySelector('.category-filter-btn.active')?.dataset.category || 'all';

    testCards.forEach(card => {
      const title = (card.dataset.title || '').toLowerCase();
      const code = (card.dataset.code || '').toLowerCase();
      const category = card.dataset.category || '';

      const matchesQuery = !query || title.includes(query) || code.includes(query);
      const matchesCategory = activeCategory === 'all' || category === activeCategory;

      if (matchesQuery && matchesCategory) {
        card.style.display = '';
      } else {
        card.style.display = 'none';
      }
    });
  }

  if (testSearchInput) {
    testSearchInput.addEventListener('input', filterTests);
  }

  categoryFilters.forEach(btn => {
    btn.addEventListener('click', function () {
      categoryFilters.forEach(b => b.classList.remove('active', 'btn-primary-lm'));
      categoryFilters.forEach(b => b.classList.add('btn-outline-secondary'));
      this.classList.remove('btn-outline-secondary');
      this.classList.add('active', 'btn-primary-lm');
      filterTests();
    });
  });

  // 2. Quick Test Booking Dynamic Multi-select & Total Calculation
  const testCheckboxes = document.querySelectorAll('.booking-test-checkbox');
  const bookingTotalElem = document.getElementById('bookingCalculatedTotal');
  const homeCollectionCheckbox = document.getElementById('isHomeCollectionCheckbox');
  const homeAddressField = document.getElementById('homeCollectionAddressWrapper');

  function updateBookingTotal() {
    let total = 0;
    let selectedCount = 0;

    testCheckboxes.forEach(cb => {
      if (cb.checked) {
        total += parseFloat(cb.dataset.price || '0');
        selectedCount++;
      }
    });

    if (bookingTotalElem) {
      bookingTotalElem.textContent = '₹ ' + total.toFixed(2);
    }

    const countElem = document.getElementById('selectedTestCount');
    if (countElem) {
      countElem.textContent = selectedCount;
    }
  }

  testCheckboxes.forEach(cb => {
    cb.addEventListener('change', updateBookingTotal);
  });

  if (homeCollectionCheckbox && homeAddressField) {
    homeCollectionCheckbox.addEventListener('change', function () {
      if (this.checked) {
        homeAddressField.classList.remove('d-none');
        const textarea = homeAddressField.querySelector('textarea');
        if (textarea) textarea.required = true;
      } else {
        homeAddressField.classList.add('d-none');
        const textarea = homeAddressField.querySelector('textarea');
        if (textarea) textarea.required = false;
      }
    });
  }

  // Initialize total if on booking page
  updateBookingTotal();
});
