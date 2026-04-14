import { useState, useRef, useEffect, useMemo, useCallback } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

/* ────────────────────────────────────────────
   Design tokens (TurnosUI-compatible)
   ──────────────────────────────────────────── */
const T = {
    bg: '#0f1117',
    card: '#1a1d27',
    cardHover: '#22253a',
    border: '#2a2d3a',
    text: '#e2e8f0',
    textMuted: '#94a3b8',
    textDim: '#64748b',
    blue: '#3b82f6',
    blueHover: '#2563eb',
    blueSoft: 'rgba(59,130,246,0.1)',
    green: '#22c55e',
    greenSoft: 'rgba(34,197,94,0.1)',
    yellow: '#eab308',
    yellowSoft: 'rgba(234,179,8,0.1)',
    purple: '#a855f7',
    purpleSoft: 'rgba(168,85,247,0.1)',
    red: '#ef4444',
    redSoft: 'rgba(239,68,68,0.1)',
    orange: '#f97316',
    orangeSoft: 'rgba(249,115,22,0.1)',
    mono: "'JetBrains Mono', 'Fira Code', monospace",
    radius: '8px',
    radiusLg: '12px',
};

/* ────────────────────────────────────────────
   Section data
   ──────────────────────────────────────────── */
const SECTIONS = [
    { id: 'intro', icon: '🏠', title: 'Introducción', shortTitle: 'Inicio' },
    { id: 'onboarding', icon: '🚀', title: 'Registro y Onboarding', shortTitle: 'Registro' },
    { id: 'dashboard', icon: '📊', title: 'Panel de Administración', shortTitle: 'Admin' },
    { id: 'personalizacion', icon: '🎨', title: 'Personalización (White-Label)', shortTitle: 'Marca' },
    { id: 'anuncios', icon: '📢', title: 'Anuncios de Pantalla', shortTitle: 'Anuncios' },
    { id: 'operacion', icon: '⚡', title: 'Operación Diaria', shortTitle: 'Operación' },
    { id: 'pantalla', icon: '🖥️', title: 'Pantalla Pública', shortTitle: 'Pantalla' },
    { id: 'qr', icon: '📱', title: 'Códigos QR', shortTitle: 'QR' },
    { id: 'analytics', icon: '📈', title: 'Analytics y Reportes', shortTitle: 'Analytics' },
    { id: 'roles', icon: '👥', title: 'Roles y Permisos', shortTitle: 'Roles' },
    { id: 'seguridad', icon: '🔒', title: 'Seguridad', shortTitle: 'Seguridad' },
    { id: 'faq', icon: '❓', title: 'Preguntas Frecuentes', shortTitle: 'FAQ' },
];

/* ────────────────────────────────────────────
   Reusable sub-components
   ──────────────────────────────────────────── */

function SectionHeading({ id, icon, title }) {
    return (
        <h2 id={id} style={{
            fontSize: '1.5rem',
            fontWeight: 700,
            color: T.text,
            display: 'flex',
            alignItems: 'center',
            gap: '0.5rem',
            margin: '2.5rem 0 1rem',
            paddingTop: '1rem',
            scrollMarginTop: '80px',
        }}>
            <span style={{ fontSize: '1.3rem' }}>{icon}</span> {title}
        </h2>
    );
}

function SubHeading({ children }) {
    return (
        <h3 style={{
            fontSize: '1.1rem',
            fontWeight: 600,
            color: T.text,
            margin: '1.5rem 0 0.75rem',
        }}>
            {children}
        </h3>
    );
}

function P({ children }) {
    return (
        <p style={{
            color: T.textMuted,
            lineHeight: 1.7,
            margin: '0.5rem 0',
            fontSize: '0.925rem',
        }}>
            {children}
        </p>
    );
}

function StepList({ steps }) {
    return (
        <ol style={{ margin: '0.75rem 0', paddingLeft: 0, listStyle: 'none', display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
            {steps.map((step, i) => (
                <li key={i} style={{
                    display: 'flex',
                    gap: '0.75rem',
                    alignItems: 'flex-start',
                    background: T.card,
                    border: `1px solid ${T.border}`,
                    borderRadius: T.radius,
                    padding: '0.75rem 1rem',
                }}>
                    <span style={{
                        background: T.blueSoft,
                        color: T.blue,
                        fontWeight: 700,
                        fontSize: '0.8rem',
                        borderRadius: '50%',
                        width: 28,
                        height: 28,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        flexShrink: 0,
                        marginTop: 2,
                    }}>
                        {i + 1}
                    </span>
                    <span style={{ color: T.textMuted, lineHeight: 1.6, fontSize: '0.9rem' }}>{step}</span>
                </li>
            ))}
        </ol>
    );
}

function InfoBox({ type = 'info', children }) {
    const styles = {
        info: { bg: T.blueSoft, border: T.blue, icon: 'ℹ️' },
        tip: { bg: T.greenSoft, border: T.green, icon: '💡' },
        warning: { bg: T.yellowSoft, border: T.yellow, icon: '⚠️' },
        important: { bg: T.purpleSoft, border: T.purple, icon: '🔑' },
    };
    const s = styles[type] || styles.info;
    return (
        <div style={{
            background: s.bg,
            borderLeft: `3px solid ${s.border}`,
            borderRadius: `0 ${T.radius} ${T.radius} 0`,
            padding: '0.75rem 1rem',
            margin: '0.75rem 0',
            display: 'flex',
            gap: '0.5rem',
            alignItems: 'flex-start',
        }}>
            <span style={{ fontSize: '1rem', flexShrink: 0 }}>{s.icon}</span>
            <span style={{ color: T.textMuted, lineHeight: 1.6, fontSize: '0.9rem' }}>{children}</span>
        </div>
    );
}

function RoleBadge({ role }) {
    const colors = {
        admin: { bg: T.purpleSoft, color: T.purple },
        operador: { bg: T.blueSoft, color: T.blue },
        cliente: { bg: T.greenSoft, color: T.green },
        manager: { bg: T.orangeSoft, color: T.orange },
    };
    const c = colors[role] || colors.operador;
    return (
        <span style={{
            background: c.bg,
            color: c.color,
            fontSize: '0.75rem',
            fontWeight: 600,
            padding: '2px 8px',
            borderRadius: '4px',
            textTransform: 'uppercase',
            letterSpacing: '0.5px',
        }}>
            {role}
        </span>
    );
}

function CodeBlock({ children }) {
    return (
        <code style={{
            background: 'rgba(59,130,246,0.1)',
            color: T.blue,
            padding: '2px 6px',
            borderRadius: '4px',
            fontFamily: T.mono,
            fontSize: '0.825rem',
        }}>
            {children}
        </code>
    );
}

function KeyValue({ label, children }) {
    return (
        <div style={{ display: 'flex', gap: '0.5rem', margin: '0.25rem 0', fontSize: '0.9rem' }}>
            <span style={{ color: T.textDim, minWidth: 120, flexShrink: 0 }}>{label}:</span>
            <span style={{ color: T.textMuted }}>{children}</span>
        </div>
    );
}

function FeatureGrid({ items }) {
    return (
        <div style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))',
            gap: '0.75rem',
            margin: '0.75rem 0',
        }}>
            {items.map((item, i) => (
                <div key={i} style={{
                    background: T.card,
                    border: `1px solid ${T.border}`,
                    borderRadius: T.radius,
                    padding: '1rem',
                }}>
                    <div style={{ fontSize: '1.25rem', marginBottom: '0.5rem' }}>{item.icon}</div>
                    <div style={{ color: T.text, fontWeight: 600, fontSize: '0.9rem', marginBottom: '0.25rem' }}>{item.title}</div>
                    <div style={{ color: T.textDim, fontSize: '0.825rem', lineHeight: 1.5 }}>{item.desc}</div>
                </div>
            ))}
        </div>
    );
}

