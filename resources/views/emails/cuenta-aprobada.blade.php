@component('mail::message')
# ¡Tu cuenta fue aprobada! ✅

Hola **{{ $user->name ?? 'jugador/a' }}**, ya podés entrar y anotarte a mesas, sumar **Puntos de Honor** y recibir
avisos de cupo.

@component('mail::button', ['url' => $ctaUrl ?? url('/mesas')])
Ir a las mesas
@endcomponent

Si querés recibir alertas al instante (cupo liberado, recordatorios), activá las **notificaciones** dentro del sitio.

Gracias por ser parte de **La Taberna**.

— El equipo de La Taberna
@endcomponent