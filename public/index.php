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
    <header class="top"><div class="brand">🏠 Gestión Comunidad</div><div><?=h($u['unidad_nombre']??'')?> · <?=h($u['rol_nombre']??role_label($u['rol']))?> &nbsp; <a href="index.php?page=logout">Salir</a></div></header><div class="wrap"><aside>
    <div class="nav-title">Principal</div><a href="index.php">Dashboard</a><a href="index.php?page=unidades">Unidades</a><a href="index.php?page=gastos">Gastos</a><a href="index.php?page=presupuestos">Presupuestos</a><a href="index.php?page=recibos">Recibos</a>
    <div class="nav-title">Administración</div><?php if(can('GESTION_USUARIOS') || can('GESTION_ROLES')): ?><a href="index.php?page=usuarios">Usuarios</a><a href="index.php?page=roles">Roles</a><?php endif; ?><?php if(can('CAMBIO_PROPIETARIO')): ?><a href="index.php?page=propietarios">Cambios de propietario</a><?php endif; ?><div class="nav-title">Comunidad</div><a href="index.php?page=derramas">Derramas</a><a href="index.php?page=morosidad">Morosidad</a><a href="index.php?page=incidencias">Incidencias</a><a href="index.php?page=documentos">Documentos</a><a href="index.php?page=votaciones">Votaciones</a>
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
    if(!can('GESTION_UNIDADES')){echo '<div class="alert">No tienes permiso para gestionar unidades.</div>';break;}
    echo '<h1>Unidades</h1><table><tr><th>Unidad</th><th>Propietario</th><th>General</th><th>Escalera</th><th>Acceso</th></tr>';
    foreach($pdo->query("SELECT * FROM unidades ORDER BY id") as $x) echo '<tr><td>'.h($x['nombre']).'</td><td>'.h($x['propietario']).'</td><td>'.$x['coef_general'].'%</td><td>'.$x['coef_escalera'].'%</td><td>'.($x['tiene_acceso_escalera']?'Sí':'No').'</td></tr>';
    echo '</table>';
break;

case 'gastos':
    if(can('GESTION_GASTOS') && $_SERVER['REQUEST_METHOD']==='POST'){
        $pdo->prepare("INSERT INTO gastos(fecha,concepto,proveedor,importe_total,tipo_gasto,pagado,created_by) VALUES(?,?,?,?,?,?,?)")
            ->execute([$_POST['fecha'],$_POST['concepto'],$_POST['proveedor'],$_POST['importe'],$_POST['tipo'],isset($_POST['pagado'])?1:0,current_user()['id']]);
        log_action('Creó gasto','gastos'); header('Location:index.php?page=gastos'); exit;
    }
    echo '<h1>Gastos</h1>';
    if(can('GESTION_GASTOS')) echo '<form method="post" class="form"><label>Fecha</label><input type="date" name="fecha" required><label>Concepto</label><input name="concepto" required><label>Proveedor</label><input name="proveedor"><label>Importe</label><input type="number" step="0.01" name="importe" required><label>Tipo</label><select name="tipo"><option>GENERAL</option><option>ESCALERA</option></select><label><input type="checkbox" name="pagado"> Pagado</label><br><button class="btn">Guardar gasto</button></form><br>';
    $rows=$pdo->query("SELECT g.*,u.email FROM gastos g LEFT JOIN usuarios u ON u.id=g.created_by ORDER BY g.fecha DESC,g.id DESC")->fetchAll();
    echo '<table><tr><th>Fecha</th><th>Concepto</th><th>Proveedor</th><th>Tipo</th><th>Importe</th><th>Estado</th></tr>';
    foreach($rows as $x) echo '<tr><td>'.h($x['fecha']).'</td><td>'.h($x['concepto']).'</td><td>'.h($x['proveedor']).'</td><td><span class="pill">'.h($x['tipo_gasto']).'</span></td><td>'.number_format($x['importe_total'],2,',','.').' €</td><td>'.($x['pagado']?'Pagado':'Pendiente').'</td></tr>';
    echo '</table>';
