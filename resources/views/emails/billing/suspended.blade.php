<x-mail::message>
# Suscripción suspendida

Tu suscripción a **{{ $planName }}** quedó suspendida porque no pudimos
procesar el pago tras varios intentos.

Tus datos siguen seguros. Para reactivar el servicio, actualizá tu método
de pago y contactanos.

¿Necesitás ayuda? Respondé este correo.

Gracias,
{{ config('app.name') }}

<small>Referencia: {{ $subscriptionId }}</small>
</x-mail::message>
