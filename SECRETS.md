# SECRETS.md

Convenciones del proyecto para credenciales sensibles. Cubre tanto el `.env` de runtime (dev y producción) como los secrets configurados en GitHub Actions para CI.

## Reglas generales

- **Nunca commitear secrets al repo.** El archivo `.env` está en `.gitignore`; solo `.env.example` (sin valores) se versiona.
- **Test mode vs live mode son canales separados.** Cada gateway externo (Stripe, MercadoPago) expone dos juegos de credenciales. La variable `STRIPE_MODE` selecciona cuál se carga en runtime; las del modo inactivo se ignoran.
- **CI usa exclusivamente test mode.** Live keys jamás se inyectan en GitHub Actions. Producción real recibe live keys vía secrets manager dedicado (decisión pendiente para Nivel C, ver `BACKLOG.md`).
- **Rotación de secrets:** ante cualquier sospecha de exposición, rotar inmediatamente en el provider (Stripe Dashboard) y actualizar los consumidores (.env local + GitHub Actions secret + producción).

---

## Stripe

### Variables de entorno

Definidas en `.env.example` con valores vacíos. El archivo `.env` real debe completarlas según el modo activo.

| Variable | Modo | Formato | Origen |
|----------|------|---------|--------|
| `BILLING_DEFAULT_GATEWAY` | Ambos | `stripe \| mercadopago \| manual` | Decisión del proyecto |
| `STRIPE_ENABLED` | Ambos | `true \| false` | Feature flag |
| `STRIPE_MODE` | Ambos | `test \| live` | Selector de credenciales |
| `STRIPE_TEST_PUBLIC_KEY` | Test | `pk_test_*` | Stripe Dashboard → modo Test → API keys |
| `STRIPE_TEST_SECRET_KEY` | Test | `sk_test_*` | Stripe Dashboard → modo Test → API keys |
| `STRIPE_TEST_WEBHOOK_SECRET` | Test | `whsec_*` | Stripe Dashboard → modo Test → Webhooks → endpoint |
| `STRIPE_LIVE_PUBLIC_KEY` | Live | `pk_live_*` | Stripe Dashboard → modo Live → API keys |
| `STRIPE_LIVE_SECRET_KEY` | Live | `sk_live_*` | Stripe Dashboard → modo Live → API keys |
| `STRIPE_LIVE_WEBHOOK_SECRET` | Live | `whsec_*` | Stripe Dashboard → modo Live → Webhooks → endpoint |

### Cómo obtener test keys

1. Ir a `https://dashboard.stripe.com/test/apikeys` (toggle "Viewing test data" activo).
2. Copiar **Publishable key** (`pk_test_*`) → `STRIPE_TEST_PUBLIC_KEY`.
3. Click en **Reveal test key** → copiar **Secret key** (`sk_test_*`) → `STRIPE_TEST_SECRET_KEY`.
4. Para webhook secret: `https://dashboard.stripe.com/test/webhooks` → crear endpoint apuntando a tu servidor → copiar **Signing secret** (`whsec_*`) → `STRIPE_TEST_WEBHOOK_SECRET`.

### Cómo obtener live keys

Idénticos pasos sobre `https://dashboard.stripe.com/apikeys` y `https://dashboard.stripe.com/webhooks` (toggle test data **apagado**).

Live keys NO se configuran en repo ni en CI. Solo en el secrets manager de producción (pendiente Nivel C).

### Webhook secrets — múltiples endpoints

Stripe genera un `whsec_*` distinto **por endpoint registrado**. Si el proyecto registra varios endpoints (ej. uno para producción, uno para staging), cada uno tiene su propio signing secret. Se documentan acá cuando se registran.

Actualmente registrados: ninguno todavía (PR-F implementó el endpoint, falta deploy con URL pública).

### Smart Retries

Configuración recomendada para pagos fallidos: Stripe Dashboard → Settings → Subscriptions and emails → activar Smart Retries con curva default (8 retries durante 3 semanas). Pendiente documentar en este archivo cuando se active en live mode (ver Nivel C en `BACKLOG.md`).

---

## GitHub Actions — Secrets configurados

Configurados en `Settings → Secrets and variables → Actions` del repo.

| Secret | Workflow consumidor | Propósito |
|--------|---------------------|-----------|
| `CIPHERSWEET_KEY` | `ci-cd.yml` | Encriptación de PII en tests (User email, phone, IP) |
| `STRIPE_TEST_SECRET_KEY` | `stripe-smoke.yml` | Test mode secret para connectivity smoke daily |

### Cómo configurar `STRIPE_TEST_SECRET_KEY` en CI

1. Obtener test secret key de Stripe Dashboard (paso "Cómo obtener test keys" arriba).
2. En GitHub: `Settings → Secrets and variables → Actions → New repository secret`.
3. Name: `STRIPE_TEST_SECRET_KEY`. Value: `sk_test_...`.
4. Save.

El workflow `stripe-smoke.yml` la inyecta vía `${{ secrets.STRIPE_TEST_SECRET_KEY }}` en el paso "Prepare environment". Si la secret no está configurada, el valor resuelve a string vacío y el test se auto-skipea con mensaje claro — el run termina verde (warning, no failure).

### Cómo disparar el smoke manualmente

GitHub UI: `Actions → Stripe Smoke → Run workflow → Run workflow` (branch `epic/billing` o cualquier otra). Útil cuando:
- Cambiaste credenciales Stripe y querés validar el switch antes del próximo schedule.
- Diagnosticando un fallo del schedule nocturno.
- Antes de mergear cambios billing significativos.

CLI alternativa: `gh workflow run stripe-smoke.yml`.

---

## Otros gateways

### MercadoPago

Pendiente — implementación post Nivel B.

### Manual gateway

Sin credenciales externas. Configurado cuando `BILLING_DEFAULT_GATEWAY=manual`.

---

## Referencias

- ADR-015: gateway contract + Stripe read adapter (`docs/billing/DECISIONS.md`)
- ADR-016: create flows (`docs/billing/DECISIONS.md`)
- PR-K: rollback CI gate, donde se descubrió la necesidad de stub del secret en tests architecture
- BACKLOG.md: Nivel C — secrets manager en producción
