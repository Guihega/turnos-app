<x-mail::message>
# Hola,

Tu último pago de **{{ $planName }}** no pudo procesarse.

No te preocupes: tu acceso al servicio sigue activo por unos días mientras
intentamos cobrar nuevamente. Para evitar interrupciones, actualizá tu
método de pago lo antes posible.

Si los reintentos automáticos no logran procesar el pago, tu suscripción
será suspendida.

¿Dudas? Respondé este correo y te ayudamos.

Gracias,
{{ config('app.name') }}

<small>Referencia: {{ $subscriptionId }}</small>
</x-mail::message>
