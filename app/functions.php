<?php
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function calcularReparto(float $importe, string $tipo): array {
    if ($tipo === 'GENERAL') return [
        'Local Comercial'=>round($importe*0.20,2),
        'Garaje Privado'=>round($importe*0.20,2),
        'Piso 1º'=>round($importe*0.40,2),
        'Piso 2º'=>round($importe*0.20,2),
    ];
    return [
        'Local Comercial'=>0.00,
        'Garaje Privado'=>0.00,
        'Piso 1º'=>round($importe*0.50,2),
        'Piso 2º'=>round($importe*0.50,2),
    ];
}
function repartoUnidades(float $importe,string $tipo,$unidades): array {
    $out=[];
    foreach($unidades as $u){
        $coef = $tipo==='GENERAL' ? (float)$u['coef_general'] : (float)$u['coef_escalera'];
        $out[$u['id']] = round($importe*$coef/100,2);
    }
    return $out;
}
function log_action($accion,$entidad=''){
    global $pdo;
    $uid=$_SESSION['user']['id']??null;
    $pdo->prepare("INSERT INTO logs(usuario_id,accion,entidad_afectada) VALUES(?,?,?)")->execute([$uid,$accion,$entidad]);
}

function external_url($url){
    $url=trim((string)$url);
    if($url==='' || !filter_var($url,FILTER_VALIDATE_URL)) return false;
    $scheme=strtolower((string)parse_url($url,PHP_URL_SCHEME));
    return in_array($scheme,['http','https'],true);
}
