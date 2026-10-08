<?php
function documentos_storage_dir(): string {
    $dir=__DIR__.'/../storage/documentos';
    if(!is_dir($dir) && !mkdir($dir,0775,true) && !is_dir($dir)) throw new RuntimeException('No se pudo crear el directorio de documentos.');
    return $dir;
}
function documento_max_upload_bytes(): int {
    return 25*1024*1024;
}
function documento_upload(array $file): array {
    if(!isset($file['error'])) throw new RuntimeException('No se ha recibido ningún archivo.');
    $errors=[
        UPLOAD_ERR_INI_SIZE=>'El archivo supera el límite de subida configurado en el servidor.',
        UPLOAD_ERR_FORM_SIZE=>'El archivo supera el límite permitido por el formulario.',
        UPLOAD_ERR_PARTIAL=>'La subida del archivo quedó incompleta.',
        UPLOAD_ERR_NO_FILE=>'No has seleccionado ningún archivo.',
        UPLOAD_ERR_NO_TMP_DIR=>'El servidor no tiene directorio temporal de subida.',
        UPLOAD_ERR_CANT_WRITE=>'El servidor no puede escribir el archivo subido.',
        UPLOAD_ERR_EXTENSION=>'Una extensión de PHP ha detenido la subida.'
    ];
    if($file['error']!==UPLOAD_ERR_OK) throw new RuntimeException($errors[$file['error']]??'No se ha podido subir el archivo.');
    if((int)$file['size']<=0 || (int)$file['size']>documento_max_upload_bytes()) throw new RuntimeException('El archivo debe ocupar entre 1 byte y 25 MB.');
    $original=basename((string)($file['name']??'documento'));
    $ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
    $allowed=[
        'pdf'=>'application/pdf','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp',
        'txt'=>'text/plain','csv'=>'text/csv'
    ];
    if(!isset($allowed[$ext])) throw new RuntimeException('Tipo de archivo no permitido. Usa PDF, Word, Excel, imágenes, TXT o CSV.');
    // Algunos servidores (especialmente hosting compartido) devuelven application/octet-stream
    // para documentos válidos. La extensión ya está limitada a la lista anterior, así que no
    // bloqueamos una subida válida únicamente por el MIME detectado por el servidor.
    $mime=$file['type']??'application/octet-stream';
    $finfo=function_exists('finfo_open')?finfo_open(FILEINFO_MIME_TYPE):false;
    if($finfo){
        $detected=finfo_file($finfo,$file['tmp_name']);
        if($detected) $mime=$detected;
        finfo_close($finfo);
    }
    $stored=bin2hex(random_bytes(16)).'.'.$ext;
    $path=documentos_storage_dir().'/'.$stored;
    if(!is_dir(dirname($path)) && !mkdir(dirname($path),0775,true) && !is_dir(dirname($path))) throw new RuntimeException('No se pudo crear la carpeta de documentos.');
    if(!is_writable(dirname($path))) throw new RuntimeException('La carpeta de documentos no tiene permisos de escritura en el servidor.');
    if(!move_uploaded_file($file['tmp_name'],$path)) throw new RuntimeException('No se pudo guardar el archivo en el servidor.');
    return ['path'=>$stored,'name'=>$original,'mime'=>$mime,'size'=>(int)$file['size']];
}
function documento_delete_file(?string $relative): void {
    if(!$relative) return;
    $base=realpath(documentos_storage_dir());
    $full=realpath(documentos_storage_dir().'/'.basename($relative));
    if($base && $full && strpos($full,$base.DIRECTORY_SEPARATOR)===0 && is_file($full)) @unlink($full);
}
function documento_download(PDO $pdo,int $id): void {
    $st=$pdo->prepare("SELECT * FROM documentos WHERE id=? AND tipo='ARCHIVO' AND archivo_path IS NOT NULL");
    $st->execute([$id]); $doc=$st->fetch();
    if(!$doc){http_response_code(404);exit('Documento no encontrado.');}
    $full=realpath(documentos_storage_dir().'/'.basename($doc['archivo_path']));
    $base=realpath(documentos_storage_dir());
    if(!$full || !$base || strpos($full,$base.DIRECTORY_SEPARATOR)!==0 || !is_file($full)){http_response_code(404);exit('Archivo no encontrado.');}
    $downloadName=preg_replace('/[^A-Za-z0-9._ -]/u','_',basename($doc['archivo_nombre']?:$doc['titulo']));
    header('Content-Type: '.($doc['archivo_mime']?:'application/octet-stream'));
    header('Content-Length: '.filesize($full));
    header('Content-Disposition: attachment; filename="'.str_replace('"','',$downloadName).'"');
    header('X-Content-Type-Options: nosniff');
    readfile($full); exit;
}
