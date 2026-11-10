(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.romaFormFeedback = {
    attach(context) {
      once('roma-form-feedback', 'form[data-hotel-feedback]', context).forEach((form) => {
        // Server validation remains authoritative. Bring its feedback into view
        // after a full POST, including when the browser restores a scroll offset.
        const invalid = [...form.querySelectorAll('[aria-invalid="true"]')]
          .find((field) => !field.disabled && field.type !== 'hidden' && field.getClientRects().length);
        const target = invalid || document.querySelector('.messages--error') || form.querySelector('.hotel-receipt');
        if (!target) return;
        if (!invalid) target.setAttribute('tabindex', '-1');
        requestAnimationFrame(() => requestAnimationFrame(() => {
          target.focus({ preventScroll: true });
          target.scrollIntoView({ block: 'center', behavior: 'instant' });
        }));
      });
    },
  };
})(Drupal, once);
