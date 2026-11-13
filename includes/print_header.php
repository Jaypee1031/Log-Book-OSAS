<?php
/**
 * Official Paper Log Header & Signatories Component
 * Guidance Office Logbook Management System (OSAS)
 */

$print_settings = get_system_settings($pdo);
?>
<div class="print-header text-center pb-3 mb-4 border-b-2 border-black flex items-center justify-center gap-4">
  <img src="assets/images/qsulogo.png" alt="QSU Logo" class="h-16 w-auto object-contain">
  <div class="text-center">
    <div class="text-[10px] uppercase tracking-widest font-serif text-slate-700">Republic of the Philippines</div>
    <h1 class="text-base font-extrabold uppercase tracking-wide text-black mt-0.5"><?= e($print_settings['institution_name'] ?? 'Quirino State University - Diffun Campus') ?></h1>
    <h2 class="text-xs font-semibold uppercase tracking-wider text-black"><?= e($print_settings['office_name'] ?? 'Office of Student Affairs and Services (OSAS)') ?></h2>
    <h3 class="text-xs font-bold text-black"><?= e($print_settings['office_unit'] ?? 'Office of Guidance & Counseling Affairs') ?></h3>
    <div class="text-[10px] italic text-slate-600 mt-0.5"><?= e($print_settings['office_address'] ?? 'Diffun, Quirino') ?></div>
  </div>
</div>