break;

case 'presupuestos':
    if(can('GESTION_PRESUPUESTOS') && $_SERVER['REQUEST_METHOD']==='POST'){
        $pdo->prepare("INSERT INTO presupuestos(anio,tipo_gasto,concepto,importe_previsto) VALUES(?,?,?,?)")->execute([$_POST['anio'],$_POST['tipo'],$_POST['concepto'],$_POST['importe']]);
        header('Location:index.php?page=presupuestos');exit;
    }
    echo '<h1>Presupuestos anuales</h1>';
    if(can('GESTION_PRESUPUESTOS')) echo '<form method="post" class="form"><label>Año</label><input type="number" name="anio" value="'.date('Y').'" required><label>Tipo</label><select name="tipo"><option>GENERAL</option><option>ESCALERA</option></select><label>Concepto</label><input name="concepto" required><label>Importe previsto anual</label><input type="number" step="0.01" name="importe" required><br><button class="btn">Añadir</button></form><br>';
    echo '<table><tr><th>Año</th><th>Tipo</th><th>Concepto</th><th>Previsto</th><th>Real</th></tr>';
    foreach($pdo->query("SELECT * FROM presupuestos ORDER BY anio DESC,id DESC") as $x) echo '<tr><td>'.$x['anio'].'</td><td>'.$x['tipo_gasto'].'</td><td>'.h($x['concepto']).'</td><td>'.number_format($x['importe_previsto'],2,',','.').' €</td><td>'.number_format($x['importe_real'],2,',','.').' €</td></tr>';
    echo '</table>';
break;

