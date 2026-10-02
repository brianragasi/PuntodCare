(() => {
  'use strict';

  const sidebar = document.getElementById('site-sidebar');
  const openButton = document.querySelector('[data-sidebar-open]');
  const closeButton = document.querySelector('[data-sidebar-close-button]');
  const scrim = document.querySelector('[data-sidebar-close]');
  let lastFocused = null;

  function setSidebar(open) {
    if (!sidebar || !openButton || !scrim) return;
    sidebar.classList.toggle('is-open', open);
    scrim.hidden = !open;
    document.body.classList.toggle('sidebar-open', open);
    openButton.setAttribute('aria-expanded', String(open));
    if (open) {
      lastFocused = document.activeElement;
      closeButton?.focus();
    } else if (lastFocused && typeof lastFocused.focus === 'function') {
      lastFocused.focus();
    }
  }

  openButton?.addEventListener('click', () => setSidebar(true));
  closeButton?.addEventListener('click', () => setSidebar(false));
  scrim?.addEventListener('click', () => setSidebar(false));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && sidebar?.classList.contains('is-open')) setSidebar(false);
  });
  window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => {
    if (event.matches && sidebar?.classList.contains('is-open')) setSidebar(false);
  });

  document.querySelectorAll('.review-form').forEach((form) => {
    const note = form.querySelector('textarea[name="note"]');
    form.addEventListener('submit', (event) => {
      const decision = event.submitter?.value;
      note.required = decision === 'reject' || decision === 'suspend';
      if (!form.reportValidity()) event.preventDefault();
    });
  });

  const toast = document.getElementById('preview-toast');
  let toastTimer;
  function showPreviewMessage(message) {
    if (!toast) return;
    toast.textContent = message;
    toast.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.hidden = true; }, 4500);
  }
  document.querySelectorAll('[data-preview-action]').forEach((button) => {
    button.addEventListener('click', () => showPreviewMessage('This is a design preview. This action will be connected in a later phase.'));
  });

  const form = document.getElementById('sample-form');
  if (!form) return;
  const fieldMessages = {
    'sample-name': 'Enter a name with at least 2 characters.',
    'sample-email': 'Enter a valid email address.',
    'sample-service': 'Choose a service to preview.'
  };
  const fields = Array.from(form.querySelectorAll('input, select'));
  function validateField(field) {
    const error = document.getElementById(`${field.id}-error`);
    const invalid = !field.checkValidity();
    field.setAttribute('aria-invalid', String(invalid));
    if (error) error.textContent = invalid ? fieldMessages[field.id] : '';
    return !invalid;
  }
  fields.forEach((field) => {
    field.addEventListener('input', () => {
      if (field.getAttribute('aria-invalid') === 'true') validateField(field);
    });
    field.addEventListener('change', () => {
      if (field.getAttribute('aria-invalid') === 'true') validateField(field);
    });
  });
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const valid = fields.map(validateField).every(Boolean);
    const result = document.getElementById('form-result');
    if (!result) return;
    if (!valid) {
      result.textContent = 'Please check the highlighted fields.';
      result.className = 'form-result is-error';
      fields.find((field) => field.getAttribute('aria-invalid') === 'true')?.focus();
      return;
    }
    result.textContent = 'The example form looks good. This preview does not save information.';
    result.className = 'form-result is-success';
    showPreviewMessage('Example checked. No information was saved.');
  });
})();
