(function (Drupal, once) {
  'use strict';
  Drupal.behaviors.romaElegante = {
    attach(context) {
      once('roma-menu', '.menu-toggle', context).forEach((button) => {
        const menu = document.getElementById(button.getAttribute('aria-controls'));
        const close = () => { button.setAttribute('aria-expanded', 'false'); menu.classList.remove('is-open'); };
        button.addEventListener('click', () => {
          const open = button.getAttribute('aria-expanded') !== 'true';
          button.setAttribute('aria-expanded', String(open));
          button.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
          menu.classList.toggle('is-open', open);
        });
        menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', close));
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
      });
      once('roma-planner', '#stay-planner', context).forEach((form) => {
        const arrival = form.querySelector('#arrival');
        const departure = form.querySelector('#departure');
        const guests = form.querySelector('#guests');
        const result = document.getElementById('planner-result');
        const day = 86400000;
        const limaToday = new Intl.DateTimeFormat('sv-SE', {timeZone: 'America/Lima'}).format(new Date());
        arrival.min = limaToday;
        departure.min = limaToday;
        const updateDates = () => {
          arrival.setCustomValidity('');
          departure.setCustomValidity('');
          if (arrival.value) departure.min = new Date(Date.parse(arrival.value + 'T00:00:00Z') + day).toISOString().slice(0, 10);
          if (arrival.value && departure.value && departure.value <= arrival.value) departure.setCustomValidity('La salida debe ser posterior a la llegada.');
          if (arrival.value && arrival.value < limaToday) arrival.setCustomValidity('Elige una llegada desde hoy.');
          result.hidden = true;
        };
        [arrival, departure, guests].forEach((field) => field.addEventListener('change', updateDates));
        form.addEventListener('submit', (event) => {
          event.preventDefault();
          updateDates();
          if (!form.reportValidity()) return;
          const target = new URL(form.action, window.location.origin);
          target.search = new URLSearchParams({arrival: arrival.value, departure: departure.value, guests: guests.value}).toString();
          window.location.assign(target.toString());
        });
      });
      once('roma-filter', '[data-room-filter]', context).forEach((button) => {
        button.addEventListener('click', () => {
          const filter = button.dataset.roomFilter;
          document.querySelectorAll('[data-room-filter]').forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
          let visible = 0;
          document.querySelectorAll('.room-card').forEach((card) => { card.hidden = filter !== 'all' && card.dataset.capacity !== filter; if (!card.hidden) visible++; });
          const status = document.querySelector('.room-filter-status');
          if (status) status.textContent = `${visible} ${visible === 1 ? 'habitación ilustrativa' : 'habitaciones ilustrativas'}.`;
        });
      });
      once('roma-details', '[data-room]', context).forEach((button) => {
        const dialog = document.getElementById('room-' + button.dataset.room);
        button.addEventListener('click', () => { dialog.showModal(); document.body.classList.add('dialog-open'); });
        dialog.querySelector('.dialog-close').addEventListener('click', () => dialog.close());
        dialog.querySelector('.dialog-plan').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (event) => { if (event.target === dialog) { const rect = dialog.getBoundingClientRect(); if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close(); } });
        dialog.addEventListener('close', () => document.body.classList.remove('dialog-open'));
      });

    }
  };
})(Drupal, once);

document.addEventListener('click', event => {
 const button=event.target.closest('#hotel-map-open');if(!button)return;
 const frame=document.createElement('iframe');frame.title='Ubicación del hotel';frame.src='https://www.google.com/maps?q='+encodeURIComponent(button.dataset.address)+'&output=embed';frame.loading='lazy';frame.referrerPolicy='no-referrer-when-downgrade';frame.setAttribute('allowfullscreen','');document.querySelector('#hotel-map-frame').replaceChildren(frame);button.hidden=true;
});
