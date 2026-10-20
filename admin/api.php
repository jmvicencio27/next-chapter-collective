<?php
declare(strict_types=1);
require __DIR__ . '/core.php';
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
try {
 ensure_dirs(); session_boot();
 $action=$_GET['action']??'bootstrap';if(!is_string($action))fail('Invalid request.');
 $method=$_SERVER['REQUEST_METHOD']??'GET';
 if($method==='POST')protect_post();
 elseif($method!=='GET')fail('Use GET or POST for this request.',405);
 $input=[];
 if($method==='POST'&&$action!=='upload'){$raw=file_get_contents('php://input');if(strlen($raw)>2500000)fail('This request is too large.',413);$input=json_decode($raw,true);if(!is_array($input))fail('Send a valid JSON request.');}
 $account=user_record();
 if($action==='bootstrap'){
  $signed=!empty($_SESSION['user'])&&!empty($account['username'])&&hash_equals($account['username'],$_SESSION['user'])&&hash_equals($account['version']??'',$_SESSION['version']??'');
  $result=['csrf'=>$_SESSION['csrf'],'authenticated'=>$signed,'setupRequired'=>!$account,'username'=>$signed?$account['username']:null,'requirements'=>['openssl'=>function_exists('openssl_encrypt'),'dom'=>class_exists('DOMDocument'),'fileinfo'=>class_exists('finfo'),'images'=>function_exists('getimagesize'),'writable'=>is_writable(CMS_ROOT)]];
 }elseif($action==='setup'&&$method==='POST'){
  rate_limit('setup');
  $result=locked(function()use($input){if(user_record())fail('The owner account has already been created.',403);$expected=require __DIR__.'/install-key.php';$key=text_value($input,'setupKey',150);if(!hash_equals($expected,hash('sha256',$key)))fail('The setup key is incorrect.',403);$user=text_value($input,'username',80);$pass=password_value($input,'password');if(!preg_match('/^[a-zA-Z0-9@._-]{3,80}$/D',$user))fail('Use 3–80 letters, numbers, dots, hyphens or @ for the username.');if(strlen($pass)<12)fail('Choose a password with at least 12 characters.');$record=['username'=>$user,'password'=>password_hash($pass,PASSWORD_DEFAULT),'version'=>bin2hex(random_bytes(16)),'created'=>gmdate('c')];save_data(CMS_DATA.'/account.php',$record);session_regenerate_id(true);$_SESSION['user']=$user;$_SESSION['version']=$record['version'];$_SESSION['last']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));return ['csrf'=>$_SESSION['csrf'],'username'=>$user];});
 }elseif($action==='login'&&$method==='POST'){
  rate_limit('login');$user=text_value($input,'username',80);$pass=password_value($input,'password');$fallback='$2y$12$7RhB0okLQcAt3BWK3hYf3eRZS0R1YKSlj39hP8d03YIvN2tZHlZmi';$valid=password_verify($pass,$account['password']??$fallback);if(!$account||!$valid||!hash_equals($account['username'],$user))fail('The username or password is incorrect.',401);session_regenerate_id(true);$_SESSION['user']=$user;$_SESSION['version']=$account['version'];$_SESSION['last']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));$result=['csrf'=>$_SESSION['csrf'],'username'=>$user];
 }else{
  require_auth();if(!hash_equals($account['version']??'',$_SESSION['version']??''))fail('Please sign in again.',401);
  if($action==='state'&&$method==='GET')$result=['pages'=>pages_list(),'media'=>media_list(),'settings'=>settings_get()];
  elseif($action==='page'&&$method==='GET')$result=['page'=>page_get((string)($_GET['slug']??''))];
  elseif($action==='revisions'&&$method==='GET')$result=['revisions'=>revision_list((string)($_GET['slug']??''))];
  elseif($action==='media'&&$method==='GET')$result=['media'=>media_list()];
  elseif($action==='draft'&&$method==='POST')$result=draft_save($input);
  elseif($action==='publish'&&$method==='POST')$result=publish_page($input);
  elseif($action==='create'&&$method==='POST')$result=new_page($input);
  elseif($action==='upload'&&$method==='POST')$result=media_upload();
  elseif($action==='menu'&&$method==='POST')$result=menu_save($input);
  elseif($action==='settings'&&$method==='POST')$result=settings_save($input);
  elseif($action==='discard'&&$method==='POST')$result=locked(function()use($input){$name=slug(text_value($input,'slug',64));if(!is_file(page_file($name)))fail('Move a new page to Trash instead of discarding its only draft.');@unlink(CMS_DATA.'/drafts/'.$name.'.php');return ['page'=>page_get($name),'pages'=>pages_list()];});
  elseif($action==='restore_revision'&&$method==='POST'){$name=slug(text_value($input,'slug',64));$id=text_value($input,'id',80);if(!preg_match('/^[a-zA-Z0-9-]+$/D',$id))fail('Invalid revision.');$revision=load_data(CMS_DATA.'/revisions/'.$name.'/'.$id.'.php');if(!$revision)fail('This revision was not found.',404);$input['html']=$revision['html'];$result=publish_page($input);}
  elseif($action==='trash'&&$method==='POST')$result=locked(function()use($input){$name=slug(text_value($input,'slug',64));if($name==='index')fail('The homepage cannot be moved to Trash.');$page=page_get($name);$public=is_file(page_file($name))?file_get_contents(page_file($name)):'';$id=$name.'-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(3));save_data(CMS_DATA.'/trash/'.$id.'.php',['id'=>$id,'page'=>$page,'published_html'=>$public,'updated'=>gmdate('c')]);if(is_file(page_file($name))&&!unlink(page_file($name)))fail('This page could not be moved to Trash.',500);@unlink(CMS_DATA.'/drafts/'.$name.'.php');return ['pages'=>pages_list()];});
  elseif($action==='trash_list'&&$method==='GET'){$items=[];foreach(glob(CMS_DATA.'/trash/*.php')?:[] as $file){$item=load_data($file);$items[]=['id'=>$item['id'],'title'=>$item['page']['title'],'slug'=>$item['page']['slug'],'updated'=>$item['updated']];}$result=['items'=>$items];}
  elseif($action==='restore_trash'&&$method==='POST')$result=locked(function()use($input){$id=text_value($input,'id',150);if(!preg_match('/^[a-zA-Z0-9-]+$/D',$id))fail('Invalid page.');$file=CMS_DATA.'/trash/'.$id.'.php';$item=load_data($file);if(!$item)fail('This page was not found in Trash.',404);$name=slug($item['page']['slug']);if(is_file(page_file($name))||is_file(CMS_DATA.'/drafts/'.$name.'.php'))fail('Another page already uses that address.',409);$p=$item['page'];save_data(CMS_DATA.'/drafts/'.$name.'.php',['slug'=>$name,'title'=>$p['title'],'html'=>$p['html'],'base_revision'=>'','updated'=>gmdate('c')]);unlink($file);return ['pages'=>pages_list(),'page'=>page_get($name)];});
  elseif($action==='password'&&$method==='POST'){$old=password_value($input,'currentPassword');$new=password_value($input,'newPassword');rate_limit('password');if(!password_verify($old,$account['password']))fail('The current password is incorrect.',403);if(strlen($new)<12)fail('Choose a password with at least 12 characters.');$result=locked(function()use($account,$new){$account['password']=password_hash($new,PASSWORD_DEFAULT);$account['version']=bin2hex(random_bytes(16));save_data(CMS_DATA.'/account.php',$account);$_SESSION['version']=$account['version'];session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));return ['csrf'=>$_SESSION['csrf']];});}
  elseif($action==='logout'&&$method==='POST'){$_SESSION=[];session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));$result=['csrf'=>$_SESSION['csrf']];}
  else fail('This admin action was not found.',404);
 }
 echo json_encode(['success'=>true]+$result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}catch(CmsError $e){http_response_code($e->status);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}catch(Throwable $e){error_log('NCC admin: '.$e->getMessage());http_response_code(500);echo json_encode(['success'=>false,'message'=>'The request could not be completed. Check the server error log or try again.']);}
