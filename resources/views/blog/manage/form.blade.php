@extends('layouts.app')

@section('title', $post->exists ? 'Editar entrada' : 'Nueva entrada')

@section('content')
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
        <form method="post" action="{{ $post->exists ? route('blog.update', $post) : route('blog.store') }}" enctype="multipart/form-data" class="form">
          @csrf
          @if ($post->exists)
            @method('put')
          @endif

          <div class="form-group">
            <label for="title">Título</label>
            <input id="title" name="title" type="text" value="{{ old('title', $post->title) }}" required>
          </div>

          <div class="form-group">
            <label for="slug">Slug (opcional)</label>
            <input id="slug" name="slug" type="text" value="{{ old('slug', $post->slug) }}" placeholder="mi-entrada-super-epica">
            <small class="hint">Se usará en la URL. Si se deja vacío se generará automáticamente.</small>
          </div>

          <div class="form-group">
            <label for="excerpt">Resumen</label>
            <textarea id="excerpt" name="excerpt" rows="3" maxlength="500">{{ old('excerpt', $post->excerpt) }}</textarea>
          </div>

          <div class="form-group">
            <label for="content">Contenido</label>
            <div class="blog-editor" data-blog-editor data-target="content">
              <div class="blog-editor-toolbar" role="toolbar" aria-label="Herramientas de formato">
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
                      <button type="button" class="blog-editor-swatch" data-color-value="#1F2937" style="--swatch:#1F2937" title="Pizarra"></button>
                      <button type="button" class="blog-editor-swatch" data-color-value="#0F172A" style="--swatch:#0F172A" title="Azul profundo"></button>
                      <button type="button" class="blog-editor-swatch" data-color-value="#DB2777" style="--swatch:#DB2777" title="Magenta"></button>
                      <button type="button" class="blog-editor-swatch" data-color-value="#2563EB" style="--swatch:#2563EB" title="Azul" ></button>
                      <button type="button" class="blog-editor-swatch" data-color-value="#16A34A" style="--swatch:#16A34A" title="Verde"></button>
                      <button type="button" class="blog-editor-swatch" data-color-custom title="Color personalizado">Personalizar</button>
                    </div>
                  </div>
                  <div class="blog-editor-palette" data-color-command="hiliteColor" aria-label="Colores de fondo">
                    <span class="blog-editor-group-label">Fondo</span>
                    <div class="blog-editor-swatches">
                      <button type="button" class="blog-editor-swatch" data-color-value="#F8FAFC" style="--swatch:#F8FAFC" title="Niebla"></button>
                      <button type="button" class="blog-editor-swatch" data-color-value="#FDE68A" style="--swatch:#FDE68A" title="Dorado"></button>
                      <button type="button" class="blog-editor-swatch" data-color-value="#E0F2FE" style="--swatch:#E0F2FE" title="Cielo"></button>
                      <button type="button" class="blog-editor-swatch" data-color-value="#DCFCE7" style="--swatch:#DCFCE7" title="Prado"></button>
                      <button type="button" class="blog-editor-swatch" data-color-value="#F3E8FF" style="--swatch:#F3E8FF" title="Lavanda"></button>
                      <button type="button" class="blog-editor-swatch" data-color-custom title="Color personalizado">Personalizar</button>
                    </div>
                  </div>
                </div>
                <div class="blog-editor-group" role="group" aria-label="Enlaces y multimedia">
                  <button type="button" class="blog-editor-btn" data-action="createLink" title="Insertar enlace">🔗</button>
                  <button type="button" class="blog-editor-btn" data-action="insertImage" title="Insertar imagen">🖼️</button>
                  <button type="button" class="blog-editor-btn" data-action="insertBox" title="Insertar caja resaltada">▩</button>
                </div>
              </div>
              <div id="content-editor" class="blog-editor-canvas" contenteditable="true" aria-label="Editor de contenido enriquecido"></div>
            </div>
            <textarea id="content" name="content" rows="12" required>@php echo old('content', $post->content); @endphp</textarea>
            <small class="hint">Escribí libremente, podés aplicar colores, encabezados, imágenes remotas y cajas resaltadas. Si desactivás JavaScript, el área inferior funciona como editor plano.</small>
          </div>

          @php
            $themes = (array) config('blog.themes', []);
            $defaultTheme = (string) config('blog.default_theme', 'classic');
            $currentTheme = old('theme', $post->theme ?? $defaultTheme);
            if (!array_key_exists($currentTheme, $themes)) {
                $currentTheme = $defaultTheme;
            }
            $accentPalette = (array) config('blog.accent_palette', []);
            $textPalette = (array) config('blog.text_palette', []);
            $currentAccent = old('accent_color', $post->accent_color ?? ($themes[$currentTheme]['accent'] ?? config('blog.default_accent')));
            $currentTextAccent = old('accent_text_color', $post->accent_text_color ?? ($themes[$currentTheme]['text'] ?? config('blog.default_text_color')));
          @endphp

          <div class="form-group">
            <label>Personalización visual</label>
            <div class="blog-customizer" data-blog-customizer data-theme-input="#theme" data-accent-input="#accent_color" data-text-input="#accent_text_color">
              <div class="blog-theme-selector" data-blog-theme-picker>
                @foreach ($themes as $value => $theme)
                  <button type="button" class="blog-theme-option{{ $value === $currentTheme ? ' is-active' : '' }}" data-theme-value="{{ $value }}" data-theme-accent="{{ $theme['accent'] ?? '#2563EB' }}" data-theme-text="{{ $theme['text'] ?? '#0F172A' }}" style="--blog-theme-preview: {{ $theme['preview'] ?? 'linear-gradient(135deg, rgba(37,99,235,.12) 0%, rgba(255,255,255,.9) 100%)' }}">
                    <span class="blog-theme-option-accent" style="background: {{ $theme['accent'] ?? '#2563EB' }}"></span>
                    <span class="blog-theme-option-name">{{ $theme['label'] ?? ucfirst($value) }}</span>
                  </button>
                @endforeach
              </div>

              <div class="blog-customizer-row">
                <div class="blog-color-picker" data-blog-color-picker data-input="#accent_color" data-role="accent">
                  <p class="blog-color-picker-title">Color de acento</p>
                  <div class="blog-color-swatches">
                    @foreach ($accentPalette as $color => $label)
                      <button type="button" class="blog-color-swatch{{ strtoupper($color) === strtoupper($currentAccent) ? ' is-active' : '' }}" data-color-value="{{ $color }}" style="--swatch-color: {{ $color }}" title="{{ $label }}">
                        <span class="sr-only">{{ $label }}</span>
                      </button>
                    @endforeach
                    <button type="button" class="blog-color-swatch is-custom" data-color-custom title="Elegir otro color">✦</button>
                  </div>
                </div>

                <div class="blog-color-picker" data-blog-color-picker data-input="#accent_text_color" data-role="text">
                  <p class="blog-color-picker-title">Color de titulares y botones</p>
                  <div class="blog-color-swatches">
                    @foreach ($textPalette as $color => $label)
                      <button type="button" class="blog-color-swatch{{ strtoupper($color) === strtoupper($currentTextAccent) ? ' is-active' : '' }}" data-color-value="{{ $color }}" style="--swatch-color: {{ $color }}" title="{{ $label }}">
                        <span class="sr-only">{{ $label }}</span>
                      </button>
                    @endforeach
                    <button type="button" class="blog-color-swatch is-custom" data-color-custom title="Elegir otro color">✦</button>
                  </div>
                </div>
              </div>

              <div class="blog-customizer-preview" data-blog-customizer-preview>
                <div class="blog-customizer-preview-card">
                  <span class="blog-customizer-pill">Vista previa</span>
                  <h3>Así se verá tu entrada</h3>
                  <p>Los títulos, botones y acentos usan tus colores seleccionados. Podés probar diferentes combinaciones antes de publicar.</p>
                </div>
              </div>
            </div>
            <input type="hidden" id="theme" name="theme" value="{{ $currentTheme }}">
            <input type="hidden" id="accent_color" name="accent_color" value="{{ strtoupper($currentAccent ?? '') }}">
            <input type="hidden" id="accent_text_color" name="accent_text_color" value="{{ strtoupper($currentTextAccent ?? '') }}">
            <small class="hint">Elegí un estilo distintivo para la portada y el contenido. Los colores personalizados también aplican en la lista del blog.</small>
          </div>

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

          <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection

@push('head')
  <link rel="stylesheet" href="{{ asset('css/components/blog-editor.css') }}">
@endpush

@push('scripts')
  <script src="{{ asset('js/blog-editor.js') }}" defer></script>
  <script src="{{ asset('js/blog-customizer.js') }}" defer></script>
@endpush
