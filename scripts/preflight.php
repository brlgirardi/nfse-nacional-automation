<?php
declare(strict_types=1);
function stop(string $m):never{fwrite(STDERR,"ERRO: ".$m.PHP_EOL);exit(1);}
function digits(string $v):string{return preg_replace('/\D+/','',$v)??'';}
$p=__DIR__.'/../config/modelo-fiscal.json';
if(!is_file($p))stop('Configure prestador com python3 scripts/setup.py.');
try{$cfg=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);}
catch(Throwable $e){stop('JSON fiscal inválido.');}
$a=$cfg['prestador']??[];$s=$cfg['servico']??[];$r=$a['regimeTributario']??[];$d=$cfg['dps']??[];
if(!preg_match('/^\d{14}$/',digits((string)($a['cnpj']??'')))||preg_match('/^(\d)\1+$/',digits((string)($a['cnpj']??''))))stop('CNPJ prestador ausente ou inválido.');
if(trim((string)($a['nome']??''))==='')stop('Razão social ausente.');
foreach(['municipioEmissaoIbge','municipioPrestacaoIbge']as $k)if(!preg_match('/^\d{7}$/',digits((string)($a[$k]??''))))stop("Código IBGE $k inválido.");
if(!in_array($r['opSimpNac']??null,[1,2,3],true))stop('Opção Simples não confirmada.');
if(($r['opSimpNac']??null)===3&&!in_array($r['regApTribSN']??null,[1,2,3],true))stop('Apuração Simples não confirmada.');
if(!is_int($r['regEspTrib']??null))stop('Regime especial não confirmado.');
if(!preg_match('/^\d{6}$/',digits((string)($s['codigoTributacaoNacional']??''))))stop('Código nacional deve ter 6 dígitos.');
$nbs=digits((string)($s['codigoNbs']??''));if($nbs!==''&&!preg_match('/^\d{9}$/',$nbs))stop('NBS deve conter 9 dígitos.');
if(!is_int($s['tribISSQN']??null)||!is_bool($s['issRetido']??null))stop('Tributação/retenção ISS não confirmada.');
if(($r['opSimpNac']??null)===3&&(!is_numeric($s['percentualTotalTributosSN']??null)||floatval($s['percentualTotalTributosSN'])<0||floatval($s['percentualTotalTributosSN'])>100))stop('Percentual dos tributos Simples não confirmado.');
if(!preg_match('/^\d{1,5}$/',(string)($d['serieAutomacao']??'')))stop('Série DPS não configurada.');
if(isset($argv[1])){
 $f=$argv[1];if(!is_file($f))stop('Solicitação não encontrada.');
 try{$q=json_decode((string)file_get_contents($f),true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){stop('Solicitação JSON inválida.');}
 $op=$q['operacao']??'';$env=$q['ambiente']??'';
 if(!in_array($op,['emitir','cancelar'],true)||!in_array($env,['homologation','production'],true))stop('Operação/ambiente inválido.');
 if($env==='production'&&($q['confirmarProducao']??false)!==true)stop('Produção exige confirmarProducao=true.');
 if($op==='emitir'){
  if(!preg_match('/^\d{11}$|^\d{14}$/',digits((string)($q['tomadorDocumento']??''))))stop('CPF/CNPJ tomador inválido.');
  foreach(['tomadorNome','competencia','numeroDps']as $k)if(trim((string)($q[$k]??''))==='')stop("Campo $k obrigatório.");
  if(trim((string)($q['descricaoServico']??''))==='' && (($cfg['defaults']['confirmEachInvoice']??true)!==false || trim((string)($cfg['defaults']['serviceDescription']??''))===''))stop('Descrição obrigatória: indique na NFS-e ou configure padrão explicitamente autorizado.');
  if(!preg_match('/^\d{1,15}$/',(string)$q['numeroDps']))stop('DPS inválida.');
  $date=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$q['competencia']);
  if(!$date||$date->format('Y-m-d')!==$q['competencia'])stop('Competência inválida.');
  if(!is_numeric($q['valor']??null)||(float)$q['valor']<0.01)stop('Valor inválido.');
 }else{
  if(!preg_match('/^\d{50}$/',digits((string)($q['chaveAcesso']??''))))stop('Chave acesso inválida.');
  if(!in_array((string)($q['codigoMotivo']??''),['1','2','9'],true))stop('Código de motivo inválido.');
  if(trim((string)($q['motivo']??''))==='')stop('Motivo obrigatório.');
 }
}
echo "Preflight passou; NÃO comprova enquadramento tributário, conexão ou autorização fiscal.\n";
