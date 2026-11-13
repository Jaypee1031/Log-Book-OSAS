<?php
/**
 * Global Footer Component
 * Guidance Office Logbook Management System (OSAS)
 */
?>
    </main>
  </div>

  <!-- Global Confirm Modal Dialog -->
  <div id="confirm-modal" class="fixed inset-0 z-50 hidden items-center justify-center modal-backdrop-custom modal-container">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden border border-slate-200 animate-in fade-in zoom-in duration-150">
      <div class="p-6 text-center">
        <div id="confirm-icon-box" class="w-14 h-14 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mx-auto mb-4 text-2xl">
          <i class="bi bi-exclamation-triangle-fill"></i>
        </div>
        <h3 id="confirm-title" class="text-lg font-bold text-slate-900 mb-2">Confirm Action</h3>
        <p id="confirm-message" class="text-sm text-slate-600 mb-6 leading-relaxed">Are you sure you want to proceed with this action?</p>
        
        <div class="flex items-center justify-center gap-3">
          <button type="button" onclick="closeModal('confirm-modal')" class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 font-medium hover:bg-slate-50 transition text-sm">
            Cancel
          </button>
          <a id="confirm-action-btn" href="#" class="px-5 py-2.5 rounded-xl bg-brand-900 hover:bg-brand-800 text-white font-medium transition text-sm shadow-sm">
            Confirm
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="assets/js/app.js"></script>
</body>
</html>
