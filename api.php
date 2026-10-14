<?php
declare(strict_types=1);
require __DIR__.'/lib.php';
if(!current_user()) json_out(['ok'=>false,'error'=>'No autenticado'],401);

/* La acción y los datos viajan en el cuerpo JSON del POST; la query string es fallback */
 $raw=json_decode(file_get_contents('php://input'),true);
 $in=is_array($raw)?$raw:[];
 $action=(string)($in['action']??($_GET['action']??''));
 $data=is_array($in['data']??null)?$in['data']:$in;
 $pdo=db();

switch($action){
case 'bootstrap':
  json_out(['ok'=>true,'state'=>build_state()]);

case 'save_project': {
  check_csrf();
  $id=(int)($data['id']??0);
  $name=trim((string)($data['name']??''));
  if($name==='')json_out(['ok'=>false,'error'=>'El proyecto necesita un nombre.'],400);
  $color=preg_match('/^#[0-9a-fA-F]{6}$/',(string)($data['color']??''))?$data['color']:'#5b8cff';
  $isPublic=!empty($data['isPublic'])?1:0;
  $slugIn=trim((string)($data['slug']??''));
  $clean=[];
  foreach(($data['plan']??[]) as $tid=>$h){$tid=(int)$tid;$h=(float)$h;if($tid>0&&$h>0)$clean[$tid]=round($h,2);}
  $pdo->beginTransaction();
  try{
    if($id>0){
      $st=$pdo->prepare('SELECT id FROM projects WHERE id=?');$st->execute([$id]);
      if(!$st->fetch())throw new RuntimeException('El proyecto ya no existe.');
      $pdo->prepare('UPDATE projects SET name=?,color=?,is_public=?,slug=? WHERE id=?')
        ->execute([$name,$color,$isPublic,unique_slug($pdo,$slugIn!==''?$slugIn:$name,$id),$id]);
    }else{
      $pdo->prepare('INSERT INTO projects(name,color,is_public,slug) VALUES(?,?,?,?)')
        ->execute([$name,$color,$isPublic,unique_slug($pdo,$slugIn!==''?$slugIn:$name)]);
      $id=(int)$pdo->lastInsertId();
    }
    $pdo->prepare('DELETE FROM plans WHERE project_id=?')->execute([$id]);
    $pi=$pdo->prepare('INSERT INTO plans(project_id,tag_id,hours) VALUES(?,?,?)');
    foreach($clean as $tid=>$h)$pi->execute([$id,$tid,$h]);
    $pdo->commit();
  }catch(Throwable $e){$pdo->rollBack();json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
  json_out(['ok'=>true,'state'=>build_state()]);
}

case 'archive_project':
  check_csrf();
  $pdo->prepare('UPDATE projects SET archived=? WHERE id=?')->execute([!empty($data['archived'])?1:0,(int)($data['id']??0)]);
  json_out(['ok'=>true,'state'=>build_state()]);

case 'delete_project':
  check_csrf();
  $pdo->prepare('DELETE FROM projects WHERE id=?')->execute([(int)($data['id']??0)]);
  json_out(['ok'=>true,'state'=>build_state()]);

case 'save_tag': {
  check_csrf();
  $id=(int)($data['id']??0);$name=trim((string)($data['name']??''));
  if($name==='')json_out(['ok'=>false,'error'=>'Nombre vacío.'],400);
  if($id>0)$pdo->prepare('UPDATE tags SET name=? WHERE id=?')->execute([$name,$id]);
  else{
    $max=(int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM tags')->fetchColumn();
    $pdo->prepare('INSERT INTO tags(name,sort_order) VALUES(?,?)')->execute([$name,$max+1]);
  }
  json_out(['ok'=>true,'state'=>build_state()]);
}

case 'delete_tag':
  check_csrf();
  $id=(int)($data['id']??0);
  $st=$pdo->prepare('SELECT COUNT(*) FROM entries WHERE tag_id=?');$st->execute([$id]);
  if((int)$st->fetchColumn()>0)json_out(['ok'=>false,'error'=>'Esta tipología tiene entradas asociadas. Elimina o edita esas entradas primero.'],400);
  $pdo->prepare('DELETE FROM tags WHERE id=?')->execute([$id]);
  json_out(['ok'=>true,'state'=>build_state()]);

case 'save_entry': {
  check_csrf();
  $id=(int)($data['id']??0);
  $pid=(int)($data['projectId']??0);$tid=(int)($data['tagId']??0);
  $date=(string)($data['date']??'');$min=(int)($data['minutes']??0);
  $desc=mb_substr(trim((string)($data['desc']??'')),0,500);
  if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))json_out(['ok'=>false,'error'=>'Fecha inválida.'],400);
  if($min<=0||$min>24*60)json_out(['ok'=>false,'error'=>'Duración inválida.'],400);
  foreach([['projects',$pid],['tags',$tid]] as $t){
    $st=$pdo->prepare("SELECT id FROM {$t[0]} WHERE id=?");$st->execute([$t[1]]);
    if(!$st->fetch())json_out(['ok'=>false,'error'=>'Proyecto o tipología inexistente.'],400);
  }
  if($id>0)$pdo->prepare('UPDATE entries SET project_id=?,tag_id=?,`date`=?,minutes=?,description=? WHERE id=?')
    ->execute([$pid,$tid,$date,$min,$desc,$id]);
  else $pdo->prepare('INSERT INTO entries(project_id,tag_id,`date`,minutes,description) VALUES(?,?,?,?,?)')
    ->execute([$pid,$tid,$date,$min,$desc]);
  json_out(['ok'=>true,'state'=>build_state()]);
}

case 'delete_entry':
  check_csrf();
  $pdo->prepare('DELETE FROM entries WHERE id=?')->execute([(int)($data['id']??0)]);
  json_out(['ok'=>true,'state'=>build_state()]);

case 'save_launcher': {
  check_csrf();
  $id=(int)($data['id']??0);$pid=(int)($data['projectId']??0);$tid=(int)($data['tagId']??0);
  $url=mb_substr(trim((string)($data['url']??'')),0,500);
  $name=trim((string)($data['name']??''));
  $pn=$pdo->prepare('SELECT name FROM projects WHERE id=?');$pn->execute([$pid]);
  $pname=$pn->fetchColumn();
  $tn=$pdo->prepare('SELECT name FROM tags WHERE id=?');$tn->execute([$tid]);
  $tname=$tn->fetchColumn();
  if($pname===false||$tname===false)json_out(['ok'=>false,'error'=>'Proyecto o tipología inexistente.'],400);
  if($name==='')$name=$pname.' · '.$tname;
  if($id>0)$pdo->prepare('UPDATE launchers SET name=?,project_id=?,tag_id=?,url=? WHERE id=?')->execute([$name,$pid,$tid,$url,$id]);
  else{
    $max=(int)$pdo->query('SELECT COALESCE(MAX(sort),0) FROM launchers')->fetchColumn();
    $pdo->prepare('INSERT INTO launchers(name,project_id,tag_id,url,sort) VALUES(?,?,?,?,?)')->execute([$name,$pid,$tid,$url,$max+1]);
  }
  json_out(['ok'=>true,'state'=>build_state()]);
}

case 'delete_launcher':
  check_csrf();
  $pdo->prepare('DELETE FROM launchers WHERE id=?')->execute([(int)($data['id']??0)]);
  json_out(['ok'=>true,'state'=>build_state()]);

case 'save_settings':
  check_csrf();
  if(array_key_exists('autoPause',$data))$pdo->prepare('REPLACE INTO settings(skey,svalue) VALUES("autoPause",?)')->execute([json_encode(!empty($data['autoPause']))]);
  if(array_key_exists('autoPauseMin',$data)){ $v=min(120,max(1,(int)$data['autoPauseMin'])); $pdo->prepare('REPLACE INTO settings(skey,svalue) VALUES("autoPauseMin",?)')->execute([json_encode($v)]); }
  if(array_key_exists('theme',$data)&&in_array($data['theme'],['dark','light'],true))$pdo->prepare('REPLACE INTO settings(skey,svalue) VALUES("theme",?)')->execute([json_encode($data['theme'])]);
  json_out(['ok'=>true,'state'=>build_state()]);

case 'import_json': {
  check_csrf();
  $payload=is_string($data['raw']??null)?json_decode($data['raw'],true):null;
  if(!$payload)$payload=isset($data['projects'])?$data:null;
  if(!$payload||empty($payload['tags'])||!isset($payload['projects'],$payload['entries']))json_out(['ok'=>false,'error'=>'El archivo no es un respaldo válido de TimeTrack.'],400);
  $pdo->beginTransaction();
  try{
    foreach(['entries','launchers','plans','projects','tags'] as $t)$pdo->exec("DELETE FROM $t");
    $tagMap=[];
    $ti=$pdo->prepare('INSERT INTO tags(name,sort_order) VALUES(?,?)');
    $i=0;
    foreach($payload['tags'] as $t){$ti->execute([(string)$t['name'],$i++]);$tagMap[(string)$t['id']]=(int)$pdo->lastInsertId();}
    $projMap=[];
    $pi=$pdo->prepare('INSERT INTO projects(name,color,is_public,slug) VALUES(?,?,0,?)');
    foreach($payload['projects'] as $p){
      $pi->execute([(string)$p['name'],(string)($p['color']??'#5b8cff'),unique_slug($pdo,(string)$p['name'])]);
      $pid=(int)$pdo->lastInsertId();
      $projMap[(string)$p['id']]=$pid;
      foreach(($p['plan']??[]) as $tid=>$h){
        $h=(float)$h;$nt=$tagMap[(string)$tid]??null;
        if($nt&&$h>0)$pdo->prepare('INSERT INTO plans(project_id,tag_id,hours) VALUES(?,?,?)')->execute([$pid,$nt,$h]);
      }
    }
    $ei=$pdo->prepare('INSERT INTO entries(project_id,tag_id,`date`,minutes,description,created_at) VALUES(?,?,?,?,?,?)');
    foreach($payload['entries'] as $e){
      $np=$projMap[(string)$e['projectId']]??null;$nt=$tagMap[(string)$e['tagId']]??null;
      if(!$np||!$nt||!preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$e['date']))continue;
      $ca=date('Y-m-d H:i:s',(int)(($e['createdAt']??0)/1000));
      $ei->execute([$np,$nt,$e['date'],(int)$e['minutes'],(string)($e['desc']??''),$ca]);
    }
    $li=$pdo->prepare('INSERT INTO launchers(name,project_id,tag_id,url,sort) VALUES(?,?,?,?,?)');
    $i=0;
    foreach(($payload['launchers']??[]) as $l){
      $np=$projMap[(string)$l['projectId']]??null;$nt=$tagMap[(string)$l['tagId']]??null;
      if(!$np||!$nt)continue;
      $li->execute([(string)($l['name']??''),$np,$nt,(string)($l['url']??''),$i++]);
    }
    $pdo->commit();
  }catch(Throwable $e){$pdo->rollBack();json_out(['ok'=>false,'error'=>'Importación fallida: '.$e->getMessage()],400);}
  json_out(['ok'=>true,'state'=>build_state()]);
}

case 'wipe_all':
  check_csrf();
  $pdo->beginTransaction();
  foreach(['entries','launchers','plans','projects','tags'] as $t)$pdo->exec("DELETE FROM $t");
  $pdo->commit();
  json_out(['ok'=>true,'state'=>build_state()]);

case 'logout':
  check_csrf();
  $_SESSION=[];
  session_destroy();
  json_out(['ok'=>true]);

default:
  json_out(['ok'=>false,'error'=>'Acción desconocida'],400);
}