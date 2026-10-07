GESTIÓN COMUNIDAD — PHP + MySQL
================================

REQUISITOS
- PHP 8.1+ (recomendado 8.2/8.3)
- MySQL 5.7+/MariaDB 10.4+
- Extensión PDO MySQL habilitada.

INSTALACIÓN
1. Crea una base de datos o importa directamente database/database.sql.
2. Edita config/config.php con host, nombre, usuario y contraseña de MySQL.
3. Sube el contenido de la carpeta al hosting.
4. Apunta el dominio/subdominio a la carpeta public/ (recomendado).
   Si no puedes usar public/ como raíz, configura el DocumentRoot del hosting.
5. Abre la web.

ACCESO INICIAL
Email: admin@comunidad.local
Contraseña: Cambiar123!

IMPORTANTE: cambia esta contraseña inmediatamente en una versión de producción.

ESTRUCTURA
/public        -> entrada web y CSS
/app           -> lógica PHP
/config        -> configuración
/database      -> SQL
/storage       -> archivos subidos

FUNCIONALIDAD INCLUIDA
- Login y roles
- Dashboard
- Unidades y coeficientes
- Gastos GENERAL / ESCALERA
- Presupuestos
- Generación mensual de recibos
- Derramas
- Informe de morosidad
- Incidencias
- Tablas para documentos, votaciones, votos, intereses y logs
- Función central calcularReparto()

REGLA DE REPARTO
GENERAL: Local 20%, Garaje 20%, Piso 1 40%, Piso 2 20%
ESCALERA: Piso 1 50%, Piso 2 50%; Local y Garaje 0%

Esta entrega es una base funcional inicial. Antes de ponerla en producción conviene completar:
- recuperación/cambio de contraseña
- subida real de documentos
- certificado de deuda PDF Art. 21 LPH
- cálculo de intereses por tramos
- adelantos con pantalla y trazabilidad completa
- pagos parciales
- votaciones completas
- permisos finos por tipo de gasto
- protección CSRF, rate limiting y endurecimiento de producción
- generación de actas/notificaciones por email
