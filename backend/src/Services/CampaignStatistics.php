<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Input;
final class CampaignStatistics {
 public static function calculate(array $d):array{
  $n1=(int)($d['n1']??0);$n2=(int)($d['n2']??0);$x1=(int)($d['x1']??0);$x2=(int)($d['x2']??0);
  if(min($n1,$n2,$x1,$x2)<0||$x1>$n1||$x2>$n2)Input::fail('denominadores da campanha');
  $rate=fn($a,$b)=>$b>0?100*$a/$b:null;$p1=$n1?$x1/$n1:null;$p2=$n2?$x2/$n2:null;$effect=$p1!==null&&$p2!==null?100*($p1-$p2):null;$se=$effect===null?null:100*sqrt($p1*(1-$p1)/$n1+$p2*(1-$p2)/$n2);
  $target=(float)($d['minimum_gain_pp']??4);if($target<=0||$target>100)Input::fail('meta');
  $sample=(int)ceil(7.84*((($p2??.5)*(1-($p2??.5)))+(min(.999,($p2??.5)+$target/100)*(1-min(.999,($p2??.5)+$target/100))))/pow($target/100,2));
  $sent=(int)($d['sent']??$n1);$responded=(int)($d['responded72']??0);$scheduled=(int)($d['scheduled']??0);$attended=(int)($d['attended']??0);
  $cost=$sent*(float)($d['message_cost']??.05)+(int)($d['redeemed']??0)*(float)($d['benefit_cost']??0)+(float)($d['nursing_hours']??0)*(float)($d['hour_cost']??25);
  $gain=$p2!==null?$x1-$n1*$p2:0;$times=$d['response_hours']??[];sort($times);$count=count($times);$median=$count?($count%2?$times[intdiv($count,2)]:($times[$count/2-1]+$times[$count/2])/2):null;
  return ['n1'=>$n1,'n2'=>$n2,'x1'=>$x1,'x2'=>$x2,'rate1'=>$rate($x1,$n1),'rate2'=>$rate($x2,$n2),'effect_pp'=>$effect,'lower_pp'=>$se===null?null:$effect-1.96*$se,'upper_pp'=>$se===null?null:$effect+1.96*$se,'sample_per_arm'=>$sample,'response_percent'=>$rate($responded,$sent),'median_response_hours'=>$median,'scheduling_percent'=>$rate($scheduled,$n1),'attendance_percent'=>$rate($attended,$scheduled),'on_time_percent'=>$rate((int)($d['on_time']??0),$n1),'checkin_percent'=>$rate((int)($d['checkins']??0),$n1),'adherence_percent'=>$rate((int)($d['adhered']??0),$n1),'optout_percent'=>$rate((int)($d['optouts']??0),$sent),'satisfaction_percent'=>$d['satisfaction_percent']??null,'upheld_pressure'=>(bool)($d['upheld_pressure']??false),'cost_total'=>$cost,'recovered'=>$gain>0?$gain:null,'cost_per_recovered'=>$gain>0?$cost/$gain:null,'minimum_gain_pp'=>$target,'max_cost'=>(float)($d['max_cost']??126),'period_ended'=>(bool)($d['period_ended']??false),'sent'=>$sent,'responded_total'=>(int)($d['responded_total']??$responded),'responded72'=>$responded,'scheduled'=>$scheduled,'attended'=>$attended];
 }
}
