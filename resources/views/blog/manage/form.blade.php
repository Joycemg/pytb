@extends('layouts.app')

@section('title', $post->exists ? 'Editar entrada' : 'Nueva entrada')

@section('content')
  @php
    $availableTags = $availableTags ?? collect();
    $popularTags = $popularTags ?? collect();
  @endphp
  <div class="page container blog-form">
    <header class="page-head">
      <h1 class="page-title">{{ $post->exists ? 'Editar entrada' : 'Nueva entrada' }}</h1>
      <a class="btn" href="{{ route('blog.manage') }}">← Volver</a>
    </header>

    @if ($errors->any())
      <div class="flash flash-error">
        <p><strong>Por favor revisá el formulario.</strong></p>
        <ul>
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @if (session('status'))
      <div class="flash flash-success">{{ session('status') }}</div>
    @endif

    <div class="card">
      <div class="card-body">
        <div class="blog-form-layout">
          <div class="blog-form-main">
            <form id="blog-entry-form" method="post" action="{{ $post->exists ? route('blog.update', $post) : route('blog.store') }}" enctype="multipart/form-data" class="form blog-form-body">
              @csrf
              @if ($post->exists)
                @method('put')
              @endif

            <section id="blog-form-basics" class="blog-form-section" aria-labelledby="blog-form-basics-title">
              <div class="blog-form-section-header">
                <h2 id="blog-form-basics-title" class="blog-form-section-title">Datos principales</h2>
                <p class="blog-form-section-description">Definí la información base que se mostrará en la portada y en los buscadores.</p>
              </div>

              <div class="blog-form-section-body">
                <div class="form-group">
                  <label for="title">Título</label>
                  <input id="title" name="title" type="text" value="{{ old('title', $post->title) }}" required>
                </div>

                <div class="form-group">
                  <label for="slug">Slug (opcional)</label>
                  <input id="slug" name="slug" type="text" value="{{ old('slug', $post->slug) }}" placeholder="mi-entrada-super-epica">
                  <small class="hint">Se usará en la URL. Si se deja vacío se generará automáticamente.</small>
                </div>

                @php
                  $selectedTagIds = collect(old('tags', $post->tags->pluck('id')->all()))
                    ->map(fn ($id) => (int) $id)
                    ->all();
                @endphp

                <div class="form-group blog-tags-field">
                  <div class="blog-tags-field-header">
                    <label for="tags-group">Etiquetas</label>
                    <p class="hint">Marcá todas las etiquetas que representen el tema de la entrada. Servirán para organizar y encontrar la nota más rápido.</p>
                  </div>
                  <div class="blog-tag-selector-wrapper" data-tag-selector data-tag-max="3">
                    @if ($popularTags->isNotEmpty())
                      <div class="blog-tag-quick-picks" data-tag-quick-picks>
                        <p class="blog-tag-quick-picks-title">Selección rápida</p>
                        <div class="blog-tag-quick-picks-options">
                          @foreach ($popularTags as $tag)
                            <button type="button" class="blog-tag-quick-pick" data-tag-quick-pick data-tag-id="{{ $tag['id'] }}">
                              #{{ $tag['name'] }}
                            </button>
                          @endforeach
                        </div>
                      </div>
                    @endif
                    <div id="tags-group" class="blog-tag-selector" role="group" aria-label="Seleccionar etiquetas">
                      @forelse ($availableTags as $tag)
                        <label class="blog-tag-option{{ in_array($tag['id'], $selectedTagIds, true) ? ' is-selected' : '' }}">
                          <input type="checkbox" name="tags[]" value="{{ $tag['id'] }}" data-tag-name="{{ $tag['name'] }}" data-tag-id="{{ $tag['id'] }}" {{ in_array($tag['id'], $selectedTagIds, true) ? 'checked' : '' }}>
                          <span>#{{ $tag['name'] }}</span>
                        </label>
                      @empty
                        <p class="hint">Todavía no hay etiquetas creadas. Podés sumar nuevas abajo.</p>
                      @endforelse
                    </div>
                    <div class="blog-tag-selector-summary" data-tag-summary aria-live="polite">
                      <p class="blog-tag-selector-summary-title">Etiquetas seleccionadas</p>
                      <p class="blog-tag-selector-summary-empty" data-tag-summary-empty>Ninguna por ahora. Podés combinarlas para resaltar distintos enfoques.</p>
                      <ul class="blog-tag-selector-summary-list" data-tag-summary-list hidden></ul>
                      <p class="blog-tag-selector-limit" data-tag-limit hidden>Podés elegir hasta 3 etiquetas.</p>
                    </div>
                  </div>
                  <div class="blog-tag-creator">
                    <label for="new_tags">Crear etiquetas nuevas</label>
                    <input id="new_tags" name="new_tags" type="text" value="{{ old('new_tags') }}" placeholder="Ej: Comunidad, Eventos">
                    <small class="hint">Separá múltiples etiquetas con comas. Las etiquetas nuevas estarán disponibles para futuras entradas.</small>
                  </div>
                </div>
              </div>
            </section>

            <section id="blog-form-content" class="blog-form-section" aria-labelledby="blog-form-content-title">
              <div class="blog-form-section-header">
                <h2 id="blog-form-content-title" class="blog-form-section-title">Contenido</h2>
                <p class="blog-form-section-description">Escribí y dale estilo a la nota con el editor enriquecido. Podés insertar imágenes, enlaces y cajas destacadas.</p>
              </div>

              <div class="blog-form-section-body">
                <div class="form-group">
                  <label for="content">Contenido</label>
                  <div class="blog-editor" data-blog-editor data-target="content">
              <div class="blog-editor-toolbar" role="toolbar" aria-label="Herramientas de formato">
                <div class="blog-editor-group" role="group" aria-label="Historial de edición">
                  <button type="button" class="blog-editor-btn" data-history-action="undo" title="Deshacer">↺</button>
                  <button type="button" class="blog-editor-btn" data-history-action="redo" title="Rehacer">↻</button>
                </div>
                <div class="blog-editor-group" role="group" aria-label="Formato de texto">
                  <button type="button" class="blog-editor-btn" data-command="bold" title="Negrita"><span>Negrita</span></button>
                  <button type="button" class="blog-editor-btn" data-command="italic" title="Cursiva"><span>Cursiva</span></button>
                  <button type="button" class="blog-editor-btn" data-command="underline" title="Subrayado"><span>Subrayado</span></button>
                  <button type="button" class="blog-editor-btn" data-command="removeFormat" title="Limpiar formato"><span>Limpiar</span></button>
                </div>
                <div class="blog-editor-group" role="group" aria-label="Bloques">
                  <button type="button" class="blog-editor-btn" data-format-block="p" title="Párrafo">P</button>
                  <button type="button" class="blog-editor-btn" data-format-block="h2" title="Título">H2</button>
                  <button type="button" class="blog-editor-btn" data-format-block="blockquote" title="Cita">❝</button>
                  <button type="button" class="blog-editor-btn" data-command="insertOrderedList" title="Lista numerada">1.</button>
                  <button type="button" class="blog-editor-btn" data-command="insertUnorderedList" title="Lista con viñetas">•</button>
                </div>
                <div class="blog-editor-group" role="group" aria-label="Alineación">
                  <button type="button" class="blog-editor-btn" data-command="justifyLeft" title="Alinear a la izquierda">⟸</button>
                  <button type="button" class="blog-editor-btn" data-command="justifyCenter" title="Centrar">☼</button>
                  <button type="button" class="blog-editor-btn" data-command="justifyRight" title="Alinear a la derecha">⟹</button>
                </div>
                <div class="blog-editor-group blog-editor-group-colors" role="group" aria-label="Colores">
                  <div class="blog-editor-palette" data-color-command="foreColor" aria-label="Colores de texto">
                    <span class="blog-editor-group-label">Texto</span>
                    <div class="blog-editor-swatches">
                      <button type="button" class="blog-editor-swatch" data-color-value="#1F2937" style="--swatch-color:#1F2937" title="Pizarra"></button>
                      <button type="button" class="blog-editor-swatch" data-color-value="#0F172A" style="--swatch-color:#0F172A" title="Azul profundo"></button>
                      <button type="button" class="blog-editor-swatch" data-color-value="#DB2777" style="--swatch-color:#DB2777" title="Magenta"></button>
                      <button type="button" class="blog-editor-swatch" data-color-value="#2563EB" style="--swatch-color:#2563EB" title="Azul"></button>
                      <button type="button" class="blog-editor-swatch" data-color-value="#16A34A" style="--swatch-color:#16A34A" title="Verde"></button>
                      <button type="button" class="blog-editor-swatch" data-color-custom title="Color personalizado">Personalizar</button>
                    </div>
                    <div class="blog-editor-color-custom" data-editor-color-panel hidden>
                      <p class="blog-editor-color-custom-title">Elegí un color en formato hex (#RRGGBB)</p>
                      <div class="blog-editor-color-custom-row">
                        <input type="color" class="blog-editor-color-custom-picker" data-editor-color-picker value="#2563EB" aria-label="Elegir color" />
                        <input type="text" class="blog-editor-color-custom-input" data-editor-color-input placeholder="#2563EB" maxlength="7" autocomplete="off">
                        <span class="blog-editor-color-custom-preview" data-editor-color-preview aria-hidden="true"></span>
                      </div>
                      <p class="blog-editor-color-custom-error" data-editor-color-error hidden>Ingresá un color válido. Ejemplo: #2563EB</p>
                      <div class="blog-editor-color-custom-actions">
                        <button type="button" class="blog-editor-color-custom-btn" data-editor-color-action="cancel">Cancelar</button>
                        <button type="button" class="blog-editor-color-custom-btn is-primary" data-editor-color-action="apply">Aplicar</button>
                      </div>
                    </div>
                  </div>
                  <div class="blog-editor-palette" data-color-command="hiliteColor" aria-label="Colores de fondo">
                    <span class="blog-editor-group-label">Fondo</span>
                    <div class="blog-editor-swatches">
                      <button type="button" class="blog-editor-swatch" data-color-value="#F8FAFC" style="--swatch-color:#F8FAFC" title="Niebla"></button>
                      <button type="button" class="blog-editor-swatch" data-color-value="#FDE68A" style="--swatch-color:#FDE68A" title="Dorado"></button>
                      <button type="button" class="blog-editor-swatch" data-color-value="#E0F2FE" style="--swatch-color:#E0F2FE" title="Cielo"></button>
                      <button type="button" class="blog-editor-swatch" data-color-value="#DCFCE7" style="--swatch-color:#DCFCE7" title="Prado"></button>
                      <button type="button" class="blog-editor-swatch" data-color-value="#F3E8FF" style="--swatch-color:#F3E8FF" title="Lavanda"></button>
                      <button type="button" class="blog-editor-swatch" data-color-custom title="Color personalizado">Personalizar</button>
                    </div>
                    <div class="blog-editor-color-custom" data-editor-color-panel hidden>
                      <p class="blog-editor-color-custom-title">Ingresá un color en formato hex (#RRGGBB)</p>
                      <div class="blog-editor-color-custom-row">
                        <input type="color" class="blog-editor-color-custom-picker" data-editor-color-picker value="#FDE68A" aria-label="Elegir color" />
                        <input type="text" class="blog-editor-color-custom-input" data-editor-color-input placeholder="#FDE68A" maxlength="7" autocomplete="off">
                        <span class="blog-editor-color-custom-preview" data-editor-color-preview aria-hidden="true"></span>
                      </div>
                      <p class="blog-editor-color-custom-error" data-editor-color-error hidden>Ingresá un color válido. Ejemplo: #FDE68A</p>
                      <div class="blog-editor-color-custom-actions">
                        <button type="button" class="blog-editor-color-custom-btn" data-editor-color-action="cancel">Cancelar</button>
                        <button type="button" class="blog-editor-color-custom-btn is-primary" data-editor-color-action="apply">Aplicar</button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="blog-editor-group" role="group" aria-label="Enlaces y multimedia">
                  <button type="button" class="blog-editor-btn blog-editor-btn--with-label" data-action="createLink" title="Insertar enlace">
                    <span class="blog-editor-btn-icon" aria-hidden="true">🔗</span>
                    <span class="blog-editor-btn-label">Enlace</span>
                  </button>
                  <button type="button" class="blog-editor-btn blog-editor-btn--with-label" data-action="insertImage" title="Insertar imagen">
                    <span class="blog-editor-btn-icon" aria-hidden="true">🖼️</span>
                    <span class="blog-editor-btn-label">Imagen</span>
                  </button>
                  <button type="button" class="blog-editor-btn blog-editor-btn--with-label" data-action="insertBox" title="Insertar caja resaltada">
                    <span class="blog-editor-btn-icon" aria-hidden="true">🗂</span>
                    <span class="blog-editor-btn-label">Caja</span>
                  </button>
                </div>
              </div>
              <div class="blog-editor-overlay" data-editor-overlay hidden></div>
              <div class="blog-editor-dialogs" data-blog-editor-dialogs>
                <section class="blog-editor-dialog" data-editor-dialog="link" hidden>
                  <h3 class="blog-editor-dialog-title">Insertar enlace</h3>
                  <p class="blog-editor-dialog-description">Agregá un enlace con un texto descriptivo para que la lectura sea más clara.</p>
                  <label class="blog-editor-dialog-label" for="blog-editor-link-url">URL del enlace</label>
                  <input id="blog-editor-link-url" type="url" class="blog-editor-dialog-input" data-dialog-input="url" placeholder="https://ejemplo.com/articulo" autocomplete="off" data-dialog-focus>
                  <label class="blog-editor-dialog-label" for="blog-editor-link-text">Texto visible (opcional)</label>
                  <input id="blog-editor-link-text" type="text" class="blog-editor-dialog-input" data-dialog-input="text" placeholder="Leer más sobre…" autocomplete="off">
                  <p class="blog-editor-dialog-hint">Si no completás el texto se usará el contenido seleccionado en el editor.</p>
                  <p class="blog-editor-dialog-error" data-dialog-error hidden></p>
                  <div class="blog-editor-dialog-actions">
                    <button type="button" class="blog-editor-dialog-btn" data-dialog-action="cancel">Cancelar</button>
                    <button type="button" class="blog-editor-dialog-btn is-primary" data-dialog-action="submit">Insertar enlace</button>
                  </div>
                </section>

                <section class="blog-editor-dialog" data-editor-dialog="image" hidden>
                  <h3 class="blog-editor-dialog-title">Insertar imagen</h3>
                  <p class="blog-editor-dialog-description">Pegá una imagen alojada en un sitio externo. Asegurate de tener permiso para usarla.</p>
                  <label class="blog-editor-dialog-label" for="blog-editor-image-url">URL de la imagen</label>
                  <input id="blog-editor-image-url" type="url" class="blog-editor-dialog-input" data-dialog-input="url" placeholder="https://cdn.ejemplo.com/imagen.jpg" autocomplete="off" data-dialog-focus>
                  <label class="blog-editor-dialog-label" for="blog-editor-image-alt">Texto alternativo</label>
                  <input id="blog-editor-image-alt" type="text" class="blog-editor-dialog-input" data-dialog-input="alt" placeholder="Descripción corta de la imagen" autocomplete="off">
                  <p class="blog-editor-dialog-hint">El texto alternativo ayuda a las personas con lectores de pantalla y mejora el SEO.</p>
                  <p class="blog-editor-dialog-error" data-dialog-error hidden></p>
                  <div class="blog-editor-dialog-actions">
                    <button type="button" class="blog-editor-dialog-btn" data-dialog-action="cancel">Cancelar</button>
                    <button type="button" class="blog-editor-dialog-btn is-primary" data-dialog-action="submit">Insertar imagen</button>
                  </div>
                </section>

                <section class="blog-editor-dialog" data-editor-dialog="box" hidden>
                  <h3 class="blog-editor-dialog-title">Caja resaltada</h3>
                  <p class="blog-editor-dialog-description">Elegí el estilo de caja para destacar consejos, avisos o resultados.</p>
                  <div class="blog-editor-choice-group" role="group" aria-label="Tipo de caja resaltada">
                    <button type="button" class="blog-editor-choice" data-box-value="info">Informativa</button>
                    <button type="button" class="blog-editor-choice" data-box-value="success">Éxito</button>
                    <button type="button" class="blog-editor-choice" data-box-value="warning">Alerta</button>
                    <button type="button" class="blog-editor-choice" data-box-value="neutral">Neutra</button>
                  </div>
                  <p class="blog-editor-dialog-hint">Podés editar el texto una vez insertada la caja.</p>
                  <div class="blog-editor-dialog-actions">
                    <button type="button" class="blog-editor-dialog-btn" data-dialog-action="cancel">Cancelar</button>
                    <button type="button" class="blog-editor-dialog-btn is-primary" data-dialog-action="submit">Insertar caja</button>
                  </div>
                </section>
              </div>
              <div id="content-editor" class="blog-editor-canvas" contenteditable="true" aria-label="Editor de contenido enriquecido"></div>
            </div>
                  <textarea id="content" name="content" rows="12" required>@php echo old('content', $post->content); @endphp</textarea>
                  <small class="hint">Escribí libremente, podés aplicar colores, encabezados, imágenes remotas y cajas resaltadas. Si desactivás JavaScript, el área inferior funciona como editor plano.</small>
                </div>
              </div>
            </section>

            @if (! $post->exists)
              <section id="blog-form-style" class="blog-form-section" aria-labelledby="blog-form-style-title">
                <div class="blog-form-section-header">
                  <h2 id="blog-form-style-title" class="blog-form-section-title">Personalización visual</h2>
                  <p class="blog-form-section-description">El estilo se asigna automáticamente de forma aleatoria al crear una entrada.</p>
                </div>

                <div class="blog-form-section-body">
                  <div class="form-group">
                    <p>Ya no es necesario elegir un tema manualmente: cada nueva publicación obtendrá un estilo aleatorio.</p>
                    <small class="hint">Cuando publiques esta entrada se asignará automáticamente un conjunto de colores.</small>
                  </div>
                </div>
              </section>
            @endif

            <section id="blog-form-media" class="blog-form-section" aria-labelledby="blog-form-media-title">
              <div class="blog-form-section-header">
                <h2 id="blog-form-media-title" class="blog-form-section-title">Recursos y archivos</h2>
                <p class="blog-form-section-description">Complementá la entrada con imágenes destacadas y materiales adicionales para descargar.</p>
              </div>

              <div class="blog-form-section-body">
                <div class="form-group">
                  <label for="hero_image_url">Imagen de cabecera (opcional)</label>
                  <input id="hero_image_url" name="hero_image_url" type="text" value="{{ old('hero_image_url', $post->hero_image_url) }}" placeholder="https://cdn.ejemplo.com/imagen.jpg">
                  <small class="hint">La imagen se mostrará en la parte superior de la entrada. Debe ser una URL absoluta.</small>
                </div>

                <div class="form-group">
                  <label for="hero_image_caption">Texto descriptivo de la imagen</label>
                  <input id="hero_image_caption" name="hero_image_caption" type="text" value="{{ old('hero_image_caption', $post->hero_image_caption) }}" maxlength="160" placeholder="Créditos o una breve descripción de la foto">
                </div>

                <div class="form-group">
                  <label for="attachments">Adjuntar archivos</label>
                  <input id="attachments" name="attachments[]" type="file" multiple>
                  <small class="hint">Podés subir imágenes, PDF, archivos comprimidos y más (hasta 50MB cada uno).</small>
                </div>

                @if ($post->exists && $post->attachments->isNotEmpty())
                  <div class="form-group">
                    <label>Archivos actuales</label>
                    <ul class="attachment-list">
                      @foreach ($post->attachments as $attachment)
                        <li>
                          <a href="{{ Storage::disk('public')->url($attachment->path) }}" target="_blank" rel="noopener">{{ $attachment->original_name }}</a>
                          <form method="post" action="{{ route('blog.attachments.destroy', [$post, $attachment]) }}" onsubmit="return confirm('¿Eliminar este archivo?');">
                            @csrf
                            @method('delete')
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                          </form>
                        </li>
                      @endforeach
                    </ul>
                  </div>
                @endif
              </div>
            </section>

            <div class="form-actions blog-form-actions">
              <button type="submit" class="btn btn-primary">{{ $post->exists ? 'Guardar cambios' : 'Guardar entrada' }}</button>
              @if ($post->exists)
                <a class="btn" href="{{ route('blog.show', ['post' => $post->slug]) }}" target="_blank" rel="noopener">Ver publicación</a>
              @endif
            </div>
          </form>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('head')
  <link rel="stylesheet" href="{{ asset('css/components/blog-editor.css') }}">
@endpush

@push('scripts')
  <script src="{{ asset('js/blog-editor.js') }}" defer></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      document.querySelectorAll('[data-tag-selector]').forEach(function (block) {
        var checkboxes = block.querySelectorAll('input[type="checkbox"][data-tag-name]');
        if (!checkboxes.length) {
          return;
        }

        var summary = block.querySelector('[data-tag-summary]');
        if (!summary) {
          return;
        }

        var list = summary.querySelector('[data-tag-summary-list]');
        var emptyState = summary.querySelector('[data-tag-summary-empty]');
        var limitMessage = summary.querySelector('[data-tag-limit]');
        var quickPickButtons = block.querySelectorAll('[data-tag-quick-pick]');
        var maxTags = parseInt(block.getAttribute('data-tag-max') || '0', 10);

        function refreshTagSummary() {
          var selectedNames = [];
          var selectedIds = [];

          checkboxes.forEach(function (checkbox) {
            var label = checkbox.closest('.blog-tag-option');
            if (label) {
              label.classList.toggle('is-selected', checkbox.checked);
            }

            if (checkbox.checked) {
              selectedNames.push(checkbox.getAttribute('data-tag-name'));
              selectedIds.push(checkbox.getAttribute('data-tag-id'));
            }
          });

          if (list) {
            list.innerHTML = '';

            if (selectedNames.length > 0) {
              selectedNames.forEach(function (name) {
                var item = document.createElement('li');
                item.className = 'blog-tag-selector-summary-chip';
                item.textContent = '#' + name;
                list.appendChild(item);
              });
              list.hidden = false;
            } else {
              list.hidden = true;
            }
          }

          if (emptyState) {
            emptyState.hidden = selectedNames.length > 0;
          }

          if (limitMessage) {
            if (maxTags > 0 && selectedNames.length >= maxTags) {
              limitMessage.hidden = false;
            } else {
              limitMessage.hidden = true;
            }
          }

          if (quickPickButtons.length) {
            quickPickButtons.forEach(function (button) {
              var tagId = button.getAttribute('data-tag-id');
              var isSelected = selectedIds.indexOf(tagId) !== -1;
              button.classList.toggle('is-selected', isSelected);
            });
          }
        }

        function enforceLimit(changedCheckbox) {
          if (!(maxTags > 0)) {
            return;
          }

          var selectedCount = 0;
          checkboxes.forEach(function (checkbox) {
            if (checkbox.checked) {
              selectedCount++;
            }
          });

          if (selectedCount > maxTags && changedCheckbox && changedCheckbox.checked) {
            changedCheckbox.checked = false;
          }
        }

        checkboxes.forEach(function (checkbox) {
          checkbox.addEventListener('change', function () {
            enforceLimit(checkbox);
            refreshTagSummary();
          });
        });

        if (quickPickButtons.length) {
          quickPickButtons.forEach(function (button) {
            button.addEventListener('click', function () {
              var tagId = button.getAttribute('data-tag-id');
              var target = Array.prototype.find.call(checkboxes, function (checkbox) {
                return checkbox.getAttribute('data-tag-id') === tagId;
              });

              if (!target) {
                return;
              }

              target.checked = !target.checked;
              enforceLimit(target);
              refreshTagSummary();
            });
          });
        }

        refreshTagSummary();
      });
    });
  </script>
@endpush