case 'recibos':
    if(can('GESTION_RECIBOS') && $_SERVER['REQUEST_METHOD']==='POST'){
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
    if(can('GESTION_RECIBOS')) echo '<form method="post" class="form"><label>Año</label><input type="number" name="anio" value="'.date('Y').'"><label>Mes</label><input type="number" min="1" max="12" name="mes" value="'.date('n').'"><label>Vencimiento</label><input type="date" name="vencimiento" value="'.date('Y-m-d',strtotime('+15 days')).'"><br><button class="btn">Generar recibos</button></form><br>';
    echo '<table><tr><th>Periodo</th><th>Unidad</th><th>General</th><th>Escalera</th><th>Total</th><th>Estado</th></tr>';
    $rows=$pdo->query("SELECT r.*,u.nombre FROM recibos r JOIN unidades u ON u.id=r.unidad_id ORDER BY r.anio DESC,r.mes DESC,u.id")->fetchAll();
    foreach($rows as $x) echo '<tr><td>'.$x['mes'].'/'.$x['anio'].'</td><td>'.h($x['nombre']).'</td><td>'.number_format($x['importe_general'],2,',','.').' €</td><td>'.number_format($x['importe_escalera'],2,',','.').' €</td><td><b>'.number_format($x['total'],2,',','.').' €</b></td><td>'.$x['estado'].'</td></tr>';
    echo '</table>';
break;

case 'derramas':
    if(can('GESTION_DERRAMAS') && $_SERVER['REQUEST_METHOD']==='POST'){
        $pdo->prepare("INSERT INTO derramas(titulo,descripcion,fecha_acuerdo_junta,importe_total,tipo,fecha_limite) VALUES(?,?,?,?,?,?)")
        ->execute([$_POST['titulo'],$_POST['descripcion'],$_POST['fecha_acuerdo'],$_POST['importe'],$_POST['tipo'],$_POST['limite']]);
        log_action('Creó derrama','derramas');header('Location:index.php?page=derramas');exit;
    }
    echo '<h1>Derramas</h1>';
    if(can('GESTION_DERRAMAS')) echo '<form method="post" class="form"><label>Título</label><input name="titulo" required><label>Descripción</label><textarea name="descripcion"></textarea><label>Fecha acuerdo</label><input type="date" name="fecha_acuerdo"><label>Importe total</label><input type="number" step="0.01" name="importe" required><label>Tipo</label><select name="tipo"><option>GENERAL</option><option>ESCALERA</option></select><label>Fecha límite</label><input type="date" name="limite" required><br><button class="btn">Crear derrama</button></form><br>';
    echo '<table><tr><th>Título</th><th>Tipo</th><th>Importe</th><th>Límite</th></tr>';
    foreach($pdo->query("SELECT * FROM derramas ORDER BY id DESC") as $x) echo '<tr><td>'.h($x['titulo']).'</td><td>'.$x['tipo'].'</td><td>'.number_format($x['importe_total'],2,',','.').' €</td><td>'.$x['fecha_limite'].'</td></tr>';
    echo '</table>';
break;

case 'morosidad':
    if(!can('VER_MOROSIDAD')){echo '<div class="alert">No tienes permiso para consultar la morosidad.</div>';break;}
    echo '<h1>Morosidad</h1><p class="muted">Informe interno de deuda. Los propietarios normales no pueden ver el detalle de otros vecinos.</p>';
    $rows=$pdo->query("SELECT u.id,u.nombre,u.propietario,COALESCE(SUM(CASE WHEN r.estado IN ('VENCIDO') THEN r.total ELSE 0 END),0) deuda FROM unidades u LEFT JOIN recibos r ON r.unidad_id=u.id GROUP BY u.id ORDER BY deuda DESC")->fetchAll();
    echo '<table><tr><th>Unidad</th><th>Propietario</th><th>Recibos vencidos</th></tr>';
    foreach($rows as $x) echo '<tr><td>'.h($x['nombre']).'</td><td>'.h($x['propietario']).'</td><td>'.number_format($x['deuda'],2,',','.').' €</td></tr>';
    echo '</table>';
break;

case 'incidencias':
    if(can('GESTION_INCIDENCIAS') && $_SERVER['REQUEST_METHOD']==='POST'){
        $pdo->prepare("INSERT INTO incidencias(titulo,descripcion,tipo,created_by) VALUES(?,?,?,?)")->execute([$_POST['titulo'],$_POST['descripcion'],$_POST['tipo'],current_user()['id']]);
        header('Location:index.php?page=incidencias');exit;
    }
    echo '<h1>Incidencias</h1>'; if(can('GESTION_INCIDENCIAS')) echo '<form method="post" class="form"><label>Título</label><input name="titulo" required><label>Descripción</label><textarea name="descripcion"></textarea><label>Tipo</label><select name="tipo"><option>GENERAL</option><option>ESCALERA</option></select><br><button class="btn">Crear incidencia</button></form><br>';
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


case 'usuarios':
    if(!can('GESTION_USUARIOS')){echo '<div class="alert">No tienes permiso para gestionar usuarios.</div>';break;}

    $activeRoles=$pdo->query("SELECT id,codigo,nombre FROM roles WHERE activo=1 ORDER BY sistema DESC,nombre")->fetchAll();
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $action=$_POST['action']??'';

        if($action==='create'){
            $email=trim($_POST['email']??'');
            $password=$_POST['password']??'';
            $unidad=(int)($_POST['unidad_id']??0);
            $roles=array_values(array_unique(array_map('intval',$_POST['roles']??[])));

            if(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<8 || !$roles){
                echo '<div class="alert">Email, al menos un rol y una contraseña de 8 caracteres son obligatorios.</div>';
            } else {
                try{
                    $pdo->beginTransaction();
                    $valid=$pdo->prepare("SELECT id,codigo FROM roles WHERE activo=1 AND id IN (".implode(',',array_fill(0,count($roles),'?')).")");
                    $valid->execute($roles);
                    $validRoles=$valid->fetchAll();
                    if(count($validRoles)!==count($roles)) throw new Exception('Hay roles no válidos o inactivos.');

                    $primary=$validRoles[0];
                    foreach($validRoles as $vr){
                        if($vr['codigo']==='PRESIDENTE'){$primary=$vr;break;}
                    }

                    $pdo->prepare("INSERT INTO usuarios(email,password_hash,unidad_id,rol,rol_id,activo) VALUES(?,?,?,?,?,1)")
                        ->execute([$email,password_hash($password,PASSWORD_DEFAULT),$unidad?:null,$primary['codigo'],$primary['id']]);
                    $uid=(int)$pdo->lastInsertId();

                    $ins=$pdo->prepare("INSERT INTO usuario_roles(usuario_id,rol_id) VALUES(?,?)");
                    foreach($validRoles as $vr) $ins->execute([$uid,$vr['id']]);

                    $pdo->commit();
                    log_action('Creó usuario','usuarios');
                    header('Location:index.php?page=usuarios');exit;
                }catch(Throwable $e){
                    if($pdo->inTransaction()) $pdo->rollBack();
                    echo '<div class="alert">No se pudo crear el usuario. Comprueba el email y los roles seleccionados.</div>';
                }
            }
        } elseif($action==='update'){
            $id=(int)$_POST['id'];
            $email=trim($_POST['email']??'');
            $unidad=(int)($_POST['unidad_id']??0);
            $roles=array_values(array_unique(array_map('intval',$_POST['roles']??[])));
            $activo=isset($_POST['activo'])?1:0;
            if($id===(int)current_user()['id']) $activo=1;

            if(!filter_var($email,FILTER_VALIDATE_EMAIL) || !$roles){
                echo '<div class="alert">Email y al menos un rol son obligatorios.</div>';
            } else {
                try{
                    $pdo->beginTransaction();
                    $valid=$pdo->prepare("SELECT id,codigo FROM roles WHERE activo=1 AND id IN (".implode(',',array_fill(0,count($roles),'?')).")");
                    $valid->execute($roles);
                    $validRoles=$valid->fetchAll();
                    if(count($validRoles)!==count($roles)) throw new Exception('Hay roles no válidos o inactivos.');

                    $primary=$validRoles[0];
                    foreach($validRoles as $vr){
                        if($vr['codigo']==='PRESIDENTE'){$primary=$vr;break;}
                    }

                    $pdo->prepare("UPDATE usuarios SET email=?,unidad_id=?,rol=?,rol_id=?,activo=? WHERE id=?")
                        ->execute([$email,$unidad?:null,$primary['codigo'],$primary['id'],$activo,$id]);

                    $pdo->prepare("DELETE FROM usuario_roles WHERE usuario_id=?")->execute([$id]);
                    $ins=$pdo->prepare("INSERT INTO usuario_roles(usuario_id,rol_id) VALUES(?,?)");
                    foreach($validRoles as $vr) $ins->execute([$id,$vr['id']]);

                    $pdo->commit();
                    log_action('Actualizó usuario #'.$id,'usuarios');
                    header('Location:index.php?page=usuarios');exit;
                }catch(Throwable $e){
                    if($pdo->inTransaction()) $pdo->rollBack();
                    echo '<div class="alert">No se pudo actualizar el usuario. Comprueba el email y los roles seleccionados.</div>';
                }
            }
        } elseif($action==='password'){
            $id=(int)$_POST['id'];$password=$_POST['password']??'';
            if(strlen($password)>=8){
                $pdo->prepare("UPDATE usuarios SET password_hash=? WHERE id=?")
                    ->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
                log_action('Cambió contraseña de usuario #'.$id,'usuarios');
            }
            header('Location:index.php?page=usuarios');exit;
        }
    }

    echo '<h1>Usuarios</h1>';
    echo '<div class="card"><h2>Nuevo usuario</h2><form method="post" class="form">
        <input type="hidden" name="action" value="create">
        <label>Email</label><input type="email" name="email" required>
        <label>Contraseña inicial</label><input type="password" name="password" minlength="8" required>
        <label>Unidad</label><select name="unidad_id"><option value="0">Sin unidad</option>';
    foreach($pdo->query("SELECT id,nombre,propietario FROM unidades ORDER BY id") as $x)
        echo '<option value="'.$x['id'].'">'.h($x['nombre']).' — '.h($x['propietario']).'</option>';
    echo '</select><label>Roles</label><div class="grid">';
    foreach($activeRoles as $r)
        echo '<label><input type="checkbox" name="roles[]" value="'.$r['id'].'"> '.h($r['nombre']).'</label>';
    echo '</div><br><button class="btn">Crear usuario</button></form></div><br>';

    $users=$pdo->query("SELECT u.*,un.nombre unidad_nombre FROM usuarios u LEFT JOIN unidades un ON un.id=u.unidad_id ORDER BY u.id")->fetchAll();
    echo '<div class="card"><h2>Usuarios existentes</h2><table><tr><th>Email</th><th>Unidad</th><th>Roles</th><th>Estado</th><th>Guardar</th><th>Contraseña</th></tr>';
    $roleSt=$pdo->prepare("SELECT r.id,r.codigo,r.nombre FROM usuario_roles ur JOIN roles r ON r.id=ur.rol_id WHERE ur.usuario_id=? ORDER BY r.sistema DESC,r.nombre");
    foreach($users as $x){
        $roleSt->execute([$x['id']]);
        $assigned=$roleSt->fetchAll();
        $assignedIds=array_map('intval',array_column($assigned,'id'));

        echo '<tr><td><form method="post" class="form"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="'.$x['id'].'"><input type="email" name="email" value="'.h($x['email']).'" required></td><td><select name="unidad_id"><option value="0">Sin unidad</option>';
        foreach($pdo->query("SELECT id,nombre FROM unidades ORDER BY id") as $un)
            echo '<option value="'.$un['id'].'"'.((int)$x['unidad_id']===(int)$un['id']?' selected':'').'>'.h($un['nombre']).'</option>';
        echo '</select></td><td><div class="grid">';
        foreach($activeRoles as $r)
            echo '<label><input type="checkbox" name="roles[]" value="'.$r['id'].'"'.(in_array((int)$r['id'],$assignedIds,true)?' checked':'').'> '.h($r['nombre']).'</label>';
        echo '</div></td><td><label><input type="checkbox" name="activo"'.($x['activo']?' checked':'').'> Activo</label></td><td><button class="btn">Guardar cambios</button></form></td><td><form method="post" class="form"><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="'.$x['id'].'"><input name="password" type="password" minlength="8" placeholder="Nueva contraseña" required><button class="btn gray">Cambiar</button></form></td></tr>';
    }
    echo '</table></div>';
