(function () {
  'use strict';

  function ready(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
      callback();
    }
  }

  function normalizeHex(value) {
    if (!value) {
      return null;
    }
    var trimmed = String(value).trim();
    if (trimmed === '') {
      return null;
    }
    if (trimmed.charAt(0) === '#') {
      trimmed = trimmed.slice(1);
    }
    if (trimmed.length === 3) {
      trimmed = trimmed.split('').map(function (char) {
        return char + char;
      }).join('');
    }
    if (!/^([0-9a-fA-F]{6})$/.test(trimmed)) {
      return null;
    }
    return '#' + trimmed.toUpperCase();
  }

  function updatePreview(container) {
    if (!container) {
      return;
    }
    var preview = container.querySelector('[data-blog-customizer-preview]');
    var accentSelector = container.dataset.accentInput || '';
    var textSelector = container.dataset.textInput || '';
    var themeSelector = container.dataset.themeInput || '';
    var accentInput = accentSelector ? document.querySelector(accentSelector) : null;
    var textInput = textSelector ? document.querySelector(textSelector) : null;
    var themeInput = themeSelector ? document.querySelector(themeSelector) : null;

    var accent = accentInput && accentInput.value ? accentInput.value : '#2563EB';
    var textAccent = textInput && textInput.value ? textInput.value : '#0F172A';
    if (preview) {
      preview.style.setProperty('--blog-accent', accent);
      preview.style.setProperty('--blog-accent-text', textAccent);
    }

    if (themeInput) {
      container.setAttribute('data-theme', themeInput.value || '');
      var previousClass = container.dataset.themeClass;
      if (previousClass) {
        container.classList.remove(previousClass);
      }
      if (themeInput.value) {
        var nextClass = 'blog-theme-' + themeInput.value;
        container.classList.add(nextClass);
        container.dataset.themeClass = nextClass;
      } else {
        container.dataset.themeClass = '';
      }
    }
  }

  function setActive(buttons, active) {
    buttons.forEach(function (button) {
      button.classList.toggle('is-active', button === active);
    });
  }

  function attachThemePicker(picker) {
    var container = picker.closest('[data-blog-customizer]');
    if (!container) {
      return;
    }
    var themeSelector = container.dataset.themeInput || '';
    var themeInput = themeSelector ? document.querySelector(themeSelector) : null;
    var accentSelector = container.dataset.accentInput || '';
    var accentInput = accentSelector ? document.querySelector(accentSelector) : null;
    var textSelector = container.dataset.textInput || '';
    var textInput = textSelector ? document.querySelector(textSelector) : null;

    picker.addEventListener('click', function (event) {
      var button = event.target.closest('[data-theme-value]');
      if (!button) {
        return;
      }
      event.preventDefault();

      var theme = button.getAttribute('data-theme-value');
      if (themeInput) {
        themeInput.value = theme;
      }

      var themeAccent = normalizeHex(button.getAttribute('data-theme-accent'));
      var themeText = normalizeHex(button.getAttribute('data-theme-text'));

      if (accentInput && (!accentInput.value || accentInput.dataset.locked !== 'true')) {
        accentInput.value = themeAccent || accentInput.value;
      }
      if (textInput && (!textInput.value || textInput.dataset.locked !== 'true')) {
        textInput.value = themeText || textInput.value;
      }

      setActive(Array.prototype.slice.call(picker.querySelectorAll('[data-theme-value]')), button);
      updatePreview(container);
    });
  }

  function getThemeDefaults(container) {
    var themeButton = container ? container.querySelector('[data-blog-theme-picker] .blog-theme-option.is-active') : null;
    if (!themeButton) {
      return { accent: '#2563EB', text: '#0F172A' };
    }
    return {
      accent: normalizeHex(themeButton.getAttribute('data-theme-accent')) || '#2563EB',
      text: normalizeHex(themeButton.getAttribute('data-theme-text')) || '#0F172A'
    };
  }

  function attachColorPicker(picker) {
    var container = picker.closest('[data-blog-customizer]');
    var selector = picker.dataset.input || '';
    var input = selector ? document.querySelector(selector) : null;
    if (!container || !input) {
      return;
    }

    var buttons = Array.prototype.slice.call(picker.querySelectorAll('[data-color-value]'));
    buttons.forEach(function (button) {
      if (normalizeHex(button.getAttribute('data-color-value')) === input.value) {
        button.classList.add('is-active');
      }
    });

    var customPanel = picker.querySelector('[data-color-custom-panel]');
    var customInput = customPanel ? customPanel.querySelector('[data-color-custom-input]') : null;
    var customPicker = customPanel ? customPanel.querySelector('[data-color-custom-picker]') : null;
    var customError = customPanel ? customPanel.querySelector('[data-color-custom-error]') : null;
    var customPreview = customPanel ? customPanel.querySelector('[data-color-custom-preview]') : null;

    function hideCustomError() {
      if (customError) {
        customError.textContent = '';
        customError.hidden = true;
        customError.classList.remove('is-visible');
      }
      if (customInput) {
        customInput.classList.remove('is-invalid');
      }
    }

    function updateCustomPreview(value) {
      if (!customPreview) {
        return;
      }
      var normalized = normalizeHex(value);
      var color = normalized || '#E2E8F0';
      customPreview.style.setProperty('--custom-color', color);
    }

    function getRoleDefaultColor() {
      var defaults = getThemeDefaults(container);
      var role = picker.dataset.role || '';
      return role === 'text' ? defaults.text : defaults.accent;
    }

    function getCustomDefaultColor() {
      var defaultColor = normalizeHex(getRoleDefaultColor()) || '#2563EB';
      return defaultColor;
    }

    function syncCustomControls(value) {
      var normalized = normalizeHex(value);
      var color = normalized || getCustomDefaultColor();
      if (customInput) {
        customInput.value = normalized || (value || color);
      }
      if (customPicker) {
        customPicker.value = color.toLowerCase();
      }
      updateCustomPreview(color);
    }

    function closeCustomPanel() {
      if (!customPanel) {
        return;
      }
      customPanel.hidden = true;
      customPanel.classList.remove('is-visible');
      hideCustomError();
    }

    function openCustomPanel() {
      if (!customPanel) {
        return;
      }
      customPanel.hidden = false;
      customPanel.classList.add('is-visible');
      hideCustomError();
      var initial = input.value || '';
      syncCustomControls(initial);
      window.setTimeout(function () {
        if (customInput) {
          customInput.focus();
          if (typeof customInput.select === 'function') {
            customInput.select();
          }
        } else if (customPicker) {
          customPicker.focus();
        }
      }, 20);
    }

    function applyCustomColor() {
      var normalized = null;
      if (customInput) {
        normalized = normalizeHex(customInput.value || '');
      }
      if (!normalized && customPicker) {
        normalized = normalizeHex(customPicker.value || '');
        if (normalized && customInput) {
          customInput.value = normalized;
        }
      }
      if (!normalized) {
        if (customError) {
          customError.textContent = 'Ingresá un color válido en formato #RRGGBB.';
          customError.hidden = false;
          customError.classList.add('is-visible');
        }
        if (customInput) {
          customInput.classList.add('is-invalid');
          customInput.focus();
        }
        return false;
      }

      var defaultColor = normalizeHex(getRoleDefaultColor()) || '#2563EB';

      input.value = normalized;
      input.dataset.locked = normalized === defaultColor ? 'false' : 'true';
      buttons.forEach(function (button) {
        button.classList.remove('is-active');
      });
      closeCustomPanel();
      updatePreview(container);
      return true;
    }

    var defaults = getThemeDefaults(container);
    var role = picker.dataset.role || '';
    var defaultColor = normalizeHex(role === 'text' ? defaults.text : defaults.accent);
    if (normalizeHex(input.value) === defaultColor) {
      input.dataset.locked = 'false';
    } else if (input.value) {
      input.dataset.locked = 'true';
    }

    picker.addEventListener('click', function (event) {
      var target = event.target.closest('[data-color-value], [data-color-custom]');
      if (!target) {
        return;
      }
      event.preventDefault();

      defaults = getThemeDefaults(container);
      defaultColor = normalizeHex(role === 'text' ? defaults.text : defaults.accent);

      if (target.hasAttribute('data-color-custom')) {
        openCustomPanel();
        return;
      }

      closeCustomPanel();

      var color = normalizeHex(target.getAttribute('data-color-value'));
      if (!color) {
        return;
      }
      input.value = color;
      input.dataset.locked = color === defaultColor ? 'false' : 'true';
      buttons.forEach(function (button) {
        button.classList.toggle('is-active', button === target);
      });

      updatePreview(container);
    });

    if (customInput) {
      customInput.addEventListener('input', function () {
        hideCustomError();
        updateCustomPreview(customInput.value);
        var normalized = normalizeHex(customInput.value || '');
        if (customPicker && normalized) {
          customPicker.value = normalized.toLowerCase();
        }
      });

      customInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          applyCustomColor();
        } else if (event.key === 'Escape') {
          event.preventDefault();
          closeCustomPanel();
        }
      });
    }

    if (customPicker) {
      customPicker.addEventListener('input', function () {
        hideCustomError();
        var normalized = normalizeHex(customPicker.value || '');
        if (customInput && normalized) {
          customInput.value = normalized;
        }
        updateCustomPreview(customPicker.value);
      });
    }

    if (customPanel) {
      customPanel.addEventListener('click', function (event) {
        var actionButton = event.target.closest('[data-color-custom-action]');
        if (!actionButton) {
          return;
        }
        event.preventDefault();
        var action = actionButton.getAttribute('data-color-custom-action');
        if (action === 'cancel') {
          closeCustomPanel();
          return;
        }
        if (action === 'apply') {
          applyCustomColor();
        }
      });
    }
  }

  ready(function () {
    document.querySelectorAll('[data-blog-theme-picker]').forEach(attachThemePicker);
    document.querySelectorAll('[data-blog-color-picker]').forEach(attachColorPicker);

    document.querySelectorAll('[data-blog-customizer]').forEach(function (container) {
      updatePreview(container);
    });
  });
})();
