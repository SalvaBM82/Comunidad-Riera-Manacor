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
function is_president() { return !empty($_SESSION['user']) && $_SESSION['user']['rol']==='PRESIDENTE'; }
function role_label($rol) {
    return [
        'PRESIDENTE'=>'Presidente',
        'PROPIETARIO_ESCALERA'=>'Propietario con escalera',
        'PROPIETARIO_SIN_ESCALERA'=>'Propietario sin escalera',
        'PROPIETARIO'=>'Propietario'
    ][$rol] ?? $rol;
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