break;

case 'roles':
    if(!can('GESTION_ROLES')){echo '<div class="alert">No tienes permiso para gestionar roles.</div>';break;}
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $action=$_POST['action']??'';
        if($action==='create_role'){
            $nombre=trim($_POST['nombre']??'');$descripcion=trim($_POST['descripcion']??'');
            $ascii=function_exists('iconv')?iconv('UTF-8','ASCII//TRANSLIT',$nombre):$nombre;
            $codigo=strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/','_',$ascii),'_'));
            if(!$codigo || !$nombre){echo '<div class="alert">El nombre del rol es obligatorio.</div>';}
            else {
                try{
                    $pdo->prepare("INSERT INTO roles(codigo,nombre,descripcion,sistema,activo) VALUES(?,?,?,0,1)")->execute([$codigo,$nombre,$descripcion]);
                    $rid=(int)$pdo->lastInsertId();
                    foreach(($_POST['permisos']??[]) as $pid){$pdo->prepare("INSERT IGNORE INTO rol_permisos(rol_id,permiso_id) VALUES(?,?)")->execute([$rid,(int)$pid]);}
                    log_action('Creó rol '.$codigo,'roles');header('Location:index.php?page=roles');exit;
                }catch(PDOException $e){echo '<div class="alert">No se pudo crear el rol. Es posible que ya exista otro con el mismo código.</div>';}
            }
        } elseif($action==='update_role'){
            $rid=(int)$_POST['id'];$nombre=trim($_POST['nombre']??'');$descripcion=trim($_POST['descripcion']??'');$activo=isset($_POST['activo'])?1:0;
            $st=$pdo->prepare("SELECT * FROM roles WHERE id=?");$st->execute([$rid]);$role=$st->fetch();
            if($role && $nombre){
                if($role['codigo']==='PRESIDENTE'){$activo=1;}
                if(!$activo){
                    $st=$pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE rol_id=? AND activo=1");
                    $st->execute([$rid]);
                    if((int)$st->fetchColumn()>0){
                        echo '<div class="alert">No se puede desactivar este rol porque hay usuarios activos asignados a él. Cambia primero esos usuarios a otro rol.</div>';
                        break;
                    }
                }
                $pdo->prepare("UPDATE roles SET nombre=?,descripcion=?,activo=? WHERE id=?")->execute([$nombre,$descripcion,$activo,$rid]);
                $pdo->prepare("DELETE FROM rol_permisos WHERE rol_id=?")->execute([$rid]);
                if($role['codigo']==='PRESIDENTE'){
                    $pdo->prepare("INSERT IGNORE INTO rol_permisos(rol_id,permiso_id) SELECT ?,id FROM permisos")->execute([$rid]);
                } else {
                    foreach(($_POST['permisos']??[]) as $pid){$pdo->prepare("INSERT IGNORE INTO rol_permisos(rol_id,permiso_id) VALUES(?,?)")->execute([$rid,(int)$pid]);}
                }
                log_action('Actualizó rol '.$role['codigo'],'roles');header('Location:index.php?page=roles');exit;
            }
        }
    }

    $permisos=$pdo->query("SELECT * FROM permisos ORDER BY nombre")->fetchAll();
    echo '<h1>Roles</h1><p class="muted">Aquí defines qué puede hacer cada tipo de usuario. Los roles del sistema mantienen su código interno, pero puedes cambiar su nombre, descripción y permisos.</p>';
    echo '<div class="card"><h2>Crear rol</h2><form method="post" class="form"><input type="hidden" name="action" value="create_role"><label>Nombre</label><input name="nombre" required placeholder="Ej.: Tesorero"><label>Descripción</label><input name="descripcion"><h3>Permisos</h3><div class="grid">';
    foreach($permisos as $p) echo '<label><input type="checkbox" name="permisos[]" value="'.$p['id'].'"> '.h($p['nombre']).'</label>';
    echo '</div><br><button class="btn">Crear rol</button></form></div><br>';

    $roles=$pdo->query("SELECT * FROM roles ORDER BY sistema DESC,nombre")->fetchAll();
    foreach($roles as $r){
        $st=$pdo->prepare("SELECT permiso_id FROM rol_permisos WHERE rol_id=?");$st->execute([$r['id']]);$selected=array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));
        echo '<div class="card"><h2>'.h($r['nombre']).'</h2><form method="post" class="form"><input type="hidden" name="action" value="update_role"><input type="hidden" name="id" value="'.$r['id'].'"><label>Nombre</label><input name="nombre" value="'.h($r['nombre']).'" required><label>Descripción</label><input name="descripcion" value="'.h($r['descripcion']).'"><label><input type="checkbox" name="activo"'.($r['activo']?' checked':'').' '.($r['codigo']==='PRESIDENTE'?'disabled':'').'> Activo</label><h3>Permisos</h3><div class="grid">';
        foreach($permisos as $p) echo '<label><input type="checkbox" name="permisos[]" value="'.$p['id'].'"'.(in_array((int)$p['id'],$selected,true)?' checked':'').($r['codigo']==='PRESIDENTE'?' disabled':'').'> '.h($p['nombre']).'</label>';
        echo '</div><br><button class="btn">Guardar rol</button></form></div><br>';
    }
