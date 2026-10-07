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
    if (!current_user()) {
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
        $st=$pdo->prepare("SELECT u.*, un.nombre AS unidad_nombre, un.tiene_acceso_escalera
            FROM usuarios u
            LEFT JOIN unidades un ON un.id=u.unidad_id
            WHERE u.id=? LIMIT 1");
        $st->execute([$id]);
        $row=$st->fetch();
        if (!$row || !(int)$row['activo']) {
            $_SESSION=[];
            return null;
        }

        $rs=$pdo->prepare("SELECT r.id,r.codigo,r.nombre,r.activo
            FROM usuario_roles ur
            JOIN roles r ON r.id=ur.rol_id
            WHERE ur.usuario_id=? AND r.activo=1
            ORDER BY r.sistema DESC,r.nombre");
        $rs->execute([$id]);
        $roles=$rs->fetchAll();

        if (!$roles) {
            // Compatibilidad durante la transición: si V4 aún no se ha ejecutado,
            // se conserva el rol único de V3.
            if (!empty($row['rol_id'])) {
                $rs=$pdo->prepare("SELECT id,codigo,nombre,activo FROM roles WHERE id=?");
                $rs->execute([(int)$row['rol_id']]);
                $legacy=$rs->fetch();
                if ($legacy) $roles=[$legacy];
            }
        }

        $row['roles']=$roles;
        $row['role_codes']=array_column($roles,'codigo');
        $row['role_names']=array_column($roles,'nombre');
        $row['rol_nombre']=$roles ? implode(', ',$row['role_names']) : '';
        $row['rol']=$roles ? $row['role_codes'][0] : ($row['rol']??'');

        if (!$roles) {
            $_SESSION=[];
            return null;
        }

        $user=$row;
        $_SESSION['user']=$row;
        return $user;
    } catch (Throwable $e) {
        // Antes de V4 puede seguir utilizándose la sesión antigua.
        $user=$_SESSION['user']??null;
        return $user;
    }
}

function role_label($rol) {
    global $pdo;
    static $cache=[];
    if (!$rol) return '';
    if (isset($cache[$rol])) return $cache[$rol];
    try {
        $st=$pdo->prepare("SELECT nombre FROM roles WHERE codigo=? LIMIT 1");
        $st->execute([$rol]);
        $name=$st->fetchColumn();
        if ($name) return $cache[$rol]=$name;
    } catch(Throwable $e) {}
    return $cache[$rol]=$rol;
}

function user_roles() {
    $u=current_user();
    return $u['roles']??[];
}

function has_role($code) {
    $u=current_user();
    return $u && in_array($code,$u['role_codes']??[],true);
}

function can($permission) {
    global $pdo;
    $u=current_user();
    if (!$u) return false;

    try {
        $st=$pdo->prepare("SELECT 1
            FROM usuario_roles ur
            JOIN roles r ON r.id=ur.rol_id AND r.activo=1
            JOIN rol_permisos rp ON rp.rol_id=r.id
            JOIN permisos p ON p.id=rp.permiso_id AND p.codigo=?
            JOIN usuarios u ON u.id=ur.usuario_id AND u.activo=1
            WHERE ur.usuario_id=? LIMIT 1");
        $st->execute([$permission,$u['id']]);
        if ($st->fetchColumn()) return true;
    } catch(Throwable $e) {
        // Compatibilidad V3: un usuario todavía puede tener un único rol.
        try {
            $st=$pdo->prepare("SELECT 1
                FROM usuarios u
                JOIN roles r ON r.id=u.rol_id AND r.activo=1
                JOIN rol_permisos rp ON rp.rol_id=r.id
                JOIN permisos p ON p.id=rp.permiso_id AND p.codigo=?
                WHERE u.id=? AND u.activo=1 LIMIT 1");
            $st->execute([$permission,$u['id']]);
            return (bool)$st->fetchColumn();
        } catch(Throwable $e2) {
            return false;
        }
    }
    return false;
}

function is_president() {
    return has_role('PRESIDENTE');
}

function login_user($email,$password) {
    global $pdo;
    $st=$pdo->prepare("SELECT u.*, un.nombre AS unidad_nombre, un.tiene_acceso_escalera
        FROM usuarios u
        LEFT JOIN unidades un ON un.id=u.unidad_id
        WHERE u.email=? AND u.activo=1");
    $st->execute([$email]);
    $u=$st->fetch();

    if ($u && password_verify($password,$u['password_hash'])) {
        session_regenerate_id(false);
        $_SESSION['user']=['id'=>$u['id']];
        // Carga inmediata de roles y estado.
        current_user();
        return (bool)current_user();
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
