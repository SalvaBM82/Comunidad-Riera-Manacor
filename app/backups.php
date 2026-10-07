<?php
function backup_sql_value($value){
    global $pdo;
    if($value===null) return 'NULL';
    if(is_bool($value)) return $value?'1':'0';
    return $pdo->quote((string)$value);
}
function backup_generate_sql(){
    global $pdo;
    $tables=$pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $out="-- GESTION_COMUNIDAD_BACKUP\n-- Generado: ".date('Y-m-d H:i:s')."\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    foreach($tables as $table){
        $create=$pdo->query("SHOW CREATE TABLE ".$table)->fetch(PDO::FETCH_ASSOC);
        $createSql=$create['Create Table']??array_values($create)[1]??'';
        $out.="DROP TABLE IF EXISTS ".$table.";\n".$createSql.";\n\n";
        $rows=$pdo->query("SELECT * FROM ".$table);
        while($row=$rows->fetch(PDO::FETCH_ASSOC)){
            $cols=[];$vals=[];
            foreach($row as $col=>$val){$cols[]=$col;$vals[]=backup_sql_value($val);}
            $out.="INSERT INTO ".$table." (".implode(',',$cols).") VALUES (".implode(',',$vals).");\n";
        }
        $out.="\n";
    }
    return $out."SET FOREIGN_KEY_CHECKS=1;\n";
}
function backup_split_sql($sql){
    $statements=[];$buf='';$len=strlen($sql);$quote=null;$lineComment=false;$blockComment=false;
    for($i=0;$i<$len;$i++){
        $ch=$sql[$i];$next=$i+1<$len?$sql[$i+1]:'';
        if($lineComment){$buf.=$ch;if($ch==="\n")$lineComment=false;continue;}
        if($blockComment){if($ch==='*'&&$next==='/'){$i++;$blockComment=false;}continue;}
        if($quote!==null){
            $buf.=$ch;
            if($ch==='\\'&&$i+1<$len){$buf.=$sql[++$i];continue;}
            if($ch===$quote)$quote=null;
            continue;
        }
        if($ch==="'"||$ch==='"'||$ch===chr(96)){$quote=$ch;$buf.=$ch;continue;}
        if($ch==='#'||($ch==='-'&&$next==='-')){$lineComment=true;if($ch==='-'){$buf.='--';$i++;}else{$buf.='#';}continue;}
        if($ch==='/'&&$next==='*'){$blockComment=true;$i++;continue;}
        if($ch===';'){$s=trim($buf);if($s!=='')$statements[]=$s;$buf='';continue;}
        $buf.=$ch;
    }
    $s=trim($buf);if($s!=='')$statements[]=$s;
    return $statements;
}
function backup_restore_sql($sql){
    global $pdo;
    if(strpos($sql,'GESTION_COMUNIDAD_BACKUP')===false) throw new Exception('El archivo no parece una copia de seguridad de Gestión Comunidad.');
    $statements=backup_split_sql($sql);
    if(count($statements)<3) throw new Exception('La copia de seguridad está vacía o incompleta.');
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    try{foreach($statements as $statement)$pdo->exec($statement);}
    finally{$pdo->exec("SET FOREIGN_KEY_CHECKS=1");}
    return count($statements);
}
?>