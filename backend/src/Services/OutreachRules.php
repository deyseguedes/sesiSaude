<?php
declare(strict_types=1);
namespace App\Services;
final class OutreachRules {
 public static function classify(array $p,string $today,array $config):?array{
  if(($p['status']??'')!=='ATIVO'||in_array($p['state']??'pending',['confirmed','rescheduled'],true)||empty($p['return_date']))return null;
  $now=new \DateTimeImmutable($today);$days=(int)$now->diff(new \DateTimeImmutable($p['return_date']))->format('%r%a');if($days>$config['horizon_days'])return null;
  $age=(int)(new \DateTimeImmutable(substr($p['message_sent_at']??$today,0,10)))->diff($now)->format('%r%a');$p['days_due']=$days;$p['message_age']=max(0,$age);$p['unanswered_count']=$p['unanswered_count']??1;
  $p['why']=($days<0?'retorno vencido há '.abs($days).' dias':'retorno vence em '.$days.' dias').'; '.(($p['reply']??null)==='cannot'?'respondeu “não posso”; dificuldade voluntária: '.OutreachService::barrier($p['barrier']??null):(($p['reply']??null)==='support'?'pediu apoio à equipe':'mensagem sem resposta há '.$p['message_age'].' dias'));
  $p['category']=$age>=$config['unanswered_days']||in_array($p['reply']??null,['cannot','support'],true)?'unanswered':'waiting';return $p;
 }
 public static function compare(array $a,array $b):int{return $a['days_due']<=>$b['days_due']?:(($b['reply']??null)==='cannot')<=>(($a['reply']??null)==='cannot')?:($b['unanswered_count']??1)<=>($a['unanswered_count']??1)?:($b['risk_current']??0)<=>($a['risk_current']??0)?:strcmp($a['registration'],$b['registration']);}
}
