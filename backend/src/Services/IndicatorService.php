<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{HttpException,Input};
final class IndicatorService
{
    public const TYPES=['numeric','percentage','categorical','frequency','range','adherence','attendance','continuity'];
    public function __construct(private CareService $care) {}
    public function indicator(int $id): array
    {
        $this->care->access->roles(['doctor','nurse']);
        $row=$this->care->store->one('SELECT * FROM clinical_indicators WHERE id=? AND company_id=?',[$id,$this->care->user['company_id']]);
        if (!$row) throw new HttpException(404,'NOT_FOUND','Indicador não encontrado.');
        $this->care->access->patient((int)$row['patient_id']); $row['categories']=json_decode($row['categories'],true); return $row;
    }
    public function list(int $patient): array
    {
        $this->care->access->patient($patient);
        $rows=$this->care->store->all('SELECT * FROM clinical_indicators WHERE patient_id=? AND company_id=? ORDER BY id',[$patient,$this->care->user['company_id']]);
        foreach($rows as &$r) $r['categories']=json_decode($r['categories'],true);
        return $rows;
    }
    public function create(array $d): array
    {
        $patient=Input::id($d,'patient_id'); $this->care->access->patient($patient);
        $type=Input::choice($d,'type',self::TYPES); $categories=$d['categories']??[];
        if (!is_array($categories) || !array_is_list($categories) || count($categories)>30) Input::fail('categories');
        foreach($categories as $v) if (!is_string($v) || trim($v)==='' || mb_strlen($v)>150) Input::fail('categories');
        if ($type==='categorical' && count($categories)<2) Input::fail('categories');
        $data=['patient_id'=>$patient,'name'=>Input::text($d,'name',150),'type'=>$type,'unit'=>Input::text($d,'unit',40),'reference_value'=>Input::text($d,'reference_value',500,true),'measurement_method'=>Input::text($d,'measurement_method',200),'frequency'=>Input::text($d,'frequency',100),'categories'=>json_encode(array_values(array_unique($categories)),JSON_THROW_ON_ERROR),'company_id'=>$this->care->user['company_id'],'created_by'=>$this->care->user['id'],'created_at'=>date('Y-m-d H:i:s')];
        return $this->care->store->transaction(function()use($data){
            $id=$this->care->store->insert('clinical_indicators',$data); Audit::record($this->care->store,$this->care->user,'indicator_created','indicator',$id); return ['id'=>$id];
        });
    }
    public function measurements(int $id): array
    {
        $this->indicator($id);
        Audit::record($this->care->store,$this->care->user,'read','indicator',$id);
        return $this->care->store->all('SELECT * FROM indicator_measurements WHERE indicator_id=? ORDER BY collected_at,id',[$id]);
    }
    public function measure(int $id,array $d): array
    {
        $i=$this->indicator($id); $source=Input::choice($d,'source',['professional','self_reported']);
        $at=Input::timestamp($d,'collected_at'); $method=Input::text($d,'method',200);
        if($this->care->store->one('SELECT id FROM indicator_measurements WHERE indicator_id=? AND source=? AND collected_at=?',[$id,$source,$at])) throw new HttpException(409,'DUPLICATE_MEASUREMENT','Já existe uma medição desta origem no instante informado.');
        $num=null; $category=null;
        if ($i['type']==='categorical') {
            $category=Input::choice($d,'value',$i['categories']);
        } else {
            $num=Input::number($d['value']??null,'value');
            if ($i['type']==='percentage' && ($num<0 || $num>100)) Input::fail('value percentual');
            if (in_array($i['type'],['frequency','adherence','attendance','continuity'],true) && $num<0) Input::fail('value');
        }
        return $this->care->store->transaction(function()use($id,$num,$category,$source,$at,$method){
            $record=$this->care->store->insert('indicator_measurements',['indicator_id'=>$id,'value_num'=>$num,'value_category'=>$category,'source'=>$source,'professional_id'=>$source==='professional'?$this->care->user['id']:null,'recorded_by'=>$this->care->user['id'],'collected_at'=>$at,'recorded_at'=>date('Y-m-d H:i:s'),'method'=>$method]);
            Audit::record($this->care->store,$this->care->user,'measurement_recorded','indicator',$id); return ['id'=>$record];
        });
    }
    public function goal(int $plan,array $d,?int $goalId=null): array
    {
        $p=$this->care->plan($plan); if ($p['status']==='closed') throw new HttpException(409,'PLAN_CLOSED','Reabra o plano antes de alterar metas.');
        $indicator=$this->indicator(Input::id($d,'indicator_id'));
        if ((int)$indicator['patient_id']!==(int)$p['patient_id']) Input::fail('indicator_id de outro paciente');
        $responsible=Input::id($d,'responsible_id'); $this->care->access->team((int)$p['patient_id'],(int)$p['team_id'],$responsible);
        $source=Input::choice($d,'measurement_source',['professional','self_reported']);
        $direction=Input::choice($d,'direction',['increase','decrease','range','maintain','category']);
        $samples=Input::id($d,'min_samples'); if ($samples>1000) Input::fail('min_samples');
        $target=$d['target_value']??null; $initial=$d['initial_value']??null; $min=null; $max=null;
        if ($indicator['type']==='categorical') {
            if ($direction!=='category') Input::fail('direction');
            $target=Input::choice($d,'target_value',$indicator['categories']);
            if ($initial!==null && !in_array($initial,$indicator['categories'],true)) Input::fail('initial_value');
        } else {
            if ($direction==='category') Input::fail('direction');
            if ($initial!==null) $initial=Input::number($initial,'initial_value');
            if ($direction==='range') {
                $min=Input::number($d['target_min']??null,'target_min'); $max=Input::number($d['target_max']??null,'target_max');
                if ($min>$max) Input::fail('faixa-alvo'); $target=null;
            } else $target=Input::number($target,'target_value');
            if ($indicator['type']==='percentage') foreach([$initial,$target,$min,$max] as $value) if ($value!==null && ($value<0 || $value>100)) Input::fail('alvo percentual');
        }
        $deadline=Input::date($d,'deadline'); if ($deadline<$p['start_date']) Input::fail('deadline');
        $data=['plan_id'=>$plan,'description'=>Input::text($d,'description',1000),'initial_value'=>$initial===null?null:(string)$initial,'target_value'=>$target===null?null:(string)$target,'target_min'=>$min,'target_max'=>$max,'unit'=>$indicator['unit'],'direction'=>$direction,'frequency'=>Input::text($d,'frequency',100),'deadline'=>$deadline,'responsible_id'=>$responsible,'status'=>Input::choice($d,'status',['active','paused','cancelled']),'min_samples'=>$samples,'updated_at'=>date('Y-m-d H:i:s')];
        return $this->care->store->transaction(function()use($plan,$d,$goalId,$data,$indicator,$source){
            $s=$this->care->store;
            $data['evidence_after_id']=(int)($s->one('SELECT MAX(id) n FROM indicator_measurements WHERE indicator_id=?',[$indicator['id']])['n']??0);
            if ($goalId===null) {
                $id=$s->insert('care_plan_goals',$data+['created_at'=>date('Y-m-d H:i:s')]);
                $s->insert('care_goal_indicators',['goal_id'=>$id,'indicator_id'=>$indicator['id'],'measurement_source'=>$source]); $before=null;
            } else {
                $before=$s->one('SELECT * FROM care_plan_goals WHERE id=? AND plan_id=?',[$goalId,$plan]);
                if (!$before) throw new HttpException(404,'NOT_FOUND','Meta não encontrada.');
                $version=Input::id($d,'version'); $columns=array_keys($data);
                $q=$s->db->prepare('UPDATE care_plan_goals SET '.implode(',',array_map(fn($c)=>$c.'=?',$columns)).',version=version+1 WHERE id=? AND version=?');
                $q->execute([...array_values($data),$goalId,$version]);
                if ($q->rowCount()!==1) throw new HttpException(409,'CONFLICT','Recarregue a meta antes de alterar.');
                $s->execute('UPDATE care_goal_indicators SET indicator_id=?,measurement_source=? WHERE goal_id=?',[$indicator['id'],$source,$goalId]); $id=$goalId;
            }
            $this->care->event($plan,$goalId===null?'goal_created':'goal_updated',['goal_id'=>$id,'before'=>$before,'after'=>$data,'indicator_id'=>$indicator['id'],'measurement_source'=>$source]); return ['id'=>$id];
        });
    }
    public function progress(array $goal): array
    {
        $link=$this->care->store->one('SELECT * FROM care_goal_indicators WHERE goal_id=?',[$goal['id']]);
        if (!$link) return ['state'=>'insufficient_data','samples'=>0,'latest'=>null];
        $rows=$this->care->store->all('SELECT * FROM indicator_measurements WHERE indicator_id=? AND source=? AND collected_at>=? AND id>? ORDER BY collected_at,id',[$link['indicator_id'],$link['measurement_source'],$goal['updated_at'],$goal['evidence_after_id']]);
        $count=count($rows); $latest=$count?$rows[$count-1]:null;
        if ($goal['status']!=='active') return ['state'=>$goal['status'],'samples'=>$count,'latest'=>$latest];
        if ($count<(int)$goal['min_samples']) return ['state'=>'insufficient_data','samples'=>$count,'latest'=>$latest];
        $v=$latest['value_num']; $target=$goal['target_value'];
        $met=match($goal['direction']) {
            'category'=>$latest['value_category']===$target,
            'increase'=>$v>=(float)$target,
            'decrease'=>$v<=(float)$target,
            'range'=>$v>=(float)$goal['target_min'] && $v<=(float)$goal['target_max'],
            'maintain'=>abs($v-(float)$target)<0.000001,
            default=>false,
        };
        return ['state'=>$met?'attained':'in_progress','samples'=>$count,'latest'=>$latest];
    }
    public function goals(int $plan): array
    {
        $rows=$this->care->goals($plan);
        foreach($rows as &$row) {
            $row['indicator']=$this->care->store->one('SELECT i.id,i.name,i.type,l.measurement_source FROM clinical_indicators i JOIN care_goal_indicators l ON l.indicator_id=i.id WHERE l.goal_id=?',[$row['id']]);
            $row['progress']=$this->progress($row);
        }
        return $rows;
    }
}
