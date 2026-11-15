<?php
declare(strict_types=1);
namespace App\Services;
final class CampaignDiagnosis {
 public static function read(array $stats,array $funnels,array $segments=[],string $kind='return_call',array $barriers=[]):array{
  $biggest=['from'=>null,'to'=>null,'loss_percent'=>0.0];foreach($funnels as $variant=>$stages){$prev=null;foreach($stages as $name=>$count){if($prev&&$prev['count']>0){$loss=100*max(0,$prev['count']-$count)/$prev['count'];if($loss>$biggest['loss_percent'])$biggest=['variant'=>$variant,'from'=>$prev['name'],'to'=>$name,'loss_percent'=>$loss];}$prev=['name'=>$name,'count'=>$count];}}
  $suggestion=match($biggest['to']){'read'=>'Trocar o canal ou enviar depois do fim do turno.','responded'=>'Encurtar o texto, deixar data e hora prontas e responder em um toque.','scheduled'=>'Investigar barreiras voluntárias e oferecer horário compatível com o turno.','attended'=>'Lembrar na véspera; avisar o líder somente com autorização.','interested'=>'Rever o convite e a clareza do benefício.','participating'=>'Oferecer acolhimento e uma ação simples.','completed'=>'Reduzir o conteúdo para 1 a 3 ações simples.',default=>'Acompanhar a próxima etapa e ouvir a equipe.'};
  if(($stats['optout_percent']??0)>5)$suggestion='Reduzir a frequência ou mudar o tom; trocar PDF longo por 1 a 3 ações em linguagem simples.';
  if($kind==='incentive'&&($stats['cost_per_recovered']??0)>($stats['max_cost']??126))$suggestion='Rever o benefício de custo direto: folga no aniversário de familiar ou prioridade nas férias, conforme política da empresa.';
  $safe=[];foreach($segments as $segment)if(($segment['n']??0)>=10){$segment['below_average']=($stats['rate1']??0)-($segment['rate']??0)>=10;$safe[]=$segment;}
  return ['largest_drop'=>$biggest,'suggestion'=>$suggestion,'segments'=>$safe,'barriers'=>$barriers,'funnels'=>$funnels,'read_receipt'=>'Leitura simulada quando informada; nunca inferida como leitura real.'];
 }
}
