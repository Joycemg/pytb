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

  function closestElement(node, tagName) {
    var upper = tagName ? String(tagName).toUpperCase() : '';
    while (node && node !== document) {
      if (node.nodeType === 1 && node.nodeName === upper) {
        return node;
      }
      node = node.parentNode;
    }
    return null;
  }

  function closestNode(node, predicate, boundary) {
    while (node && node !== boundary) {
      if (predicate(node)) {
        return node;
      }
      node = node.parentNode;
    }
    if (boundary && predicate(boundary)) {
      return boundary;
    }
    return null;
  }

  function isWhitespaceNode(node) {
    return node && node.nodeType === 3 && node.textContent.trim() === '';
  }

  function isBlockContainer(node) {
    if (!node || node.nodeType !== 1) {
      return false;
    }
    return /^(P|DIV|LI|BLOCKQUOTE|H1|H2|H3|H4|H5|H6|FIGURE)$/i.test(node.nodeName);
  }

  function getBlockContainer(node, boundary) {
    return closestNode(node, function (current) {
      return current && current.nodeType === 1 && isBlockContainer(current);
    }, boundary);
  }

  function isEmptyBlock(element) {
    if (!element || element.nodeType !== 1) {
      return true;
    }
    if (element.querySelector && element.querySelector('img, video, iframe, table, .blog-box')) {
      return false;
    }
    var text = element.textContent.replace(/\u200B/g, '').trim();
    if (text !== '') {
      return false;
    }
    var child = element.firstChild;
    while (child) {
      if (child.nodeType === 1 && child.nodeName !== 'BR') {
        return false;
      }
      child = child.nextSibling;
    }
    return true;
  }

  function isLastSibling(element) {
    if (!element) {
      return false;
    }
    var sibling = element.nextSibling;
    while (sibling) {
      if (sibling.nodeType === 1) {
        if (sibling.nodeName === 'BR') {
          sibling = sibling.nextSibling;
          continue;
        }
        if (sibling.hasAttribute && sibling.hasAttribute('data-blog-box-exit')) {
          sibling = sibling.nextSibling;
          continue;
        }
        return false;
      }
      if (sibling.nodeType === 3 && sibling.textContent.trim() !== '') {
        return false;
      }
      sibling = sibling.nextSibling;
    }
    return true;
  }

  function getSelectionRange(canvas) {
    var selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) {
      return null;
    }
    var range = selection.getRangeAt(0);
    if (!isEditableRange(range, canvas)) {
      return null;
    }
    return range;
  }

  function setCaretAtStart(element) {
    if (!element) {
      return;
    }
    var range = document.createRange();
    range.selectNodeContents(element);
    range.collapse(true);
    var selection = window.getSelection();
    if (selection) {
      selection.removeAllRanges();
      selection.addRange(range);
    }
  }

  function setCaretAtEnd(element) {
    if (!element) {
      return;
    }
    var range = document.createRange();
    range.selectNodeContents(element);
    range.collapse(false);
    var selection = window.getSelection();
    if (selection) {
      selection.removeAllRanges();
      selection.addRange(range);
    }
  }

  function isCaretAtEnd(range, element) {
    if (!range || !element || !range.collapsed) {
      return false;
    }
    var reference = document.createRange();
    reference.selectNodeContents(element);
    reference.collapse(false);
    return range.compareBoundaryPoints(Range.END_TO_END, reference) === 0;
  }

  function createParagraphAfter(element) {
    if (!element || !element.parentNode) {
      return null;
    }
    var paragraph = document.createElement('p');
    paragraph.innerHTML = '<br>';
    if (element.nextSibling) {
      element.parentNode.insertBefore(paragraph, element.nextSibling);
    } else {
      element.parentNode.appendChild(paragraph);
    }
    return paragraph;
  }

  function ensureBoxExitButton(box) {
    if (!box || box.querySelector('[data-blog-box-exit]')) {
      return;
    }
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'blog-box-exit';
    button.setAttribute('data-blog-box-exit', '');
    button.setAttribute('contenteditable', 'false');
    button.setAttribute('aria-label', 'Salir de la caja y continuar escribiendo');
    button.textContent = 'Seguir escribiendo afuera';
    box.appendChild(button);
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
    this.boundCanvasKeydown = this.handleCanvasKeydown.bind(this);
    this.boundWindowUpdate = this.updateImageToolsPosition.bind(this);
    this.boundScrollUpdate = this.handleScrollUpdate.bind(this);
    this.boundResizeMove = this.handleImageResizeMove.bind(this);
    this.boundResizeEnd = this.handleImageResizeEnd.bind(this);
    this.toolbar = this.wrapper.querySelector('.blog-editor-toolbar');
    this.toolbarFrame = null;
    this.boundToolbarStateUpdate = this.requestToolbarScrollState.bind(this);
    this.history = [];
    this.historyIndex = -1;
    this.historyTimer = null;
    this.isApplyingHistory = false;
    this.activeImage = null;
    this.imageTools = null;
    this.imageResizeState = null;

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

    this.decorateBlogBoxes();

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
    this.initializeToolbar();
    this.createImageTools();
    this.recordHistory(true);
  };

  Editor.prototype.decorateBlogBoxes = function () {
    if (!this.canvas) {
      return;
    }
    var boxes = this.canvas.querySelectorAll('.blog-box');
    boxes.forEach(function (box) {
      ensureBoxExitButton(box);
    });
  };

  Editor.prototype.clearActiveBox = function () {
    if (!this.canvas) {
      return;
    }
    this.canvas.querySelectorAll('.blog-box.is-active').forEach(function (box) {
      box.classList.remove('is-active');
    });
  };

  Editor.prototype.updateActiveBox = function (range) {
    if (!this.canvas) {
      return;
    }
    this.clearActiveBox();
    var currentRange = range || getSelectionRange(this.canvas);
    if (!currentRange) {
      return;
    }
    var box = closestNode(currentRange.startContainer, function (node) {
      return node && node.nodeType === 1 && node.classList && node.classList.contains('blog-box');
    }, this.canvas);
    if (box) {
      box.classList.add('is-active');
    }
  };

  Editor.prototype.registerEvents = function () {
    var self = this;

    this.canvas.addEventListener('focus', function () {
      self.saveSelection();
    });
    this.canvas.addEventListener('keydown', this.boundCanvasKeydown);
    this.canvas.addEventListener('keyup', function () {
      self.saveSelection();
    });
    this.canvas.addEventListener('mouseup', function () {
      self.saveSelection();
    });
    this.canvas.addEventListener('click', function (event) {
      var exitButton = event.target.closest('[data-blog-box-exit]');
      if (exitButton) {
        event.preventDefault();
        var box = exitButton.closest('.blog-box');
        if (box) {
          var paragraphAfterBox = createParagraphAfter(box);
          if (paragraphAfterBox) {
            setCaretAtStart(paragraphAfterBox);
            self.saveSelection();
            self.commitHistorySoon();
          }
        }
        return;
      }

      var image = event.target.closest('img.blog-image');
      if (image) {
        self.activateImage(image);
      } else if (!event.target.closest('.blog-editor-image-toolbar')) {
        self.deactivateImage();
      }
    });
    this.canvas.addEventListener('dragstart', function (event) {
      var image = event.target.closest('img.blog-image');
      if (image) {
        self.activateImage(image);
      }
    });
    this.canvas.addEventListener('dragend', function () {
      self.commitHistorySoon();
      self.deactivateImage();
    });
    this.canvas.addEventListener('drop', function () {
      self.commitHistorySoon();
    });
    this.canvas.addEventListener('scroll', this.boundScrollUpdate);
    this.canvas.addEventListener('input', function () {
      self.decorateBlogBoxes();
      self.updateActiveBox();
      self.textarea.value = self.prepareContent(self.canvas.innerHTML);
      self.commitHistorySoon();
      self.updateImageToolsPosition();
    });
    document.addEventListener('click', function (event) {
      if (!self.wrapper.contains(event.target)) {
        self.deactivateImage();
        self.clearActiveBox();
      }
    });

    var form = this.wrapper.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        self.textarea.value = self.prepareContent(self.canvas.innerHTML);
      });
    }

    this.wrapper.addEventListener('click', function (event) {
      var button = event.target.closest('[data-command], [data-format-block], [data-action], [data-history-action]');
      if (!button) {
        return;
      }
      event.preventDefault();
      self.canvas.focus();
      self.restoreSelection();

      var historyAction = button.getAttribute('data-history-action');
      if (historyAction) {
        if (historyAction === 'undo') {
          self.undo();
        } else if (historyAction === 'redo') {
          self.redo();
        }
        return;
      }

      var command = button.getAttribute('data-command');
      if (command) {
        document.execCommand(command, false, null);
        self.saveSelection();
        self.commitHistorySoon();
        return;
      }

      var block = button.getAttribute('data-format-block');
      if (block) {
        document.execCommand('formatBlock', false, block.toUpperCase());
        self.saveSelection();
        self.commitHistorySoon();
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
      var customPicker = customPanel ? customPanel.querySelector('[data-editor-color-picker]') : null;
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

      function getDefaultCustomColor() {
        if (customInput) {
          var placeholder = customInput.getAttribute('placeholder');
          var normalizedPlaceholder = normalizeColor(placeholder);
          if (normalizedPlaceholder) {
            return normalizedPlaceholder;
          }
        }
        if (customPicker) {
          var pickerValue = normalizeColor(customPicker.value);
          if (pickerValue) {
            return pickerValue;
          }
        }
        return '#2563EB';
      }

      function syncCustomControls(value) {
        var normalized = normalizeColor(value);
        var color = normalized || getDefaultCustomColor();
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
        customPanel.classList.remove('is-active');
        hideCustomError();
        self.requestToolbarScrollState();
      }

      function openCustomPanel(initial) {
        if (!customPanel) {
          return;
        }
        customPanel.hidden = false;
        customPanel.classList.add('is-active');
        hideCustomError();
        self.requestToolbarScrollState();
        var value = initial || palette.getAttribute('data-active-color') || '';
        syncCustomControls(value);
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
          normalized = normalizeColor(customInput.value || '');
        }
        if (!normalized && customPicker) {
          normalized = normalizeColor(customPicker.value || '');
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
        self.commitHistorySoon();
      });

      if (customInput) {
        customInput.addEventListener('input', function () {
          hideCustomError();
          updateCustomPreview(customInput.value);
          var normalized = normalizeColor(customInput.value || '');
          if (customPicker && normalized) {
            customPicker.value = normalized.toLowerCase();
          }
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
            self.commitHistorySoon();
          } else if (event.key === 'Escape') {
            event.preventDefault();
            closeCustomPanel();
          }
        });
      }

      if (customPicker) {
        customPicker.addEventListener('input', function () {
          hideCustomError();
          var normalized = normalizeColor(customPicker.value || '');
          if (customInput && normalized) {
            customInput.value = normalized;
          }
          updateCustomPreview(customPicker.value);
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
            self.commitHistorySoon();
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
    window.addEventListener('resize', this.boundWindowUpdate);
    document.addEventListener('scroll', this.boundScrollUpdate, true);
  };

  Editor.prototype.initializeToolbar = function () {
    if (!this.toolbar) {
      return;
    }

    this.requestToolbarScrollState();
    this.toolbar.addEventListener('scroll', this.boundToolbarStateUpdate, { passive: true });
    window.addEventListener('resize', this.boundToolbarStateUpdate);
  };

  Editor.prototype.requestToolbarScrollState = function () {
    var self = this;
    if (!this.toolbar) {
      return;
    }
    if (this.toolbarFrame) {
      window.cancelAnimationFrame(this.toolbarFrame);
    }
    this.toolbarFrame = window.requestAnimationFrame(function () {
      self.toolbarFrame = null;
      self.updateToolbarScrollState();
    });
  };

  Editor.prototype.updateToolbarScrollState = function () {
    if (!this.toolbar) {
      return;
    }

    var tolerance = 2;
    var scrollWidth = this.toolbar.scrollWidth;
    var clientWidth = this.toolbar.clientWidth;
    var scrollLeft = this.toolbar.scrollLeft;
    var hasOverflow = scrollWidth - clientWidth > tolerance;

    this.toolbar.classList.toggle('is-scrollable', hasOverflow);

    if (!hasOverflow) {
      this.toolbar.classList.remove('has-left-shadow', 'has-right-shadow');
      return;
    }

    var atStart = scrollLeft <= tolerance;
    var atEnd = scrollLeft + clientWidth >= scrollWidth - tolerance;

    this.toolbar.classList.toggle('has-left-shadow', !atStart);
    this.toolbar.classList.toggle('has-right-shadow', !atEnd);
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

  Editor.prototype.handleCanvasKeydown = function (event) {
    if (this.handleHistoryKeydown(event)) {
      return true;
    }
    if (this.handleBlockquoteKeydown(event)) {
      return true;
    }
    if (this.handleBoxKeydown(event)) {
      return true;
    }
    return false;
  };

  Editor.prototype.handleHistoryKeydown = function (event) {
    if (!event.ctrlKey && !event.metaKey) {
      return false;
    }
    var key = event.key.toLowerCase();
    if (key === 'z') {
      event.preventDefault();
      if (event.shiftKey) {
        this.redo();
      } else {
        this.undo();
      }
      return true;
    }
    if (key === 'y') {
      event.preventDefault();
      this.redo();
      return true;
    }
    return false;
  };

  Editor.prototype.handleBlockquoteKeydown = function (event) {
    if (event.key === 'Enter' && !event.shiftKey && !event.ctrlKey && !event.metaKey) {
      var range = getSelectionRange(this.canvas);
      if (!range || !range.collapsed) {
        return false;
      }
      var blockquote = closestElement(range.startContainer, 'BLOCKQUOTE');
      if (!blockquote) {
        return false;
      }
      var block = getBlockContainer(range.startContainer, blockquote) || blockquote;
      if (block && isEmptyBlock(block) && isLastSibling(block)) {
        event.preventDefault();
        if (block !== blockquote && block.parentNode === blockquote) {
          blockquote.removeChild(block);
        }
        var paragraph = document.createElement('p');
        paragraph.innerHTML = '<br>';
        if (blockquote.nextSibling) {
          blockquote.parentNode.insertBefore(paragraph, blockquote.nextSibling);
        } else {
          blockquote.parentNode.appendChild(paragraph);
        }
        setCaretAtStart(paragraph);
        this.saveSelection();
        this.commitHistorySoon();
        return true;
      }
    }

    if (event.key === 'Backspace' && !event.shiftKey && !event.ctrlKey && !event.metaKey) {
      var rangeBackspace = getSelectionRange(this.canvas);
      if (!rangeBackspace || !rangeBackspace.collapsed) {
        return false;
      }
      var blockElement = getBlockContainer(rangeBackspace.startContainer, this.canvas);
      if (!blockElement || !isEmptyBlock(blockElement)) {
        return false;
      }
      var previous = blockElement.previousSibling;
      while (previous && (isWhitespaceNode(previous) || (previous.nodeType === 1 && previous.nodeName === 'BR'))) {
        previous = previous.previousSibling;
      }
      if (previous && previous.nodeType === 1 && previous.nodeName === 'BLOCKQUOTE') {
        event.preventDefault();
        var parent = blockElement.parentNode;
        parent.removeChild(blockElement);
        setCaretAtEnd(previous);
        this.saveSelection();
        this.commitHistorySoon();
        return true;
      }
    }

    return false;
  };

  Editor.prototype.handleBoxKeydown = function (event) {
    if (event.key !== 'Enter' && event.key !== 'ArrowDown') {
      return false;
    }

    var range = getSelectionRange(this.canvas);
    if (!range || !range.collapsed) {
      return false;
    }

    var box = closestNode(range.startContainer, function (node) {
      return node && node.nodeType === 1 && node.classList && node.classList.contains('blog-box');
    }, this.canvas);
    if (!box) {
      return false;
    }

    var block = getBlockContainer(range.startContainer, box) || box;
    var caretContext = block === box ? box : block;
    var atEnd = isCaretAtEnd(range, caretContext);
    var lastSibling = isLastSibling(block);

    if (event.key === 'Enter') {
      if (event.shiftKey) {
        return false;
      }
      if ((event.ctrlKey || event.metaKey) && lastSibling && atEnd) {
        event.preventDefault();
        var paragraphAfterCtrl = createParagraphAfter(box);
        if (paragraphAfterCtrl) {
          setCaretAtStart(paragraphAfterCtrl);
          this.saveSelection();
          this.commitHistorySoon();
        }
        return true;
      }
      if (!event.ctrlKey && !event.metaKey && block && isEmptyBlock(block) && lastSibling) {
        event.preventDefault();
        if (block !== box && block.parentNode === box) {
          box.removeChild(block);
        }
        var paragraphAfter = createParagraphAfter(box);
        if (paragraphAfter) {
          setCaretAtStart(paragraphAfter);
          this.saveSelection();
          this.commitHistorySoon();
        }
        return true;
      }
      return false;
    }

    if (event.key === 'ArrowDown' && !event.shiftKey && !event.ctrlKey && !event.metaKey && lastSibling && atEnd) {
      event.preventDefault();
      var next = box.nextSibling;
      while (next && ((next.nodeType === 3 && next.textContent.trim() === '') || (next.nodeType === 1 && next.nodeName === 'BR'))) {
        next = next.nextSibling;
      }
      if (next && next.nodeType === 1) {
        setCaretAtStart(next);
        this.saveSelection();
        return true;
      }
      var paragraphAfterArrow = createParagraphAfter(box);
      if (paragraphAfterArrow) {
        setCaretAtStart(paragraphAfterArrow);
        this.saveSelection();
        this.commitHistorySoon();
      }
      return true;
    }

    return false;
  };

  Editor.prototype.commitHistorySoon = function () {
    var self = this;
    window.clearTimeout(this.historyTimer);
    this.historyTimer = window.setTimeout(function () {
      self.recordHistory(false);
    }, 120);
  };

  Editor.prototype.recordHistory = function (force) {
    if (this.isApplyingHistory) {
      return;
    }
    var content = this.prepareContent(this.canvas.innerHTML);
    if (!force && this.history.length > 0 && this.history[this.history.length - 1] === content) {
      return;
    }
    if (this.historyIndex < this.history.length - 1) {
      this.history = this.history.slice(0, this.historyIndex + 1);
    }
    this.history.push(content);
    this.historyIndex = this.history.length - 1;
    this.textarea.value = content;
  };

  Editor.prototype.applyHistory = function (index) {
    if (index < 0 || index >= this.history.length) {
      return;
    }
    window.clearTimeout(this.historyTimer);
    this.isApplyingHistory = true;
    this.historyIndex = index;
    var html = this.history[index];
    this.canvas.innerHTML = html && html.trim() !== '' ? html : '<p></p>';
    this.decorateBlogBoxes();
    this.textarea.value = this.prepareContent(this.canvas.innerHTML);
    this.isApplyingHistory = false;
    this.deactivateImage();
    this.canvas.focus();
    setCaretAtEnd(this.canvas);
    this.saveSelection();
  };

  Editor.prototype.undo = function () {
    if (this.historyIndex <= 0) {
      return;
    }
    this.applyHistory(this.historyIndex - 1);
  };

  Editor.prototype.redo = function () {
    if (this.historyIndex >= this.history.length - 1) {
      return;
    }
    this.applyHistory(this.historyIndex + 1);
  };

  Editor.prototype.handleScrollUpdate = function () {
    this.updateImageToolsPosition();
  };

  Editor.prototype.createImageTools = function () {
    if (this.imageTools || !this.wrapper) {
      return;
    }
    var self = this;
    var container = document.createElement('div');
    container.className = 'blog-editor-image-tools';
    container.setAttribute('hidden', 'hidden');
    container.innerHTML = '' +
      '<div class="blog-editor-image-frame" data-image-frame>' +
      '  <button type="button" class="blog-editor-image-handle" data-resize-handle="w" aria-label="Reducir ancho"></button>' +
      '  <button type="button" class="blog-editor-image-handle" data-resize-handle="e" aria-label="Aumentar ancho"></button>' +
      '</div>' +
      '<div class="blog-editor-image-toolbar" data-image-toolbar>' +
      '  <button type="button" class="blog-editor-image-toolbar-btn" data-image-action="align-left" title="Alinear a la izquierda" aria-label="Alinear a la izquierda">⟸</button>' +
      '  <button type="button" class="blog-editor-image-toolbar-btn" data-image-action="align-center" title="Centrar" aria-label="Centrar">☼</button>' +
      '  <button type="button" class="blog-editor-image-toolbar-btn" data-image-action="align-right" title="Alinear a la derecha" aria-label="Alinear a la derecha">⟹</button>' +
      '  <span class="blog-editor-image-toolbar-separator" aria-hidden="true"></span>' +
      '  <button type="button" class="blog-editor-image-toolbar-btn" data-image-action="remove" title="Eliminar imagen" aria-label="Eliminar imagen">✕</button>' +
      '</div>';
    this.wrapper.appendChild(container);

    var frame = container.querySelector('[data-image-frame]');
    var toolbar = container.querySelector('[data-image-toolbar]');
    container.querySelectorAll('[data-resize-handle]').forEach(function (handle) {
      handle.addEventListener('mousedown', function (event) {
        var direction = handle.getAttribute('data-resize-handle');
        self.startImageResize(direction, event);
      });
    });
    if (toolbar) {
      toolbar.addEventListener('click', function (event) {
        var button = event.target.closest('[data-image-action]');
        if (!button || !self.activeImage) {
          return;
        }
        event.preventDefault();
        var action = button.getAttribute('data-image-action');
        switch (action) {
          case 'align-left':
            self.setImageAlignment('left');
            break;
          case 'align-center':
            self.setImageAlignment('center');
            break;
          case 'align-right':
            self.setImageAlignment('right');
            break;
          case 'remove':
            self.removeActiveImage();
            break;
          default:
            break;
        }
      });
    }

    this.imageTools = {
      container: container,
      frame: frame,
      toolbar: toolbar
    };
  };

  Editor.prototype.activateImage = function (image) {
    if (!image) {
      return;
    }
    this.createImageTools();
    if (this.activeImage && this.activeImage !== image) {
      this.activeImage.classList.remove('is-selected');
    }
    this.activeImage = image;
    this.ensureImageAlignment(image);
    image.classList.add('is-selected');
    if (!image.hasAttribute('draggable')) {
      image.setAttribute('draggable', 'true');
    }
    if (this.imageTools) {
      this.imageTools.container.hidden = false;
    }
    this.updateImageToolbarState();
    this.updateImageToolsPosition();
  };

  Editor.prototype.deactivateImage = function () {
    if (this.activeImage) {
      this.activeImage.classList.remove('is-selected');
    }
    this.activeImage = null;
    if (this.imageTools) {
      this.imageTools.container.hidden = true;
    }
  };

  Editor.prototype.ensureImageAlignment = function (image) {
    if (!image) {
      return 'center';
    }
    var align = image.getAttribute('data-image-align');
    if (!align) {
      if (image.classList.contains('is-align-left')) {
        align = 'left';
      } else if (image.classList.contains('is-align-right')) {
        align = 'right';
      } else {
        align = 'center';
      }
      image.setAttribute('data-image-align', align);
    }
    image.classList.remove('is-align-left', 'is-align-right', 'is-align-center');
    if (align === 'left') {
      image.classList.add('is-align-left');
    } else if (align === 'right') {
      image.classList.add('is-align-right');
    } else {
      image.classList.add('is-align-center');
    }
    return align;
  };

  Editor.prototype.updateImageToolbarState = function () {
    if (!this.imageTools || !this.imageTools.toolbar || !this.activeImage) {
      return;
    }
    var align = this.ensureImageAlignment(this.activeImage);
    this.imageTools.toolbar.querySelectorAll('[data-image-action]').forEach(function (button) {
      var action = button.getAttribute('data-image-action');
      if (action === 'align-' + align) {
        button.classList.add('is-active');
      } else if (action && action.indexOf('align-') === 0) {
        button.classList.remove('is-active');
      }
    });
  };

  Editor.prototype.setImageAlignment = function (value) {
    if (!this.activeImage) {
      return;
    }
    var align = value || 'center';
    this.activeImage.setAttribute('data-image-align', align);
    this.ensureImageAlignment(this.activeImage);
    this.updateImageToolbarState();
    this.updateImageToolsPosition();
    this.commitHistorySoon();
  };

  Editor.prototype.startImageResize = function (direction, event) {
    if (!this.activeImage) {
      return;
    }
    event.preventDefault();
    event.stopPropagation();
    var rect = this.activeImage.getBoundingClientRect();
    this.imageResizeState = {
      direction: direction === 'w' ? 'w' : 'e',
      startX: event.clientX,
      startWidth: rect.width
    };
    document.addEventListener('mousemove', this.boundResizeMove);
    document.addEventListener('mouseup', this.boundResizeEnd);
  };

  Editor.prototype.handleImageResizeMove = function (event) {
    if (!this.imageResizeState || !this.activeImage) {
      return;
    }
    event.preventDefault();
    var delta = event.clientX - this.imageResizeState.startX;
    if (this.imageResizeState.direction === 'w') {
      delta = -delta;
    }
    var newWidth = this.imageResizeState.startWidth + delta;
    var minWidth = 120;
    var maxWidth = Math.max(this.canvas.clientWidth || this.canvas.offsetWidth || newWidth, minWidth);
    if (newWidth < minWidth) {
      newWidth = minWidth;
    }
    if (newWidth > maxWidth) {
      newWidth = maxWidth;
    }
    this.activeImage.style.width = newWidth + 'px';
    this.activeImage.style.height = 'auto';
    this.updateImageToolsPosition();
  };

  Editor.prototype.handleImageResizeEnd = function (event) {
    if (event) {
      event.preventDefault();
    }
    if (!this.imageResizeState) {
      return;
    }
    document.removeEventListener('mousemove', this.boundResizeMove);
    document.removeEventListener('mouseup', this.boundResizeEnd);
    this.imageResizeState = null;
    this.commitHistorySoon();
  };

  Editor.prototype.removeActiveImage = function () {
    if (!this.activeImage) {
      return;
    }
    var image = this.activeImage;
    var parent = image.parentNode;
    var range = null;
    if (parent) {
      range = document.createRange();
      if (image.nextSibling) {
        range.setStartAfter(image);
      } else {
        range.setStartBefore(image);
      }
      range.collapse(true);
    }
    this.deactivateImage();
    if (parent) {
      parent.removeChild(image);
    }
    if (range) {
      var selection = window.getSelection();
      if (selection) {
        selection.removeAllRanges();
        selection.addRange(range);
      }
    }
    this.commitHistorySoon();
    this.canvas.focus();
    this.saveSelection();
  };

  Editor.prototype.updateImageToolsPosition = function () {
    if (!this.imageTools || !this.imageTools.frame || !this.activeImage) {
      return;
    }
    var imageRect = this.activeImage.getBoundingClientRect();
    var wrapperRect = this.wrapper.getBoundingClientRect();
    var scrollTop = typeof this.wrapper.scrollTop === 'number' ? this.wrapper.scrollTop : 0;
    var scrollLeft = typeof this.wrapper.scrollLeft === 'number' ? this.wrapper.scrollLeft : 0;
    var top = imageRect.top - wrapperRect.top + scrollTop;
    var left = imageRect.left - wrapperRect.left + scrollLeft;
    this.imageTools.frame.style.top = top + 'px';
    this.imageTools.frame.style.left = left + 'px';
    this.imageTools.frame.style.width = imageRect.width + 'px';
    this.imageTools.frame.style.height = imageRect.height + 'px';
    if (this.imageTools.toolbar) {
      var toolbarLeft = left + imageRect.width / 2;
      var toolbarTop = top + imageRect.height + 12;
      this.imageTools.toolbar.style.left = toolbarLeft + 'px';
      this.imageTools.toolbar.style.top = toolbarTop + 'px';
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

    this.commitHistorySoon();
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
    var html = '<img src="' + this.escapeAttribute(url) + '" alt="' + this.escapeAttribute(alt) + '" class="blog-image is-align-center" data-image-align="center" draggable="true">';
    document.execCommand('insertHTML', false, html);
    this.commitHistorySoon();
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
    this.decorateBlogBoxes();
    this.commitHistorySoon();
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
      this.selection = null;
      this.clearActiveBox();
      return;
    }
    var range = selection.getRangeAt(0);
    if (!isEditableRange(range, this.canvas)) {
      this.selection = null;
      this.clearActiveBox();
      return;
    }
    this.selection = range;
    this.updateActiveBox(range);
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
    var container = document.createElement('div');
    container.innerHTML = html;
    container.querySelectorAll('[data-blog-box-exit]').forEach(function (button) {
      if (button.parentNode) {
        button.parentNode.removeChild(button);
      }
    });
    var cleaned = container.innerHTML
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
