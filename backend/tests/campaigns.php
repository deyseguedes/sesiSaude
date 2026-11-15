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
ob_end_flush();echo "OK: {$count} combined campaign checks\n";
