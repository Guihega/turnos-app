# Billing — Known Issues

Bugs reproducibles, inconsistencias, o features parcialmente
implementadas en el subsistema billing que **no se resuelven
inmediatamente** por dependencia de decisiones de producto, costo
desproporcionado, o ambos.

Cada entrada incluye:

- **Tipo**: bug latente (silencioso, requiere fix) vs feature gap
  (incompleto, requiere completar).
- **Repro** o **estado actual**.
- **Impacto** real.
- **Workaround** si aplica.
- **Plan de resolución**.

Cuando un issue se resuelva, su entrada se elimina y la fix queda en
el PR correspondiente.

---

## KI-001: Tax ID no se propaga a Stripe al crear customer

**Tipo**: Bug latente silencioso
**Estado**: Open
**Surgida en**: PR-X discovery (auditoría de TODOs post PR-W)
**Plan de resolución**: ver ADR-021 (decisión de diferir hasta product input)

### Repro

1. Tenant con columna `tenants.tax_id` poblada (RFC, RUT, NIT, etc.).
2. Trigger pilot onboarding (`CreatePilotCustomerAction::execute`).
3. El action construye `CreateCustomerInput` con `taxId: $tenant->tax_id` y lo pasa al gateway vía `BillingGatewayWriter::createCustomer`.
4. `StripeBillingGateway::createCustomer` **ignora silenciosamente** el campo `$input->taxId`. El wiring a `tax_ids->create()` nunca fue implementado.
5. Stripe registra el customer **sin tax_id**.
6. `Customer` local persiste `tax_id` en columna propia (`customers.tax_id`).

### Impacto

**Discrepancia silenciosa entre fuente local y gateway**:

- Customer local: tiene tax_id.
- Customer en Stripe: NO tiene tax_id.
- Facturas emitidas por Stripe salen sin tax_id.
- Reportes locales que asumen consistencia gateway↔local pueden mostrar info contradictoria.

**Severidad**: media-alta para tenants con requirement legal de
facturación con tax_id (México RFC, Argentina CUIT, etc.). Para
tenants sin ese requirement, el bug no se manifiesta.

**Sin signal de error**: no hay excepción, ni log, ni warning. El bug
es **completamente silencioso** hasta que alguien audita facturas vs
registros locales.

### Workaround actual

- **Operacional**: si un tenant necesita tax_id en facturas Stripe,
  agregarlo manualmente en el dashboard de Stripe después del
  onboarding. No hay automatización.
- **Defensivo**: el flow no se bloquea ni rompe; el tenant simplemente
  queda con la discrepancia.

### Por qué no se resuelve ya

Decisiones de producto pendientes (ver ADR-021 §Context). En resumen:

1. Qué tipos de tax_id LATAM se soportan (Stripe requiere tipo
   específico: `mx_rfc`, `cl_tin`, `co_nit`, etc.).
2. Cómo inferir tipo desde el string + `country`.
3. Validación de formato.
4. Encriptación at-rest (PII en algunas jurisdicciones).
5. UX para corrección retroactiva de customers ya creados.

### Referencias

- `app/Billing/Stripe/StripeBillingGateway.php:279` (call site afectado)
- `app/Billing/DTOs/CreateCustomerInput.php:31` (`$taxId` aceptado en DTO)
- `app/Actions/Billing/CreatePilotCustomerAction.php:58` (pasa `tax_id` desde tenant)
- Stripe Tax IDs API: https://docs.stripe.com/api/customer_tax_ids
- ADR-021 (decisión de diferir)

---

## KI-002: billing_address no se propaga al gateway (feature gap, no bug silencioso)

**Tipo**: Feature gap (incomplete implementation)
**Estado**: Open
**Surgida en**: PR-X discovery (misma auditoría que KI-001)
**Plan de resolución**: ver ADR-021 (atado al mismo paquete que tax_id)

### Estado actual

A diferencia de KI-001, billing_address **NO genera discrepancia
silenciosa**, porque el campo nunca intenta cruzar la frontera
DTO→gateway:

- `StoreCustomerRequest:48-54` valida estructura completa: `street`, `street2`, `city`, `state`, `zip`, `country`.
- `CreateCustomerAction:134` persiste `billing_address` en columna JSON de `Customer` local.
- **`CreateCustomerInput` DTO (línea ~25-34) NO incluye `$billingAddress`** — solo `email`, `name`, `country`, `taxId`, `metadata`.
- Por lo tanto, `StripeBillingGateway::createCustomer` no recibe ni intenta enviar billing_address. No hay pérdida silenciosa porque no hay envío.

El TODO original en `StripeBillingGateway:280` decía "Same for
billing_address" pero esa afirmación era **especulativa**: no hay
nada que mapear desde un DTO que no lleva el campo.

### Impacto

**Asimétrico vs KI-001**:

- Customers locales tienen billing_address completa.
- Customers en Stripe **están sin address**.
- Stripe Tax (cálculo automático de impuestos por jurisdicción)
  **no puede funcionar correctamente** sin address.
- Facturas Stripe salen sin dirección de billing.
- No hay falsa sensación de consistencia: cualquiera que mire al
  customer en Stripe ve que falta el address.

**Severidad**: media. Menor que KI-001 porque no es bug silencioso, es
ausencia visible. Pero impacta facturación, Stripe Tax, y compliance
fiscal en jurisdicciones que requieren dirección en facturas.

### Workaround actual

- **Operacional**: agregar address manualmente en Stripe dashboard
  después del onboarding cuando se necesita.
- **Defensivo**: el flow no rompe; Stripe acepta customers sin address.

### Por qué no se resuelve ya

Decisiones de producto pendientes (ver ADR-021 §Context):

1. Mapping de schema local (`street`, `street2`, `zip`) a Stripe
   (`line1`, `line2`, `postal_code`). Renombres no son 1:1.
2. Handle de address parcial (¿qué si llega solo `city` + `country`?).
3. Atar `country` del address con `country` del customer (que ya
   existe en el DTO como `string`).
4. Backfill para customers ya creados sin address.

### Referencias

- `app/Billing/Stripe/StripeBillingGateway.php:279-281` (TODO obsoleto que mencionaba este gap)
- `app/Billing/DTOs/CreateCustomerInput.php` (DTO actual sin `$billingAddress`)
- `app/Http/Requests/Billing/StoreCustomerRequest.php:48-54` (request validation completa)
- `app/Models/Billing/Customer.php:30,51,58` (persistencia local)
- Stripe Customer address API: https://docs.stripe.com/api/customers/object#customer_object-address
- ADR-021 (decisión de diferir)