function TableSimple({ headers, rows }) {
    return (
        <div style={{ overflowX: 'auto', margin: '0.75rem 0' }}>
            <table style={{
                width: '100%',
                borderCollapse: 'collapse',
                fontSize: '0.875rem',
            }}>
                <thead>
                    <tr>
                        {headers.map((h, i) => (
                            <th key={i} style={{
                                textAlign: 'left',
                                padding: '0.5rem 0.75rem',
                                borderBottom: `2px solid ${T.border}`,
                                color: T.textDim,
                                fontWeight: 600,
                                fontSize: '0.8rem',
                                textTransform: 'uppercase',
                                letterSpacing: '0.5px',
                            }}>{h}</th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, i) => (
                        <tr key={i}>
                            {row.map((cell, j) => (
                                <td key={j} style={{
                                    padding: '0.5rem 0.75rem',
                                    borderBottom: `1px solid ${T.border}`,
                                    color: T.textMuted,
                                }}>{cell}</td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/* ────────────────────────────────────────────
   Section content components
   ──────────────────────────────────────────── */

function SectionIntro() {
    return (
        <>
            <SectionHeading id="intro" icon="🏠" title="Introducción" />
            <P>
                Bienvenido al Centro de Ayuda de Olinora. Aquí encontrarás toda la documentación necesaria para
                configurar, personalizar y operar tu sistema de gestión de turnos.
            </P>
            <P>
                Olinora es una plataforma multi-tenant (white-label) diseñada para clínicas, bancos, oficinas de gobierno
                y cualquier negocio que maneje atención al público con turnos. Soporta más de 20 países de Latinoamérica,
                Estados Unidos y España.
            </P>

            <SubHeading>¿Qué puedes hacer con Olinora?</SubHeading>
            <FeatureGrid items={[
                { icon: '🎫', title: 'Gestión de Turnos', desc: 'Emite, llama, completa y transfiere turnos en tiempo real con WebSockets.' },
                { icon: '🏢', title: 'Multi-Sucursal', desc: 'Administra múltiples sucursales desde un solo panel centralizado.' },
                { icon: '🎨', title: 'White-Label', desc: 'Personaliza marca, colores, logo y cada aspecto visual del sistema.' },
                { icon: '🖥️', title: 'Pantalla Pública', desc: 'Muestra turnos, clima, hora y anuncios multimedia en pantallas de espera.' },
                { icon: '📱', title: 'Kiosco Digital', desc: 'Permite a clientes tomar turno desde un kiosco web o código QR.' },
                { icon: '📊', title: 'Analytics', desc: 'Métricas en tiempo real, reportes diarios y KPIs de rendimiento.' },
            ]} />

            <SubHeading>Primeros pasos rápidos</SubHeading>
            <StepList steps={[
                'Completa el registro en /onboarding con los datos de tu empresa y primera sucursal.',
                'Accede al panel de administración y configura tus servicios y colas.',
                'Crea ventanillas y asigna operadores.',
                'Personaliza tu marca (logo, colores, nombre) en Personalización.',
                'Comparte el enlace del kiosco o código QR con tus clientes.',
                'Conecta una pantalla para mostrar los turnos en tiempo real.',
            ]} />

            <InfoBox type="tip">
                Puedes navegar por las secciones usando el índice lateral o la barra de búsqueda en la parte superior.
                También puedes compartir enlaces directos a cada sección usando el # en la URL (ej: /ayuda#operacion).
            </InfoBox>
        </>
    );
}

function SectionOnboarding() {
    return (
        <>
            <SectionHeading id="onboarding" icon="🚀" title="Registro y Onboarding" />
            <P>
                El proceso de registro en Olinora se realiza mediante un wizard de 3 pasos. No se usa el registro
                estándar — todo se maneja a través de la ruta /onboarding.
            </P>

            <SubHeading>Wizard de 3 pasos</SubHeading>
            <StepList steps={[
                <><strong>Cuenta personal</strong> — Ingresa tu nombre, email y contraseña. El email debe ser único en toda la plataforma y se usará para verificación.</>,
                <><strong>Datos de empresa</strong> — Nombre de la empresa, slug (URL única, ej: mi-clinica), país, teléfono opcional. El slug se valida en tiempo real con debounce para asegurar disponibilidad.</>,
                <><strong>Primera sucursal</strong> — Nombre de la sucursal, dirección con selección cascada de país → estado → ciudad (vía GeoNames), horario de operación y capacidad máxima.</>,
            ]} />

            <InfoBox type="info">
                Todo el proceso se ejecuta en una transacción de base de datos. Si algún paso falla, no se crea nada. Se crean automáticamente: el tenant, el usuario administrador, la sucursal, y las configuraciones por defecto.
            </InfoBox>

            <SubHeading>Después del registro</SubHeading>
            <P>
                Una vez completado el registro, recibirás un email de verificación. Puedes acceder al sistema
                inmediatamente, pero algunas funciones requieren email verificado. Tu cuenta se crea con el rol
                de Administrador (nivel 80), que tiene acceso completo a todas las funciones.
            </P>

            <InfoBox type="warning">
                El slug de empresa no se puede cambiar después del registro. Elige cuidadosamente ya que aparecerá en URLs y configuraciones.
            </InfoBox>
        </>
    );
}

function SectionDashboard() {
    return (
        <>
            <SectionHeading id="dashboard" icon="📊" title="Panel de Administración" />
            <P>
                El panel de administración es tu centro de control. Desde aquí puedes gestionar todos los aspectos de tu organización:
                sucursales, servicios, colas, ventanillas y usuarios.
            </P>

            <SubHeading>Dashboard principal</SubHeading>
            <P>
                El dashboard muestra métricas en tiempo real de la sucursal seleccionada: turnos en espera, turnos atendidos hoy,
                tiempo promedio de espera y tiempo promedio de atención. Se actualiza automáticamente vía WebSocket (cada 30 segundos) con
                polling de respaldo cada 15 segundos si la conexión WebSocket no está disponible.
            </P>

            <SubHeading>Sucursales</SubHeading>
            <P>
                Cada sucursal representa una ubicación física de tu organización. Al crear una sucursal, configuras:
            </P>
            <FeatureGrid items={[
                { icon: '📍', title: 'Ubicación', desc: 'País, estado y ciudad con selección automática vía GeoNames. Coordenadas GPS para el widget de clima.' },
                { icon: '🕐', title: 'Horario', desc: 'Horarios de apertura y cierre para cada día de la semana. Fuera de horario, el kiosco no emite turnos.' },
                { icon: '📊', title: 'Capacidad', desc: 'Límite máximo de turnos simultáneos en espera. Previene sobre-saturación.' },
                { icon: '🌍', title: 'Zona horaria', desc: 'Se configura automáticamente según el país seleccionado.' },
            ]} />

            <SubHeading>Servicios</SubHeading>
            <P>
                Los servicios son las categorías de atención que ofreces (ej: "Consulta General", "Urgencias", "Trámites").
                Cada servicio tiene un nombre, descripción opcional, y puede estar activo o inactivo.
            </P>

            <SubHeading>Colas</SubHeading>
            <P>
                Las colas vinculan servicios con sucursales. Una cola define: qué servicio se atiende, un prefijo para los turnos
                (ej: "CG" para Consulta General), la capacidad máxima de espera, y la prioridad relativa. Los turnos se generan
                con el formato PREFIJO-NÚMERO (ej: CG-001, CG-002).
            </P>
            <InfoBox type="tip">
                Puedes tener múltiples colas para el mismo servicio en una sucursal si necesitas separar flujos (ej: "Urgencias Adultos" y "Urgencias Pediátricos").
            </InfoBox>

            <SubHeading>Ventanillas</SubHeading>
            <P>
                Las ventanillas son los puntos de atención físicos (módulos, consultorios, cajas). Cada ventanilla tiene
                un nombre/número identificador y puede asignarse a un operador. En la pantalla pública, se muestra qué turno
                está siendo atendido en cada ventanilla.
            </P>

            <SubHeading>Usuarios</SubHeading>
            <P>
                Gestiona los usuarios de tu organización. Puedes crear usuarios con diferentes roles, asignarlos a sucursales
                específicas, y controlar sus permisos. Los usuarios reciben un email de invitación para establecer su contraseña.
            </P>
        </>
    );
}

function SectionPersonalizacion() {
    return (
        <>
            <SectionHeading id="personalizacion" icon="🎨" title="Personalización (White-Label)" />
            <P>
                Olinora es completamente personalizable. Desde la sección de Personalización puedes adaptar cada aspecto visual
                del sistema a tu marca. Los cambios se aplican en tiempo real con vista previa.
            </P>

            <SubHeading>Pestañas de configuración</SubHeading>

            <FeatureGrid items={[
                { icon: '🖼️', title: 'Marca', desc: 'Logo de la empresa, nombre comercial y colores primarios/secundarios. El logo aparece en el login, pantalla pública y kiosco.' },
                { icon: '🖥️', title: 'Pantalla', desc: 'Personaliza la pantalla pública: mostrar/ocultar clima, hora, logo. Configurar mensajes de bienvenida y despedida.' },
                { icon: '🎫', title: 'Kiosco', desc: 'Mensajes del kiosco, textos de botones, mensaje de turno emitido. Configurar detección de bots.' },
                { icon: '🎟️', title: 'Turnos', desc: 'Formato de numeración, reinicio diario automático, sonido de llamada, comportamiento de transferencias.' },
                { icon: '🔐', title: 'Seguridad', desc: 'Rate limiting configurable por IP, por sucursal. Límites por hora. Detección de bots en kiosco.' },
            ]} />

            <InfoBox type="info">
                Cada pestaña tiene una vista previa en vivo. Los cambios no se guardan hasta que presiones el botón "Guardar".
                Puedes experimentar libremente con las opciones antes de aplicarlas.
            </InfoBox>

            <SubHeading>Logo</SubHeading>
            <P>
                Sube tu logo en formato PNG, JPG o SVG. El tamaño recomendado es de 200x200 píxeles mínimo. El logo se redimensiona
                automáticamente y se muestra en la pantalla pública, el kiosco, y la página de login. Si no subes un logo, se muestra
                el nombre de tu empresa como texto.
            </P>
        </>
    );
}

function SectionAnuncios() {
    return (
        <>
            <SectionHeading id="anuncios" icon="📢" title="Anuncios de Pantalla" />
            <P>
                Los anuncios aparecen en la pantalla pública de espera y permiten comunicar información importante a los clientes
                mientras esperan. Soportan imágenes, videos y texto, con programación por fechas.
            </P>

            <SubHeading>Tipos de anuncio</SubHeading>
            <TableSimple
                headers={['Tipo', 'Icono', 'Uso recomendado']}
                rows={[
                    ['Anuncio', '📢', 'Avisos importantes, cambios de horario, mantenimiento'],
                    ['Noticia', '📰', 'Noticias de la empresa, logros, actualizaciones'],
                    ['Promoción', '🎁', 'Ofertas, descuentos, servicios nuevos'],
                ]}
            />

            <SubHeading>Media (imágenes y videos)</SubHeading>
            <P>
                Cada anuncio puede incluir una imagen o video. Los formatos soportados son JPG, PNG, GIF para imágenes
                y MP4, WEBM para videos. El tamaño máximo es de 20MB. Los archivos se guardan en el almacenamiento
                del servidor y se asocian al tenant.
            </P>

            <SubHeading>Programación</SubHeading>
            <P>
                Puedes programar anuncios con fechas de inicio y fin. Solo los anuncios activos y dentro del rango de
                fechas se muestran en la pantalla pública. La rotación entre anuncios es automática cada 8 segundos.
            </P>

            <StepList steps={[
                'Ve a Admin → Anuncios en el menú principal.',
                'Haz clic en "Nuevo Anuncio".',
                'Selecciona el tipo, escribe el título y contenido.',
                'Opcionalmente, sube una imagen o video.',
                'Configura las fechas de inicio y fin (opcional).',
                'Asigna el anuncio a una sucursal específica o a todas.',
                'Guarda. El anuncio aparecerá en la pantalla pública automáticamente.',
            ]} />

            <InfoBox type="tip">
                Los videos se reproducen sin sonido y en loop en la pantalla pública. Para mejores resultados, usa videos cortos (15-30 segundos) con texto grande visible.
            </InfoBox>
        </>
    );
}

function SectionOperacion() {
    return (
        <>
            <SectionHeading id="operacion" icon="⚡" title="Operación Diaria" />
            <P>
                Esta sección describe el flujo de trabajo diario para cada rol involucrado en la operación del sistema de turnos.
            </P>

            <SubHeading>Flujo del cliente</SubHeading>
            <P>
                El cliente interactúa con el sistema a través del kiosco (físico o web) o escaneando un código QR:
            </P>
            <StepList steps={[
                'El cliente accede al kiosco o escanea el QR de la sucursal.',
                'Selecciona el servicio deseado de la lista disponible.',
                'El sistema emite un turno con código único (ej: CG-015).',
                'El cliente ve su turno y posición en la fila.',
                'Cuando es llamado, la pantalla pública muestra su turno y ventanilla.',
                'El dispositivo del cliente vibra (si tiene la página abierta) cuando es llamado.',
                'Se dirige a la ventanilla indicada para ser atendido.',
            ]} />

            <SubHeading>Panel del operador <RoleBadge role="operador" /></SubHeading>
            <P>
                El operador gestiona la atención desde su panel dedicado. Las acciones principales son:
            </P>
            <FeatureGrid items={[
                { icon: '📞', title: 'Llamar siguiente', desc: 'Llama al siguiente turno en la cola. El turno se asigna a tu ventanilla automáticamente.' },
                { icon: '✅', title: 'Completar turno', desc: 'Marca el turno actual como completado. Las métricas de tiempo se calculan automáticamente.' },
                { icon: '🔄', title: 'Transferir', desc: 'Transfiere el turno a otra cola/servicio. El cliente mantiene su prioridad de llegada.' },
                { icon: '📊', title: 'KPIs en vivo', desc: 'Ve en tiempo real: turnos en espera, tu promedio de atención, turnos atendidos hoy.' },
            ]} />

            <InfoBox type="important">
                Las actualizaciones son en tiempo real vía WebSocket. Si la conexión se pierde, el sistema cambia automáticamente a polling cada 8-15 segundos.
            </InfoBox>

            <SubHeading>Panel del administrador <RoleBadge role="admin" /></SubHeading>
            <P>
                El administrador tiene vista panorámica de toda la operación. Desde el dashboard puede ver todas las colas,
                los operadores activos, métricas agregadas, y también puede emitir turnos manualmente. El dashboard admin
                se actualiza en tiempo real de la misma forma que el del operador.
            </P>
        </>
    );
}

function SectionPantalla() {
    return (
        <>
            <SectionHeading id="pantalla" icon="🖥️" title="Pantalla Pública" />
            <P>
                La pantalla pública está diseñada para mostrarse en televisores o monitores en la sala de espera.
                Muestra información actualizada en tiempo real sin necesidad de recargar.
            </P>

            <SubHeading>Elementos de la pantalla</SubHeading>
            <FeatureGrid items={[
                { icon: '🎫', title: 'Turnos activos', desc: 'Lista de turnos siendo atendidos: número de turno, ventanilla asignada, y servicio.' },
                { icon: '🌤️', title: 'Widget de clima', desc: 'Temperatura actual, condición y ciudad. Se actualiza cada 30 minutos vía OpenWeatherMap.' },
                { icon: '🕐', title: 'Reloj', desc: 'Hora actual de la sucursal (según su zona horaria configurada).' },
                { icon: '📢', title: 'Anuncios', desc: 'Rotación automática de anuncios activos con imágenes y videos cada 8 segundos.' },
                { icon: '📰', title: 'Noticias', desc: 'Ticker de noticias en la parte inferior de la pantalla.' },
            ]} />

            <SubHeading>Configuración</SubHeading>
            <P>
                La pantalla se accede desde el menú "Pantalla" y se puede abrir en pantalla completa (F11).
                Para mostrarla en un televisor, abre la URL en un navegador en modo kiosco. La pantalla se adapta
                automáticamente al tamaño del monitor.
            </P>

            <InfoBox type="tip">
                Para una experiencia óptima en televisores, usa un dispositivo dedicado (Chromecast, Fire Stick, mini PC)
                con el navegador en modo pantalla completa. La pantalla se actualiza sola — no requiere interacción.
            </InfoBox>

            <SubHeading>Widget de clima</SubHeading>
            <P>
                El clima se obtiene automáticamente usando las coordenadas GPS de la sucursal. Si no hay coordenadas,
                se usa la ciudad y país como respaldo. Los datos se cachean por 30 minutos para optimizar las llamadas
                a la API. Puedes habilitar o deshabilitar el clima desde Personalización → Pantalla.
            </P>
        </>
    );
}

function SectionQR() {
    return (
        <>
            <SectionHeading id="qr" icon="📱" title="Códigos QR" />
            <P>
                Los códigos QR permiten a los clientes acceder al kiosco digital desde su propio celular, sin necesidad de
                tocar una pantalla compartida. Esto es especialmente útil para reducir el contacto físico y agilizar el flujo.
            </P>

            <SubHeading>Cómo funciona</SubHeading>
            <StepList steps={[
                'Accede a Admin → Códigos QR desde el menú.',
                'Selecciona la sucursal para la que deseas generar el QR.',
                'El sistema genera un código QR que apunta directamente al kiosco de esa sucursal.',
                'Imprime el QR y colócalo en lugares visibles: entrada, sala de espera, recepción.',
                'Los clientes escanean con su celular y acceden al kiosco para tomar turno.',
            ]} />

            <InfoBox type="tip">
                Puedes imprimir el QR en diferentes tamaños. Para mejor legibilidad, recomendamos al menos 5cm x 5cm.
                El QR funciona con cualquier app de cámara o lector de QR.
            </InfoBox>

            <P>
                El enlace del kiosco también se puede compartir directamente como URL (sin necesidad de QR) por
                WhatsApp, email, o redes sociales. Cualquier persona con el enlace puede tomar un turno, siempre
                que la sucursal esté en horario de operación y haya capacidad disponible.
            </P>
        </>
    );
}

function SectionAnalytics() {
    return (
        <>
            <SectionHeading id="analytics" icon="📈" title="Analytics y Reportes" />
            <P>
                Olinora incluye herramientas de análisis para monitorear el rendimiento de tu operación y tomar
                decisiones informadas basadas en datos.
            </P>

            <SubHeading>Analytics en tiempo real <RoleBadge role="admin" /></SubHeading>
            <P>
                El módulo de Analytics muestra 6 KPIs principales y gráficos interactivos:
            </P>
            <FeatureGrid items={[
                { icon: '🎫', title: 'Turnos totales', desc: 'Cantidad total de turnos emitidos en el período seleccionado.' },
                { icon: '✅', title: 'Turnos completados', desc: 'Turnos que fueron atendidos exitosamente.' },
                { icon: '⏱️', title: 'Tiempo promedio espera', desc: 'Tiempo promedio que un cliente espera desde que toma turno hasta ser llamado.' },
                { icon: '🕐', title: 'Tiempo promedio atención', desc: 'Duración promedio de la atención por turno.' },
                { icon: '❌', title: 'Turnos cancelados', desc: 'Turnos que no fueron atendidos (expirados, cancelados).' },
                { icon: '🔄', title: 'Tasa de transferencia', desc: 'Porcentaje de turnos que fueron transferidos entre colas.' },
            ]} />

            <SubHeading>Gráficos disponibles</SubHeading>
            <P>
                Los gráficos se generan con Recharts y son interactivos (hover para ver detalles):
            </P>
            <TableSimple
                headers={['Gráfico', 'Descripción']}
                rows={[
                    ['Por hora', 'Distribución de turnos emitidos por hora del día. Identifica horas pico.'],
                    ['Tendencia', 'Evolución diaria de turnos y tiempos en el período seleccionado.'],
                    ['Por servicio', 'Comparativa de volumen y tiempos entre servicios.'],
                    ['Por operador', 'Rendimiento individual: turnos atendidos, tiempo promedio.'],
                ]}
            />

            <SubHeading>Reportes <RoleBadge role="admin" /></SubHeading>
            <P>
                La sección de Reportes muestra un resumen diario con 7 KPIs, gráfico de tendencia y rendimiento
                por operador. Los datos se pueden filtrar por sucursal y rango de fechas. Adicionalmente, el sistema
                genera un reporte semanal automático todos los lunes a las 8am que se envía vía Telegram.
            </P>
        </>
    );
}

function SectionRoles() {
    return (
        <>
            <SectionHeading id="roles" icon="👥" title="Roles y Permisos" />
            <P>
                Olinora usa un sistema de roles basado en niveles numéricos. Cada rol tiene un nivel de acceso
                que determina qué funciones puede utilizar.
            </P>

            <TableSimple
                headers={['Rol', 'Nivel', 'Descripción', 'Acceso']}
                rows={[
                    [<RoleBadge role="admin" />, '80', 'Administrador del tenant', 'Acceso total: configuración, CRUD, analytics, reportes, personalización'],
                    [<RoleBadge role="manager" />, '60', 'Gerente de sucursal', 'Gestión de sucursal: servicios, colas, operadores, reportes'],
                    [<RoleBadge role="operador" />, '40', 'Operador de ventanilla', 'Atención: llamar, completar, transferir turnos'],
                    [<><RoleBadge role="cliente" /> Staff</>, '30', 'Personal auxiliar', 'Vista limitada según permisos asignados'],
                ]}
            />

            <SubHeading>Asignación a sucursales</SubHeading>
            <P>
                Los operadores y managers se asignan a sucursales específicas. Un usuario solo puede ver y operar
                en las sucursales a las que tiene acceso. Los administradores tienen acceso a todas las sucursales
                del tenant.
            </P>

            <InfoBox type="important">
                Cada usuario pertenece a un tenant. No es posible que un usuario acceda a datos de otro tenant.
                El aislamiento multi-tenant se aplica automáticamente en cada consulta a la base de datos.
            </InfoBox>
        </>
    );
}

function SectionSeguridad() {
    return (
        <>
            <SectionHeading id="seguridad" icon="🔒" title="Seguridad" />
            <P>
                Olinora implementa múltiples capas de seguridad para proteger tu información y prevenir abusos.
            </P>

            <SubHeading>Capas de protección</SubHeading>
            <StepList steps={[
                <><strong>Nginx</strong> — Rate limiting a nivel de servidor web. Protección contra DDoS básico.</>,
                <><strong>Por IP</strong> — Límite de peticiones por dirección IP. Configurable desde Personalización → Seguridad.</>,
                <><strong>Por IP + Sucursal</strong> — Límite combinado para prevenir abuso en kioscos públicos.</>,
                <><strong>Por Sucursal por hora</strong> — Capacidad máxima de turnos por hora por sucursal.</>,
                <><strong>Detección de bots</strong> — Campos honeypot y validación de timestamp en el kiosco. Configurable.</>,
                <><strong>Validaciones de negocio</strong> — Verificación de horario, capacidad, y estado de la cola.</>,
            ]} />

            <SubHeading>Aislamiento multi-tenant</SubHeading>
            <P>
                Cada organización (tenant) tiene sus datos completamente aislados. Global Scopes en Laravel aseguran
                que las consultas a la base de datos siempre incluyan el filtro de tenant. No es posible acceder
                a datos de otro tenant ni por error ni intencionalmente.
            </P>

            <SubHeading>Autenticación</SubHeading>
            <P>
                El sistema usa Laravel Sanctum para autenticación basada en sesiones (web) y tokens (API).
                Se registra la fecha y dirección IP del último login de cada usuario. La verificación de email
                es requerida para funciones sensibles.
            </P>

            <InfoBox type="info">
                Todos los hallazgos de la auditoría de seguridad v1 (22 items) han sido corregidos y verificados.
                El sistema cuenta con 236 tests automatizados que cubren todos los aspectos de seguridad.
            </InfoBox>
        </>
    );
}

function SectionFAQ() {
    const faqs = [
        {
            q: '¿Puedo usar Olinora en varios países?',
            a: 'Sí. Olinora soporta más de 20 países de Latinoamérica, Estados Unidos y España. Cada sucursal puede estar en un país diferente, con su zona horaria y formato adecuado.'
        },
        {
            q: '¿Qué pasa si se cae la conexión a internet?',
            a: 'El sistema tiene un mecanismo de polling de respaldo. Si la conexión WebSocket se pierde, automáticamente cambia a actualizaciones periódicas (cada 8-15 segundos). Cuando la conexión se restablece, vuelve a WebSocket.'
        },
        {
            q: '¿Cuántos turnos simultáneos soporta?',
            a: 'No hay un límite fijo del sistema. El límite se configura por sucursal (capacidad máxima) y por cola. El sistema está optimizado con Redis para manejar alto volumen de turnos.'
        },
        {
            q: '¿Puedo personalizar los colores y logo?',
            a: 'Sí. Desde Personalización → Marca puedes subir tu logo y configurar los colores primarios y secundarios. Los cambios se aplican inmediatamente en toda la interfaz.'
        },
        {
            q: '¿Los clientes necesitan crear una cuenta?',
            a: 'No. Los clientes toman turno desde el kiosco o QR sin necesidad de registro. Solo necesitan seleccionar el servicio deseado.'
        },
        {
            q: '¿Cómo configuro la pantalla de espera?',
            a: 'Abre la ruta "Pantalla" desde el menú y ponla en pantalla completa (F11). Para un televisor, usa un dispositivo como Chromecast o mini PC con el navegador en modo kiosco.'
        },
        {
            q: '¿El widget de clima es obligatorio?',
            a: 'No. Puedes habilitarlo o deshabilitarlo desde Personalización → Pantalla. Si está habilitado, se configura automáticamente con las coordenadas de la sucursal.'
        },
        {
            q: '¿Puedo transferir un turno a otro servicio?',
            a: 'Sí. El operador puede transferir un turno a cualquier otra cola/servicio de la misma sucursal. El cliente mantiene su prioridad de llegada en la nueva cola.'
        },
        {
            q: '¿Cómo funciona el reinicio de numeración?',
            a: 'La numeración de turnos se reinicia automáticamente cada día. El primer turno del día comienza en 001 para cada cola.'
        },
        {
            q: '¿Hay una API disponible?',
            a: 'Sí. Olinora incluye una API RESTful con autenticación via Sanctum (tokens). Incluye 25 rutas para gestión de turnos, métricas, y endpoints públicos.'
        },
    ];

    return (
        <>
            <SectionHeading id="faq" icon="❓" title="Preguntas Frecuentes" />
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem', margin: '1rem 0' }}>
                {faqs.map((faq, i) => (
                    <FAQItem key={i} q={faq.q} a={faq.a} />
                ))}
            </div>
        </>
    );
}

function FAQItem({ q, a }) {
    const [open, setOpen] = useState(false);
    return (
        <div style={{
            background: T.card,
            border: `1px solid ${T.border}`,
            borderRadius: T.radius,
            overflow: 'hidden',
        }}>
            <button
                onClick={() => setOpen(!open)}
                style={{
                    width: '100%',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    padding: '0.875rem 1rem',
                    background: 'none',
                    border: 'none',
                    color: T.text,
                    cursor: 'pointer',
                    fontSize: '0.9rem',
                    fontWeight: 500,
                    textAlign: 'left',
                }}
            >
                {q}
                <span style={{
                    transform: open ? 'rotate(180deg)' : 'rotate(0)',
                    transition: 'transform 0.2s ease',
                    color: T.textDim,
                    fontSize: '0.75rem',
                    flexShrink: 0,
                    marginLeft: '0.5rem',
                }}>
                    ▼
                </span>
            </button>
            {open && (
                <div style={{
                    padding: '0 1rem 0.875rem',
                    color: T.textMuted,
                    fontSize: '0.875rem',
                    lineHeight: 1.6,
                    borderTop: `1px solid ${T.border}`,
                    paddingTop: '0.75rem',
                }}>
                    {a}
                </div>
            )}
        </div>
    );
}

/* ────────────────────────────────────────────
   Main Help Center component
   ──────────────────────────────────────────── */
export default function HelpIndex({ userRole }) {
    const [activeSection, setActiveSection] = useState('intro');
    const [searchQuery, setSearchQuery] = useState('');
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const contentRef = useRef(null);

    // Track active section on scroll
    useEffect(() => {
        const handleScroll = () => {
            const sections = SECTIONS.map(s => ({
                id: s.id,
                el: document.getElementById(s.id),
            })).filter(s => s.el);

            let current = sections[0]?.id || 'intro';
            for (const s of sections) {
                const rect = s.el.getBoundingClientRect();
                if (rect.top <= 120) current = s.id;
            }
            setActiveSection(current);
        };
        window.addEventListener('scroll', handleScroll, { passive: true });
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    // Handle initial hash
    useEffect(() => {
        const hash = window.location.hash.replace('#', '');
        if (hash) {
            setTimeout(() => {
                const el = document.getElementById(hash);
                if (el) el.scrollIntoView({ behavior: 'smooth' });
            }, 100);
        }
    }, []);

    const scrollTo = useCallback((id) => {
        const el = document.getElementById(id);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth' });
            window.history.replaceState(null, '', `#${id}`);
        }
        setMobileNavOpen(false);
    }, []);

    // Search filtering — highlight matching sections
    const filteredSections = useMemo(() => {
        if (!searchQuery.trim()) return SECTIONS;
        const q = searchQuery.toLowerCase();
        return SECTIONS.filter(s =>
            s.title.toLowerCase().includes(q) ||
            s.shortTitle.toLowerCase().includes(q)
        );
    }, [searchQuery]);

    return (
        <AuthenticatedLayout>
            <Head title="Centro de Ayuda" />

            <div style={{
                maxWidth: 1100,
                margin: '0 auto',
                padding: '0 1rem',
                display: 'flex',
                gap: '2rem',
                position: 'relative',
            }}>
                {/* ─── Sidebar (desktop) ─── */}
                <aside style={{
                    width: 240,
                    flexShrink: 0,
                    position: 'sticky',
                    top: 72,
                    height: 'fit-content',
                    maxHeight: 'calc(100vh - 90px)',
                    overflowY: 'auto',
                    paddingBottom: '2rem',
                    display: 'none',
                    // Show on desktop via media query workaround — inline below
                }}>
                    <SidebarContent
                        sections={filteredSections}
                        activeSection={activeSection}
                        searchQuery={searchQuery}
                        setSearchQuery={setSearchQuery}
                        scrollTo={scrollTo}
                    />
                </aside>

                {/* Desktop sidebar via style tag */}
                <style>{`
                    @media (min-width: 768px) {
                        .help-sidebar { display: block !important; }
                        .help-mobile-toggle { display: none !important; }
                    }
                    @media (max-width: 767px) {
                        .help-sidebar { display: none !important; }
                    }
                    .help-sidebar::-webkit-scrollbar { width: 4px; }
                    .help-sidebar::-webkit-scrollbar-thumb { background: ${T.border}; border-radius: 4px; }
                    .help-nav-item { transition: all 0.15s ease; }
                    .help-nav-item:hover { background: ${T.cardHover} !important; }
                    .help-search:focus { border-color: ${T.blue} !important; outline: none; }
                `}</style>

                <aside className="help-sidebar" style={{
                    width: 240,
                    flexShrink: 0,
                    position: 'sticky',
                    top: 72,
                    height: 'fit-content',
                    maxHeight: 'calc(100vh - 90px)',
                    overflowY: 'auto',
                    paddingBottom: '2rem',
                    paddingTop: '1rem',
                }}>
                    <SidebarContent
                        sections={filteredSections}
                        activeSection={activeSection}
                        searchQuery={searchQuery}
                        setSearchQuery={setSearchQuery}
                        scrollTo={scrollTo}
                    />
                </aside>

                {/* ─── Mobile nav toggle ─── */}
                <button
                    className="help-mobile-toggle"
                    onClick={() => setMobileNavOpen(!mobileNavOpen)}
                    style={{
                        position: 'fixed',
                        bottom: 20,
                        right: 20,
                        zIndex: 50,
                        background: T.blue,
                        color: '#fff',
                        border: 'none',
                        borderRadius: '50%',
                        width: 48,
                        height: 48,
                        fontSize: '1.25rem',
                        cursor: 'pointer',
                        boxShadow: '0 4px 12px rgba(0,0,0,0.3)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                    }}
                >
                    📑
                </button>

                {/* ─── Mobile sidebar overlay ─── */}
                {mobileNavOpen && (
                    <div style={{
                        position: 'fixed',
                        inset: 0,
                        zIndex: 40,
                        background: 'rgba(0,0,0,0.5)',
                    }} onClick={() => setMobileNavOpen(false)}>
                        <div
                            style={{
                                position: 'absolute',
                                bottom: 0,
                                left: 0,
                                right: 0,
                                background: T.bg,
                                borderTop: `1px solid ${T.border}`,
                                borderRadius: '16px 16px 0 0',
                                padding: '1.5rem',
                                maxHeight: '70vh',
                                overflowY: 'auto',
                            }}
                            onClick={e => e.stopPropagation()}
                        >
                            <SidebarContent
                                sections={filteredSections}
                                activeSection={activeSection}
                                searchQuery={searchQuery}
                                setSearchQuery={setSearchQuery}
                                scrollTo={scrollTo}
                            />
                        </div>
                    </div>
                )}

                {/* ─── Main content ─── */}
                <main ref={contentRef} style={{ flex: 1, minWidth: 0, paddingTop: '0.5rem', paddingBottom: '4rem' }}>
                    {/* Header */}
                    <div style={{
                        background: `linear-gradient(135deg, ${T.blueSoft}, ${T.purpleSoft})`,
                        borderRadius: T.radiusLg,
                        padding: '2rem',
                        marginBottom: '1rem',
                        border: `1px solid ${T.border}`,
                    }}>
                        <h1 style={{
                            fontSize: '1.75rem',
                            fontWeight: 700,
                            color: T.text,
                            margin: 0,
                            display: 'flex',
                            alignItems: 'center',
                            gap: '0.5rem',
                        }}>
                            📖 Centro de Ayuda
                        </h1>
                        <p style={{ color: T.textMuted, margin: '0.5rem 0 0', fontSize: '0.925rem' }}>
                            Documentación completa del sistema de gestión de turnos Olinora.
                        </p>
                    </div>

                    {/* All sections */}
                    <SectionIntro />
                    <Divider />
                    <SectionOnboarding />
                    <Divider />
                    <SectionDashboard />
                    <Divider />
                    <SectionPersonalizacion />
                    <Divider />
                    <SectionAnuncios />
                    <Divider />
                    <SectionOperacion />
                    <Divider />
                    <SectionPantalla />
                    <Divider />
                    <SectionQR />
                    <Divider />
                    <SectionAnalytics />
                    <Divider />
                    <SectionRoles />
                    <Divider />
                    <SectionSeguridad />
                    <Divider />
                    <SectionFAQ />

                    {/* Footer */}
                    <div style={{
                        marginTop: '3rem',
                        padding: '1.5rem',
                        background: T.card,
                        border: `1px solid ${T.border}`,
                        borderRadius: T.radiusLg,
                        textAlign: 'center',
                    }}>
                        <p style={{ color: T.textDim, fontSize: '0.85rem', margin: 0 }}>
                            ¿No encontraste lo que buscas? Contáctanos en{' '}
                            <span style={{ color: T.blue }}>soporte@olinora.com.mx</span>
                        </p>
                    </div>
                </main>
            </div>
        </AuthenticatedLayout>
    );
}

/* ─── Sidebar content ─── */
function SidebarContent({ sections, activeSection, searchQuery, setSearchQuery, scrollTo }) {
    return (
        <>
            {/* Search */}
            <div style={{ marginBottom: '1rem' }}>
                <input
                    className="help-search"
                    type="text"
                    value={searchQuery}
                    onChange={e => setSearchQuery(e.target.value)}
                    placeholder="Buscar sección..."
                    style={{
                        width: '100%',
                        padding: '0.5rem 0.75rem',
                        background: T.card,
                        border: `1px solid ${T.border}`,
                        borderRadius: T.radius,
                        color: T.text,
                        fontSize: '0.825rem',
                        outline: 'none',
                        boxSizing: 'border-box',
                    }}
                />
            </div>

            {/* Nav items */}
            <nav style={{ display: 'flex', flexDirection: 'column', gap: '2px' }}>
                {sections.map(s => {
                    const isActive = activeSection === s.id;
                    return (
                        <button
                            key={s.id}
                            className="help-nav-item"
                            onClick={() => scrollTo(s.id)}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: '0.5rem',
                                padding: '0.5rem 0.75rem',
                                background: isActive ? T.blueSoft : 'transparent',
                                border: 'none',
                                borderRadius: '6px',
                                cursor: 'pointer',
                                textAlign: 'left',
                                width: '100%',
                                borderLeft: isActive ? `3px solid ${T.blue}` : '3px solid transparent',
                            }}
                        >
                            <span style={{ fontSize: '0.9rem', flexShrink: 0 }}>{s.icon}</span>
                            <span style={{
                                fontSize: '0.825rem',
                                color: isActive ? T.blue : T.textMuted,
                                fontWeight: isActive ? 600 : 400,
                                whiteSpace: 'nowrap',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                            }}>
                                {s.title}
                            </span>
                        </button>
                    );
                })}
            </nav>

            {sections.length === 0 && searchQuery && (
                <p style={{ color: T.textDim, fontSize: '0.8rem', textAlign: 'center', marginTop: '1rem' }}>
                    No se encontraron secciones
                </p>
            )}
        </>
    );
}

function Divider() {
    return <hr style={{ border: 'none', borderTop: `1px solid ${T.border}`, margin: '1.5rem 0' }} />;
}
