<?php
// Sesiones con cookies seguras y protección básica contra fijación de sesión.
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}
require_once __DIR__.'/db.php';

function require_login() {
    $u = current_user();
    if (!$u) {
        header('Location: index.php?page=login');
        exit;
    }
}

function current_user() {
    global $pdo;
    static $loaded = false;
    static $user = null;
    if ($loaded) return $user;
    $loaded = true;

    $id = (int)($_SESSION['user']['id'] ?? 0);
    if (!$id) return null;

    try {
        $st = $pdo->prepare("SELECT u.*, un.nombre AS unidad_nombre, un.tiene_acceso_escalera,
                    r.nombre AS rol_nombre, r.activo AS rol_activo
                FROM usuarios u
                LEFT JOIN unidades un ON un.id=u.unidad_id
                LEFT JOIN roles r ON r.id=u.rol_id
                WHERE u.id=? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();

        if (!$row || !(int)$row['activo']) {
            $_SESSION = [];
            return null;
        }

        // Un rol desactivado no permite acceso a ninguna función, salvo que sea Presidente.
        if (($row['rol'] ?? '') !== 'PRESIDENTE' && array_key_exists('rol_activo', $row) && !(int)$row['rol_activo']) {
            $_SESSION = [];
            return null;
        }

        $user = $row;
        $_SESSION['user'] = $row;
        return $user;
    } catch (Throwable $e) {
        // Compatibilidad temporal con una instalación que todavía no haya ejecutado V3.
        $user = $_SESSION['user'] ?? null;
        return $user;
    }
}

function role_label($rol) {
    global $pdo;
    static $cache = [];
    if (!$rol) return '';
    if (isset($cache[$rol])) return $cache[$rol];

    try {
        $st=$pdo->prepare("SELECT nombre FROM roles WHERE codigo=? LIMIT 1");
        $st->execute([$rol]);
        $name=$st->fetchColumn();
        if ($name) return $cache[$rol]=$name;
    } catch(Throwable $e) {
        // La tabla roles puede no existir todavía en una instalación anterior a V3.
    }

    $labels = [
        'PRESIDENTE'=>'Presidente',
        'PROPIETARIO_ESCALERA'=>'Propietario con escalera',
        'PROPIETARIO_SIN_ESCALERA'=>'Propietario sin escalera',
        'PROPIETARIO'=>'Propietario'
    ];
    return $cache[$rol]=$labels[$rol]??$rol;
}

function can($permission) {
    global $pdo;
    $u=current_user();
    if (!$u) return false;
    if (($u['rol']??'')==='PRESIDENTE') return true;

    try {
        $st=$pdo->prepare("SELECT 1
            FROM usuarios u
            JOIN roles r ON r.id=u.rol_id AND r.activo=1
            JOIN rol_permisos rp ON rp.rol_id=r.id
            JOIN permisos p ON p.id=rp.permiso_id AND p.codigo=?
            WHERE u.id=? AND u.activo=1 LIMIT 1");
        $st->execute([$permission,$u['id']]);
        return (bool)$st->fetchColumn();
    } catch(Throwable $e) {
        return false;
    }
}

function is_president() {
    $u=current_user();
    return $u && ($u['rol']??'')==='PRESIDENTE';
}

function login_user($email,$password) {
    global $pdo;
    $st=$pdo->prepare("SELECT u.*, un.nombre AS unidad_nombre, un.tiene_acceso_escalera,
                r.nombre AS rol_nombre, r.activo AS rol_activo
            FROM usuarios u
            LEFT JOIN unidades un ON un.id=u.unidad_id
            LEFT JOIN roles r ON r.id=u.rol_id
            WHERE u.email=? AND u.activo=1");
    $st->execute([$email]);
    $u=$st->fetch();

    if ($u && ($u['rol']??'PRESIDENTE') !== 'PRESIDENTE' && array_key_exists('rol_activo',$u) && !(int)$u['rol_activo']) {
        return false;
    }

    if ($u && password_verify($password,$u['password_hash'])) {
        session_regenerate_id(false);
        $_SESSION['user']=$u;
        return true;
    }
    return false;
}

function logout_user(){
    $_SESSION=[];
    if (ini_get('session.use_cookies')) {
        $params=session_get_cookie_params();
        setcookie(session_name(),'',time()-42000,$params['path'],$params['domain'],$params['secure'],$params['httponly']);
    }
    session_destroy();
}
?>
