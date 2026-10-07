<?php
require_once __DIR__.'/../app/auth.php';
require_once __DIR__.'/../app/functions.php';

$page=$_GET['page']??'dashboard';
if($page==='logout'){ logout_user(); header('Location: index.php?page=login'); exit; }

if($page==='login'){
    $err='';
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(login_user(trim($_POST['email']),$_POST['password'])){ header('Location:index.php'); exit; }
        $err='Email o contraseña incorrectos.';
    }
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><title>Acceso</title><link rel="stylesheet" href="style.css"></head><body>
    <div class="login card"><h1>Gestión Comunidad</h1><p class="muted">Acceso de propietarios</p><?php if($err):?><div class="alert"><?=h($err)?></div><?php endif;?>
    <form method="post" class="form"><label>Email</label><input name="email" type="email" required><label>Contraseña</label><input name="password" type="password" required><br><br><button class="btn">Entrar</button></form></div></body></html><?php exit;
}
require_login();

function layout_start($title){
    $u=current_user();
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($title)?> · Gestión Comunidad</title><link rel="stylesheet" href="style.css"></head><body>
    <header class="top"><div class="brand">🏠 Gestión Comunidad</div><div><?=h($u['unidad_nombre']??'')?> · <?=h($u['rol'])?> &nbsp; <a href="index.php?page=logout">Salir</a></div></header><div class="wrap"><aside>
    <div class="nav-title">Principal</div><a href="index.php">Dashboard</a><a href="index.php?page=unidades">Unidades</a><a href="index.php?page=gastos">Gastos</a><a href="index.php?page=presupuestos">Presupuestos</a><a href="index.php?page=recibos">Recibos</a>
    <div class="nav-title">Comunidad</div><a href="index.php?page=derramas">Derramas</a><a href="index.php?page=morosidad">Morosidad</a><a href="index.php?page=incidencias">Incidencias</a><a href="index.php?page=documentos">Documentos</a><a href="index.php?page=votaciones">Votaciones</a>
    </aside><main class="main"><?php
}
function layout_end(){ ?></main></div></body></html><?php }

layout_start(ucfirst($page));

