<?php
declare(strict_types=1);
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])]);
if(session_status()===PHP_SESSION_NONE) session_start();

function base_dir(){ return rtrim(dirname($_SERVER['SCRIPT_NAME']),'/\\'); }

function load_config(): array {
  $f=__DIR__.'/config.php';
  if(!file_exists($f)){ http_response_code(500); exit('TimeTrack no está instalado. Abre <a href="install.php">install.php</a>'); }
  return require $f;
}
function db(): PDO {
  static $pdo=null;
  if($pdo===null){
    $c=load_config()['db'];
    $pdo=new PDO("mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}",$c['user'],$c['pass'],[
      PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
  }
  return $pdo;
}
function current_user(): ?array {
  if(empty($_SESSION['tt_uid'])) return null;
  $st=db()->prepare('SELECT id,username FROM users WHERE id=?');
  $st->execute([$_SESSION['tt_uid']]);
  $r=$st->fetch();
  return $r?:null;
}
function json_out(array $d,int $code=200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($d,JSON_UNESCAPED_UNICODE);
  exit;
}
function require_user(): array {
  $u=current_user();
  if(!$u) json_out(['ok'=>false,'error'=>'Sesión caducada. Recarga la página.'],401);
  return $u;
}
function check_csrf(): void {
  $t=$_SERVER['HTTP_X_CSRF']??'';
  if(!$t||!hash_equals($_SESSION['tt_csrf']??'',$t)) json_out(['ok'=>false,'error'=>'Token inválido. Recarga la página.'],403);
}
function slugify(string $s): string {
  $t=@iconv('UTF-8','ASCII//TRANSLIT',$s);
  if($t===false||$t==='')$t=$s;
  $t=strtolower($t);
  $t=preg_replace('/[^a-z0-9]+/','-',$t);
  $t=trim($t,'-');
  if(strlen($t)>60)$t=substr($t,0,60);
  $t=rtrim($t,'-');
  return $t===''?'proyecto':$t;
}
function unique_slug(PDO $pdo,string $base,int $ignoreId=0): string {
  $base=slugify($base);$slug=$base;$i=2;
  while(true){
    $st=$pdo->prepare('SELECT id FROM projects WHERE slug=?'.($ignoreId?' AND id<>?':''));
    $st->execute($ignoreId?[$slug,$ignoreId]:[$slug]);
    if(!$st->fetch())return $slug;
    $slug=$base.'-'.($i++);
  }
}
function build_state(): array {
  $pdo=db();
  $projects=[];
  foreach($pdo->query('SELECT * FROM projects ORDER BY created_at,id') as $r){
    $projects[]=['id'=>(int)$r['id'],'name'=>$r['name'],'color'=>$r['color'],'slug'=>$r['slug'],
      'isPublic'=>(int)$r['is_public'],'archived'=>(int)$r['archived'],'plan'=>[]];
  }
  foreach($pdo->query('SELECT project_id,tag_id,hours FROM plans') as $r){
    $pid=(int)$r['project_id'];
    foreach($projects as &$p) if($p['id']===$pid){$p['plan'][(int)$r['tag_id']]=(float)$r['hours'];break;}
    unset($p);
  }
  $tags=[];
  foreach($pdo->query('SELECT id,name FROM tags ORDER BY sort_order,id') as $r)$tags[]=['id'=>(int)$r['id'],'name'=>$r['name']];
  $entries=[];
  foreach($pdo->query('SELECT id,project_id,tag_id,`date`,minutes,description,created_at FROM entries ORDER BY `date` DESC,id DESC') as $r){
    $entries[]=['id'=>(int)$r['id'],'projectId'=>(int)$r['project_id'],'tagId'=>(int)$r['tag_id'],
      'date'=>$r['date'],'minutes'=>(int)$r['minutes'],'desc'=>(string)$r['description'],
      'createdAt'=>strtotime($r['created_at'])*1000];
  }
  $launchers=[];
  foreach($pdo->query('SELECT id,name,project_id,tag_id,url FROM launchers ORDER BY sort,id') as $r){
    $launchers[]=['id'=>(int)$r['id'],'name'=>$r['name'],'projectId'=>(int)$r['project_id'],'tagId'=>(int)$r['tag_id'],'url'=>$r['url']];
  }
  $settings=['autoPause'=>true,'autoPauseMin'=>10,'theme'=>'dark'];
  foreach($pdo->query('SELECT skey,svalue FROM settings') as $r){
    $v=json_decode($r['svalue'],true);
    if($v!==null)$settings[$r['skey']]=$v;
  }
  return ['projects'=>$projects,'tags'=>$tags,'entries'=>$entries,'launchers'=>$launchers,
    'settings'=>$settings,'me'=>$_SESSION['tt_user']??''];
}