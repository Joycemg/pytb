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

  function Editor(wrapper) {
    this.wrapper = wrapper;
    this.canvas = wrapper.querySelector('.blog-editor-canvas');
    this.targetId = wrapper.getAttribute('data-target');
    this.textarea = this.targetId ? document.getElementById(this.targetId) : null;
    this.selection = null;

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

    this.wrapper.querySelectorAll('[data-color-command]').forEach(function (input) {
      input.addEventListener('input', function () {
        self.restoreSelection();
        self.canvas.focus();
        var cmd = input.getAttribute('data-color-command');
        if (!cmd) {
          return;
        }
        var value = input.value;
        if (cmd === 'hiliteColor' && !document.queryCommandSupported('hiliteColor')) {
          cmd = 'backColor';
        }
        document.execCommand(cmd, false, value);
        self.saveSelection();
      });
    });

    if (this.textarea.form) {
      this.textarea.form.addEventListener('submit', function () {
        self.textarea.value = self.prepareContent(self.canvas.innerHTML);
      });
    }
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
    var url = window.prompt('Ingresá la URL (https://ejemplo.com):');
    if (!url) {
      return;
    }
    if (!/^https?:\/\//i.test(url) && !/^mailto:/i.test(url)) {
      url = 'https://' + url.replace(/^\/*/, '');
    }
    document.execCommand('createLink', false, url);
    this.saveSelection();
  };

  Editor.prototype.handleInsertImage = function () {
    var url = window.prompt('URL de la imagen (https://...):');
    if (!url) {
      return;
    }
    if (!/^https?:\/\//i.test(url)) {
      window.alert('Solo se permiten imágenes externas con HTTP o HTTPS.');
      return;
    }
    var alt = window.prompt('Texto alternativo (descripción corta):', '');
    var html = '<img src="' + this.escapeAttribute(url) + '" alt="' + this.escapeAttribute(alt || '') + '" class="blog-image">';
    document.execCommand('insertHTML', false, html);
    this.saveSelection();
  };

  Editor.prototype.handleInsertBox = function () {
    var type = window.prompt('Tipo de caja (info, exito, alerta, neutra):', 'info');
    if (!type) {
      type = 'info';
    }
    type = type.toLowerCase();
    var className = 'blog-box';
    switch (type) {
      case 'exito':
      case 'éxito':
        className += ' blog-box-success';
        break;
      case 'alerta':
      case 'peligro':
        className += ' blog-box-warning';
        break;
      case 'neutra':
      case 'neutral':
        className += ' blog-box-neutral';
        break;
      default:
        className += ' blog-box-info';
        break;
    }
    var html = '<div class="' + className + '"><p>Escribí aquí el contenido de la caja…</p></div>';
    document.execCommand('insertHTML', false, html);
    this.saveSelection();
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
