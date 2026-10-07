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

function documentos_count($tipo,$id){
    global $pdo;
    $st=$pdo->prepare("SELECT COUNT(*) FROM documentos WHERE entidad_tipo=? AND entidad_id=?");
    $st->execute([$tipo,(int)$id]);
    return (int)$st->fetchColumn();
}

function documentos_panel($tipo,$id,$titulo='Documentos asociados'){
    global $pdo;
    $tipo=trim((string)$tipo); $id=(int)$id;
    $st=$pdo->prepare("SELECT * FROM documentos WHERE entidad_tipo=? AND entidad_id=? ORDER BY created_at DESC,id DESC");
    $st->execute([$tipo,$id]); $docs=$st->fetchAll();
    echo '<div class="card"><h2>'.h($titulo).'</h2>';
    if(can('GESTION_DOCUMENTOS')){
        echo '<form method="post" class="form"><input type="hidden" name="documento_action" value="add"><input type="hidden" name="documento_tipo" value="'.h($tipo).'"><input type="hidden" name="documento_entidad_id" value="'.$id.'"><label>Nombre / título</label><input name="documento_titulo" required><label>Categoría</label><input name="documento_categoria" placeholder="Factura, presupuesto, acta, foto, informe..."><label>Enlace externo</label><input type="url" name="documento_url" placeholder="https://..." required><br><button class="btn">Añadir documento</button></form><br>';
    }
    if(!$docs){ echo '<p class="muted">No hay documentos asociados.</p>'; }
    else { echo '<table><tr><th>Documento</th><th>Categoría</th><th>Fecha</th>'.(can('GESTION_DOCUMENTOS')?'<th>Acciones</th>':'').'</tr>';
        foreach($docs as $d){ echo '<tr><td><a href="'.h($d['archivo_url']).'" target="_blank" rel="noopener noreferrer">'.h($d['titulo']).'</a></td><td>'.h($d['categoria']??'').'</td><td>'.h($d['created_at']).'</td>';
            if(can('GESTION_DOCUMENTOS')) echo '<td><form method="post" style="display:inline" onsubmit="return confirm(\'¿Borrar este documento?\')"><input type="hidden" name="documento_action" value="delete"><input type="hidden" name="documento_id" value="'.$d['id'].'"><input type="hidden" name="documento_tipo" value="'.h($tipo).'"><input type="hidden" name="documento_entidad_id" value="'.$id.'"><button class="btn gray">Borrar</button></form></td>';
            echo '</tr>'; }
        echo '</table>'; }
    echo '</div>';
}
