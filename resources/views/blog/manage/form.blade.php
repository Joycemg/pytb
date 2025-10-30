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
            <textarea id="content" name="content" rows="12" required>{{ old('content', $post->content) }}</textarea>
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
