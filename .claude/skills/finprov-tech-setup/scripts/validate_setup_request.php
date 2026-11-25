<?php
declare(strict_types=1);
/** Read-only planner: no subprocess, network, file writes or deployment. PHP CLI8+. */
function fpCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function fpKeys(array $d, array $allowed, array $required=[]): void {
    fpCheck(!array_diff(array_keys($d),$allowed) && !array_diff($required,array_keys($d)), 'Unknown or missing fields');
}
function fpUrl(mixed $value): array {
    fpCheck(is_string($value) && strlen($value)<=2048,'Expected final HTTPS URL');
    $u=parse_url($value);fpCheck(is_array($u),'Invalid URL');$host=$u['host']??'';
    fpCheck(($u['scheme']??'')==='https' && $host!=='' && !isset($u['user'],$u['pass']) && !isset($u['port']) && !isset($u['user']) && !isset($u['pass']),'Use HTTPS without credentials or ports');
    fpCheck((bool)preg_match('/^[a-z0-9]+(?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9]+(?:[a-z0-9-]*[a-z0-9])?)+$/D',$host),'Expected DNS hostname');
    fpCheck(!filter_var($host,FILTER_VALIDATE_IP) && !preg_match('/\.(localhost|local|internal|invalid|test)$/D',$host),'IP/private/test hosts blocked');
    fpCheck(!isset($u['query'])&&!isset($u['fragment']),'No query/fragment');
    fpCheck((bool)preg_match('~^/(?:[A-Za-z0-9_-]+/)*$~D',$u['path']??''),'Use a trailing-slash path without encoding/traversal/shell characters');
    return $u;
}
// json_decode validates syntax first; this token walk rejects duplicate keys, including escaped keys.
function fpUnique(array $tokens, int &$i): void {
    $token=$tokens[$i++]??null;
    if($token==='{'){$seen=[];if(($tokens[$i]??null)==='}'){$i++;return;}while(true){$key=json_decode($tokens[$i++],true,64,JSON_THROW_ON_ERROR);fpCheck(!array_key_exists($key,$seen),'Duplicate JSON field');$seen[$key]=true;$i++;fpUnique($tokens,$i);$sep=$tokens[$i++];if($sep==='}')break;}}
    elseif($token==='['){if(($tokens[$i]??null)===']'){$i++;return;}while(true){fpUnique($tokens,$i);$sep=$tokens[$i++];if($sep===']')break;}}
}
function fpRead(string $p): array {
    fpCheck(is_file($p)&&!is_link($p),'Expected regular non-symlink file');$raw=file_get_contents($p);fpCheck(is_string($raw)&&strlen($raw)<=65536,'File too large/unreadable');
    $d=json_decode($raw,true,64,JSON_THROW_ON_ERROR);fpCheck(is_array($d)&&str_starts_with(ltrim($raw),'{'),'Expected JSON object');
    preg_match_all('~"(?:[^"\\\\]|\\\\.)*"|[{}\[\]:,]|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?|true|false|null~s',$raw,$m);$i=0;fpUnique($m[0],$i);
    return [$d,hash('sha256',$raw)];
}
function fpNoLinks(string $p): void { for($v=$p;;$v=dirname($v)){fpCheck(!is_link($v),'Symlink path blocked');if(dirname($v)===$v)break;} }
function fpInside(string $p,string $root): bool {return $p===$root||str_starts_with($p,rtrim($root,'/').'/');}
function fpPlan(string $request, ?string $policy=null): array {
    [$d,$digest]=fpRead($request);$required=['version','repository','campaign','production_url','design_approved','tracking'];fpKeys($d,array_merge($required,['thank_you_path']),$required);
    fpCheck(($d['version']??null)===1,'Unsupported version');$repo=$d['repository'];
    fpCheck(is_string($repo)&&(bool)preg_match('~^https://github\.com/finprovteam-cell/[A-Za-z0-9][A-Za-z0-9._-]{0,99}$~D',$repo),'Expected finprovteam-cell HTTPS campaign repo');
    fpCheck(!str_contains($repo,'..')&&!str_contains($repo,'REPLACE')&&!str_ends_with($repo,'/finprov-landing-page-template'),'Use actual campaign repo');
    fpCheck(is_string($d['campaign'])&&strlen($d['campaign'])>0&&strlen($d['campaign'])<=150&&!preg_match('/[\x00-\x1f\x7f]/',$d['campaign'])&&!str_contains($d['campaign'],'REPLACE'),'Missing campaign');
    $u=fpUrl($d['production_url']);fpCheck($d['design_approved']===true,'Explicit design approval required');$ty=$d['thank_you_path']??'';
    fpCheck(is_string($ty)&&($ty===''||((bool)preg_match('~^/(?:[A-Za-z0-9_-]+/)+$~D',$ty)&&str_starts_with($ty,$u['path']))),'Thank-you outside campaign');
    $t=$d['tracking'];fpCheck(is_array($t),'Invalid tracking');$fields=['mode','gtm_id','ga4_id','clarity_id'];fpKeys($t,$fields,$fields);fpCheck(in_array($t['mode'],['gtm','direct','unconfigured'],true),'Invalid tracking mode');
    foreach(['gtm_id'=>'GTM-[A-Z0-9]+','ga4_id'=>'G-[A-Z0-9]+','clarity_id'=>'[a-z0-9]+'] as $k=>$pattern){fpCheck(is_string($t[$k])&&strlen($t[$k])<=64&&($t[$k]===''||(bool)preg_match('~^'.$pattern.'$~D',$t[$k])),'Invalid public tracking ID');}
    fpCheck($t['mode']!=='unconfigured'||($t['gtm_id']===''&&$t['ga4_id']===''&&$t['clarity_id']===''),'Unconfigured mode needs blank IDs');
    $out=['mode'=>'read-only-plan','authorized'=>false,'requires_tech_approval'=>true,'repository'=>$repo,'production_url'=>$d['production_url'],'request_sha256'=>$digest];
    if($policy===null)return $out+['status'=>'needs-independently-verified-server-policy'];
    fpCheck(str_starts_with($policy,'/'),'Policy path must be absolute');fpNoLinks($policy);[$rules,$pdigest]=fpRead($policy);fpCheck(function_exists('posix_geteuid'),'Need POSIX CLI ownership check; do not bypass it');
    fpCheck(fileowner($policy)===posix_geteuid()&&(fileperms($policy)&0077)===0,'Policy must be owned by current user and private (0600 or stricter)');
    $repoRoot=realpath(dirname($request));$policyReal=realpath($policy);fpCheck(!fpInside($policyReal,$repoRoot),'Policy must be outside campaign checkout');
    fpKeys($rules,['version','targets'],['version','targets']);fpCheck($rules['version']===1&&is_array($rules['targets']),'Invalid policy');$matches=[];
    foreach($rules['targets'] as $target){fpCheck(is_array($target),'Invalid target');fpKeys($target,['production_url','document_root','allow_domain_root'],['production_url','document_root','allow_domain_root']);fpUrl($target['production_url']);fpCheck(is_bool($target['allow_domain_root'])&&is_string($target['document_root'])&&str_starts_with($target['document_root'],'/'),'Invalid target');$root=$target['document_root'];fpNoLinks($root);fpCheck(is_dir($root),'Verify existing document root in cPanel');fpCheck(!fpInside($policyReal,realpath($root)),'Policy must be outside public roots');if($target['production_url']===$d['production_url'])$matches[]=$target;}
    fpCheck(count($matches)===1,'URL needs exactly one Tech-approved mapping');$target=$matches[0];fpCheck($u['path']!=='/'||$target['allow_domain_root'],'Domain-root publication needs explicit Tech policy approval');
    $root=realpath($target['document_root']);$dest=rtrim($root,'/').($u['path']==='/'?'':rtrim($u['path'],'/'));fpNoLinks($dest);fpCheck(fpInside($dest,$root),'Destination outside root');
    fpCheck(!file_exists($dest)||(is_dir($dest)&&count(array_diff(scandir($dest),['.','..']))===0),'Existing/nonempty destination: use existing-site workflow');
    return $out+['status'=>'ready-for-tech-plan-review','destination'=>$dest,'existing_empty_directory'=>file_exists($dest),'policy_sha256'=>$pdigest,'note'=>'No commands executed. This does not grant access or authorize launch.'];
}
if(!defined('FINPROV_SETUP_NO_MAIN')){
    try{$args=$argv;array_shift($args);fpCheck(count($args)===1||(count($args)===3&&$args[1]==='--policy'),'Usage: php validate_setup_request.php request.json [--policy /private/policy.json]');echo json_encode(fpPlan($args[0],$args[2]??null),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;}
    catch(Throwable $e){fwrite(STDERR,'Setup blocked: '.$e->getMessage().PHP_EOL);exit(1);}
}
