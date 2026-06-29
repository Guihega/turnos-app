# ADR-021: Customer Gateway Enrichment Deferred

- **Status**: Accepted
- **Date**: 2026-05-28
- **Deciders**: Guillermo Herrera (sole maintainer)
- **Related**: ADR-016 (billing write contract), KI-001, KI-002, PR-X

## Context

Durante PR-X discovery (auditoría de TODOs post PR-W), se identificaron dos issues relacionados con la propagación de información del customer hacia el gateway de pago (Stripe):

1. **KI-001** (bug latente silencioso): `taxId` se acepta en el DTO `CreateCustomerInput`, se pasa al gateway, pero `StripeBillingGateway::createCustomer` lo ignora silenciosamente. Stripe registra customers sin tax_id, generando discrepancia silenciosa con la copia local.

2. **KI-002** (feature gap): `billing_address` se acepta y persiste localmente, pero ni siquiera está en el DTO que va al gateway. Stripe registra customers sin address; Stripe Tax no funciona correctamente.

El TODO original en `StripeBillingGateway:279-281` mencionaba ambos como "follow-up" sin documentar el contexto ni los bloqueos. Esto creaba una deuda invisible: futuros desarrolladores leían el TODO sin entender qué decisiones faltaban.

## Decision

**Diferir la implementación de customer enrichment en el gateway hasta que las decisiones de producto pendientes estén tomadas.**

El TODO obsoleto se reemplaza por un comentario que apunta explícitamente a `KNOWN_ISSUES.md` (KI-001 + KI-002) y a este ADR. No se implementa wiring de tax_id ni de billing_address en `StripeBillingGateway` en este PR ni en ninguno posterior **hasta que las preguntas listadas debajo estén resueltas**.

## Rationale

### Por qué diferir y no implementar a medias

Implementar el wiring sin las decisiones de producto resultaría en código que:

- Asume formatos de tax_id sin saber cuáles soporta el negocio.
- Maneja errores de validación de Stripe sin UX definida (¿se aborta el customer creation? ¿se crea sin tax_id?).
- Persiste tax_ids como plain text sin la encriptación que el docblock de `CreateCustomerInput` ya promete ("encryption happens at storage time").
- Crea un mapeo arbitrario de `street/street2/zip` (schema local) a `line1/line2/postal_code` (schema Stripe) sin saber si los renombres reflejan la intención.

Una implementación a medias **multiplica** la deuda en vez de cerrarla: futuros desarrolladores heredan código que parece terminado pero esconde decisiones implícitas. Documentar los bloqueos explícitamente (este ADR + entries en KNOWN_ISSUES) es honest engineering: el feature está pensado, las preguntas están listadas, el cierre depende de input que no tenemos hoy.

### Por qué no resolver las preguntas ahora

Las preguntas no son técnicas. Son:

**Para tax_id (KI-001)**:

1. **Qué tipos de tax_id LATAM se soportan inicialmente.** Stripe acepta tipos específicos (`mx_rfc`, `cl_tin`, `co_nit`, `ar_cuit`, `br_cnpj`, `br_cpf`, etc.). Cada uno tiene formato propio. La decisión es producto: ¿soportamos solo México en MVP? ¿LATAM completa? ¿Mundial?

2. **Cómo inferir tipo desde el string + country.** Posibles heurísticas mixtas (regex + country mapping). Cualquiera funciona técnicamente; la decisión es cuál es menos confusa para el admin del tenant.

3. **Qué hacer cuando Stripe rechaza el tax_id como inválido.** Tres opciones: (a) abortar customer creation, (b) crear customer sin tax_id y registrar warning local, (c) prompt al user para corregir. Cada una tiene UX implication.

4. **Encriptación at-rest.** Tax_id es PII en algunas jurisdicciones. El docblock del DTO ya promete encriptación pero no está implementada. ¿Laravel Crypt? ¿Field-level encryption con clave dedicada? ¿Hashing one-way para búsqueda?

5. **UX para corrección retroactiva.** ¿Comando admin `php artisan billing:backfill-tax-ids`? ¿Self-service desde Customer Portal? ¿Webhook trigger?

**Para billing_address (KI-002)**:

1. **Mapping de campos locales a Stripe.** Local usa `street/street2/zip`; Stripe usa `line1/line2/postal_code`. ¿Es 1:1 obvio o hay sutilezas (street2 ≠ line2 siempre)?

2. **Handle de address parcial.** ¿Permitimos guardar customer con solo `country`? ¿Solo `city + country`? ¿Address completa o nada?

3. **Coherencia `country`.** El DTO ya tiene `$country`. ¿Es el mismo que `billing_address.country`? ¿Cuál gana si difieren?