break;

case 'propietarios':
    if(!can('CAMBIO_PROPIETARIO')){echo '<div class="alert">No tienes permiso para gestionar propietarios.</div>';break;}
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $unidad=(int)$_POST['unidad_id']; $fecha=$_POST['fecha_inicio']; $nombre=trim($_POST['propietario']); $email=trim($_POST['email']); $telefono=trim($_POST['telefono']); $motivo=trim($_POST['motivo']);
        if($unidad && $nombre && $fecha){
            $pdo->beginTransaction();
            try{
                $st=$pdo->prepare("SELECT * FROM unidades WHERE id=? FOR UPDATE");$st->execute([$unidad]);$old=$st->fetch();
                $pdo->prepare("UPDATE propietarios_historial SET fecha_fin=DATE_SUB(?,INTERVAL 1 DAY) WHERE unidad_id=? AND fecha_fin IS NULL")->execute([$fecha,$unidad]);
                $pdo->prepare("INSERT INTO propietarios_historial(unidad_id,propietario,email,telefono,fecha_inicio,motivo,created_by) VALUES(?,?,?,?,?,?,?)")->execute([$unidad,$nombre,$email,$telefono,$fecha,$motivo,current_user()['id']]);
                $pdo->prepare("UPDATE unidades SET propietario=?,email=?,telefono=? WHERE id=?")->execute([$nombre,$email,$telefono,$unidad]);
                $pdo->prepare("UPDATE usuarios SET email=? WHERE unidad_id=? AND email=? AND NOT EXISTS (SELECT 1 FROM usuario_roles ur JOIN roles r ON r.id=ur.rol_id WHERE ur.usuario_id=usuarios.id AND r.codigo='PRESIDENTE')")->execute([$email,$unidad,$old['email']]);
                log_action('Cambio de propietario de unidad #'.$unidad,'propietarios_historial');
                $pdo->commit(); header('Location:index.php?page=propietarios&unidad_id='.$unidad); exit;
            }catch(Throwable $e){$pdo->rollBack();echo '<div class="alert">No se pudo registrar el cambio de propietario: '.h($e->getMessage()).'</div>';}
        }
    }
    echo '<h1>Cambio de propietario</h1><div class="card"><form method="post" class="form"><label>Unidad</label><select name="unidad_id" onchange="if(this.value) location.href=\'index.php?page=propietarios&unidad_id=\'+this.value">';
    foreach($pdo->query("SELECT * FROM unidades ORDER BY id") as $x) echo '<option value="'.$x['id'].'"'.((int)($_GET['unidad_id']??0)===(int)$x['id']?' selected':'').'>'.h($x['nombre']).' — '.h($x['propietario']).'</option>';
    echo '</select>';
    $sel=(int)($_GET['unidad_id']??1);$st=$pdo->prepare("SELECT * FROM unidades WHERE id=?");$st->execute([$sel]);$unit=$st->fetch();
    echo '<label>Nuevo propietario</label><input name="propietario" value="" required><label>Email</label><input type="email" name="email" value="'.h($unit['email']??'').'"><label>Teléfono</label><input name="telefono" value="'.h($unit['telefono']??'').'"><label>Fecha de inicio</label><input type="date" name="fecha_inicio" value="'.date('Y-m-d').'" required><label>Motivo</label><input name="motivo" placeholder="Compraventa, herencia, etc."><br><button class="btn">Registrar cambio</button></form></div><br>';
    echo '<div class="card"><h2>Historial</h2><table><tr><th>Propietario</th><th>Email</th><th>Inicio</th><th>Fin</th><th>Motivo</th></tr>';
    $st=$pdo->prepare("SELECT * FROM propietarios_historial WHERE unidad_id=? ORDER BY fecha_inicio DESC,id DESC");$st->execute([$sel]);
    foreach($st as $x) echo '<tr><td>'.h($x['propietario']).'</td><td>'.h($x['email']).'</td><td>'.h($x['fecha_inicio']).'</td><td>'.h($x['fecha_fin']??'Actual').'</td><td>'.h($x['motivo']).'</td></tr>';
    echo '</table></div>';
break;

default: echo '<div class="alert">Módulo no encontrado.</div>';
}
layout_end();
