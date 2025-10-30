@component('mail::message')
# {{ $mesas->count() === 1 ? '¡Se abrió una mesa!' : '¡Se abrieron varias mesas!' }}

Hola **{{ $user->name ?? 'jugador/a' }}**,
{{ $mesas->count() === 1 ? 'ya podés inscribirte a esta mesa:' : 'ya podés inscribirte a estas mesas:' }}

@php $tz = config('app.timezone'); @endphp

@foreach($mesas as $m)
 **{{ $m['title'] }}**
 📅 Apertura: {{ \Illuminate\Support\Carbon::parse($m['opens_at'])->timezone($tz)->format('d/m/Y H:i') }}
 [Ir a la mesa]({{ $m['url'] }})

 @if(!$loop->last)
  ---
 @endif
@endforeach

— El equipo de La Taberna

@slot('subcopy')
Recibís este correo porque tenés activas las alertas de apertura o seguís estas mesas en **La Taberna**.
@endslot
@endcomponent