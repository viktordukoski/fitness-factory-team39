<?php
$root=dirname(__DIR__);
$marketing=require $root.'/app/lang/marketing.php';$supplement=require $root.'/app/lang/supplement.php';$languages=['en','mk','sq','de','fr','it'];$dict=[];
foreach($languages as $lang)$dict[$lang]=array_merge(require $root.'/app/lang/'.$lang.'.php',$marketing[$lang],$supplement[$lang]);
$keys=[];
$files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app'));
foreach($files as $file){
 if($file->getExtension()!=='php'||str_contains($file->getPathname(),DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR))continue;
 $tokens=token_get_all(file_get_contents($file->getPathname()));
 for($i=0;$i<count($tokens);$i++){
  if(!is_array($tokens[$i])||$tokens[$i][0]!==T_STRING||!in_array($tokens[$i][1],['t','ff_heading','ff_empty','form_field']))continue;
  $j=$i+1;while(isset($tokens[$j])&&is_array($tokens[$j])&&$tokens[$j][0]===T_WHITESPACE)$j++;
  if(($tokens[$j]??null)!=='(')continue;$j++;
  while(isset($tokens[$j])&&is_array($tokens[$j])&&$tokens[$j][0]===T_WHITESPACE)$j++;
  if(!isset($tokens[$j])||!is_array($tokens[$j])||$tokens[$j][0]!==T_CONSTANT_ENCAPSED_STRING)continue;
  $s=$tokens[$j][1];$key=$s[0]==="'"?str_replace(["\\'","\\\\"],["'","\\"],substr($s,1,-1)):stripcslashes(substr($s,1,-1));
  if($key==='FITNESS FACTORY / OHRID')continue;$keys[$key]=true;
 }
}
$missing=[];foreach($keys as $key=>$unused)foreach($languages as $lang)if(!array_key_exists($key,$dict[$lang]))$missing[$key][]=$lang;
echo json_encode(['keys_checked'=>count($keys),'dictionary_size'=>array_map('count',$dict),'missing'=>$missing],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
exit($missing?1:0);
