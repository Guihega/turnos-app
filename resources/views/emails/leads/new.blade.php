<x-mail::message>
# Nuevo lead en Olinora

**{{ $lead->name }}** de **{{ $lead->organization }}** solicitó una demostración.

<x-mail::panel>
**Sector:** {{ $lead->sectorLabel() }}
**Tamaño:** {{ $lead->sizeLabel() }}
**Correo:** {{ $lead->email }}
</x-mail::panel>

@if($lead->message)
## Mensaje
> {{ $lead->message }}
@endif

## Metadatos

- **IP:** {{ $lead->ip ?? 'n/d' }}
- **User-Agent:** {{ \Illuminate\Support\Str::limit($lead->user_agent ?? 'n/d', 80) }}
- **Referrer:** {{ $lead->referrer ?? 'directo' }}
- **Recibido:** {{ $lead->created_at->format('d/M/Y H:i') }}

<x-mail::button :url="'mailto:' . $lead->email">
Responder a {{ $lead->name }}
</x-mail::button>

---

Responda en menos de 24 horas hábiles para mantener la tasa de conversión alta.

— Olinora
</x-mail::message>
