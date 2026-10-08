<?php
require_once __DIR__.'/../app/auth.php';
require_once __DIR__.'/../app/functions.php';
require_once __DIR__.'/../app/backups.php';
require_once __DIR__.'/../app/documents.php';

$page=$_GET['page']??'dashboard';
if($page==='logout'){ logout_user(); header('Location: index.php?page=login'); exit; }

if($page==='login'){
    $err='';
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(login_user(trim($_POST['email']),$_POST['password'])){ header('Location:index.php'); exit; }
        $err='Email o contraseña incorrectos.';
    }
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><title>Acceso</title><link rel="stylesheet" href="style.css"><link rel="icon" type="image/svg+xml" href="favicon.svg"></head><body>
    <div class="login card"><h1>Gestión Comunidad</h1><p class="muted">Acceso de propietarios</p><?php if($err):?><div class="alert"><?=h($err)?></div><?php endif;?>
    <form method="post" class="form"><label>Email</label><input name="email" type="email" required><label>Contraseña</label><input name="password" type="password" required><br><br><button class="btn">Entrar</button></form></div></body></html><?php exit;
}
require_login();

if($page==='documento_descarga'){
    documento_download($pdo,(int)($_GET['id']??0));
}

if($page==='backups' && ($_GET['action']??'')==='download'){
    if(!can('GESTION_BACKUPS')){http_response_code(403);exit('No tienes permiso para gestionar copias de seguridad.');}
    $sql=backup_generate_sql();
    $filename='backup_comunidad_'.date('Y-m-d_H-i-s').'.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Length: '.strlen($sql));
    echo $sql;
    exit;
}
if($page==='backups' && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['restore_backup'])){
    if(!can('GESTION_BACKUPS')){http_response_code(403);exit('No tienes permiso para restaurar copias de seguridad.');}
    if(!isset($_FILES['backup_file']) || $_FILES['backup_file']['error']!==UPLOAD_ERR_OK) exit('No se ha podido subir el archivo de backup.');
    if($_FILES['backup_file']['size']>100*1024*1024) exit('El archivo supera el límite de 100 MB.');
    $name=$_FILES['backup_file']['name']??'';
    if(strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='sql') exit('El backup debe ser un archivo .sql.');
    $sql=file_get_contents($_FILES['backup_file']['tmp_name']);
    try{
        $count=backup_restore_sql($sql);
        log_action('Restauró una copia de seguridad ('.$count.' sentencias)','backups');
        header('Location:index.php?page=backups&restored=1');exit;
    }catch(Throwable $e){
        http_response_code(500);
        exit('No se pudo restaurar la copia de seguridad: '.h($e->getMessage()));
    }
}

// Gestión común de documentos asociados a entidades: archivos subidos o enlaces externos.
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['documento_action'])){
    if(!can('GESTION_DOCUMENTOS')){ http_response_code(403); exit('No tienes permiso para gestionar documentos.'); }
    $action=$_POST['documento_action'];
    $tipo=trim($_POST['documento_tipo']??''); $entidadId=(int)($_POST['documento_entidad_id']??0);
    $allowed=['gasto'=>'GESTION_GASTOS','presupuesto'=>'GESTION_PRESUPUESTOS','recibo'=>'GESTION_RECIBOS','derrama'=>'GESTION_DERRAMAS','incidencia'=>'GESTION_INCIDENCIAS','votacion'=>'GESTION_VOTACIONES'];
    $tables=['gasto'=>'gastos','presupuesto'=>'presupuestos','recibo'=>'recibos','derrama'=>'derramas','incidencia'=>'incidencias','votacion'=>'votaciones'];
    if(!isset($allowed[$tipo]) || $entidadId<1) exit('Entidad documental no válida.');
    if(!can($allowed[$tipo])){http_response_code(403);exit('No tienes permiso para gestionar esta entidad.');}
    $table=$tables[$tipo]; $st=$pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE id=?");$st->execute([$entidadId]);
    if((int)$st->fetchColumn()===0) exit('La entidad no existe.');
    try{
        if($action==='add'){
            $titulo=trim($_POST['documento_titulo']??'');$categoria=trim($_POST['documento_categoria']??'');
            $tipoDoc=$_POST['documento_origen']??'ARCHIVO';$url=trim($_POST['documento_url']??'');
            if($titulo==='') throw new Exception('El título es obligatorio.');
            if($tipoDoc==='ENLACE'){
                if(!external_url($url)) throw new Exception('Indica un enlace externo válido (http:// o https://).');
                $pdo->prepare("INSERT INTO documentos(titulo,categoria,tipo,archivo_url,entidad_tipo,entidad_id,created_by) VALUES(?,?,?,?,?,?,?)")->execute([$titulo,$categoria?:null,'ENLACE',$url,$tipo,$entidadId,current_user()['id']]);
            }else{
                $up=documento_upload($_FILES['documento_archivo']??[]);
                $pdo->prepare("INSERT INTO documentos(titulo,categoria,tipo,archivo_path,archivo_nombre,archivo_mime,archivo_tamano,entidad_tipo,entidad_id,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)")->execute([$titulo,$categoria?:null,'ARCHIVO',$up['path'],$up['name'],$up['mime'],$up['size'],$tipo,$entidadId,current_user()['id']]);
            }
            log_action('Añadió documento a '.$tipo.' #'.$entidadId,'documentos');
        }elseif($action==='delete'){
            $docId=(int)($_POST['documento_id']??0);
            $st=$pdo->prepare("SELECT archivo_path FROM documentos WHERE id=? AND entidad_tipo=? AND entidad_id=?");$st->execute([$docId,$tipo,$entidadId]);$doc=$st->fetch();
            if($doc){$pdo->prepare("DELETE FROM documentos WHERE id=? AND entidad_tipo=? AND entidad_id=?")->execute([$docId,$tipo,$entidadId]);documento_delete_file($doc['archivo_path']??null);}
            log_action('Borró documento del '.$tipo.' #'.$entidadId,'documentos');
        }
        header('Location:index.php?page='.urlencode($page).'&docs_tipo='.urlencode($tipo).'&docs_id='.$entidadId);exit;
    }catch(Throwable $e){exit('No se pudo guardar el documento: '.h($e->getMessage()));}
}


function layout_start($title){
    $u=current_user();
    $currentPage=$_GET['page']??'dashboard';
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($title)?> · Gestión Comunidad</title><link rel="stylesheet" href="style.css"></head><body>
    <header class="top"><div class="brand">🏠 Gestión Comunidad</div><div class="user-info"><span><?=h($u['unidad_nombre']??'')?></span><span class="user-separator">·</span><span><?=h($u['rol_nombre']??role_label($u['rol']))?></span><a class="logout" href="index.php?page=logout">Salir</a></div></header><div class="wrap"><aside class="sidebar">
    <nav class="sidebar-nav">
        <div class="nav-title">Comunidad</div>
        <a class="nav-link<?=($currentPage==='dashboard'?' active':'')?>" href="index.php"><span class="nav-icon">📊</span><span>Inicio</span></a>
        <?php if(can('GESTION_UNIDADES')): ?><a class="nav-link<?=($currentPage==='unidades'?' active':'')?>" href="index.php?page=unidades"><span class="nav-icon">🏢</span><span>Unidades</span></a><?php endif; ?>

        <div class="nav-title">Gestión económica</div>
        <a class="nav-link<?=($currentPage==='gastos'?' active':'')?>" href="index.php?page=gastos"><span class="nav-icon">💶</span><span>Gastos</span></a>
        <a class="nav-link<?=($currentPage==='presupuestos'?' active':'')?>" href="index.php?page=presupuestos"><span class="nav-icon">📋</span><span>Presupuestos</span></a>
        <a class="nav-link<?=($currentPage==='recibos'?' active':'')?>" href="index.php?page=recibos"><span class="nav-icon">🧾</span><span>Recibos</span></a>
        <a class="nav-link<?=($currentPage==='derramas'?' active':'')?>" href="index.php?page=derramas"><span class="nav-icon">💰</span><span>Derramas</span></a>
        <a class="nav-link<?=($currentPage==='morosidad'?' active':'')?>" href="index.php?page=morosidad"><span class="nav-icon">⚠️</span><span>Morosidad</span></a>

        <div class="nav-title">Gestión</div>
        <a class="nav-link<?=($currentPage==='incidencias'?' active':'')?>" href="index.php?page=incidencias"><span class="nav-icon">🛠️</span><span>Incidencias</span></a>
        <a class="nav-link<?=($currentPage==='documentos'?' active':'')?>" href="index.php?page=documentos"><span class="nav-icon">📄</span><span>Documentos</span></a>
        <a class="nav-link<?=($currentPage==='votaciones'?' active':'')?>" href="index.php?page=votaciones"><span class="nav-icon">🗳️</span><span>Votaciones</span></a>

        <?php if(can('GESTION_USUARIOS') || can('GESTION_ROLES') || can('CAMBIO_PROPIETARIO') || can('GESTION_BACKUPS')): ?>
        <div class="nav-title">Administración</div>
        <?php if(can('GESTION_USUARIOS')): ?><a class="nav-link<?=($currentPage==='usuarios'?' active':'')?>" href="index.php?page=usuarios"><span class="nav-icon">👤</span><span>Usuarios</span></a><?php endif; ?>
        <?php if(can('GESTION_ROLES')): ?><a class="nav-link<?=($currentPage==='roles'?' active':'')?>" href="index.php?page=roles"><span class="nav-icon">🛡️</span><span>Roles</span></a><?php endif; ?>
        <?php if(can('CAMBIO_PROPIETARIO')): ?><a class="nav-link<?=($currentPage==='propietarios'?' active':'')?>" href="index.php?page=propietarios"><span class="nav-icon">🔄</span><span>Cambios de propietario</span></a><?php endif; ?>
        <?php if(can('GESTION_BACKUPS')): ?><a class="nav-link<?=($currentPage==='backups'?' active':'')?>" href="index.php?page=backups"><span class="nav-icon">💾</span><span>Backups</span></a><?php endif; ?>
        <?php endif; ?>
    </nav>
    </aside><main class="main"><?php
}
function layout_end(){ ?></main></div></body></html><?php }

