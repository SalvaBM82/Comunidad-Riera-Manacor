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

ACTUALIZACIÓN V2 (instalación existente)
1. Haz una copia de seguridad de la base de datos.
2. Importa `database/migration_v2.sql` sobre la base de datos existente. No vuelvas a importar `database/database.sql`.
3. La V2 añade Administración → Usuarios y roles y Administración → Cambios de propietario.
4. Los roles iniciales son: PRESIDENTE, PROPIETARIO_ESCALERA y PROPIETARIO_SIN_ESCALERA.

ACTUALIZACIÓN V3 — ROLES Y USUARIOS CONFIGURABLES
1. Después de V2, importa `database/migration_v3.sql`.
2. Administración → Usuarios permite editar email, unidad, rol, estado y contraseña de cualquier usuario.
3. Administración → Roles permite crear y editar roles, descripción, estado y permisos.
4. Los roles tienen permisos configurables y los roles del sistema conservan su código interno.
5. El Presidente mantiene acceso completo y no puede quedar desactivado.
6. Los cambios de propietario siguen guardándose en `propietarios_historial`.
7. Mantén `config/config.php` únicamente en el hosting; no publiques credenciales reales en GitHub.

ACCESO INICIAL
Email: admin@comunidad.local
Contraseña: Cambiar123

IMPORTANTE: cambia esta contraseña inmediatamente en una versión de producción.

ESTRUCTURA
/public        -> entrada web y CSS
/app           -> lógica PHP
/config        -> configuración
/database      -> SQL
/storage       -> archivos subidos

FUNCIONALIDAD INCLUIDA
- Login y roles
- Administración de usuarios y roles
- Cambio de propietario con histórico
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