switch($page){
case 'dashboard':
    $un=$pdo->query("SELECT * FROM unidades ORDER BY id")->fetchAll();
    $g=(float)$pdo->query("SELECT COALESCE(SUM(importe_total),0) x FROM gastos WHERE tipo_gasto='GENERAL'")->fetch()['x'];
    $e=(float)$pdo->query("SELECT COALESCE(SUM(importe_total),0) x FROM gastos WHERE tipo_gasto='ESCALERA'")->fetch()['x'];
    $pend=(float)$pdo->query("SELECT COALESCE(SUM(total),0) x FROM recibos WHERE estado<>'PAGADO'")->fetch()['x'];
    echo '<h1>Dashboard</h1><div class="grid">';
    echo '<div class="card general"><div class="muted">Gastos GENERAL</div><div class="big">'.number_format($g,2,',','.').' €</div></div>';
    echo '<div class="card escalera"><div class="muted">Gastos ESCALERA</div><div class="big">'.number_format($e,2,',','.').' €</div></div>';
    echo '<div class="card"><div class="muted">Pendiente de cobro</div><div class="big">'.number_format($pend,2,',','.').' €</div></div>';
    echo '<div class="card"><div class="muted">Unidades</div><div class="big">'.count($un).'</div></div></div><br>';
    echo '<div class="card"><h2>Coeficientes</h2><table><tr><th>Unidad</th><th>General</th><th>Escalera</th><th>Acceso</th></tr>';
    foreach($un as $x) echo '<tr><td>'.h($x['nombre']).'</td><td>'.$x['coef_general'].'%</td><td>'.$x['coef_escalera'].'%</td><td>'.($x['tiene_acceso_escalera']?'Sí':'No').'</td></tr>';
    echo '</table></div>';
break;

case 'unidades':
    if(!is_president()){echo '<div class="alert">Solo el Presidente puede gestionar unidades.</div>';break;}
    echo '<h1>Unidades</h1><table><tr><th>Unidad</th><th>Propietario</th><th>General</th><th>Escalera</th><th>Acceso</th></tr>';
    foreach($pdo->query("SELECT * FROM unidades ORDER BY id") as $x) echo '<tr><td>'.h($x['nombre']).'</td><td>'.h($x['propietario']).'</td><td>'.$x['coef_general'].'%</td><td>'.$x['coef_escalera'].'%</td><td>'.($x['tiene_acceso_escalera']?'Sí':'No').'</td></tr>';
    echo '</table>';
break;

case 'gastos':
    if(is_president() && $_SERVER['REQUEST_METHOD']==='POST'){
        $pdo->prepare("INSERT INTO gastos(fecha,concepto,proveedor,importe_total,tipo_gasto,pagado,created_by) VALUES(?,?,?,?,?,?,?)")
            ->execute([$_POST['fecha'],$_POST['concepto'],$_POST['proveedor'],$_POST['importe'],$_POST['tipo'],isset($_POST['pagado'])?1:0,current_user()['id']]);
        log_action('Creó gasto','gastos'); header('Location:index.php?page=gastos'); exit;
    }
    echo '<h1>Gastos</h1>';
    if(is_president()) echo '<form method="post" class="form"><label>Fecha</label><input type="date" name="fecha" required><label>Concepto</label><input name="concepto" required><label>Proveedor</label><input name="proveedor"><label>Importe</label><input type="number" step="0.01" name="importe" required><label>Tipo</label><select name="tipo"><option>GENERAL</option><option>ESCALERA</option></select><label><input type="checkbox" name="pagado"> Pagado</label><br><button class="btn">Guardar gasto</button></form><br>';
    $rows=$pdo->query("SELECT g.*,u.email FROM gastos g LEFT JOIN usuarios u ON u.id=g.created_by ORDER BY g.fecha DESC,g.id DESC")->fetchAll();
    echo '<table><tr><th>Fecha</th><th>Concepto</th><th>Proveedor</th><th>Tipo</th><th>Importe</th><th>Estado</th></tr>';
    foreach($rows as $x) echo '<tr><td>'.h($x['fecha']).'</td><td>'.h($x['concepto']).'</td><td>'.h($x['proveedor']).'</td><td><span class="pill">'.h($x['tipo_gasto']).'</span></td><td>'.number_format($x['importe_total'],2,',','.').' €</td><td>'.($x['pagado']?'Pagado':'Pendiente').'</td></tr>';
    echo '</table>';
break;

case 'presupuestos':
    if(is_president() && $_SERVER['REQUEST_METHOD']==='POST'){
        $pdo->prepare("INSERT INTO presupuestos(anio,tipo_gasto,concepto,importe_previsto) VALUES(?,?,?,?)")->execute([$_POST['anio'],$_POST['tipo'],$_POST['concepto'],$_POST['importe']]);
        header('Location:index.php?page=presupuestos');exit;
    }
    echo '<h1>Presupuestos anuales</h1>';
    if(is_president()) echo '<form method="post" class="form"><label>Año</label><input type="number" name="anio" value="'.date('Y').'" required><label>Tipo</label><select name="tipo"><option>GENERAL</option><option>ESCALERA</option></select><label>Concepto</label><input name="concepto" required><label>Importe previsto anual</label><input type="number" step="0.01" name="importe" required><br><button class="btn">Añadir</button></form><br>';
    echo '<table><tr><th>Año</th><th>Tipo</th><th>Concepto</th><th>Previsto</th><th>Real</th></tr>';
    foreach($pdo->query("SELECT * FROM presupuestos ORDER BY anio DESC,id DESC") as $x) echo '<tr><td>'.$x['anio'].'</td><td>'.$x['tipo_gasto'].'</td><td>'.h($x['concepto']).'</td><td>'.number_format($x['importe_previsto'],2,',','.').' €</td><td>'.number_format($x['importe_real'],2,',','.').' €</td></tr>';
    echo '</table>';
break;

case 'recibos':
    if(is_president() && $_SERVER['REQUEST_METHOD']==='POST'){
        $year=(int)$_POST['anio'];$month=(int)$_POST['mes'];
        $ug=$pdo->query("SELECT COALESCE(SUM(importe_previsto),0) x FROM presupuestos WHERE anio=$year AND tipo_gasto='GENERAL'")->fetch()['x']/12;
        $ue=$pdo->query("SELECT COALESCE(SUM(importe_previsto),0) x FROM presupuestos WHERE anio=$year AND tipo_gasto='ESCALERA'")->fetch()['x']/12;
        $units=$pdo->query("SELECT * FROM unidades")->fetchAll();
        foreach($units as $u){
            $ig=round($ug*$u['coef_general']/100,2); $ie=round($ue*$u['coef_escalera']/100,2);
            $pdo->prepare("INSERT INTO recibos(anio,mes,unidad_id,importe_general,importe_escalera,total,fecha_vencimiento) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE importe_general=VALUES(importe_general),importe_escalera=VALUES(importe_escalera),total=VALUES(total)")
            ->execute([$year,$month,$u['id'],$ig,$ie,$ig+$ie,$_POST['vencimiento']]);
        }
        log_action("Generó recibos $year-$month",'recibos'); header('Location:index.php?page=recibos');exit;
    }
    echo '<h1>Recibos</h1>';
    if(is_president()) echo '<form method="post" class="form"><label>Año</label><input type="number" name="anio" value="'.date('Y').'"><label>Mes</label><input type="number" min="1" max="12" name="mes" value="'.date('n').'"><label>Vencimiento</label><input type="date" name="vencimiento" value="'.date('Y-m-d',strtotime('+15 days')).'"><br><button class="btn">Generar recibos</button></form><br>';
    echo '<table><tr><th>Periodo</th><th>Unidad</th><th>General</th><th>Escalera</th><th>Total</th><th>Estado</th></tr>';
    $rows=$pdo->query("SELECT r.*,u.nombre FROM recibos r JOIN unidades u ON u.id=r.unidad_id ORDER BY r.anio DESC,r.mes DESC,u.id")->fetchAll();
    foreach($rows as $x) echo '<tr><td>'.$x['mes'].'/'.$x['anio'].'</td><td>'.h($x['nombre']).'</td><td>'.number_format($x['importe_general'],2,',','.').' €</td><td>'.number_format($x['importe_escalera'],2,',','.').' €</td><td><b>'.number_format($x['total'],2,',','.').' €</b></td><td>'.$x['estado'].'</td></tr>';
    echo '</table>';
break;

case 'derramas':
    if(is_president() && $_SERVER['REQUEST_METHOD']==='POST'){
        $pdo->prepare("INSERT INTO derramas(titulo,descripcion,fecha_acuerdo_junta,importe_total,tipo,fecha_limite) VALUES(?,?,?,?,?,?)")
        ->execute([$_POST['titulo'],$_POST['descripcion'],$_POST['fecha_acuerdo'],$_POST['importe'],$_POST['tipo'],$_POST['limite']]);
        log_action('Creó derrama','derramas');header('Location:index.php?page=derramas');exit;
    }
    echo '<h1>Derramas</h1>';
    if(is_president()) echo '<form method="post" class="form"><label>Título</label><input name="titulo" required><label>Descripción</label><textarea name="descripcion"></textarea><label>Fecha acuerdo</label><input type="date" name="fecha_acuerdo"><label>Importe total</label><input type="number" step="0.01" name="importe" required><label>Tipo</label><select name="tipo"><option>GENERAL</option><option>ESCALERA</option></select><label>Fecha límite</label><input type="date" name="limite" required><br><button class="btn">Crear derrama</button></form><br>';
    echo '<table><tr><th>Título</th><th>Tipo</th><th>Importe</th><th>Límite</th></tr>';
    foreach($pdo->query("SELECT * FROM derramas ORDER BY id DESC") as $x) echo '<tr><td>'.h($x['titulo']).'</td><td>'.$x['tipo'].'</td><td>'.number_format($x['importe_total'],2,',','.').' €</td><td>'.$x['fecha_limite'].'</td></tr>';
    echo '</table>';
break;

case 'morosidad':
    if(!is_president()){echo '<div class="alert">El informe detallado de morosidad solo está disponible para el Presidente.</div>';break;}
    echo '<h1>Morosidad</h1><p class="muted">Informe interno de deuda. Los propietarios normales no pueden ver el detalle de otros vecinos.</p>';
    $rows=$pdo->query("SELECT u.id,u.nombre,u.propietario,COALESCE(SUM(CASE WHEN r.estado IN ('VENCIDO') THEN r.total ELSE 0 END),0) deuda FROM unidades u LEFT JOIN recibos r ON r.unidad_id=u.id GROUP BY u.id ORDER BY deuda DESC")->fetchAll();
    echo '<table><tr><th>Unidad</th><th>Propietario</th><th>Recibos vencidos</th></tr>';
    foreach($rows as $x) echo '<tr><td>'.h($x['nombre']).'</td><td>'.h($x['propietario']).'</td><td>'.number_format($x['deuda'],2,',','.').' €</td></tr>';
    echo '</table>';
break;

case 'incidencias':
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $pdo->prepare("INSERT INTO incidencias(titulo,descripcion,tipo,created_by) VALUES(?,?,?,?)")->execute([$_POST['titulo'],$_POST['descripcion'],$_POST['tipo'],current_user()['id']]);
        header('Location:index.php?page=incidencias');exit;
    }
    echo '<h1>Incidencias</h1><form method="post" class="form"><label>Título</label><input name="titulo" required><label>Descripción</label><textarea name="descripcion"></textarea><label>Tipo</label><select name="tipo"><option>GENERAL</option><option>ESCALERA</option></select><br><button class="btn">Crear incidencia</button></form><br>';
    echo '<table><tr><th>Título</th><th>Tipo</th><th>Estado</th><th>Fecha</th></tr>';
    foreach($pdo->query("SELECT * FROM incidencias ORDER BY id DESC") as $x) echo '<tr><td>'.h($x['titulo']).'</td><td>'.$x['tipo'].'</td><td>'.$x['estado'].'</td><td>'.$x['created_at'].'</td></tr>';
    echo '</table>';
break;

case 'documentos':
    echo '<h1>Documentos</h1><div class="card"><p>Repositorio preparado para actas, estatutos, seguros y facturas.</p><p class="muted">La estructura SQL y el almacenamiento están incluidos en esta versión inicial.</p></div>';
break;

case 'votaciones':
    echo '<h1>Votaciones</h1><div class="card"><p>Módulo de votaciones preparado para temas GENERAL y ESCALERA.</p><p class="muted">La base de datos incluye votaciones y votos con bloqueo por unidad.</p></div>';
break;

default: echo '<div class="alert">Módulo no encontrado.</div>';
}
layout_end();
