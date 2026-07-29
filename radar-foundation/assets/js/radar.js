(function () {
  'use strict';

  var boxes = Array.prototype.slice.call(document.querySelectorAll('[data-srf-select-entry]'));
  var status = document.querySelector('[data-srf-selection-status]');
  var openButton = document.querySelector('[data-srf-open-study]');
  var panel = document.querySelector('[data-srf-study-panel]');
  var field = document.querySelector('[data-srf-entry-ids]');
  var remedySelect = document.querySelector('[data-srf-remedy-select]');
  var remedyStatus = document.getElementById('srf-remedy-selection-status');

  if (remedySelect) {
    remedySelect.addEventListener('change', function (event) {
      var selected = Array.prototype.slice.call(remedySelect.selectedOptions);
      if (selected.length > 3) {
        event.target.options[event.target.selectedIndex].selected = false;
        selected = Array.prototype.slice.call(remedySelect.selectedOptions);
      }
      if (remedyStatus) {
        remedyStatus.textContent = selected.length ? selected.length + ' of 3 remedies selected.' : 'Select no more than three remedies.';
      }
    });
  }

  if (!boxes.length || !status || !openButton || !panel || !field) {
    return;
  }

  function selectedEntries() {
    return boxes.filter(function (box) { return box.checked; });
  }

  function update() {
    var chosen = selectedEntries();
    var limitReached = chosen.length >= 3;

    boxes.forEach(function (box) {
      box.disabled = limitReached && !box.checked;
    });

    status.textContent = chosen.length ? chosen.length + ' of 3 entries selected.' : 'Select up to three Radar entries.';
    openButton.disabled = chosen.length === 0;
    field.value = chosen.map(function (box) { return box.value; }).join(',');
  }

  boxes.forEach(function (box) {
    box.addEventListener('change', update);
  });

  openButton.addEventListener('click', function () {
    update();
    if (!field.value) {
      return;
    }
    panel.hidden = false;
    panel.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    var title = panel.querySelector('input[name="study_title"]');
    if (title) {
      title.focus({ preventScroll: true });
    }
  });

  update();
}());
