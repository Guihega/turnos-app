// resources/js/Pages/Legal/Privacy.jsx
import LegalPageLayout from '@/Components/LegalPageLayout';

export default function Privacy() {
    return (
        <LegalPageLayout title="Política de Privacidad" lastUpdated="15 de abril de 2026">
            <p>
                En Olinora ("nosotros", "nuestro" o "la plataforma"), nos comprometemos a proteger la privacidad de nuestros usuarios. Esta Política de Privacidad describe cómo recopilamos, usamos, almacenamos y protegemos tu información personal cuando utilizas nuestro sistema de gestión de turnos disponible en olinora.com.mx.
            </p>
            <p>
                Al registrarte o utilizar Olinora, aceptas las prácticas descritas en esta política. Si no estás de acuerdo, por favor no utilices la plataforma.
            </p>

            <h2>1. Información que Recopilamos</h2>

            <h3>1.1 Información proporcionada directamente</h3>
            <p>Cuando te registras o utilizas Olinora, podemos recopilar:</p>
            <ul>
                <li>Nombre completo</li>
                <li>Dirección de correo electrónico</li>
                <li>Nombre de la empresa u organización</li>
                <li>Teléfono de la empresa</li>
                <li>Contraseña (almacenada de forma encriptada)</li>
                <li>Información de la sucursal: dirección, ciudad, estado, país, zona horaria y coordenadas geográficas</li>
            </ul>

            <h3>1.2 Información de inicio de sesión social (OAuth)</h3>
            <p>
                Si eliges iniciar sesión con Google o Facebook, recibimos de estos proveedores tu nombre, correo electrónico e identificador de cuenta. No accedemos a tus contactos, publicaciones, fotos ni ningún otro dato de tus redes sociales. Los tokens de acceso se almacenan de forma encriptada y se utilizan únicamente para autenticación.
            </p>

            <h3>1.3 Información recopilada automáticamente</h3>
            <ul>
                <li>Dirección IP al iniciar sesión</li>
                <li>Fecha y hora del último acceso</li>
                <li>Datos de uso del sistema: turnos emitidos, completados y métricas operativas</li>
                <li>Información del navegador y dispositivo (a través de cookies estándar de sesión)</li>
            </ul>

            <h3>1.4 Información de clientes finales</h3>
            <p>
                Los turnos emitidos a través del kiosco público no requieren datos personales del cliente final. El sistema genera un número de turno sin vincular información personal de quien lo solicita.
            </p>

            <h2>2. Cómo Usamos tu Información</h2>
            <p>Utilizamos la información recopilada para:</p>
            <ul>
                <li>Crear y administrar tu cuenta en la plataforma</li>
                <li>Proveer el servicio de gestión de turnos a tu organización</li>
                <li>Enviar correos transaccionales: verificación de email y recuperación de contraseña</li>
                <li>Generar métricas y reportes de rendimiento para tu organización</li>
                <li>Mostrar información relevante en la pantalla pública (clima, hora local)</li>
                <li>Mantener la seguridad de la plataforma y prevenir abusos</li>
                <li>Mejorar y optimizar el servicio</li>
            </ul>

            <h2>3. Compartición de Datos</h2>
            <p>No vendemos, alquilamos ni compartimos tu información personal con terceros para fines de marketing. Compartimos datos únicamente con:</p>
            <ul>
                <li><strong>Proveedores de servicios esenciales:</strong> servicios de email transaccional (Resend), alojamiento (DigitalOcean), datos geográficos (GeoNames) y datos climatológicos (OpenWeatherMap). Estos proveedores procesan datos estrictamente para prestar el servicio.</li>
                <li><strong>Proveedores de autenticación:</strong> Google y Facebook, únicamente si eliges vincular tu cuenta con estos servicios.</li>
                <li><strong>Requerimientos legales:</strong> cuando sea necesario para cumplir con la ley, una orden judicial o un proceso legal.</li>
            </ul>

            <h2>4. Aislamiento de Datos (Multi-Tenant)</h2>
            <p>
                Olinora es una plataforma multi-tenant. Esto significa que cada organización tiene sus datos completamente aislados. Los usuarios de una organización no pueden ver, acceder ni modificar los datos de otra organización. Este aislamiento se garantiza a nivel de base de datos y se verifica con pruebas de seguridad automatizadas.
            </p>

            <h2>5. Seguridad</h2>
            <p>Implementamos medidas de seguridad para proteger tu información:</p>
            <ul>
                <li>Conexión cifrada mediante SSL/TLS (HTTPS) en todo momento</li>
                <li>Contraseñas almacenadas con hash seguro (bcrypt)</li>
                <li>Tokens de autenticación social encriptados en la base de datos</li>
                <li>Protección contra ataques de fuerza bruta con rate limiting multicapa</li>
                <li>Firewall activo y monitoreo continuo del sistema</li>
                <li>Respaldos diarios automatizados de la base de datos</li>
                <li>Validación explícita de permisos y propiedad en cada operación</li>
            </ul>

            <h2>6. Cookies</h2>
            <p>
                Olinora utiliza cookies estrictamente necesarias para el funcionamiento del sistema: cookies de sesión para mantener tu autenticación y cookies de preferencia para el tema visual (claro/oscuro). No utilizamos cookies de rastreo, publicidad ni análisis de terceros.
            </p>

            <h2>7. Retención de Datos</h2>
            <p>
                Conservamos tu información mientras tu cuenta esté activa. Los datos de turnos se retienen por 90 días para fines de reportes, después de lo cual se eliminan automáticamente. Si eliminas tu cuenta, tus datos personales se borran de forma permanente. Los respaldos se retienen por un periodo limitado por razones técnicas.
            </p>

            <h2>8. Tus Derechos</h2>
            <p>Tienes derecho a:</p>
            <ul>
                <li><strong>Acceder</strong> a tu información personal desde la sección "Perfil" de tu cuenta</li>
                <li><strong>Rectificar</strong> tu nombre y datos desde tu perfil</li>
                <li><strong>Eliminar</strong> tu cuenta y datos personales desde la sección "Eliminar cuenta" en tu perfil</li>
                <li><strong>Desvincular</strong> cuentas sociales (Google/Facebook) desde tu perfil</li>
                <li><strong>Solicitar</strong> información sobre los datos que tenemos sobre ti enviando un correo a contacto@olinora.com.mx</li>
            </ul>

            <h2>9. Menores de Edad</h2>
            <p>
                Olinora no está dirigido a menores de 18 años. No recopilamos intencionalmente información de menores. Si descubrimos que hemos recopilado datos de un menor, los eliminaremos de inmediato.
            </p>

            <h2>10. Cambios a esta Política</h2>
            <p>
                Podemos actualizar esta política periódicamente. Publicaremos cualquier cambio en esta página con la fecha de actualización correspondiente. El uso continuado de la plataforma después de los cambios constituye tu aceptación de la política actualizada.
            </p>

            <h2>11. Contacto</h2>
            <p>
                Si tienes preguntas sobre esta Política de Privacidad o sobre el manejo de tus datos, contáctanos en:
            </p>
            <ul>
                <li>Email: <a href="mailto:contacto@olinora.com.mx">contacto@olinora.com.mx</a></li>
                <li>Sitio web: <a href="https://olinora.com.mx">olinora.com.mx</a></li>
            </ul>
        </LegalPageLayout>
    );
}
