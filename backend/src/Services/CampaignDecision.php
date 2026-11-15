<?php
declare(strict_types=1);
namespace App\Services;
final class CampaignDecision {
 public static function suggest(array $s,?array $config=null):array{
  $c=$config??require dirname(__DIR__,2).'/config/outreach.php';
  if(($s['optout_percent']??0)>$c['maximum_optout_percent']||($s['upheld_pressure']??false)||isset($s['satisfaction_percent'])&&$s['satisfaction_percent']<$c['minimum_satisfaction_percent'])return self::result('pause','Pausar e revisar','Uma trava de saída, pressão ou satisfação foi violada.');
  if(!($s['period_ended']??false)||min($s['n1'],$s['n2'])<$c['minimum_per_arm'])return self::result('wait','Aguardar dados','Período ainda aberto ou faltam pelo menos '.max(0,$c['minimum_per_arm']-min($s['n1'],$s['n2'])).' pessoas para o mínimo por grupo. Amostra para a meta: '.$s['sample_per_arm'].' por grupo.');
  if($s['lower_pp']===null)return self::result('wait','Aguardar dados','Comparação ainda não disponível.');
  if($s['lower_pp']>0){if($s['effect_pp']>=$s['minimum_gain_pp']&&$s['cost_per_recovered']!==null&&$s['cost_per_recovered']<=$s['max_cost'])return self::result('maintain','Manter e ampliar','Efeito positivo, meta atingida e custo dentro do teto.');return self::result('adjust','Ajustar','Efeito positivo, mas ganho abaixo da meta ou custo acima do teto.');}
  if($s['upper_pp']<0)return self::result('end','Encerrar','Até o limite superior indica piora do indicador.');
  if($s['upper_pp']<$s['minimum_gain_pp'])return self::result('redesign','Redesenhar','O intervalo inclui zero e, mesmo no limite superior, não alcança a meta.');
  return self::result('wait','Aguardar dados (inconclusivo)','O intervalo inclui zero e ainda admite a meta. São necessárias aproximadamente '.$s['sample_per_arm'].' pessoas por grupo.');
 }
 private static function result(string $code,string $label,string $why):array{return compact('code','label','why');}
}