// Procesar Usuarios antes de renderizar HTML: permite redirects y evita problemas de headers.
if($page==='usuarios' && $_SERVER['REQUEST_METHOD']==='POST'){
    if(!can('GESTION_USUARIOS')){http_response_code(403);exit('No tienes permiso para gestionar usuarios.');}
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
                    echo '<div class="alert">No se pudo crear el usuario: '.h($e->getMessage()).'</div>';
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
                    echo '<div class="alert">No se pudo actualizar el usuario: '.h($e->getMessage()).'</div>';
                }
            }
        } elseif($action==='delete'){
            $id=(int)($_POST['id']??0);
            if(!$id){
                echo '<div class="alert">Usuario no válido.</div>';
            } elseif($id===(int)current_user()['id']){
                echo '<div class="alert">No puedes eliminar tu propio usuario.</div>';
            } else {
                try{
                    $st=$pdo->prepare("SELECT id,email FROM usuarios WHERE id=?");
                    $st->execute([$id]);
                    $target=$st->fetch();
                    if(!$target) throw new Exception('El usuario no existe.');
                    $pdo->beginTransaction();
                    $pdo->prepare("DELETE FROM usuarios WHERE id=?")->execute([$id]);
                    $pdo->commit();
                    log_action('Eliminó usuario #'.$id.' ('.($target['email']??'').')','usuarios');
                    header('Location:index.php?page=usuarios');exit;
                }catch(Throwable $e){
                    if($pdo->inTransaction()) $pdo->rollBack();
                    echo '<div class="alert">No se pudo eliminar el usuario: '.h($e->getMessage()).'</div>';
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
}

layout_start(ucfirst($page));

if(isset($_GET['docs_tipo'],$_GET['docs_id'])){
    $docsTipo=trim($_GET['docs_tipo']); $docsId=(int)$_GET['docs_id'];
    $docsNames=['gasto'=>'Gasto','presupuesto'=>'Presupuesto','recibo'=>'Recibo','derrama'=>'Derrama','incidencia'=>'Incidencia','votacion'=>'Votación'];
    if(isset($docsNames[$docsTipo]) && $docsId>0) documentos_panel($docsTipo,$docsId,$docsNames[$docsTipo].' · Documentos');
}

switch($page){case 'backups':
    if(!can('GESTION_BACKUPS')){echo '<div class="alert">No tienes permiso para gestionar copias de seguridad.</div>';break;}
    echo '<h1>Copias de seguridad</h1>';
    if(isset($_GET['restored'])) echo '<div class="alert" style="background:#ecfdf5;border-color:#a7f3d0">La copia de seguridad se ha restaurado correctamente.</div>';
    echo '<div class="card"><h2>Crear backup</h2><p>Genera una copia completa de la base de datos, incluyendo estructura, usuarios, configuración de roles, gastos, recibos, documentos, votaciones y demás datos.</p><p class="muted">El archivo se descarga directamente en tu ordenador y no queda expuesto públicamente en la web.</p><a class="btn green" href="index.php?page=backups&action=download">Descargar backup completo</a></div><br>';
    echo '<div class="card"><h2>Restaurar backup</h2><div class="alert">⚠️ Restaurar sustituirá los datos actuales por los contenidos en el backup. Haz primero una copia de seguridad de la situación actual.</div><form method="post" enctype="multipart/form-data" class="form"><input type="hidden" name="restore_backup" value="1"><label>Archivo .sql</label><input type="file" name="backup_file" accept=".sql,text/sql" required><p class="muted">Solo se aceptan copias generadas por esta aplicación. Tamaño máximo: 100 MB.</p><br><button class="btn red" onclick="return confirm(&quot;ATENCIÓN: se sustituirán los datos actuales. ¿Has descargado antes un backup actual?&quot;)">Restaurar backup</button></form></div>';
break;


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

    $unitError='';
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(!can('GESTION_USUARIOS')){http_response_code(403);exit('No tienes permiso para gestionar usuarios.');}
        $action=$_POST['action']??'';
        try{
            if($action==='create_unit'){
                $nombre=trim($_POST['nombre']??'');
                $propietario=trim($_POST['propietario']??'');
                $email=trim($_POST['email']??'');
                $telefono=trim($_POST['telefono']??'');
                $coefGeneral=(float)($_POST['coef_general']??0);
                $coefEscalera=(float)($_POST['coef_escalera']??0);
                $acceso=isset($_POST['tiene_acceso_escalera'])?1:0;
                $m2=($_POST['m2']??'')===''?null:(float)$_POST['m2'];
                if($nombre==='' || $propietario==='' || $coefGeneral<0 || $coefGeneral>100 || $coefEscalera<0 || $coefEscalera>100 || ($m2!==null && $m2<0)) throw new Exception('Revisa los datos de la unidad y los coeficientes.');
                if($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new Exception('El email no es válido.');
                $pdo->prepare("INSERT INTO unidades(nombre,propietario,email,telefono,coef_general,coef_escalera,tiene_acceso_escalera,m2) VALUES(?,?,?,?,?,?,?,?)")
                    ->execute([$nombre,$propietario,$email?:null,$telefono?:null,$coefGeneral,$coefEscalera,$acceso,$m2]);
                log_action('Creó unidad '.$nombre,'unidades'); header('Location:index.php?page=unidades');exit;
            }
            if($action==='update_unit'){
                $id=(int)$_POST['id'];
                $nombre=trim($_POST['nombre']??''); $propietario=trim($_POST['propietario']??'');
                $email=trim($_POST['email']??''); $telefono=trim($_POST['telefono']??'');
                $coefGeneral=(float)($_POST['coef_general']??0); $coefEscalera=(float)($_POST['coef_escalera']??0);
                $acceso=isset($_POST['tiene_acceso_escalera'])?1:0;
                $m2=($_POST['m2']??'')===''?null:(float)$_POST['m2'];
                if(!$id || $nombre==='' || $propietario==='' || $coefGeneral<0 || $coefGeneral>100 || $coefEscalera<0 || $coefEscalera>100 || ($m2!==null && $m2<0)) throw new Exception('Revisa los datos de la unidad y los coeficientes.');
                if($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new Exception('El email no es válido.');
                $pdo->prepare("UPDATE unidades SET nombre=?,propietario=?,email=?,telefono=?,coef_general=?,coef_escalera=?,tiene_acceso_escalera=?,m2=? WHERE id=?")
                    ->execute([$nombre,$propietario,$email?:null,$telefono?:null,$coefGeneral,$coefEscalera,$acceso,$m2,$id]);
                log_action('Actualizó unidad #'.$id,'unidades'); header('Location:index.php?page=unidades');exit;
            }
            if($action==='delete_unit'){
                $id=(int)$_POST['id'];
                foreach([
                    ['usuarios','unidad_id=?'],['recibos','unidad_id=?'],['votos','unidad_id=?'],['propietarios_historial','unidad_id=?']
                ] as [$table,$where]){
                    $st=$pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}"); $st->execute([$id]);
                    if((int)$st->fetchColumn()>0) throw new Exception('No se puede borrar la unidad porque tiene datos históricos o relacionados.');
                }
                $st=$pdo->prepare("SELECT COUNT(*) FROM adelantos WHERE deudor_id=? OR acreedor_id=?"); $st->execute([$id,$id]);
                if((int)$st->fetchColumn()>0) throw new Exception('No se puede borrar la unidad porque tiene adelantos relacionados.');
                $pdo->prepare("DELETE FROM unidades WHERE id=?")->execute([$id]);
                log_action('Borró unidad #'.$id,'unidades'); header('Location:index.php?page=unidades');exit;
            }
        }catch(Throwable $e){$unitError=$e->getMessage();}
    }
    if($unitError) echo '<div class="alert">'.h($unitError).'</div>';
    $editId=(int)($_GET['edit']??0); $editUnit=null;
    if($editId){$st=$pdo->prepare("SELECT * FROM unidades WHERE id=?");$st->execute([$editId]);$editUnit=$st->fetch();if(!$editUnit)$editId=0;}

    echo '<div class="section-head"><div><h1>Unidades</h1></div><button type="button" class="btn" onclick="document.getElementById(&quot;unit-create-modal&quot;).showModal()">+ Nueva unidad</button></div>';
    $unitForm=function($u=null,$id=0,$action='create_unit'){
        echo '<form method="post" class="modal-form"><input type="hidden" name="action" value="'.$action.'">'.($id?'<input type="hidden" name="id" value="'.$id.'">':'');
        echo '<div class="form-grid-2"><div><label>Nombre</label><input name="nombre" value="'.h($u['nombre']??'').'" required></div><div><label>Propietario</label><input name="propietario" value="'.h($u['propietario']??'').'" required></div>';
        echo '<div><label>Email</label><input type="email" name="email" value="'.h($u['email']??'').'"></div><div><label>Teléfono</label><input name="telefono" value="'.h($u['telefono']??'').'"></div>';
        echo '<div><label>Coeficiente GENERAL (%)</label><input type="number" step="0.001" min="0" max="100" name="coef_general" value="'.h($u['coef_general']??'0').'" required></div><div><label>Coeficiente ESCALERA (%)</label><input type="number" step="0.001" min="0" max="100" name="coef_escalera" value="'.h($u['coef_escalera']??'0').'" required></div>';
        echo '<div><label>m²</label><input type="number" step="0.01" min="0" name="m2" value="'.h($u['m2']??'').'"></div><div class="form-check"><label><input type="checkbox" name="tiene_acceso_escalera"'.(!empty($u['tiene_acceso_escalera'])?' checked':'').'> Tiene acceso a escalera</label></div></div>';
        echo '<div class="modal-actions"><button type="button" class="btn gray" onclick="this.closest(&quot;dialog&quot;).close()">Cancelar</button><button class="btn">'.($id?'Guardar cambios':'Crear unidad').'</button></div></form>';
    };
    echo '<dialog id="unit-create-modal" class="app-modal"><div class="modal-head"><h2>Nueva unidad</h2><button type="button" class="modal-close" onclick="this.closest(&quot;dialog&quot;).close()">×</button></div><div class="modal-body">';$unitForm();echo '</div></dialog>';
    foreach($pdo->query("SELECT * FROM unidades ORDER BY id") as $mx){echo '<dialog id="unit-edit-'.$mx['id'].'" class="app-modal"><div class="modal-head"><h2>Editar unidad</h2><button type="button" class="modal-close" onclick="this.closest(&quot;dialog&quot;).close()">×</button></div><div class="modal-body">';$unitForm($mx,(int)$mx['id'],'update_unit');echo '</div></dialog>';} 
    echo '<div class="card"><h2>Unidades existentes</h2><p class="muted">Las unidades con recibos, propietarios históricos, votos, usuarios o adelantos relacionados no se pueden borrar para evitar pérdida de información.</p>';
    echo '<table><tr><th>Unidad</th><th>Propietario</th><th>General</th><th>Escalera</th><th>Acceso</th><th>m²</th><th>Acciones</th></tr>';
    foreach($pdo->query("SELECT * FROM unidades ORDER BY id") as $x){
        echo '<tr><td>'.h($x['nombre']).'</td><td>'.h($x['propietario']).'</td><td>'.$x['coef_general'].'%</td><td>'.$x['coef_escalera'].'%</td><td>'.($x['tiene_acceso_escalera']?'Sí':'No').'</td><td>'.h($x['m2']??'').'</td><td>';
        echo '<button type="button" class="btn gray" onclick="document.getElementById(&quot;unit-edit-'.$x['id'].'&quot;).showModal()">Editar</button> ';
        echo '<form method="post" style="display:inline" onsubmit="return confirm(&quot;¿Borrar esta unidad? Esta acción no se puede deshacer.&quot;)"><input type="hidden" name="action" value="delete_unit"><input type="hidden" name="id" value="'.$x['id'].'"><button class="btn gray" type="submit">Borrar</button></form>';
        echo '</td></tr>';
    }
    echo '</table></div>';
break;

case 'gastos':
    if(can('GESTION_GASTOS') && $_SERVER['REQUEST_METHOD']==='POST'){
        $pdo->prepare("INSERT INTO gastos(fecha,concepto,proveedor,importe_total,tipo_gasto,factura_url,pagado,created_by) VALUES(?,?,?,?,?,?,?,?)")
            ->execute([$_POST['fecha'],$_POST['concepto'],$_POST['proveedor'],$_POST['importe'],$_POST['tipo'],trim($_POST['factura_url']??'')?:null,isset($_POST['pagado'])?1:0,current_user()['id']]);
        log_action('Creó gasto','gastos'); header('Location:index.php?page=gastos'); exit;
    }
    echo '<div class="section-head"><h1>Gastos</h1>'.(can('GESTION_GASTOS')?'<button type="button" class="btn" onclick="document.getElementById(&quot;expense-modal&quot;).showModal()">+ Nuevo gasto</button>':'').'</div>';
    if(can('GESTION_GASTOS')) echo '<dialog id="expense-modal" class="app-modal"><div class="modal-head"><h2>Nuevo gasto</h2><button type="button" class="modal-close" onclick="this.closest(&quot;dialog&quot;).close()">×</button></div><div class="modal-body"><form method="post" class="modal-form"><div class="form-grid-2"><div><label>Fecha</label><input type="date" name="fecha" required></div><div><label>Concepto</label><input name="concepto" required></div><div><label>Proveedor</label><input name="proveedor"></div><div><label>Importe</label><input type="number" step="0.01" name="importe" required></div><div><label>Tipo</label><select name="tipo"><option>GENERAL</option><option>ESCALERA</option></select></div><div><label>Enlace externo de factura/documento</label><input type="url" name="factura_url" placeholder="https://..."></div></div><label class="form-check"><input type="checkbox" name="pagado"> Pagado</label><div class="modal-actions"><button type="button" class="btn gray" onclick="this.closest(&quot;dialog&quot;).close()">Cancelar</button><button class="btn">Guardar gasto</button></div></form></div></dialog>';
    $rows=$pdo->query("SELECT g.*,u.email FROM gastos g LEFT JOIN usuarios u ON u.id=g.created_by ORDER BY g.fecha DESC,g.id DESC")->fetchAll();
    echo '<table><tr><th>Fecha</th><th>Concepto</th><th>Proveedor</th><th>Tipo</th><th>Importe</th><th>Factura/documento</th><th>Documentos</th><th>Estado</th></tr>';
    foreach($rows as $x) echo '<tr><td>'.h($x['fecha']).'</td><td>'.h($x['concepto']).'</td><td>'.h($x['proveedor']).'</td><td><span class="pill">'.h($x['tipo_gasto']).'</span></td><td>'.number_format($x['importe_total'],2,',','.').' €</td><td>'.(!empty($x['factura_url'])?'<a href="'.h($x['factura_url']).'" target="_blank" rel="noopener noreferrer">Abrir</a>':'—').'</td><td>'.($x['pagado']?'Pagado':'Pendiente').'</td></tr>';
    echo '</table>';
break;

case 'presupuestos':
    if(can('GESTION_PRESUPUESTOS') && $_SERVER['REQUEST_METHOD']==='POST'){
        $pdo->prepare("INSERT INTO presupuestos(anio,tipo_gasto,concepto,importe_previsto) VALUES(?,?,?,?)")->execute([$_POST['anio'],$_POST['tipo'],$_POST['concepto'],$_POST['importe']]);
        header('Location:index.php?page=presupuestos');exit;
    }
    echo '<div class="section-head"><h1>Presupuestos anuales</h1>'.(can('GESTION_PRESUPUESTOS')?'<button type="button" class="btn" onclick="document.getElementById(&quot;budget-modal&quot;).showModal()">+ Nuevo presupuesto</button>':'').'</div>';
    if(can('GESTION_PRESUPUESTOS')) echo '<dialog id="budget-modal" class="app-modal"><div class="modal-head"><h2>Nuevo presupuesto</h2><button type="button" class="modal-close" onclick="this.closest(&quot;dialog&quot;).close()">×</button></div><div class="modal-body"><form method="post" class="modal-form"><div class="form-grid-2"><div><label>Año</label><input type="number" name="anio" value="'.date('Y').'" required></div><div><label>Tipo</label><select name="tipo"><option>GENERAL</option><option>ESCALERA</option></select></div><div><label>Concepto</label><input name="concepto" required></div><div><label>Importe previsto anual</label><input type="number" step="0.01" name="importe" required></div></div><div class="modal-actions"><button type="button" class="btn gray" onclick="this.closest(&quot;dialog&quot;).close()">Cancelar</button><button class="btn">Añadir</button></div></form></div></dialog>';
    echo '<table><tr><th>Año</th><th>Tipo</th><th>Concepto</th><th>Previsto</th><th>Real</th><th>Documentos</th></tr>';
    foreach($pdo->query("SELECT * FROM presupuestos ORDER BY anio DESC,id DESC") as $x) echo '<tr><td>'.$x['anio'].'</td><td>'.$x['tipo_gasto'].'</td><td>'.h($x['concepto']).'</td><td>'.number_format($x['importe_previsto'],2,',','.').' €</td><td>'.number_format($x['importe_real'],2,',','.').' €</td><td><a href="index.php?page=presupuestos&docs_tipo=presupuesto&docs_id='.$x['id'].'">'.documentos_count('presupuesto',$x['id']).'</a></td></tr>';
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
    echo '<div class="section-head"><h1>Recibos</h1>'.(can('GESTION_RECIBOS')?'<button type="button" class="btn" onclick="document.getElementById(&quot;receipt-modal&quot;).showModal()">+ Generar recibos</button>':'').'</div>';
    if(can('GESTION_RECIBOS')) echo '<dialog id="receipt-modal" class="app-modal"><div class="modal-head"><h2>Generar recibos</h2><button type="button" class="modal-close" onclick="this.closest(&quot;dialog&quot;).close()">×</button></div><div class="modal-body"><form method="post" class="modal-form"><div class="form-grid-2"><div><label>Año</label><input type="number" name="anio" value="'.date('Y').'"></div><div><label>Mes</label><input type="number" min="1" max="12" name="mes" value="'.date('n').'"></div><div><label>Vencimiento</label><input type="date" name="vencimiento" value="'.date('Y-m-d',strtotime('+15 days')).'"></div></div><p class="muted">Se calcularán automáticamente según los presupuestos y coeficientes actuales.</p><div class="modal-actions"><button type="button" class="btn gray" onclick="this.closest(&quot;dialog&quot;).close()">Cancelar</button><button class="btn">Generar recibos</button></div></form></div></dialog>';
    echo '<table><tr><th>Periodo</th><th>Unidad</th><th>General</th><th>Escalera</th><th>Total</th><th>Documentos</th><th>Estado</th></tr>';
    $rows=$pdo->query("SELECT r.*,u.nombre FROM recibos r JOIN unidades u ON u.id=r.unidad_id ORDER BY r.anio DESC,r.mes DESC,u.id")->fetchAll();
    foreach($rows as $x) echo '<tr><td>'.$x['mes'].'/'.$x['anio'].'</td><td>'.h($x['nombre']).'</td><td>'.number_format($x['importe_general'],2,',','.').' €</td><td>'.number_format($x['importe_escalera'],2,',','.').' €</td><td><b>'.number_format($x['total'],2,',','.').' €</b></td><td><a href="index.php?page=recibos&docs_tipo=recibo&docs_id='.$x['id'].'">'.documentos_count('recibo',$x['id']).'</a></td><td>'.$x['estado'].'</td></tr>';
    echo '</table>';
break;

case 'derramas':
    if(can('GESTION_DERRAMAS') && $_SERVER['REQUEST_METHOD']==='POST'){
        $pdo->prepare("INSERT INTO derramas(titulo,descripcion,fecha_acuerdo_junta,importe_total,tipo,fecha_limite) VALUES(?,?,?,?,?,?)")
        ->execute([$_POST['titulo'],$_POST['descripcion'],$_POST['fecha_acuerdo'],$_POST['importe'],$_POST['tipo'],$_POST['limite']]);
        log_action('Creó derrama','derramas');header('Location:index.php?page=derramas');exit;
    }
    echo '<div class="section-head"><h1>Derramas</h1>'.(can('GESTION_DERRAMAS')?'<button type="button" class="btn" onclick="document.getElementById(&quot;levy-modal&quot;).showModal()">+ Nueva derrama</button>':'').'</div>';
    if(can('GESTION_DERRAMAS')) echo '<dialog id="levy-modal" class="app-modal"><div class="modal-head"><h2>Nueva derrama</h2><button type="button" class="modal-close" onclick="this.closest(&quot;dialog&quot;).close()">×</button></div><div class="modal-body"><form method="post" class="modal-form"><div class="form-grid-2"><div><label>Título</label><input name="titulo" required></div><div><label>Importe total</label><input type="number" step="0.01" name="importe" required></div><div><label>Fecha acuerdo</label><input type="date" name="fecha_acuerdo"></div><div><label>Fecha límite</label><input type="date" name="limite" required></div><div><label>Tipo</label><select name="tipo"><option>GENERAL</option><option>ESCALERA</option></select></div><div><label>Descripción</label><textarea name="descripcion"></textarea></div></div><div class="modal-actions"><button type="button" class="btn gray" onclick="this.closest(&quot;dialog&quot;).close()">Cancelar</button><button class="btn">Crear derrama</button></div></form></div></dialog>';
    echo '<table><tr><th>Título</th><th>Tipo</th><th>Importe</th><th>Límite</th><th>Documentos</th></tr>';
    foreach($pdo->query("SELECT * FROM derramas ORDER BY id DESC") as $x) echo '<tr><td>'.h($x['titulo']).'</td><td>'.$x['tipo'].'</td><td>'.number_format($x['importe_total'],2,',','.').' €</td><td>'.$x['fecha_limite'].'</td><td><a href="index.php?page=derramas&docs_tipo=derrama&docs_id='.$x['id'].'">'.documentos_count('derrama',$x['id']).'</a></td></tr>';
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
    echo '<div class="section-head"><h1>Incidencias</h1>'.(can('GESTION_INCIDENCIAS')?'<button type="button" class="btn" onclick="document.getElementById(&quot;issue-modal&quot;).showModal()">+ Nueva incidencia</button>':'').'</div>';
    if(can('GESTION_INCIDENCIAS')) echo '<dialog id="issue-modal" class="app-modal"><div class="modal-head"><h2>Nueva incidencia</h2><button type="button" class="modal-close" onclick="this.closest(&quot;dialog&quot;).close()">×</button></div><div class="modal-body"><form method="post" class="modal-form"><label>Título</label><input name="titulo" required><label>Descripción</label><textarea name="descripcion"></textarea><label>Tipo</label><select name="tipo"><option>GENERAL</option><option>ESCALERA</option></select><div class="modal-actions"><button type="button" class="btn gray" onclick="this.closest(&quot;dialog&quot;).close()">Cancelar</button><button class="btn">Crear incidencia</button></div></form></div></dialog>';
    echo '<table><tr><th>Título</th><th>Tipo</th><th>Estado</th><th>Fecha</th><th>Documentos</th></tr>';
    foreach($pdo->query("SELECT * FROM incidencias ORDER BY id DESC") as $x) echo '<tr><td>'.h($x['titulo']).'</td><td>'.$x['tipo'].'</td><td>'.$x['estado'].'</td><td>'.$x['created_at'].'</td><td><a href="index.php?page=incidencias&docs_tipo=incidencia&docs_id='.$x['id'].'">'.documentos_count('incidencia',$x['id']).'</a></td></tr>';
    echo '</table>';
break;

case 'documentos':
    if(!can('GESTION_DOCUMENTOS')){echo '<div class="alert">No tienes permiso para gestionar documentos.</div>';break;}
    $docError='';
    if($_SERVER['REQUEST_METHOD']==='POST'){
        try{
            $action=$_POST['action']??'';
            if($action==='create_document' || $action==='update_document'){
                $id=(int)($_POST['id']??0);$titulo=trim($_POST['titulo']??'');$categoria=trim($_POST['categoria']??'');
                if($titulo==='') throw new Exception('El título es obligatorio.');
                $origen=$_POST['origen']??'ARCHIVO';
                if($action==='create_document'){
                    if($origen==='ENLACE'){
                        $url=trim($_POST['archivo_url']??'');if(!external_url($url)) throw new Exception('Indica un enlace externo válido.');
                        $pdo->prepare("INSERT INTO documentos(titulo,categoria,tipo,archivo_url,created_by) VALUES(?,?,?,?,?)")->execute([$titulo,$categoria?:null,'ENLACE',$url,current_user()['id']]);
                    }else{
                        $up=documento_upload($_FILES['archivo']??[]);
                        $pdo->prepare("INSERT INTO documentos(titulo,categoria,tipo,archivo_path,archivo_nombre,archivo_mime,archivo_tamano,created_by) VALUES(?,?,?,?,?,?,?,?)")->execute([$titulo,$categoria?:null,'ARCHIVO',$up['path'],$up['name'],$up['mime'],$up['size'],current_user()['id']]);
                    }
                    log_action('Creó documento','documentos');
                }else{
                    $st=$pdo->prepare("SELECT * FROM documentos WHERE id=?");$st->execute([$id]);$old=$st->fetch();if(!$old) throw new Exception('Documento no encontrado.');
                    if($origen==='ENLACE'){
                        $url=trim($_POST['archivo_url']??'');if(!external_url($url)) throw new Exception('Indica un enlace externo válido.');
                        $pdo->prepare("UPDATE documentos SET titulo=?,categoria=?,tipo='ENLACE',archivo_url=?,archivo_path=NULL,archivo_nombre=NULL,archivo_mime=NULL,archivo_tamano=NULL WHERE id=?")->execute([$titulo,$categoria?:null,$url,$id]);
                        documento_delete_file($old['archivo_path']??null);
                    }elseif(!empty($_FILES['archivo']['name'])){
                        $up=documento_upload($_FILES['archivo']);
                        $pdo->prepare("UPDATE documentos SET titulo=?,categoria=?,tipo='ARCHIVO',archivo_url=NULL,archivo_path=?,archivo_nombre=?,archivo_mime=?,archivo_tamano=? WHERE id=?")->execute([$titulo,$categoria?:null,$up['path'],$up['name'],$up['mime'],$up['size'],$id]);
                        documento_delete_file($old['archivo_path']??null);
                    }else{
                        $pdo->prepare("UPDATE documentos SET titulo=?,categoria=? WHERE id=?")->execute([$titulo,$categoria?:null,$id]);
                    }
                    log_action('Actualizó documento #'.$id,'documentos');
                }
                header('Location:index.php?page=documentos');exit;
            }
            if($action==='delete_document'){
                $id=(int)$_POST['id'];$st=$pdo->prepare("SELECT archivo_path FROM documentos WHERE id=?");$st->execute([$id]);$old=$st->fetch();
                $pdo->prepare("DELETE FROM documentos WHERE id=?")->execute([$id]);documento_delete_file($old['archivo_path']??null);
                log_action('Borró documento #'.$id,'documentos');header('Location:index.php?page=documentos');exit;
            }
        }catch(Throwable $e){$docError=$e->getMessage();}
    }
    if($docError) echo '<div class="alert">'.h($docError).'</div>';
    echo '<div class="section-head"><div><h1>Documentos</h1><p class="muted">Puedes subir archivos al servidor o guardar enlaces externos.</p></div><button type="button" class="btn" onclick="document.getElementById(&quot;document-create-modal&quot;).showModal()">+ Nuevo documento</button></div>';
    echo '<dialog id="document-create-modal" class="app-modal"><div class="modal-head"><h2>Nuevo documento</h2><button type="button" class="modal-close" onclick="this.closest(&quot;dialog&quot;).close()">×</button></div><div class="modal-body"><form method="post" enctype="multipart/form-data" class="modal-form"><input type="hidden" name="action" value="create_document"><label>Título</label><input name="titulo" required><label>Categoría</label><input name="categoria" placeholder="Acta, estatutos, seguro, factura..."><label>Tipo</label><select name="origen" onchange="this.closest(&quot;form&quot;).querySelector(&quot;.doc-file-field&quot;).hidden=this.value!==&quot;ARCHIVO&quot;;this.closest(&quot;form&quot;).querySelector(&quot;.doc-url-field&quot;).hidden=this.value!==&quot;ENLACE&quot;"><option value="ARCHIVO">Archivo subido</option><option value="ENLACE">Enlace externo</option></select><div class="doc-file-field"><label>Archivo</label><input type="file" name="archivo" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp,.txt,.csv"><small class="muted">Máximo 25 MB.</small></div><div class="doc-url-field" hidden><label>Enlace externo</label><input type="url" name="archivo_url" placeholder="https://..."></div><div class="modal-actions"><button type="button" class="btn gray" onclick="this.closest(&quot;dialog&quot;).close()">Cancelar</button><button class="btn">Guardar documento</button></div></form></div></dialog>';
    $docs=$pdo->query("SELECT d.*,u.email FROM documentos d LEFT JOIN usuarios u ON u.id=d.created_by ORDER BY d.created_at DESC,d.id DESC")->fetchAll();
    echo '<div class="card"><h2>Documentos</h2><table><tr><th>Título</th><th>Categoría</th><th>Tipo</th><th>Documento</th><th>Fecha</th><th>Acciones</th></tr>';
    foreach($docs as $d){
        $link=$d['tipo']==='ARCHIVO'?'index.php?page=documento_descarga&id='.$d['id']:$d['archivo_url'];
        $label=$d['tipo']==='ARCHIVO'?'Descargar':'Abrir enlace';
        echo '<tr><td>'.h($d['titulo']).'</td><td>'.h($d['categoria']??'').'</td><td>'.($d['tipo']==='ARCHIVO'?'<span class="pill">Archivo</span>':'<span class="pill">Enlace</span>').'</td><td><a href="'.h($link).'"'.($d['tipo']==='ENLACE'?' target="_blank" rel="noopener noreferrer"':'').'>'.$label.'</a>'.($d['tipo']==='ARCHIVO'?' <small class="muted">('.number_format(((int)$d['archivo_tamano'])/1048576,2,',','.').' MB)</small>':'').'</td><td>'.h($d['created_at']).'</td><td><button type="button" class="btn gray" onclick="document.getElementById(&quot;document-edit-'.$d['id'].'&quot;).showModal()">Editar</button> <form method="post" style="display:inline" onsubmit="return confirm(&quot;¿Borrar este documento?&quot;)"><input type="hidden" name="action" value="delete_document"><input type="hidden" name="id" value="'.$d['id'].'"><button class="btn gray">Borrar</button></form></td></tr>';
    }
    echo '</table></div>';
    foreach($docs as $d){
        echo '<dialog id="document-edit-'.$d['id'].'" class="app-modal"><div class="modal-head"><h2>Editar documento</h2><button type="button" class="modal-close" onclick="this.closest(&quot;dialog&quot;).close()">×</button></div><div class="modal-body"><form method="post" enctype="multipart/form-data" class="modal-form"><input type="hidden" name="action" value="update_document"><input type="hidden" name="id" value="'.$d['id'].'"><label>Título</label><input name="titulo" value="'.h($d['titulo']).'" required><label>Categoría</label><input name="categoria" value="'.h($d['categoria']??'').'"><label>Tipo</label><select name="origen"><option value="ARCHIVO"'.($d['tipo']==='ARCHIVO'?' selected':'').'>Archivo subido</option><option value="ENLACE"'.($d['tipo']==='ENLACE'?' selected':'').'>Enlace externo</option></select>'.($d['tipo']==='ARCHIVO'?'<p class="muted">Archivo actual: '.h($d['archivo_nombre']??'').'. Si seleccionas otro, se sustituirá.</p>':'').'<div><label>Nuevo archivo (opcional)</label><input type="file" name="archivo" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp,.txt,.csv"></div><label>Enlace externo (si eliges Enlace)</label><input type="url" name="archivo_url" value="'.h($d['archivo_url']??'').'" placeholder="https://..."><div class="modal-actions"><button type="button" class="btn gray" onclick="this.closest(&quot;dialog&quot;).close()">Cancelar</button><button class="btn">Guardar cambios</button></div></form></div></dialog>';
    }
break;

case 'votaciones':
    if(!can('GESTION_VOTACIONES')){echo '<div class="alert">No tienes permiso para gestionar votaciones.</div>';break;}
    $voteError='';
    if($_SERVER['REQUEST_METHOD']==='POST'){
        try{
            $action=$_POST['vote_action']??'';
            if($action==='create_vote'){
                $titulo=trim($_POST['titulo']??''); $descripcion=trim($_POST['descripcion']??'');
                $tipo=strtoupper($_POST['tipo']??'GENERAL'); $mayoria=strtoupper($_POST['mayoria']??'SIMPLE');
                $inicio=$_POST['fecha_inicio']??''; $fin=$_POST['fecha_fin']??'';
                $inicio=$inicio!==''?str_replace('T',' ',$inicio):date('Y-m-d H:i:s');
                $fin=$fin!==''?str_replace('T',' ',$fin):date('Y-m-d H:i:s',strtotime('+7 days'));
                $quorum=$_POST['quorum_minimo']!==''?(float)$_POST['quorum_minimo']:null;
                $coefMin=$_POST['coef_minimo']!==''?(float)$_POST['coef_minimo']:null;
                if($titulo==='' || !in_array($tipo,['GENERAL','ESCALERA'],true) || !in_array($mayoria,['SIMPLE','ABSOLUTA','3_5','UNANIMIDAD','COEFICIENTE'],true)) throw new Exception('Revisa los datos de la votación.');
                $pdo->prepare("INSERT INTO votaciones(titulo,descripcion,tipo,convocatoria,fecha_inicio,fecha_fin,mayoria,coef_minimo,quorum_minimo,estado,created_by) VALUES(?,?,?,?,?,?,?,?,?,'ABIERTA',?)")
                    ->execute([$titulo,$descripcion,$tipo,trim($_POST['convocatoria']??'')?:null,$inicio,$fin,$mayoria,$coefMin,$quorum,current_user()['id']]);
                $vid=(int)$pdo->lastInsertId(); log_action('Creó votación #'.$vid,'votaciones');
                header('Location:index.php?page=votaciones&vote='.$vid); exit;
            }
            if($action==='cast_vote'){
                $vid=(int)$_POST['votacion_id']; $unidad=(int)$_POST['unidad_id']; $valor=strtoupper($_POST['voto']??'');
                if(!in_array($valor,['SI','NO','ABSTENCION'],true)) throw new Exception('Voto no válido.');
                $st=$pdo->prepare("SELECT * FROM votaciones WHERE id=? AND estado='ABIERTA'");$st->execute([$vid]);$v=$st->fetch();
                if(!$v) throw new Exception('La votación no está abierta.');
                $st=$pdo->prepare("SELECT * FROM unidades WHERE id=?");$st->execute([$unidad]);$un=$st->fetch();
                if(!$un) throw new Exception('Unidad no válida.');
                $coef=$v['tipo']==='ESCALERA'?(float)$un['coef_escalera']:(float)$un['coef_general'];
                if($coef<=0) throw new Exception('La unidad no participa en esta votación.');
                $pdo->prepare("INSERT INTO votos(votacion_id,unidad_id,voto,coeficiente,comentario) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE voto=VALUES(voto),coeficiente=VALUES(coeficiente),comentario=VALUES(comentario)")
                    ->execute([$vid,$unidad,$valor,$coef,trim($_POST['comentario']??'')?:null]);
                log_action('Registró voto en votación #'.$vid,'votos');
                header('Location:index.php?page=votaciones&vote='.$vid); exit;
            }
            if($action==='close_vote'){
                $vid=(int)$_POST['votacion_id'];
                $st=$pdo->prepare("SELECT * FROM votaciones WHERE id=? AND estado='ABIERTA'");$st->execute([$vid]);$v=$st->fetch();
                if(!$v) throw new Exception('La votación no está abierta.');
                $pdo->prepare("UPDATE votaciones SET estado='CERRADA',cerrada_at=NOW() WHERE id=?")->execute([$vid]);
                log_action('Cerró votación #'.$vid,'votaciones');
                header('Location:index.php?page=votaciones&vote='.$vid); exit;
            }
        }catch(Throwable $e){$voteError=$e->getMessage();}
    }
    if($voteError) echo '<div class="alert">'.h($voteError).'</div>';
    echo '<div class="section-head"><h1>Votaciones</h1><button type="button" class="btn" onclick="document.getElementById(&quot;vote-create-modal&quot;).showModal()">+ Nueva votación</button></div>';
    echo '<dialog id="vote-create-modal" class="app-modal wide-modal"><div class="modal-head"><h2>Nueva votación</h2><button type="button" class="modal-close" onclick="this.closest(&quot;dialog&quot;).close()">×</button></div><div class="modal-body"><form method="post" class="modal-form"><input type="hidden" name="vote_action" value="create_vote"><div class="form-grid-2"><div><label>Título / asunto</label><input name="titulo" required></div><div><label>Convocatoria</label><input name="convocatoria" placeholder="Junta ordinaria, extraordinaria..."></div><div><label>Tipo</label><select name="tipo"><option value="GENERAL">GENERAL</option><option value="ESCALERA">ESCALERA</option></select></div><div><label>Mayoría</label><select name="mayoria"><option value="SIMPLE">Mayoría simple</option><option value="ABSOLUTA">Mayoría absoluta</option><option value="3_5">3/5</option><option value="UNANIMIDAD">Unanimidad</option><option value="COEFICIENTE">Coeficiente mínimo personalizado</option></select></div><div><label>Coeficiente mínimo (%)</label><input type="number" step="0.01" min="0" max="100" name="coef_minimo"></div><div><label>Quórum mínimo (%)</label><input type="number" step="0.01" min="0" max="100" name="quorum_minimo"></div><div><label>Inicio</label><input type="datetime-local" name="fecha_inicio"></div><div><label>Fin</label><input type="datetime-local" name="fecha_fin"></div></div><label>Descripción / propuesta</label><textarea name="descripcion" rows="4"></textarea><div class="modal-actions"><button type="button" class="btn gray" onclick="this.closest(&quot;dialog&quot;).close()">Cancelar</button><button class="btn">Crear votación</button></div></form></div></dialog>';
    $votes=$pdo->query("SELECT v.*,u.email creador FROM votaciones v LEFT JOIN usuarios u ON u.id=v.created_by ORDER BY v.id DESC")->fetchAll();
    echo '<div class="card"><h2>Votaciones</h2><table><tr><th>Asunto</th><th>Tipo</th><th>Mayoría</th><th>Estado</th><th>Participación</th><th>Documentos</th></tr>';
    foreach($votes as $v){
        $st=$pdo->prepare("SELECT COUNT(*) n,COALESCE(SUM(coeficiente),0) coef FROM votos WHERE votacion_id=?");$st->execute([$v['id']]);$res=$st->fetch();
        $total=(float)$pdo->query($v['tipo']==='ESCALERA'?"SELECT COALESCE(SUM(coef_escalera),0) FROM unidades":"SELECT COALESCE(SUM(coef_general),0) FROM unidades")->fetchColumn();
        $pct=$total>0?((float)$res['coef']/$total*100):0;
        echo '<tr><td><a href="index.php?page=votaciones&vote='.$v['id'].'">'.h($v['titulo']).'</a></td><td>'.h($v['tipo']).'</td><td>'.h($v['mayoria']).'</td><td>'.h($v['estado']).'</td><td>'.number_format($pct,2,',','.').'%</td><td><a href="index.php?page=votaciones&vote='.$v['id'].'&docs_tipo=votacion&docs_id='.$v['id'].'">'.documentos_count('votacion',$v['id']).'</a></td></tr>';
    }
    echo '</table></div>';
    $voteId=(int)($_GET['vote']??0);
    if($voteId){
        $st=$pdo->prepare("SELECT * FROM votaciones WHERE id=?");$st->execute([$voteId]);$v=$st->fetch();
        if($v){
            echo '<br><div class="card"><h2>'.h($v['titulo']).'</h2><p>'.nl2br(h($v['descripcion']??'')).'</p><p><b>'.h($v['tipo']).'</b> · Mayoría: '.h($v['mayoria']).' · Estado: '.h($v['estado']).'</p>';
            echo '<p><a class="btn gray" href="index.php?page=votaciones&docs_tipo=votacion&docs_id='.$v['id'].'">Documentos ('.documentos_count('votacion',$v['id']).')</a></p>';
            if($v['estado']==='ABIERTA'){
                echo '<h3>Registrar / cambiar voto</h3><button type="button" class="btn" onclick="document.getElementById(&quot;cast-vote-modal&quot;).showModal()">Registrar / cambiar voto</button>';
                echo '<dialog id="cast-vote-modal" class="app-modal small-modal"><div class="modal-head"><h2>Registrar / cambiar voto</h2><button type="button" class="modal-close" onclick="this.closest(&quot;dialog&quot;).close()">×</button></div><div class="modal-body"><form method="post" class="modal-form"><input type="hidden" name="vote_action" value="cast_vote"><input type="hidden" name="votacion_id" value="'.$v['id'].'"><label>Unidad</label><select name="unidad_id">';
                foreach($pdo->query("SELECT id,nombre,propietario FROM unidades ORDER BY id") as $un){$coef=$v['tipo']==='ESCALERA'?(float)$un['coef_escalera']:(float)$un['coef_general'];if($coef>0) echo '<option value="'.$un['id'].'">'.h($un['nombre']).' — '.h($un['propietario']).' ('.$coef.'%)</option>';}
                echo '</select><label>Voto</label><select name="voto"><option value="SI">Sí</option><option value="NO">No</option><option value="ABSTENCION">Abstención</option></select><label>Comentario</label><textarea name="comentario" rows="3"></textarea><div class="modal-actions"><button type="button" class="btn gray" onclick="this.closest(&quot;dialog&quot;).close()">Cancelar</button><button class="btn">Guardar voto</button></div></form></div></dialog>';
                echo '<form method="post" style="margin-top:12px"><input type="hidden" name="vote_action" value="close_vote"><input type="hidden" name="votacion_id" value="'.$v['id'].'"><button class="btn gray" onclick="return confirm(&quot;¿Cerrar esta votación? Ya no se podrán modificar los votos.&quot;)">Cerrar votación</button></form>';
            }
            $st=$pdo->prepare("SELECT vo.*,un.nombre unidad,un.propietario FROM votos vo JOIN unidades un ON un.id=vo.unidad_id WHERE vo.votacion_id=? ORDER BY vo.id");$st->execute([$voteId]);$vr=$st->fetchAll();
            $si=$no=$ab=0;foreach($vr as $vv){if($vv['voto']==='SI')$si+=(float)$vv['coeficiente'];elseif($vv['voto']==='NO')$no+=(float)$vv['coeficiente'];else$ab+=(float)$vv['coeficiente'];}
            echo '<h3>Resultado actual</h3><p>Sí: <b>'.number_format($si,2,',','.').'%</b> · No: <b>'.number_format($no,2,',','.').'%</b> · Abstención: <b>'.number_format($ab,2,',','.').'%</b></p>';
            echo '<table><tr><th>Unidad</th><th>Propietario</th><th>Voto</th><th>Coeficiente</th><th>Comentario</th></tr>';
            foreach($vr as $vv) echo '<tr><td>'.h($vv['unidad']).'</td><td>'.h($vv['propietario']).'</td><td>'.h($vv['voto']).'</td><td>'.number_format($vv['coeficiente'],2,',','.').'%</td><td>'.h($vv['comentario']??'').'</td></tr>';
            echo '</table></div>';
        }
    }
break;


case 'usuarios':
    if(!can('GESTION_USUARIOS')){echo '<div class="alert">No tienes permiso para gestionar usuarios.</div>';break;}

    $activeRoles=$pdo->query("SELECT id,codigo,nombre FROM roles WHERE activo=1 ORDER BY sistema DESC,nombre")->fetchAll();
    echo '<div class="section-head"><h1>Usuarios</h1><button type="button" class="btn" onclick="document.getElementById(&quot;user-create-modal&quot;).showModal()">+ Nuevo usuario</button></div>';
    echo '<dialog id="user-create-modal" class="app-modal"><div class="modal-head"><h2>Nuevo usuario</h2><button type="button" class="modal-close" onclick="this.closest(&quot;dialog&quot;).close()">×</button></div><div class="modal-body"><form method="post" action="index.php?page=usuarios" class="modal-form"><input type="hidden" name="action" value="create"><div class="form-grid-2"><div><label>Email</label><input type="email" name="email" required></div><div><label>Contraseña inicial</label><input type="password" name="password" minlength="8" required></div><div><label>Unidad</label><select name="unidad_id"><option value="0">Sin unidad</option>';
    foreach($pdo->query("SELECT id,nombre,propietario FROM unidades ORDER BY id") as $x) echo '<option value="'.$x['id'].'">'.h($x['nombre']).' — '.h($x['propietario']).'</option>';
    echo '</select></div><div></div></div><label>Roles</label><div class="permissions-grid">';
    foreach($activeRoles as $r) echo '<label class="permission-item"><input type="checkbox" name="roles[]" value="'.$r['id'].'"> '.h($r['nombre']).'</label>';
    echo '</div><div class="modal-actions"><button type="button" class="btn gray" onclick="this.closest(&quot;dialog&quot;).close()">Cancelar</button><button class="btn">Crear usuario</button></div></form></div></dialog>';

    $users=$pdo->query("SELECT u.*,un.nombre unidad_nombre FROM usuarios u LEFT JOIN unidades un ON un.id=u.unidad_id ORDER BY u.id")->fetchAll();
    echo '<div class="card"><h2>Usuarios existentes</h2><table><tr><th>Email</th><th>Unidad</th><th>Roles</th><th>Estado</th><th>Guardar</th><th>Contraseña</th></tr>';
    $roleSt=$pdo->prepare("SELECT r.id,r.codigo,r.nombre FROM usuario_roles ur JOIN roles r ON r.id=ur.rol_id WHERE ur.usuario_id=? ORDER BY r.sistema DESC,r.nombre");
    foreach($users as $x){
        $roleSt->execute([$x['id']]);
        $assigned=$roleSt->fetchAll();
        $assignedIds=array_map('intval',array_column($assigned,'id'));
        echo '<tr><td>'.h($x['email']).'</td><td>'.h($x['unidad_nombre']??'Sin unidad').'</td><td><div class="role-tags">';
        foreach($assigned as $ar) echo '<span class="pill">'.h($ar['nombre']).'</span> ';
        echo '</div></td><td>'.($x['activo']?'<span class="status-ok">Activo</span>':'<span class="status-off">Inactivo</span>').'</td><td><button type="button" class="btn gray" onclick="document.getElementById(&quot;user-edit-'.$x['id'].'&quot;).showModal()">Editar</button> <button type="button" class="btn gray" onclick="document.getElementById(&quot;user-password-'.$x['id'].'&quot;).showModal()">Contraseña</button> '.((int)$x['id']!==(int)current_user()['id']?'<form method="post" action="index.php?page=usuarios" style="display:inline" onsubmit="return confirm(&quot;¿Eliminar este usuario? Esta acción no se puede deshacer.&quot;)"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="'.$x['id'].'"><button type="submit" class="btn red">Eliminar</button></form>':'').'</td></tr>';
    }
    echo '</table></div>';
    foreach($users as $x){
        $roleSt->execute([$x['id']]); $assigned=$roleSt->fetchAll(); $assignedIds=array_map('intval',array_column($assigned,'id'));
        echo '<dialog id="user-edit-'.$x['id'].'" class="app-modal"><div class="modal-head"><h2>Editar usuario</h2><button type="button" class="modal-close" onclick="this.closest(&quot;dialog&quot;).close()">×</button></div><div class="modal-body"><form method="post" action="index.php?page=usuarios" class="modal-form"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="'.$x['id'].'"><div class="form-grid-2"><div><label>Email</label><input type="email" name="email" value="'.h($x['email']).'" required></div><div><label>Unidad</label><select name="unidad_id"><option value="0">Sin unidad</option>';
        foreach($pdo->query("SELECT id,nombre FROM unidades ORDER BY id") as $un) echo '<option value="'.$un['id'].'"'.((int)$x['unidad_id']===(int)$un['id']?' selected':'').'>'.h($un['nombre']).'</option>';
        echo '</select></div></div><label>Roles</label><div class="permissions-grid">';
        foreach($activeRoles as $r) echo '<label class="permission-item"><input type="checkbox" name="roles[]" value="'.$r['id'].'"'.(in_array((int)$r['id'],$assignedIds,true)?' checked':'').'> '.h($r['nombre']).'</label>';
        echo '</div><label class="form-check"><input type="checkbox" name="activo"'.($x['activo']?' checked':'').'> Activo</label><div class="modal-actions"><button type="button" class="btn gray" onclick="this.closest(&quot;dialog&quot;).close()">Cancelar</button><button class="btn">Guardar cambios</button></div></form></div></dialog>';
        echo '<dialog id="user-password-'.$x['id'].'" class="app-modal small-modal"><div class="modal-head"><h2>Cambiar contraseña</h2><button type="button" class="modal-close" onclick="this.closest(&quot;dialog&quot;).close()">×</button></div><div class="modal-body"><form method="post" action="index.php?page=usuarios" class="modal-form"><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="'.$x['id'].'"><label>Nueva contraseña</label><input name="password" type="password" minlength="8" required><div class="modal-actions"><button type="button" class="btn gray" onclick="this.closest(&quot;dialog&quot;).close()">Cancelar</button><button class="btn">Cambiar contraseña</button></div></form></div></dialog>';
    }
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
                if(!$activo){
                    $st=$pdo->prepare("SELECT COUNT(*) FROM usuario_roles ur JOIN usuarios u ON u.id=ur.usuario_id WHERE ur.rol_id=? AND u.activo=1");
                    $st->execute([$rid]);
                    if((int)$st->fetchColumn()>0){
                        echo '<div class="alert">No se puede desactivar este rol porque hay usuarios activos asignados a él. Cambia primero esos usuarios a otro rol.</div>';
                        break;
                    }
                }
                $pdo->prepare("UPDATE roles SET nombre=?,descripcion=?,activo=? WHERE id=?")->execute([$nombre,$descripcion,$activo,$rid]);
                $pdo->prepare("DELETE FROM rol_permisos WHERE rol_id=?")->execute([$rid]);
                foreach(($_POST['permisos']??[]) as $pid){$pdo->prepare("INSERT IGNORE INTO rol_permisos(rol_id,permiso_id) VALUES(?,?)")->execute([$rid,(int)$pid]);}
                log_action('Actualizó rol '.$role['codigo'],'roles');header('Location:index.php?page=roles');exit;
            }
        }
    }

    $permisos=$pdo->query("SELECT * FROM permisos ORDER BY nombre")->fetchAll();

    // Roles: listado compacto. La edición se realiza en una ventana modal para no deformar la tabla.
    echo '<div class="page-head"><div><h1>Roles</h1><p class="muted">Define los permisos de cada rol. Un usuario puede tener varios roles.</p></div><button type="button" class="btn" onclick="document.getElementById(&quot;new-role-modal&quot;).showModal()">+ Nuevo rol</button></div>';

    $roles=$pdo->query("SELECT r.*, (SELECT COUNT(*) FROM usuario_roles ur JOIN usuarios u ON u.id=ur.usuario_id WHERE ur.rol_id=r.id AND u.activo=1) AS usuarios_activos FROM roles r ORDER BY r.sistema DESC,r.nombre")->fetchAll();

    echo '<div class="card roles-list"><table><tr><th>Rol</th><th>Descripción</th><th class="role-users-col">Usuarios</th><th>Estado</th><th class="role-actions-col">Acciones</th></tr>';
    foreach($roles as $r){
        $st=$pdo->prepare("SELECT permiso_id FROM rol_permisos WHERE rol_id=?");$st->execute([$r['id']]);
        $selected=array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));
        $badge=$r['activo']?'<span class="status-ok">Activo</span>':'<span class="status-off">Inactivo</span>';
        echo '<tr><td><strong>'.h($r['nombre']).'</strong>'.($r['sistema']?'<div class="muted small">Rol del sistema</div>':'').'</td><td class="role-description">'.h($r['descripcion']??'').'</td><td class="role-users-col">'.(int)$r['usuarios_activos'].'</td><td>'.$badge.'</td><td class="role-actions-col"><button type="button" class="btn gray" onclick="document.getElementById(&quot;role-modal-'.$r['id'].'&quot;).showModal()">Editar</button></td></tr>';
    }
    echo '</table></div>';

    echo '<dialog id="new-role-modal" class="role-modal"><div class="role-modal-header"><div><h2>Nuevo rol</h2><p class="muted">Crea un rol y asigna únicamente los permisos que necesite.</p></div><button type="button" class="modal-close" onclick="this.closest(&quot;dialog&quot;).close()">×</button></div><form method="post" class="role-modal-form"><input type="hidden" name="action" value="create_role"><div class="role-fields"><div><label>Nombre</label><input name="nombre" required placeholder="Ej.: Tesorero"></div><div><label>Descripción</label><input name="descripcion" placeholder="Descripción breve"></div></div><label>Permisos</label><div class="permissions-grid">';
    foreach($permisos as $p) echo '<label class="permission-item"><input type="checkbox" name="permisos[]" value="'.$p['id'].'"> '.h($p['nombre']).'</label>';
    echo '</div><div class="role-modal-actions"><button type="button" class="btn gray" onclick="this.closest(&quot;dialog&quot;).close()">Cancelar</button><button class="btn">Crear rol</button></div></form></dialog>';

    foreach($roles as $r){
        $st=$pdo->prepare("SELECT permiso_id FROM rol_permisos WHERE rol_id=?");$st->execute([$r['id']]);
        $selected=array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));
        echo '<dialog id="role-modal-'.$r['id'].'" class="role-modal"><div class="role-modal-header"><div><h2>Editar rol</h2><p class="muted">'.h($r['nombre']).($r['sistema']?' · Rol del sistema':'').'</p></div><button type="button" class="modal-close" onclick="this.closest(&quot;dialog&quot;).close()">×</button></div><form method="post" class="role-modal-form"><input type="hidden" name="action" value="update_role"><input type="hidden" name="id" value="'.$r['id'].'"><div class="role-fields"><div><label>Nombre</label><input name="nombre" value="'.h($r['nombre']).'" required></div><div><label>Descripción</label><input name="descripcion" value="'.h($r['descripcion']??'').'"></div></div><label class="role-active"><input type="checkbox" name="activo"'.($r['activo']?' checked':'').'> Activo</label><label>Permisos</label><div class="permissions-grid">';
        foreach($permisos as $p) echo '<label class="permission-item"><input type="checkbox" name="permisos[]" value="'.$p['id'].'"'.(in_array((int)$p['id'],$selected,true)?' checked':'').'> '.h($p['nombre']).'</label>';
        echo '</div><div class="role-modal-actions"><button type="button" class="btn gray" onclick="this.closest(&quot;dialog&quot;).close()">Cancelar</button><button class="btn">Guardar cambios</button></div></form></dialog>';
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
    echo '<div class="section-head"><h1>Cambio de propietario</h1><button type="button" class="btn" onclick="document.getElementById(&quot;owner-change-modal&quot;).showModal()">+ Registrar cambio</button></div>';
    echo '<dialog id="owner-change-modal" class="app-modal"><div class="modal-head"><h2>Registrar cambio de propietario</h2><button type="button" class="modal-close" onclick="this.closest(&quot;dialog&quot;).close()">×</button></div><div class="modal-body"><form method="post" class="modal-form"><div class="form-grid-2"><div><label>Unidad</label><select name="unidad_id" onchange="if(this.value) location.href=&quot;index.php?page=propietarios&unidad_id=&quot;+this.value">';
    foreach($pdo->query("SELECT * FROM unidades ORDER BY id") as $x) echo '<option value="'.$x['id'].'"'.((int)($_GET['unidad_id']??1)===(int)$x['id']?' selected':'').'>'.h($x['nombre']).' — '.h($x['propietario']).'</option>';
    echo '</select></div><div><label>Fecha de inicio</label><input type="date" name="fecha_inicio" value="'.date('Y-m-d').'" required></div></div>';
    $sel=(int)($_GET['unidad_id']??1);$st=$pdo->prepare("SELECT * FROM unidades WHERE id=?");$st->execute([$sel]);$unit=$st->fetch();
    echo '<div class="form-grid-2"><div><label>Nuevo propietario</label><input name="propietario" required></div><div><label>Email</label><input type="email" name="email" value="'.h($unit['email']??'').'"></div><div><label>Teléfono</label><input name="telefono" value="'.h($unit['telefono']??'').'"></div><div><label>Motivo</label><input name="motivo" placeholder="Compraventa, herencia, etc."></div></div><div class="modal-actions"><button type="button" class="btn gray" onclick="this.closest(&quot;dialog&quot;).close()">Cancelar</button><button class="btn">Registrar cambio</button></div></form></div></dialog>';
    echo '<div class="card"><h2>Historial</h2><table><tr><th>Propietario</th><th>Email</th><th>Inicio</th><th>Fin</th><th>Motivo</th></tr>';
    $st=$pdo->prepare("SELECT * FROM propietarios_historial WHERE unidad_id=? ORDER BY fecha_inicio DESC,id DESC");$st->execute([$sel]);
    foreach($st as $x) echo '<tr><td>'.h($x['propietario']).'</td><td>'.h($x['email']).'</td><td>'.h($x['fecha_inicio']).'</td><td>'.h($x['fecha_fin']??'Actual').'</td><td>'.h($x['motivo']).'</td></tr>';
    echo '</table></div>';
break;

default: echo '<div class="alert">Módulo no encontrado.</div>';
}
layout_end();