4. **Backfill.** Mismo issue que tax_id: customers ya creados sin address.

Sin estas decisiones, cualquier implementación es **adivinanza**. La deuda correcta es documentar las preguntas, no inventar respuestas.

### Por qué documentar en KNOWN_ISSUES en vez de solo en este ADR

KNOWN_ISSUES.md es un documento operacional con bajo costo de actualización. Cuando alguien encuentre KI-001 o KI-002 en producción (audit, customer complaint, factura sin tax_id), puede leer la entrada y entender en segundos qué está pasando y qué workaround aplicar. ADR-021 documenta la decisión técnica de diferir; KNOWN_ISSUES la información operacional para convivir con el bug. Roles distintos.

## Consequences

### Positivas

- ✅ El TODO obsoleto en `StripeBillingGateway:279-281` se elimina y se reemplaza por comentario con referencias explícitas. Cero contexto perdido.
- ✅ Discrepancia silenciosa (KI-001) y feature gap (KI-002) ahora son **visibles**. Auditoría local puede detectarlos.
- ✅ `KNOWN_ISSUES.md` inaugurado como patrón documental del proyecto. Próximos issues análogos tienen lugar establecido.
- ✅ Las 5+4 preguntas de producto quedan listadas explícitamente. Cuando llegue el momento de decidirlas, no hay re-discovery.

### Negativas / trade-offs aceptados

- ⚠️ Tenants con `tax_id` poblado siguen teniendo discrepancia con Stripe. El bug no se resuelve, solo se documenta.
- ⚠️ Customers en Stripe siguen sin billing_address. Stripe Tax no opera correctamente para esos customers.
- ⚠️ Cualquier requerimiento legal urgente de facturación con tax_id LATAM completo se convierte en bloqueo que requiere resolver este ADR antes.

### Trigger para reabrir esta decisión

Esta decisión se revisa cuando ocurra alguno de:

1. Primer tenant LATAM enterprise pidiendo facturación con tax_id válido en Stripe.
2. Compliance/legal flag por facturas sin tax_id en jurisdicciones que lo requieren.
3. Implementación de Stripe Tax automático (cálculo de impuestos por country) requiere address mínimo.
4. Decisión de producto explícita: "vamos a soportar MX RFC en MVP".

Cualquiera de estos detona una sesión para resolver las preguntas listadas y un PR de implementación que cierra KI-001 + KI-002.

## Alternatives considered

### Alternative A — Implementar tax_id only (rejected)

Resolver KI-001 ahora con decisiones improvisadas (e.g. asumir todos los tax_ids son `mx_rfc`), dejar KI-002 abierto.

**Why rejected**:

- Decisiones improvisadas generan deuda peor que la original.
- KI-001 y KI-002 comparten 4 de 5 preguntas de producto (formatos, validación, encriptación, backfill). Resolver una sin la otra es duplicar trabajo cuando llegue la siguiente.
- No hay urgencia que justifique improvisar.

### Alternative B — Convertir bug latente en excepción explícita (rejected)

`StripeBillingGateway::createCustomer` lanza excepción si `$input->taxId !== null`, en vez de ignorar silenciosamente.

**Why rejected**:

- Convierte bug silencioso en error operacional inmediato. Pilot onboarding rompe para todos los tenants con `tax_id` poblado.
- Sin discovery sobre cuántos tenants en producción tienen `tax_id`, el riesgo es desconocido. Posiblemente catastrófico.
- "Visible aunque rompa" no es mejor que "silencioso pero documentado en KNOWN_ISSUES". El segundo deja el sistema operacional y la deuda visible para quien la busque.

### Alternative C — Implementar todo ahora con decisiones improvisadas (rejected)

Resolver KI-001 + KI-002 con assumptions: solo México, sin encriptación, sin backfill, schema mapping arbitrario.

**Why rejected**:

- Las "assumptions" se vuelven deuda permanente. Cuando llegue el primer tenant fuera de México, hay refactor masivo.
- Encriptación de PII en plain text es riesgo de compliance. No debería skipearse sin decisión deliberada.
- 3-5 días de código que mañana hay que tirar.

## References

- ADR-016: Billing write contract and create flows (donde el TODO original referenciaba)
- `app/Billing/Stripe/StripeBillingGateway.php:279-281` (TODO reemplazado en PR-X)
- `app/Billing/DTOs/CreateCustomerInput.php` (DTO actual sin `$billingAddress`)
- `docs/billing/KNOWN_ISSUES.md` KI-001 + KI-002
- Stripe Tax IDs API: https://docs.stripe.com/api/customer_tax_ids
- Stripe Customer Address API: https://docs.stripe.com/api/customers/object#customer_object-address
