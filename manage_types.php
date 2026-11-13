<?php
/**
 * Administrator Logbook Section & Dynamic Field Builder
 * Guidance Office Logbook Management System (OSAS)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_admin();
$page_title = 'Logbook Section Builder';

// Handle New Logbook Type Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_type') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Security token expired. Please try again.');
        header('Location: manage_types.php');
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'bi-journal-text');
    $color_theme = trim($_POST['color_theme'] ?? 'blue');

    if (empty($slug)) {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
    }
    $slug = trim($slug, '-');

    if (empty($title) || empty($slug)) {
        set_flash('danger', 'Section Title and Slug are required.');
        header('Location: manage_types.php');
        exit;
    }

    // Check slug uniqueness
    $check = $pdo->prepare("SELECT id FROM `logbook_types` WHERE `slug` = ?");
    $check->execute([$slug]);
    if ($check->fetch()) {
        set_flash('danger', "A logbook section with slug '{$slug}' already exists.");
        header('Location: manage_types.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $max_order = (int) $pdo->query("SELECT COALESCE(MAX(display_order), 0) + 1 FROM `logbook_types`")->fetchColumn();

        $stmt = $pdo->prepare("INSERT INTO `logbook_types` (`slug`, `title`, `description`, `icon`, `color_theme`, `is_system`, `display_order`, `is_active`) VALUES (?, ?, ?, ?, ?, 0, ?, 1)");
        $stmt->execute([$slug, $title, $description, $icon, $color_theme, $max_order]);
        $new_type_id = $pdo->lastInsertId();

        $field_stmt = $pdo->prepare("INSERT INTO `logbook_fields` 
            (`logbook_type_id`, `field_key`, `field_label`, `field_type`, `options_json`, `is_required`, `show_in_table`, `show_in_filter`, `display_order`, `placeholder`, `help_text`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        // 1. Mandatory base fields
        $field_stmt->execute([$new_type_id, 'date', 'Date', 'date', null, 1, 1, 1, 1, '', 'Entry date']);
        $field_stmt->execute([$new_type_id, 'name', 'Client Name', 'text', null, 1, 1, 1, 2, 'Full Name', 'Name of individual']);

        // 2. Custom fields defined in the form
        $custom_fields = $_POST['custom_fields'] ?? [];
        $order = 3;

        foreach ($custom_fields as $cf) {
            $f_label = trim($cf['label'] ?? '');
            if (empty($f_label)) continue;

            $f_key = trim($cf['key'] ?? '');
            if (empty($f_key)) {
                $f_key = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $f_label));
            }
            $f_key = trim($f_key, '_');

            $f_type = $cf['type'] ?? 'text';
            $f_required = isset($cf['required']) ? 1 : 0;
            $f_show_table = isset($cf['show_table']) ? 1 : 0;
            $f_show_filter = isset($cf['show_filter']) ? 1 : 0;
            $f_placeholder = trim($cf['placeholder'] ?? '');
            $f_help = trim($cf['help'] ?? '');

            // Process dropdown options
            $options_json = null;
            if ($f_type === 'select' || $f_type === 'multiselect') {
                $raw_options = explode(',', $cf['options'] ?? '');
                $clean_options = [];
                foreach ($raw_options as $ro) {
                    $item = trim($ro);
                    if ($item !== '') $clean_options[] = $item;
                }
                $options_json = json_encode($clean_options, JSON_UNESCAPED_UNICODE);
            }

            $field_stmt->execute([
                $new_type_id, $f_key, $f_label, $f_type, $options_json, $f_required, $f_show_table, $f_show_filter, $order, $f_placeholder, $f_help
            ]);
            $order++;
        }

        $pdo->commit();
        log_audit($pdo, 'CREATE_LOGBOOK_TYPE', (int)$new_type_id, null, "Created new custom logbook type: {$title} ({$slug})");
        set_flash('success', "New logbook section '{$title}' created successfully!");
        header("Location: logbook.php?type=" . urlencode($slug));
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('danger', "Error creating logbook section: " . $e->getMessage());
        header('Location: manage_types.php');
        exit;
    }
}

// Handle Delete of Custom Logbook Type
if (($_GET['action'] ?? '') === 'delete_type') {
    if (!verify_csrf($_GET['token'] ?? '')) {
        set_flash('danger', 'Security token invalid.');
        header('Location: manage_types.php');
        exit;
    }

    $type_id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM `logbook_types` WHERE `id` = ?");
    $stmt->execute([$type_id]);
    $t = $stmt->fetch();

    if ($t) {
        if ($t['is_system']) {
            set_flash('danger', "Core system logbooks cannot be deleted.");
        } else {
            $pdo->prepare("DELETE FROM `logbook_types` WHERE `id` = ?")->execute([$type_id]);
            log_audit($pdo, 'DELETE_LOGBOOK_TYPE', $type_id, null, "Deleted custom logbook section: {$t['title']}");
            set_flash('info', "Custom logbook section '{$t['title']}' deleted.");
        }
    }
    header('Location: manage_types.php');
    exit;
}

$all_types = get_logbook_types($pdo, false);
require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">

  <!-- Header Banner -->
  <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Logbook Section & Field Builder</h1>
      <p class="text-xs sm:text-sm text-slate-500 mt-1">Configure new guidance office logbooks with customized fields, validation, and options without modifying code.</p>
    </div>
    <a href="#new-section-form" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-[#006633] hover:bg-[#004422] text-white font-semibold text-xs rounded-xl shadow-sm transition">
      <i class="bi bi-plus-circle"></i> Create New Section
    </a>
  </div>

  <!-- Existing Logbook Sections Grid -->
  <div>
    <h2 class="text-base font-bold text-slate-900 mb-3">Existing Logbook Sections</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach ($all_types as $lt): 
        $fields_count = (int) $pdo->query("SELECT COUNT(*) FROM `logbook_fields` WHERE `logbook_type_id` = {$lt['id']}")->fetchColumn();
      ?>
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between stat-card">
          <div>
            <div class="flex items-center justify-between mb-3">
              <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-lg">
                <i class="bi <?= e($lt['icon']) ?>"></i>
              </div>
              <?php if ($lt['is_system']): ?>
                <span class="badge-pill bg-slate-100 text-slate-600 text-xs">Core Section</span>
              <?php else: ?>
                <span class="badge-pill bg-purple-50 text-purple-700 border border-purple-200 text-xs font-semibold">Custom Section</span>
              <?php endif; ?>
            </div>

            <h3 class="text-base font-bold text-slate-900"><?= e($lt['title']) ?></h3>
            <p class="text-xs text-slate-500 mt-1 line-clamp-2"><?= e($lt['description'] ?: 'No description provided.') ?></p>

            <div class="mt-4 flex items-center gap-3 text-xs text-slate-500">
              <span class="flex items-center gap-1">
                <i class="bi bi-ui-checks-grid text-slate-400"></i>
                <strong><?= $fields_count ?></strong> fields
              </span>
              <span>•</span>
              <span class="flex items-center gap-1">
                <i class="bi bi-journal-text text-slate-400"></i>
                <strong><?= (int)$lt['active_count'] ?></strong> records
              </span>
            </div>
          </div>

          <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between">
            <a href="logbook.php?type=<?= urlencode($lt['slug']) ?>" class="text-xs text-brand-700 hover:text-brand-900 font-semibold flex items-center gap-1">
              <span>Open Logbook</span>
              <i class="bi bi-arrow-right"></i>
            </a>

            <?php if (!$lt['is_system']): ?>
              <a href="manage_types.php?action=delete_type&id=<?= $lt['id'] ?>&token=<?= csrf_token() ?>" onclick="return confirm('Delete custom logbook section \'<?= e($lt['title']) ?>\' and all associated records?')" class="text-xs text-rose-500 hover:text-rose-700 font-medium">
                <i class="bi bi-trash"></i> Delete
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Create New Logbook Section Form -->
  <div id="new-section-form" class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-sm space-y-6">
    <div class="border-b border-slate-200 pb-4">
      <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
        <i class="bi bi-plus-square-fill text-brand-900"></i>
        <span>Create Additional Logbook Section</span>
      </h2>
      <p class="text-xs text-slate-500 mt-1">Design a brand new logbook sheet with your desired fields and validation rules.</p>
    </div>

    <form method="POST" action="manage_types.php" class="space-y-6">
      <input type="hidden" name="action" value="create_type">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <!-- Section Basic Details -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">
            Section Title <span class="text-rose-500">*</span>
          </label>
          <input type="text" name="title" id="sec_title" required placeholder="e.g. Counseling Intake Log" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 text-slate-800">
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">
            URL Slug / Identifier
          </label>
          <input type="text" name="slug" id="sec_slug" placeholder="e.g. counseling-intake" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 text-slate-800">
          <span class="text-[10px] text-slate-400">Leave blank to auto-generate from title.</span>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Section Icon</label>
          <select name="icon" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 text-slate-800 bg-white">
            <option value="bi-journal-check">Journal Check (bi-journal-check)</option>
            <option value="bi-person-badge">Person Badge (bi-person-badge)</option>
            <option value="bi-shield-check">Shield Check (bi-shield-check)</option>
            <option value="bi-mortarboard">Mortarboard / Academic (bi-mortarboard)</option>
            <option value="bi-folder-symlink">Folder Referral (bi-folder-symlink)</option>
            <option value="bi-heart-pulse">Wellness / Pulse (bi-heart-pulse)</option>
            <option value="bi-clipboard-data">Clipboard Data (bi-clipboard-data)</option>
            <option value="bi-chat-square-dots">Consultation Chat (bi-chat-square-dots)</option>
          </select>
        </div>

        <div class="sm:col-span-2 lg:col-span-3">
          <label class="block text-xs font-semibold text-slate-700 mb-1">Description / Purpose</label>
          <textarea name="description" rows="2" placeholder="Brief explanation of what this logbook tracks..." class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 text-slate-800"></textarea>
        </div>

      </div>

      <!-- Configurable Dynamic Fields Builder -->
      <div class="border-t border-slate-200 pt-6">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h3 class="text-sm font-bold text-slate-900">Custom Logbook Fields</h3>
            <p class="text-xs text-slate-500">Note: <strong>Date</strong> and <strong>Client Name</strong> are automatically created for all logbooks.</p>
          </div>
          <button type="button" id="btn-add-field" class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold rounded-lg transition flex items-center gap-1.5">
            <i class="bi bi-plus-circle-fill text-brand-900"></i> Add Field
          </button>
        </div>

        <!-- Dynamic Fields Container -->
        <div id="fields-container" class="space-y-3">
          <!-- Initial 2 pre-configured custom fields as templates -->
          <div class="field-row bg-slate-50 p-4 rounded-xl border border-slate-200 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-start">
            <div class="lg:col-span-3">
              <label class="block text-[11px] font-semibold text-slate-600 mb-1">Field Label *</label>
              <input type="text" name="custom_fields[0][label]" value="Classification / Type" required class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500">
            </div>
            <div class="lg:col-span-2">
              <label class="block text-[11px] font-semibold text-slate-600 mb-1">Field Type</label>
              <select name="custom_fields[0][type]" class="field-type-select w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 bg-white">
                <option value="select" selected>Select Dropdown</option>
                <option value="multiselect">Multi-Select Dropdown</option>
                <option value="text">Text</option>
                <option value="number">Number</option>
                <option value="date">Date</option>
                <option value="time">Time</option>
                <option value="signature_confirm">Signature / Checkbox</option>
                <option value="textarea">Textarea / Notes</option>
              </select>
            </div>
            <div class="lg:col-span-4 options-container">
              <label class="block text-[11px] font-semibold text-slate-600 mb-1">Dropdown Options (Comma separated)</label>
              <input type="text" name="custom_fields[0][options]" value="Student, Faculty, Staff, Parent" placeholder="Option 1, Option 2, Option 3" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500">
            </div>
            <div class="lg:col-span-2 flex items-center gap-3 pt-5">
              <label class="flex items-center gap-1 text-xs text-slate-700 cursor-pointer">
                <input type="checkbox" name="custom_fields[0][required]" value="1" checked class="rounded text-brand-900"> Required
              </label>
              <label class="flex items-center gap-1 text-xs text-slate-700 cursor-pointer">
                <input type="checkbox" name="custom_fields[0][show_table]" value="1" checked class="rounded text-brand-900"> Table
              </label>
            </div>
            <div class="lg:col-span-1 pt-4 text-right">
              <button type="button" class="btn-remove-field p-1.5 text-slate-400 hover:text-rose-600" title="Remove Field">&times;</button>
            </div>
          </div>

          <div class="field-row bg-slate-50 p-4 rounded-xl border border-slate-200 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-start">
            <div class="lg:col-span-3">
              <label class="block text-[11px] font-semibold text-slate-600 mb-1">Field Label *</label>
              <input type="text" name="custom_fields[1][label]" value="Details / Remarks" required class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500">
            </div>
            <div class="lg:col-span-2">
              <label class="block text-[11px] font-semibold text-slate-600 mb-1">Field Type</label>
              <select name="custom_fields[1][type]" class="field-type-select w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 bg-white">
                <option value="text">Text</option>
                <option value="textarea" selected>Textarea / Notes</option>
                <option value="select">Select Dropdown</option>
                <option value="multiselect">Multi-Select Dropdown</option>
                <option value="number">Number</option>
                <option value="date">Date</option>
                <option value="time">Time</option>
                <option value="signature_confirm">Signature / Checkbox</option>
              </select>
            </div>
            <div class="lg:col-span-4 options-container hidden">
              <label class="block text-[11px] font-semibold text-slate-600 mb-1">Dropdown Options (Comma separated)</label>
              <input type="text" name="custom_fields[1][options]" placeholder="Option 1, Option 2, Option 3" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500">
            </div>
            <div class="lg:col-span-2 flex items-center gap-3 pt-5">
              <label class="flex items-center gap-1 text-xs text-slate-700 cursor-pointer">
                <input type="checkbox" name="custom_fields[1][required]" value="1" class="rounded text-brand-900"> Required
              </label>
              <label class="flex items-center gap-1 text-xs text-slate-700 cursor-pointer">
                <input type="checkbox" name="custom_fields[1][show_table]" value="1" checked class="rounded text-brand-900"> Table
              </label>
            </div>
            <div class="lg:col-span-1 pt-4 text-right">
              <button type="button" class="btn-remove-field p-1.5 text-slate-400 hover:text-rose-600" title="Remove Field">&times;</button>
            </div>
          </div>
        </div>

      </div>

      <!-- Submit Section -->
      <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-3">
        <a href="dashboard.php" class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 font-medium hover:bg-slate-50 transition text-xs">
          Cancel
        </a>
        <button type="submit" class="px-6 py-2.5 rounded-xl bg-[#006633] hover:bg-[#004422] text-white font-bold text-xs shadow-sm transition">
          Create & Activate Section
        </button>
      </div>

    </form>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  let fieldIndex = 2;
  const container = document.getElementById('fields-container');
  const addBtn = document.getElementById('btn-add-field');

  // Slug auto-generation from title
  const titleInput = document.getElementById('sec_title');
  const slugInput = document.getElementById('sec_slug');
  if (titleInput && slugInput) {
    titleInput.addEventListener('input', function() {
      if (!slugInput.dataset.manual) {
        slugInput.value = this.value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
      }
    });
    slugInput.addEventListener('input', function() {
      slugInput.dataset.manual = 'true';
    });
  }

  // Toggle options input when type changes
  function attachTypeChange(row) {
    const select = row.querySelector('.field-type-select');
    const optBox = row.querySelector('.options-container');
    if (select && optBox) {
      select.addEventListener('change', function () {
        if (this.value === 'select' || this.value === 'multiselect') {
          optBox.classList.remove('hidden');
        } else {
          optBox.classList.add('hidden');
        }
      });
    }

    const removeBtn = row.querySelector('.btn-remove-field');
    if (removeBtn) {
      removeBtn.addEventListener('click', function () {
        if (container.querySelectorAll('.field-row').length > 1) {
          row.remove();
        } else {
          alert('Logbook section must have at least one field.');
        }
      });
    }
  }

  // Attach to existing rows
  container.querySelectorAll('.field-row').forEach(attachTypeChange);

  // Add field button
  addBtn.addEventListener('click', function () {
    const newRow = document.createElement('div');
    newRow.className = 'field-row bg-slate-50 p-4 rounded-xl border border-slate-200 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-start animate-in fade-in duration-150';
    newRow.innerHTML = `
      <div class="lg:col-span-3">
        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Field Label *</label>
        <input type="text" name="custom_fields[${fieldIndex}][label]" required placeholder="e.g. Assigned Counselor" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500">
      </div>
      <div class="lg:col-span-2">
        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Field Type</label>
        <select name="custom_fields[${fieldIndex}][type]" class="field-type-select w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 bg-white">
          <option value="text">Text</option>
          <option value="select">Select Dropdown</option>
          <option value="multiselect">Multi-Select Dropdown</option>
          <option value="number">Number</option>
          <option value="date">Date</option>
          <option value="time">Time</option>
          <option value="signature_confirm">Signature / Checkbox</option>
          <option value="textarea">Textarea / Notes</option>
        </select>
      </div>
      <div class="lg:col-span-4 options-container hidden">
        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Dropdown Options (Comma separated)</label>
        <input type="text" name="custom_fields[${fieldIndex}][options]" placeholder="Option 1, Option 2, Option 3" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500">
      </div>
      <div class="lg:col-span-2 flex items-center gap-3 pt-5">
        <label class="flex items-center gap-1 text-xs text-slate-700 cursor-pointer">
          <input type="checkbox" name="custom_fields[${fieldIndex}][required]" value="1" class="rounded text-brand-900"> Required
        </label>
        <label class="flex items-center gap-1 text-xs text-slate-700 cursor-pointer">
          <input type="checkbox" name="custom_fields[${fieldIndex}][show_table]" value="1" checked class="rounded text-brand-900"> Table
        </label>
      </div>
      <div class="lg:col-span-1 pt-4 text-right">
        <button type="button" class="btn-remove-field p-1.5 text-slate-400 hover:text-rose-600 text-lg leading-none" title="Remove Field">&times;</button>
      </div>
    `;
    container.appendChild(newRow);
    attachTypeChange(newRow);
    fieldIndex++;
  });

});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
