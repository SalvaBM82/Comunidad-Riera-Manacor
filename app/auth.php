<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__.'/db.php';

function require_login() {
    if (empty($_SESSION['user'])) {
        header('Location: index.php?page=login');
        exit;
    }
}
function current_user() { return $_SESSION['user'] ?? null; }

function role_label($rol) {
    global $pdo;
    static $cache = [];
    if (!$rol) return '';
    if (isset($cache[$rol])) return $cache[$rol];
    $labels = [
        'PRESIDENTE'=>'Presidente',
        'PROPIETARIO_ESCALERA'=>'Propietario con escalera',
        'PROPIETARIO_SIN_ESCALERA'=>'Propietario sin escalera',
        'PROPIETARIO'=>'Propietario'
    ];
    if (isset($labels[$rol])) return $cache[$rol]=$labels[$rol];
    try {
        $st=$pdo->prepare("SELECT nombre FROM roles WHERE codigo=? LIMIT 1");
        $st->execute([$rol]);
        $name=$st->fetchColumn();
        return $cache[$rol]=$name ?: $rol;
    } catch(Throwable $e) {
        return $cache[$rol]=$rol;
    }
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
    return !empty($_SESSION['user']) && $_SESSION['user']['rol']==='PRESIDENTE';
}

function login_user($email,$password) {
    global $pdo;
    $st=$pdo->prepare("SELECT u.*, un.nombre unidad_nombre, un.tiene_acceso_escalera FROM usuarios u LEFT JOIN unidades un ON un.id=u.unidad_id WHERE u.email=? AND u.activo=1");
    $st->execute([$email]); $u=$st->fetch();
    if ($u && password_verify($password,$u['password_hash'])) {
        $_SESSION['user']=$u; return true;
    }
    return false;
}
function logout_user(){ $_SESSION=[]; session_destroy(); }
?>