<?php
declare(strict_types=1);
define('FINPROV_SETUP_NO_MAIN',true);require dirname(__DIR__).'/scripts/validate_setup_request.php';
function expect(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
function blocked(callable $fn):void{try{$fn();}catch(Throwable $e){return;}throw new RuntimeException('Unsafe input was accepted');}
$base=realpath(sys_get_temp_dir()).'/finprov-setup-test-'.bin2hex(random_bytes(6));mkdir($base,0700);mkdir("$base/repo");mkdir("$base/public_html");
$req="$base/repo/setup-request.json";$policy="$base/policy.json";$dest="$base/public_html/new-page";
$d=['version'=>1,'repository'=>'https://github.com/finprovteam-cell/new-site','campaign'=>'New site','production_url'=>'https://accountingcourse.in/new-page/','design_approved'=>true,'thank_you_path'=>'/new-page/thank-you/','tracking'=>['mode'=>'unconfigured','gtm_id'=>'','ga4_id'=>'','clarity_id'=>'']];
$rules=['version'=>1,'targets'=>[['production_url'=>$d['production_url'],'document_root'=>"$base/public_html",'allow_domain_root'=>false]]];
$save=function()use(&$d,&$rules,$req,$policy){file_put_contents($req,json_encode($d));file_put_contents($policy,json_encode($rules));chmod($policy,0600);clearstatcache();};
try{
$save();$p=fpPlan($req,$policy);expect($p['destination']===$dest&&!$p['authorized']&&!file_exists($dest),'Planner wrote/authorized');expect(fpPlan($req)['status']==='needs-independently-verified-server-policy','Missing policy accepted');
foreach(['shell_command','lsq_secret_key','document_root','destination'] as $key){$d[$key]='untrusted';$save();blocked(fn()=>fpPlan($req));unset($d[$key]);}
foreach(['http://accountingcourse.in/new/','https://accountingcourse.in/../x/','https://accountingcourse.in/%2e%2e/','https://user:pass@accountingcourse.in/','https://127.0.0.1/','https://accountingcourse.in//x/','https://accountingcourse.in/new/?q=1'] as $value){$d['production_url']=$value;$save();blocked(fn()=>fpPlan($req));}
$d['production_url']='https://accountingcourse.in/new-page/';$d['design_approved']=false;$save();blocked(fn()=>fpPlan($req));$d['design_approved']=true;$save();
file_put_contents($req,str_replace('"version":1','"version":1,"version":1',file_get_contents($req)));blocked(fn()=>fpPlan($req));$save();
$d['repository']='https://github.com/other/repo';$save();blocked(fn()=>fpPlan($req));$d['repository']='https://github.com/finprovteam-cell/new-site';$save();
mkdir($dest);file_put_contents("$dest/index.html",'existing');blocked(fn()=>fpPlan($req,$policy));unlink("$dest/index.html");rmdir($dest);symlink("$base/repo",$dest);blocked(fn()=>fpPlan($req,$policy));unlink($dest);
foreach(["$base/repo/policy.json","$base/public_html/policy.json"] as $exposed){copy($policy,$exposed);chmod($exposed,0600);blocked(fn()=>fpPlan($req,$exposed));unlink($exposed);}
chmod($policy,0644);clearstatcache();blocked(fn()=>fpPlan($req,$policy));$save();
$rules['targets'][0]['production_url']='https://accountingcourse.in/different/';$save();blocked(fn()=>fpPlan($req,$policy));
$d['production_url']='https://accountingcourse.in/';$d['thank_you_path']='/thank-you/';$rules['targets'][0]['production_url']=$d['production_url'];$save();blocked(fn()=>fpPlan($req,$policy));$rules['targets'][0]['allow_domain_root']=true;$save();expect(fpPlan($req,$policy)['destination']==="$base/public_html",'Explicit empty root failed');
echo "PASS: read-only planning; missing approval/policy; command/secret fields; unsafe URLs; duplicate keys; wrong repo; nonempty target; symlinks; policy protection/mapping; domain-root gate.\n";
}finally{foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f){if($f->isLink()||$f->isFile())unlink($f->getPathname());else rmdir($f->getPathname());}rmdir($base);}
