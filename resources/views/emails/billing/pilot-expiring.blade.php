<x-mail::message>
# Hola,

Tu período de prueba de **{{ $planName }}** termina en **{{ $daysRemaining === 1 ? 'un día' : $daysRemaining . ' días' }}**@if ($trialEndsFormatted) (el {{ $trialEndsFormatted }})@endif.

@if ($daysRemaining >= 30)
Querémos avisarte con tiempo: en aproximadamente un mes tu acceso al servicio terminará si no elegís un plan. No tenés que hacer nada todavía — solo es un recordatorio.
@elseif ($daysRemaining >= 15)
Es un buen momento para evaluar qué plan se ajusta mejor a tu operación. Si tenés dudas sobre cuál elegir, respondé este correo y te ayudamos.
@elseif ($daysRemaining >= 7)
Tu período de prueba está por terminar. Para mantener el acceso sin interrupciones, elegí un plan antes de la fecha de vencimiento.
@elseif ($daysRemaining === 1)
Mañana termina tu período de prueba. Si querés seguir usando el servicio sin interrupción, elegí un plan hoy mismo.
@else
Para continuar usando el servicio sin interrupción, elegí un plan antes del vencimiento.
@endif

Podés elegir y activar tu plan desde el panel de control en cualquier momento.

¿Dudas? Respondé este correo y te ayudamos.

Gracias,
{{ config('app.name') }}

<small>Referencia: {{ $subscriptionId }}</small>
</x-mail::message>
