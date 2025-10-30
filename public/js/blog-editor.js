(function () {
  'use strict';

  function ready(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
      callback();
    }
  }

  function isEditableRange(range, canvas) {
    return range && canvas.contains(range.commonAncestorContainer);
  }

  function normalizeColor(value) {
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

  function Editor(wrapper) {
    this.wrapper = wrapper;
    this.canvas = wrapper.querySelector('.blog-editor-canvas');
    this.targetId = wrapper.getAttribute('data-target');
    this.textarea = this.targetId ? document.getElementById(this.targetId) : null;
    this.selection = null;
    this.overlay = null;
    this.dialogContainer = null;
    this.dialogs = {};
    this.activeDialog = null;
    this.boundHandleKeydown = this.handleKeydown.bind(this);

    if (!this.canvas || !this.textarea) {
      return;
    }

    this.bootstrap();
  }

  Editor.prototype.bootstrap = function () {
    var initial = this.textarea.value || '';
    if (initial.trim() === '') {
      this.canvas.innerHTML = '<p></p>';
    } else {
      this.canvas.innerHTML = initial;
    }

    this.textarea.setAttribute('hidden', 'hidden');
    this.textarea.style.display = 'none';
    this.wrapper.classList.add('is-ready');

    this.overlay = this.wrapper.querySelector('[data-editor-overlay]');
    this.dialogContainer = this.wrapper.querySelector('[data-blog-editor-dialogs]');
    if (this.dialogContainer) {
      var dialogs = this.dialogContainer.querySelectorAll('[data-editor-dialog]');
      for (var i = 0; i < dialogs.length; i += 1) {
        var type = dialogs[i].getAttribute('data-editor-dialog');
        if (type) {
          this.dialogs[type] = dialogs[i];
        }
      }
    }

    this.textarea.value = this.prepareContent(this.canvas.innerHTML);
    this.registerEvents();
  };

  Editor.prototype.registerEvents = function () {
    var self = this;

    this.canvas.addEventListener('focus', function () {
      self.saveSelection();
    });
    this.canvas.addEventListener('keyup', function () {
      self.saveSelection();
    });
    this.canvas.addEventListener('mouseup', function () {
      self.saveSelection();
    });
    this.canvas.addEventListener('input', function () {
      self.textarea.value = self.prepareContent(self.canvas.innerHTML);
    });

    this.wrapper.addEventListener('click', function (event) {
      var button = event.target.closest('[data-command], [data-format-block], [data-action]');
      if (!button) {
        return;
      }
      event.preventDefault();
      self.canvas.focus();
      self.restoreSelection();

      var command = button.getAttribute('data-command');
      if (command) {
        document.execCommand(command, false, null);
        self.saveSelection();
        return;
      }

      var block = button.getAttribute('data-format-block');
      if (block) {
        document.execCommand('formatBlock', false, block.toUpperCase());
        self.saveSelection();
        return;
      }

      var action = button.getAttribute('data-action');
      if (!action) {
        return;
      }

      switch (action) {
        case 'createLink':
          self.handleCreateLink();
          break;
        case 'insertImage':
          self.handleInsertImage();
          break;
        case 'insertBox':
          self.handleInsertBox();
          break;
        default:
          break;
      }
    });

    this.wrapper.querySelectorAll('.blog-editor-palette[data-color-command]').forEach(function (palette) {
      var customPanel = palette.querySelector('[data-editor-color-panel]');
      var customInput = customPanel ? customPanel.querySelector('[data-editor-color-input]') : null;
      var customError = customPanel ? customPanel.querySelector('[data-editor-color-error]') : null;
      var customPreview = customPanel ? customPanel.querySelector('[data-editor-color-preview]') : null;

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
        var normalized = normalizeColor(value);
        var color = normalized || '#E2E8F0';
        customPreview.style.setProperty('--editor-custom-color', color);
      }

      function closeCustomPanel() {
        if (!customPanel) {
          return;
        }
        customPanel.hidden = true;
        customPanel.classList.remove('is-active');
        hideCustomError();
      }

      function openCustomPanel(initial) {
        if (!customPanel) {
          return;
        }
        customPanel.hidden = false;
        customPanel.classList.add('is-active');
        hideCustomError();
        var value = initial || palette.getAttribute('data-active-color') || '';
        if (customInput) {
          customInput.value = value;
          updateCustomPreview(value);
          window.setTimeout(function () {
            customInput.focus();
            if (typeof customInput.select === 'function') {
              customInput.select();
            }
          }, 20);
        } else {
          updateCustomPreview(value);
        }
      }

      function applyCustomColor() {
        if (!customInput) {
          return null;
        }
        var normalized = normalizeColor(customInput.value || '');
        if (!normalized) {
          if (customError) {
            customError.textContent = 'Ingresá un color válido en formato #RRGGBB.';
            customError.hidden = false;
            customError.classList.add('is-visible');
          }
          customInput.classList.add('is-invalid');
          customInput.focus();
          return null;
        }
        closeCustomPanel();
        return normalized;
      }

      palette.addEventListener('click', function (event) {
        var swatch = event.target.closest('[data-color-value], [data-color-custom]');
        if (!swatch) {
          return;
        }
        event.preventDefault();
        var cmd = palette.getAttribute('data-color-command');
        if (!cmd) {
          return;
        }

        if (swatch.hasAttribute('data-color-custom')) {
          openCustomPanel(palette.getAttribute('data-active-color') || '#');
          return;
        }

        closeCustomPanel();
        self.restoreSelection();
        self.canvas.focus();

        var value = normalizeColor(swatch.getAttribute('data-color-value'));
        if (!value) {
          return;
        }

        palette.querySelectorAll('[data-color-value]').forEach(function (button) {
          button.classList.toggle('is-active', button === swatch);
        });
        palette.setAttribute('data-active-color', value);

        if (cmd === 'hiliteColor' && !document.queryCommandSupported('hiliteColor')) {
          cmd = 'backColor';
        }
        document.execCommand(cmd, false, value);
        self.saveSelection();
      });

      if (customInput) {
        customInput.addEventListener('input', function () {
          hideCustomError();
          updateCustomPreview(customInput.value);
        });

        customInput.addEventListener('keydown', function (event) {
          if (event.key === 'Enter') {
            event.preventDefault();
            var value = applyCustomColor();
            if (!value) {
              return;
            }
            palette.querySelectorAll('[data-color-value]').forEach(function (button) {
              button.classList.remove('is-active');
            });
            palette.setAttribute('data-active-color', value);
            var command = palette.getAttribute('data-color-command');
            if (!command) {
              return;
            }
            if (command === 'hiliteColor' && !document.queryCommandSupported('hiliteColor')) {
              command = 'backColor';
            }
            self.restoreSelection();
            self.canvas.focus();
            document.execCommand(command, false, value);
            self.saveSelection();
          } else if (event.key === 'Escape') {
            event.preventDefault();
            closeCustomPanel();
          }
        });
      }

      if (customPanel) {
        customPanel.addEventListener('click', function (event) {
          var actionButton = event.target.closest('[data-editor-color-action]');
          if (!actionButton) {
            return;
          }
          event.preventDefault();
          var action = actionButton.getAttribute('data-editor-color-action');
          if (action === 'cancel') {
            closeCustomPanel();
            return;
          }
          if (action === 'apply') {
            var value = applyCustomColor();
            if (!value) {
              return;
            }
            palette.querySelectorAll('[data-color-value]').forEach(function (button) {
              button.classList.remove('is-active');
            });
            palette.setAttribute('data-active-color', value);
            var command = palette.getAttribute('data-color-command');
            if (!command) {
              return;
            }
            if (command === 'hiliteColor' && !document.queryCommandSupported('hiliteColor')) {
              command = 'backColor';
            }
            self.restoreSelection();
            self.canvas.focus();
            document.execCommand(command, false, value);
            self.saveSelection();
          }
        });
      }
    });

    if (this.textarea.form) {
      this.textarea.form.addEventListener('submit', function () {
        self.textarea.value = self.prepareContent(self.canvas.innerHTML);
      });
    }

    this.registerDialogEvents();
  };

  Editor.prototype.registerDialogEvents = function () {
    var self = this;

    if (this.overlay) {
      this.overlay.addEventListener('click', function () {
        self.closeDialog();
        self.canvas.focus();
      });
    }

    if (this.dialogContainer) {
      this.dialogContainer.addEventListener('click', function (event) {
        var actionButton = event.target.closest('[data-dialog-action]');
        if (actionButton) {
          event.preventDefault();
          var action = actionButton.getAttribute('data-dialog-action');
          if (action === 'cancel') {
            self.closeDialog();
            self.canvas.focus();
            return;
          }
          if (action === 'submit') {
            self.handleDialogSubmit();
            return;
          }
        }

        var boxButton = event.target.closest('[data-box-value]');
        if (boxButton && self.dialogs.box) {
          event.preventDefault();
          var value = boxButton.getAttribute('data-box-value');
          self.setActiveBoxChoice(self.dialogs.box, value || 'info');
        }
      });
    }

    var dialogKeys = Object.keys(this.dialogs);
    for (var i = 0; i < dialogKeys.length; i += 1) {
      var dialog = this.dialogs[dialogKeys[i]];
      if (!dialog) {
        continue;
      }
      dialog.querySelectorAll('[data-dialog-input]').forEach(function (input) {
        input.addEventListener('input', function () {
          input.classList.remove('is-invalid');
          self.hideDialogError(dialog);
        });
      });

      dialog.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey && !event.ctrlKey && !event.metaKey) {
          event.preventDefault();
          self.handleDialogSubmit();
        }
      });
    }

    if (this.dialogs.box) {
      this.setActiveBoxChoice(this.dialogs.box, 'info');
    }
  };

  Editor.prototype.openDialog = function (type) {
    var dialog = this.dialogs[type];
    if (!dialog) {
      return;
    }

    this.closeDialog();
    this.activeDialog = type;

    if (this.overlay) {
      this.overlay.hidden = false;
    }
    if (this.dialogContainer) {
      this.dialogContainer.classList.add('is-active');
    }
    this.wrapper.classList.add('has-dialog');

    dialog.hidden = false;
    dialog.classList.add('is-active');
    this.clearDialogError(dialog);

    document.addEventListener('keydown', this.boundHandleKeydown);

    var focusTarget = dialog.querySelector('[data-dialog-focus]') || dialog.querySelector('[data-dialog-input]');
    if (focusTarget) {
      window.setTimeout(function () {
        focusTarget.focus();
        if (typeof focusTarget.select === 'function') {
          focusTarget.select();
        }
      }, 20);
    }
  };

  Editor.prototype.closeDialog = function () {
    var keys = Object.keys(this.dialogs);
    for (var i = 0; i < keys.length; i += 1) {
      var dialog = this.dialogs[keys[i]];
      if (dialog) {
        dialog.hidden = true;
        dialog.classList.remove('is-active');
      }
    }

    if (this.overlay) {
      this.overlay.hidden = true;
    }
    if (this.dialogContainer) {
      this.dialogContainer.classList.remove('is-active');
    }
    this.wrapper.classList.remove('has-dialog');
    this.activeDialog = null;
    document.removeEventListener('keydown', this.boundHandleKeydown);
  };

  Editor.prototype.handleKeydown = function (event) {
    if (event.key === 'Escape' && this.activeDialog) {
      event.preventDefault();
      this.closeDialog();
      this.canvas.focus();
    }
  };

  Editor.prototype.handleDialogSubmit = function () {
    if (!this.activeDialog) {
      return;
    }
    var dialog = this.dialogs[this.activeDialog];
    if (!dialog) {
      return;
    }

    var handler;
    switch (this.activeDialog) {
      case 'link':
        handler = this.submitLinkDialog;
        break;
      case 'image':
        handler = this.submitImageDialog;
        break;
      case 'box':
        handler = this.submitBoxDialog;
        break;
      default:
        handler = null;
        break;
    }

    if (typeof handler !== 'function') {
      this.closeDialog();
      return;
    }

    this.canvas.focus();
    this.restoreSelection();
    this.clearDialogError(dialog);
    var result = handler.call(this, dialog);
    if (result !== false) {
      this.closeDialog();
      this.saveSelection();
    }
  };

  Editor.prototype.getDialogInput = function (dialog, name) {
    if (!dialog) {
      return null;
    }
    return dialog.querySelector('[data-dialog-input="' + name + '"]');
  };

  Editor.prototype.getDialogInputValue = function (dialog, name) {
    var input = this.getDialogInput(dialog, name);
    if (!input) {
      return '';
    }
    return input.value ? input.value.trim() : '';
  };

  Editor.prototype.setDialogInputValue = function (dialog, name, value) {
    var input = this.getDialogInput(dialog, name);
    if (!input) {
      return;
    }
    input.value = value || '';
  };

  Editor.prototype.markDialogInputInvalid = function (dialog, name) {
    var input = this.getDialogInput(dialog, name);
    if (input) {
      input.classList.add('is-invalid');
      if (typeof input.focus === 'function') {
        input.focus();
      }
    }
  };

  Editor.prototype.showDialogError = function (dialog, message) {
    if (!dialog) {
      return;
    }
    var error = dialog.querySelector('[data-dialog-error]');
    if (!error) {
      return;
    }
    error.textContent = message;
    error.hidden = false;
    error.classList.add('is-visible');
  };

  Editor.prototype.hideDialogError = function (dialog) {
    if (!dialog) {
      return;
    }
    var error = dialog.querySelector('[data-dialog-error]');
    if (error) {
      error.textContent = '';
      error.hidden = true;
      error.classList.remove('is-visible');
    }
  };

  Editor.prototype.clearDialogError = function (dialog) {
    if (!dialog) {
      return;
    }
    this.hideDialogError(dialog);
    dialog.querySelectorAll('[data-dialog-input]').forEach(function (input) {
      input.classList.remove('is-invalid');
    });
  };

  Editor.prototype.submitLinkDialog = function (dialog) {
    var url = this.getDialogInputValue(dialog, 'url');
    if (!url) {
      this.markDialogInputInvalid(dialog, 'url');
      this.showDialogError(dialog, 'Ingresá una URL válida.');
      return false;
    }

    if (!/^https?:\/\//i.test(url) && !/^mailto:/i.test(url)) {
      url = 'https://' + url.replace(/^\/*/, '');
    }

    var text = this.getDialogInputValue(dialog, 'text');
    var selectionText = this.selection ? this.selection.toString().trim() : '';

    if (selectionText) {
      document.execCommand('createLink', false, url);
    } else {
      if (!text) {
        text = url;
      }
      var html = '<a href="' + this.escapeAttribute(url) + '">' + this.escapeHTML(text) + '</a>';
      document.execCommand('insertHTML', false, html);
    }

    return true;
  };

  Editor.prototype.submitImageDialog = function (dialog) {
    var url = this.getDialogInputValue(dialog, 'url');
    if (!/^https?:\/\//i.test(url || '')) {
      this.markDialogInputInvalid(dialog, 'url');
      this.showDialogError(dialog, 'Ingresá una URL que comience con http o https.');
      return false;
    }

    var alt = this.getDialogInputValue(dialog, 'alt');
    var html = '<img src="' + this.escapeAttribute(url) + '" alt="' + this.escapeAttribute(alt) + '" class="blog-image">';
    document.execCommand('insertHTML', false, html);
    return true;
  };

  Editor.prototype.submitBoxDialog = function (dialog) {
    var active = dialog.querySelector('.blog-editor-choice.is-active');
    var type = active ? active.getAttribute('data-box-value') : 'info';
    var className = 'blog-box';

    switch (type) {
      case 'success':
        className += ' blog-box-success';
        break;
      case 'warning':
        className += ' blog-box-warning';
        break;
      case 'neutral':
        className += ' blog-box-neutral';
        break;
      default:
        className += ' blog-box-info';
        break;
    }

    var html = '<div class="' + className + '"><p>Escribí aquí el contenido de la caja…</p></div>';
    document.execCommand('insertHTML', false, html);
    return true;
  };

  Editor.prototype.setActiveBoxChoice = function (dialog, value) {
    if (!dialog) {
      return;
    }
    var buttons = dialog.querySelectorAll('[data-box-value]');
    buttons.forEach(function (button) {
      var isActive = button.getAttribute('data-box-value') === value;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
  };

  Editor.prototype.saveSelection = function () {
    var selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) {
      return;
    }
    var range = selection.getRangeAt(0);
    if (!isEditableRange(range, this.canvas)) {
      return;
    }
    this.selection = range;
  };

  Editor.prototype.restoreSelection = function () {
    if (!this.selection) {
      return;
    }
    var selection = window.getSelection();
    if (!selection) {
      return;
    }
    selection.removeAllRanges();
    selection.addRange(this.selection);
  };

  Editor.prototype.handleCreateLink = function () {
    if (!this.dialogs.link) {
      return;
    }
    var selectionText = this.selection ? this.selection.toString().trim() : '';
    this.setDialogInputValue(this.dialogs.link, 'url', '');
    this.setDialogInputValue(this.dialogs.link, 'text', selectionText);
    this.openDialog('link');
  };

  Editor.prototype.handleInsertImage = function () {
    if (!this.dialogs.image) {
      return;
    }
    this.setDialogInputValue(this.dialogs.image, 'url', '');
    this.setDialogInputValue(this.dialogs.image, 'alt', '');
    this.openDialog('image');
  };

  Editor.prototype.handleInsertBox = function () {
    if (!this.dialogs.box) {
      return;
    }
    this.setActiveBoxChoice(this.dialogs.box, 'info');
    this.openDialog('box');
  };

  Editor.prototype.escapeHTML = function (value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  };

  Editor.prototype.escapeAttribute = function (value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  };

  Editor.prototype.prepareContent = function (html) {
    var cleaned = html
      .replace(/<div><br><\/div>/gi, '<p></p>')
      .replace(/&nbsp;/gi, ' ');
    return cleaned.trim();
  };

  ready(function () {
    document.querySelectorAll('[data-blog-editor]').forEach(function (wrapper) {
      new Editor(wrapper);
    });
  });
})();
