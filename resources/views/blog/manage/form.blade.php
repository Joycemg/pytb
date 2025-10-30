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
                <div class="blog-editor-group" role="group" aria-label="Colores">
                  <label class="blog-editor-color" title="Color de texto">
                    <span>Texto</span>
                    <input type="color" data-color-command="foreColor" value="#1f2937">
                  </label>
                  <label class="blog-editor-color" title="Color de fondo">
                    <span>Fondo</span>
                    <input type="color" data-color-command="hiliteColor" value="#f7f8fa">
                  </label>
                </div>
                <div class="blog-editor-group" role="group" aria-label="Enlaces y multimedia">
                  <button type="button" class="blog-editor-btn" data-action="createLink" title="Insertar enlace">🔗</button>
                  <button type="button" class="blog-editor-btn" data-action="insertImage" title="Insertar imagen">🖼️</button>
                  <button type="button" class="blog-editor-btn" data-action="insertBox" title="Insertar caja resaltada">▩</button>
                </div>
              </div>
              <div id="content-editor" class="blog-editor-canvas" contenteditable="true" aria-label="Editor de contenido enriquecido"></div>
            </div>
            <textarea id="content" name="content" rows="12" required>{{ old('content', $post->content) }}</textarea>
            <small class="hint">Escribí libremente, podés aplicar colores, encabezados, imágenes remotas y cajas resaltadas. Si desactivás JavaScript, el área inferior funciona como editor plano.</small>
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
@endpush
