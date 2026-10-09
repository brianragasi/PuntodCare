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

  document.querySelectorAll('[data-confirm-remove]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirmRemove || 'Remove this reference photo?')) event.preventDefault();
    });
  });

  document.addEventListener('submit', (event) => {
    const submittedForm = event.target;
    if (!(submittedForm instanceof HTMLFormElement) || submittedForm.method.toLowerCase() !== 'post' || event.defaultPrevented) return;
    if (submittedForm.dataset.submitting === 'true') {
      event.preventDefault();
      return;
    }
    submittedForm.dataset.submitting = 'true';
    const button = event.submitter || submittedForm.querySelector('button[type="submit"]');
    if (!(button instanceof HTMLButtonElement)) return;
    const original = button.innerHTML;
    button.setAttribute('aria-busy', 'true');
    button.classList.add('is-submitting');
    button.textContent = 'Submitting…';
    const status = document.createElement('span');
    status.className = 'submit-feedback';
    status.setAttribute('role', 'status');
    status.textContent = 'Submitting…';
    button.insertAdjacentElement('afterend', status);
    const reset = () => {
      delete submittedForm.dataset.submitting;
      button.innerHTML = original;
      button.removeAttribute('aria-busy');
      button.classList.remove('is-submitting');
      status.textContent = 'No confirmation yet. Check the page before trying again.';
    };
    const onPageShow = (pageEvent) => { if (pageEvent.persisted) reset(); };
    window.addEventListener('pageshow', onPageShow);
    window.setTimeout(() => {
      window.removeEventListener('pageshow', onPageShow);
      if (submittedForm.isConnected && submittedForm.dataset.submitting === 'true') reset();
    }, 15000);
  });

  const locationButton = document.querySelector('[data-grave-location]');
  locationButton?.addEventListener('click', () => {
    const feedback = document.querySelector('[data-grave-location-feedback]');
    if (!navigator.geolocation) {
      if (feedback) feedback.textContent = 'Location is unavailable here. You can enter coordinates manually.';
      return;
    }
    locationButton.disabled = true;
    if (feedback) feedback.textContent = 'Asking your device for its current location…';
    navigator.geolocation.getCurrentPosition(
      (position) => {
        document.getElementById('grave-latitude').value = position.coords.latitude.toFixed(7);
        document.getElementById('grave-longitude').value = position.coords.longitude.toFixed(7);
        if (feedback) feedback.textContent = 'Coordinates added. Save the grave to keep this optional pin.';
        locationButton.disabled = false;
      },
      () => {
        if (feedback) feedback.textContent = 'Location was unavailable or declined. You can enter coordinates manually.';
        locationButton.disabled = false;
      },
      { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
    );
  });

  const servicePicker = document.querySelector('[data-request-service]');
  if (servicePicker) {
    const price = document.querySelector('[data-request-price]');
    const description = document.querySelector('[data-request-description]');
    const updateService = () => {
      const option = servicePicker.selectedOptions[0];
      price.textContent = option?.dataset.price || 'Choose a service';
      description.textContent = option?.dataset.description || 'See the work included before you submit.';
    };
    servicePicker.addEventListener('change', updateService);
    updateService();
  }

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
