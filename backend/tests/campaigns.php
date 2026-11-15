<?php
ob_start();require __DIR__.'/aps-import.php';
use App\Services\CampaignStatistics;
$s1=CampaignStatistics::calculate(['n1'=>420,'n2'=>420,'x1'=>244,'x2'=>197,'sent'=>420,'scheduled'=>250,'attended'=>244,'responded72'=>300,'response_hours'=>[1,3,2,4],'period_ended'=>true]);
verify($s1['lower_pp']>0&&$s1['effect_pp']>11,'Campaign confidence interval');
verify($s1['median_response_hours']===2.5,'Median response time');
verify($s1['cost_per_recovered']>0,'Recovered return cost');
verify(CampaignStatistics::calculate(['n1'=>100,'n2'=>100,'x1'=>40,'x2'=>50])['cost_per_recovered']===null,'No gain has no recovered cost');
verify(CampaignStatistics::calculate(['n1'=>0,'n2'=>0])['effect_pp']===null,'No zero denominator');
verify(CampaignStatistics::calculate(['n1'=>100,'n2'=>100,'x1'=>50,'x2'=>50])['sample_per_arm']>2400,'Small effects require program-scale sample');
$diagnosis=App\Services\CampaignDiagnosis::read($s1,['A'=>['sent'=>420,'read'=>100,'responded'=>90]], [['name'=>'Pequeno','n'=>9,'rate'=>10],['name'=>'Turno 06h às 14h','n'=>30,'rate'=>20]]);
verify($diagnosis['largest_drop']['to']==='read','Largest loss detected');verify(count($diagnosis['segments'])===1&&$diagnosis['segments'][0]['below_average'],'Segments require ten people and highlight deficit');
$cases=[
 ['n1'=>420,'n2'=>420,'x1'=>244,'x2'=>197,'period_ended'=>true,'expected'=>'maintain'],
 ['n1'=>2500,'n2'=>2500,'x1'=>1175,'x2'=>1150,'period_ended'=>true,'expected'=>'redesign'],
 ['n1'=>500,'n2'=>500,'x1'=>250,'x2'=>230,'sent'=>500,'optouts'=>35,'period_ended'=>true,'expected'=>'pause'],
 ['n1'=>1000,'n2'=>1000,'x1'=>560,'x2'=>500,'sent'=>2000,'message_cost'=>.05,'redeemed'=>100,'benefit_cost'=>83,'period_ended'=>true,'expected'=>'adjust'],
 ['n1'=>60,'n2'=>60,'x1'=>35,'x2'=>28,'period_ended'=>true,'expected'=>'wait'],
 ['n1'=>50,'n2'=>50,'x1'=>0,'x2'=>0,'period_ended'=>false,'expected'=>'wait']
];
foreach($cases as $case)verify(App\Services\CampaignDecision::suggest(CampaignStatistics::calculate($case))['code']===$case['expected'],'Campaign decision '.$case['expected']);
verify(App\Services\CampaignDecision::suggest(CampaignStatistics::calculate(['n1'=>1000,'n2'=>1000,'x1'=>300,'x2'=>500,'period_ended'=>true]))['code']==='end','Harmful campaign ends');
ob_end_flush();echo "OK: {$count} combined campaign checks\n";
