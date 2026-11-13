/**
 * Application Frontend Interactions & Dynamic Forms
 * Guidance Office Logbook Management System (OSAS)
 */

document.addEventListener('DOMContentLoaded', function () {
  // 1. Mobile Sidebar Toggle
  const toggleBtn = document.getElementById('sidebar-toggle');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');

  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', function () {
      sidebar.classList.toggle('hidden-mobile');
      if (overlay) overlay.classList.toggle('hidden');
    });
  }

  if (overlay && sidebar) {
    overlay.addEventListener('click', function () {
      sidebar.classList.add('hidden-mobile');
      overlay.classList.add('hidden');
    });
  }

  // 2. Global Modal Helpers
  window.openModal = function (modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      document.body.classList.add('overflow-hidden');
    }
  };

  window.closeModal = function (modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      document.body.classList.remove('overflow-hidden');
    }
  };

  // Close modals when clicking backdrop
  document.querySelectorAll('.modal-backdrop-custom').forEach(backdrop => {
    backdrop.addEventListener('click', function (e) {
      if (e.target === this) {
        const modal = this.closest('.fixed');
        if (modal) {
          modal.classList.add('hidden');
          modal.classList.remove('flex');
          document.body.classList.remove('overflow-hidden');
        }
      }
    });
  });

  // Close modal on Escape
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-container:not(.hidden)').forEach(modal => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      });
      document.body.classList.remove('overflow-hidden');
    }
  });

  // 3. Confirm Delete / Archive Dialog Helper
  window.confirmAction = function (message, formOrUrl) {
    if (confirm(message)) {
      if (typeof formOrUrl === 'string') {
        window.location.href = formOrUrl;
      } else if (formOrUrl && formOrUrl.submit) {
        formOrUrl.submit();
      }
      return true;
    }
    return false;
  };

  // 4. Auto-dismiss flash alerts after 5 seconds
  const flashAlert = document.getElementById('flash-alert');
  if (flashAlert) {
    setTimeout(() => {
      flashAlert.style.transition = 'opacity 0.5s ease';
      flashAlert.style.opacity = '0';
      setTimeout(() => flashAlert.remove(), 500);
    }, 4500);
  }

  // 5. Purpose "Others" field conditional toggle
  const purposeSelect = document.getElementById('field_purpose');
  const purposeDetailsContainer = document.getElementById('purpose_details_wrapper');
  if (purposeSelect && purposeDetailsContainer) {
    const checkPurpose = () => {
      if (purposeSelect.value === 'Other' || purposeSelect.value === 'Others') {
        purposeDetailsContainer.classList.remove('hidden');
      } else {
        purposeDetailsContainer.classList.add('hidden');
      }
    };
    purposeSelect.addEventListener('change', checkPurpose);
    checkPurpose();
  }
});
