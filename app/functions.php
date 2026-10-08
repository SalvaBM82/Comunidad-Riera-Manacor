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
    $tipo=trim((string)$tipo);$id=(int)$id;
    $st=$pdo->prepare("SELECT * FROM documentos WHERE entidad_tipo=? AND entidad_id=? ORDER BY created_at DESC,id DESC");$st->execute([$tipo,$id]);$docs=$st->fetchAll();
    $modalId='doc-panel-'.preg_replace('/[^a-z0-9_-]/i','',$tipo).'-'.$id;
    echo '<div class="card"><div class="section-head"><h2>'.h($titulo).'</h2>';
    if(can('GESTION_DOCUMENTOS')) echo '<button type="button" class="btn" onclick="document.getElementById(&quot;'.h($modalId).'&quot;).showModal()">+ Añadir documento</button>';
    echo '</div>';
    if(can('GESTION_DOCUMENTOS')){
        echo '<dialog id="'.h($modalId).'" class="app-modal wide-modal"><div class="modal-head"><h2>Añadir documento</h2><button type="button" class="modal-close" onclick="this.closest(&quot;dialog&quot;).close()">×</button></div><div class="modal-body"><form method="post" enctype="multipart/form-data" class="modal-form"><input type="hidden" name="documento_action" value="add"><input type="hidden" name="documento_tipo" value="'.h($tipo).'"><input type="hidden" name="documento_entidad_id" value="'.$id.'"><label>Nombre / título</label><input name="documento_titulo" required><label>Categoría</label><input name="documento_categoria" placeholder="Factura, presupuesto, acta, foto, informe..."><label>Tipo</label><select name="documento_origen" onchange="var f=this.closest(&quot;form&quot;).querySelector(&quot;input[name=documento_archivo]&quot;),u=this.closest(&quot;form&quot;).querySelector(&quot;input[name=documento_url]&quot;);this.closest(&quot;form&quot;).querySelector(&quot;.doc-file-field&quot;).hidden=this.value!==&quot;ARCHIVO&quot;;this.closest(&quot;form&quot;).querySelector(&quot;.doc-url-field&quot;).hidden=this.value!==&quot;ENLACE&quot;;f.required=this.value===&quot;ARCHIVO&quot;;u.required=this.value===&quot;ENLACE&quot;"><option value="ARCHIVO">Archivo subido</option><option value="ENLACE">Enlace externo</option></select><div class="doc-file-field"><label>Archivo</label><input type="file" name="documento_archivo" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp,.txt,.csv" required><small class="muted">Máximo 25 MB.</small></div><div class="doc-url-field" hidden><label>Enlace externo</label><input type="url" name="documento_url" placeholder="https://..."></div><div class="modal-actions"><button type="button" class="btn gray" onclick="this.closest(&quot;dialog&quot;).close()">Cancelar</button><button class="btn">Añadir documento</button></div></form></div></dialog>';
    }
    if(!$docs){echo '<p class="muted">No hay documentos asociados.</p>';}else{
        echo '<table><tr><th>Documento</th><th>Categoría</th><th>Tipo</th><th>Fecha</th>'.(can('GESTION_DOCUMENTOS')?'<th>Acciones</th>':'').'</tr>';
        foreach($docs as $d){
            $link=$d['tipo']==='ARCHIVO'?'index.php?page=documento_descarga&id='.$d['id']:$d['archivo_url'];
            $label=$d['tipo']==='ARCHIVO'?'Descargar':'Abrir enlace';
            echo '<tr><td><a href="'.h($link).'"'.($d['tipo']==='ENLACE'?' target="_blank" rel="noopener noreferrer"':'').'>'.h($d['titulo']).'</a></td><td>'.h($d['categoria']??'').'</td><td>'.($d['tipo']==='ARCHIVO'?'Archivo':'Enlace').'</td><td>'.h($d['created_at']).'</td>';
            if(can('GESTION_DOCUMENTOS')) echo '<td><form method="post" style="display:inline" onsubmit="return confirm(&quot;¿Borrar este documento?&quot;)"><input type="hidden" name="documento_action" value="delete"><input type="hidden" name="documento_id" value="'.$d['id'].'"><input type="hidden" name="documento_tipo" value="'.h($tipo).'"><input type="hidden" name="documento_entidad_id" value="'.$id.'"><button class="btn gray">Borrar</button></form></td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    echo '</div>';
}
