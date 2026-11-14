<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{HttpException,Input};
use App\Repositories\Store;
final class SchedulingService
{
    public function __construct(private Store $store,private array $user) {}
    private static function future(array $d,string $key): string
    {
        $value=Input::text($d,$key,19); $date=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value);
        if(!$date || $date->format('Y-m-d H:i:s')!==$value || $value<=date('Y-m-d H:i:s')) Input::fail($key); return $value;
    }
    public static function lockProfessional(Store $s,int $id): void { $s->execute('UPDATE users SET active=active WHERE id=?',[$id]); }
    public static function assertFree(Store $s,int $professional,string $start,string $end,?int $excludedSlot=null,?int $excludedAppointment=null): void
    {
        if($s->oneLocked('SELECT id FROM appointment_slots WHERE professional_id=? AND status IN (?,?) AND starts_at<? AND ends_at>? AND id<>?',[$professional,'available','reserved',$end,$start,$excludedSlot??0])) throw new HttpException(409,'SCHEDULE_CONFLICT','Horário sobrepõe outra disponibilidade.');
        if($s->oneLocked('SELECT id FROM appointments WHERE professional_id=? AND scheduled_at>=? AND scheduled_at<? AND status NOT IN (?,?) AND id<>?',[$professional,$start,$end,'cancelled','rescheduled',$excludedAppointment??0])) throw new HttpException(409,'SCHEDULE_CONFLICT','Profissional já possui atendimento nesse período.');
    }
    private function manageTeam(IndustryService $industry,int $team): void
    {
        $industry->manage('care_operations');
        if(!$industry->store->one('SELECT id FROM teams WHERE id=? AND company_id=?',[$team,$industry->company])) Input::fail('equipe');
        if(!$industry->isPlatform() && !$industry->store->one('SELECT user_id FROM team_members WHERE team_id=? AND user_id=?',[$team,$this->user['id']])) throw new HttpException(403,'FORBIDDEN','Coordenação limitada às próprias equipes.');
    }
    public function slots(IndustryService $industry): array
    {
        $industry->manage('care_operations');
        $rows=$this->store->all('SELECT s.*,u.name AS professional_name,l.name AS unit_name FROM appointment_slots s JOIN users u ON u.id=s.professional_id JOIN service_units l ON l.id=s.service_unit_id WHERE s.company_id=? ORDER BY s.starts_at DESC',[$industry->company]);
        return array_values(array_filter($rows,fn($s)=>$industry->isPlatform() || $this->store->one('SELECT user_id FROM team_members WHERE team_id=? AND user_id=?',[$s['team_id'],$this->user['id']])));
    }
    public function createSlot(IndustryService $industry,array $d): array
    {
        $team=Input::id($d,'team_id'); $this->manageTeam($industry,$team);
        $professional=Input::id($d,'professional_id'); $unit=Input::id($d,'service_unit_id');
        if(!$this->store->one('SELECT u.id FROM users u JOIN team_members m ON m.user_id=u.id WHERE u.id=? AND u.company_id=? AND u.active=1 AND u.role IN (?,?) AND m.team_id=?',[$professional,$industry->company,'doctor','nurse',$team])) Input::fail('profissional vinculado à equipe');
        if(!$this->store->one('SELECT id FROM service_units WHERE id=? AND company_id=? AND active=1',[$unit,$industry->company])) Input::fail('local de atendimento');
        $mode=Input::choice($d,'modality',['in_person','telehealth']); $start=self::future($d,'starts_at'); $end=self::future($d,'ends_at');
        if($end<=$start || strtotime($end)-strtotime($start)>4*3600) Input::fail('duração do horário');
        return $this->store->transaction(function()use($industry,$professional,$team,$unit,$mode,$start,$end){
            self::lockProfessional($this->store,$professional); self::assertFree($this->store,$professional,$start,$end);
            $id=$this->store->insert('appointment_slots',['company_id'=>$industry->company,'professional_id'=>$professional,'team_id'=>$team,'service_unit_id'=>$unit,'modality'=>$mode,'starts_at'=>$start,'ends_at'=>$end]);$industry->audit('slot_created','slot',$id); return ['id'=>$id];
        });
    }
    public function block(IndustryService $industry,int $id,array $d): array
    {
        $slot=$this->store->one('SELECT * FROM appointment_slots WHERE id=? AND company_id=?',[$id,$industry->company]); if(!$slot) throw new HttpException(404,'NOT_FOUND','Horário não encontrado.');
        $this->manageTeam($industry,(int)$slot['team_id']); $version=Input::id($d,'version');
        return $this->store->transaction(function()use($industry,$slot,$id,$version){
            self::lockProfessional($this->store,(int)$slot['professional_id']);
            $q=$this->store->db->prepare('UPDATE appointment_slots SET status=?,version=version+1 WHERE id=? AND status=? AND version=?'); $q->execute(['blocked',$id,'available',$version]);
            if($q->rowCount()!==1) throw new HttpException(409,'CONFLICT','Horário alterado ou já reservado.');$industry->audit('slot_blocked','slot',$id);return ['id'=>$id];
        });
    }
    public static function distance(float $lat,float $lon,float $targetLat,float $targetLon): float
    {
        $a=sin(deg2rad($targetLat-$lat)/2)**2+cos(deg2rad($lat))*cos(deg2rad($targetLat))*sin(deg2rad($targetLon-$lon)/2)**2;
        return round(6371*2*asin(sqrt(min(1,max(0,$a)))),1);
    }
    public function search(array $q): array
    {
        if(!isset($q['patient_id']) || !ctype_digit((string)$q['patient_id'])) Input::fail('beneficiário');
        $p=(new BeneficiaryService($this->store,$this->user))->eligible((int)$q['patient_id']);
        $mode=Input::choice($q,'modality',['in_person','telehealth']);
        if(!(int)$p['contract'][$mode==='in_person'?'allow_in_person':'allow_telehealth']) throw new HttpException(403,'NOT_COVERED','Modalidade não contratada.');
        $sort=Input::choice(['sort'=>$q['sort']??'earliest'],'sort',['earliest','distance']); $lat=null;$lon=null;
        $professionalRole=Input::choice(['professional_role'=>$q['professional_role']??'all'],'professional_role',['all','doctor','nurse']);
        if(($q['near']??'home')==='industry') {
            $unit=$this->store->one('SELECT latitude,longitude FROM industry_units WHERE id=? AND company_id=? AND active=1',[$p['industry_unit_id'],$p['company_id']]);
            $lat=$unit['latitude']??null;$lon=$unit['longitude']??null;
        } elseif(isset($q['latitude']) || isset($q['longitude'])) {
            if(!is_numeric($q['latitude']??null) || !is_numeric($q['longitude']??null)) Input::fail('coordenadas');
            [$lat,$lon]=IndustryService::coordinates(['latitude'=>(float)$q['latitude'],'longitude'=>(float)$q['longitude']]);
        }
        if($sort==='distance' && $mode==='in_person' && ($lat===null || $lon===null)) Input::fail('localização necessária para ordenar por distância');
        $rows=$this->store->all('SELECT s.id,s.professional_id,u.name AS professional_name,u.role,s.team_id,s.service_unit_id,l.name AS service_unit,l.address,l.postal_code,l.latitude,l.longitude,s.modality,s.starts_at,s.ends_at,s.version FROM appointment_slots s JOIN users u ON u.id=s.professional_id AND u.company_id=s.company_id JOIN service_units l ON l.id=s.service_unit_id AND l.company_id=s.company_id JOIN teams t ON t.id=s.team_id AND t.company_id=s.company_id WHERE s.company_id=? AND s.status=? AND s.starts_at>? AND s.starts_at<=? AND s.modality=? AND u.active=1 AND l.active=1 AND EXISTS (SELECT 1 FROM team_members m WHERE m.team_id=s.team_id AND m.user_id=s.professional_id) ORDER BY s.starts_at',[$p['company_id'],'available',date('Y-m-d H:i:s'),date('Y-m-d',time()+90*86400).' 23:59:59',$mode]);
        $result=[];
        foreach($rows as $r) {
            if(substr($r['starts_at'],0,10)>$p['contract']['end_date']) continue;
            if($professionalRole!=='all' && $r['role']!==$professionalRole) continue;
            if(($q['my_team']??'0')==='1' && !$this->store->one('SELECT id FROM care_team_assignments WHERE patient_id=? AND team_id=? AND company_id=? AND active=1',[$p['id'],$r['team_id'],$p['company_id']])) continue;
            $r['distance_km']=$mode==='in_person' && $lat!==null && $lon!==null && $r['latitude']!==null && $r['longitude']!==null?self::distance((float)$lat,(float)$lon,(float)$r['latitude'],(float)$r['longitude']):null;
            unset($r['latitude'],$r['longitude']); $result[]=$r;
        }
        if($sort==='distance') usort($result,fn($a,$b)=>($a['distance_km']??PHP_FLOAT_MAX)<=>($b['distance_km']??PHP_FLOAT_MAX) ?: strcmp($a['starts_at'],$b['starts_at']));
        return $result;
    }
    public function book(int $id,array $d): array
    {
        $patient=Input::id($d,'patient_id'); $version=Input::id($d,'version');
        if(($d['authorize_team']??false)!==true) Input::fail('autorização de vínculo com a equipe');
        return $this->store->transaction(function()use($id,$patient,$version){
            $this->store->execute('UPDATE patients SET name=name WHERE id=?',[$patient]);
            $p=(new BeneficiaryService($this->store,$this->user))->eligible($patient);
            $slot=$this->store->one('SELECT * FROM appointment_slots WHERE id=? AND company_id=?',[$id,$p['company_id']]);
            if(!$slot) throw new HttpException(404,'NOT_FOUND','Horário não encontrado.');
            if(substr($slot['starts_at'],0,10)>$p['contract']['end_date']) throw new HttpException(403,'NOT_COVERED','Horário fora da vigência contratada.');
            self::lockProfessional($this->store,(int)$slot['professional_id']);
            if(!$this->store->one('SELECT u.id FROM users u JOIN team_members m ON m.user_id=u.id JOIN teams t ON t.id=m.team_id JOIN service_units l ON l.company_id=u.company_id WHERE u.id=? AND u.company_id=? AND u.active=1 AND u.role IN (?,?) AND t.company_id=u.company_id AND m.team_id=? AND l.id=? AND l.active=1',[$slot['professional_id'],$p['company_id'],'doctor','nurse',$slot['team_id'],$slot['service_unit_id']])) throw new HttpException(409,'SLOT_UNAVAILABLE','Profissional, equipe ou unidade indisponível.');
            if(!(int)$p['contract'][$slot['modality']==='in_person'?'allow_in_person':'allow_telehealth']) throw new HttpException(403,'NOT_COVERED','Modalidade não contratada.');
            self::assertFree($this->store,(int)$slot['professional_id'],$slot['starts_at'],$slot['ends_at'],$id);
            if($this->store->one('SELECT id FROM appointments WHERE patient_id=? AND scheduled_at>=? AND scheduled_at<? AND status NOT IN (?,?)',[$patient,$slot['starts_at'],$slot['ends_at'],'cancelled','rescheduled'])) throw new HttpException(409,'PATIENT_CONFLICT','Já existe atendimento para este beneficiário no período.');
            if($this->store->one('SELECT a.id FROM appointments a JOIN appointment_slots s ON s.appointment_id=a.id WHERE a.patient_id=? AND s.starts_at<? AND s.ends_at>? AND a.status NOT IN (?,?)',[$patient,$slot['ends_at'],$slot['starts_at'],'cancelled','rescheduled'])) throw new HttpException(409,'PATIENT_CONFLICT','Outro atendimento do beneficiário sobrepõe o horário.');
            $claim=$this->store->db->prepare('UPDATE appointment_slots SET status=?,version=version+1 WHERE id=? AND company_id=? AND status=? AND version=? AND starts_at>?');$claim->execute(['reserved',$id,$p['company_id'],'available',$version,date('Y-m-d H:i:s')]);
            if($claim->rowCount()!==1) throw new HttpException(409,'SLOT_UNAVAILABLE','Horário reservado ou alterado. Faça uma nova busca.');
            $appointment=$this->store->insert('appointments',['company_id'=>$p['company_id'],'patient_id'=>$patient,'professional_id'=>$slot['professional_id'],'scheduled_at'=>$slot['starts_at'],'status'=>'confirmed','modality'=>$slot['modality'],'service_unit_id'=>$slot['service_unit_id']]);
            $this->store->execute('UPDATE appointment_slots SET appointment_id=? WHERE id=?',[$appointment,$id]);
            $assignment=$this->store->one('SELECT id FROM care_team_assignments WHERE patient_id=? AND team_id=?',[$patient,$slot['team_id']]);
            if($assignment) $this->store->execute('UPDATE care_team_assignments SET active=1 WHERE id=?',[$assignment['id']]);
            else $this->store->insert('care_team_assignments',['company_id'=>$p['company_id'],'patient_id'=>$patient,'team_id'=>$slot['team_id']]);
            $case=$this->store->insert('continuity_cases',['company_id'=>$p['company_id'],'patient_id'=>$patient,'appointment_id'=>$appointment,'status'=>'confirmed','created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
            $this->store->insert('team_notifications',['company_id'=>$p['company_id'],'patient_id'=>$patient,'case_id'=>$case,'event_type'=>'self_booking','created_at'=>date('Y-m-d H:i:s')]);
            Audit::record($this->store,$this->user,'booking_team_authorized','appointment',$appointment);return ['id'=>$appointment,'modality'=>$slot['modality'],'status'=>'confirmed'];
        });
    }
    public static function httpsUrl(array $d,string $key,bool $optional=false): string
    {
        $url=Input::text($d,$key,2000,$optional); if($url==='' && $optional) return '';
        if(!filter_var($url,FILTER_VALIDATE_URL) || parse_url($url,PHP_URL_SCHEME)!=='https' || parse_url($url,PHP_URL_USER)!==null || parse_url($url,PHP_URL_PASS)!==null) Input::fail($key.' HTTPS'); return $url;
    }
    public function setAccess(int $id,array $d): array
    {
        $care=new CareService($this->store,$this->user); $care->access->roles(['doctor','nurse']);
        $a=$this->store->one('SELECT * FROM appointments WHERE id=? AND company_id=? AND professional_id=? AND modality=?',[$id,$this->user['company_id'],$this->user['id'],'telehealth']);
        if(!$a) throw new HttpException(404,'NOT_FOUND','Teleconsulta não encontrada.');$care->access->patient((int)$a['patient_id']);
        if(!in_array($a['status'],['expected','confirmed'],true)) throw new HttpException(409,'INVALID_STATE','Consulta indisponível para alterar o acesso.');
        $url=Crypto::encrypt(self::httpsUrl($d,'meeting_url'));
        return $this->store->transaction(function()use($id,$url){$this->store->execute('DELETE FROM appointment_access WHERE appointment_id=?',[$id]);$this->store->insert('appointment_access',['appointment_id'=>$id,'link_encrypted'=>$url,'updated_by'=>$this->user['id'],'updated_at'=>date('Y-m-d H:i:s')]);Audit::record($this->store,$this->user,'telehealth_access_set','appointment',$id);return ['id'=>$id];});
    }
    public function access(int $id): array
    {
        $a=$this->store->one('SELECT a.*,s.ends_at FROM appointments a LEFT JOIN appointment_slots s ON s.appointment_id=a.id WHERE a.id=? AND a.company_id=? AND a.modality=?',[$id,$this->user['company_id'],'telehealth']);
        if(!$a) throw new HttpException(404,'NOT_FOUND','Teleconsulta não encontrada.');
        if(in_array($this->user['role'],['doctor','nurse'],true)) {
            if((int)$a['professional_id']!==(int)$this->user['id']) throw new HttpException(404,'NOT_FOUND','Teleconsulta não encontrada.');
            (new Access($this->store,$this->user))->patient((int)$a['patient_id']);
        } else (new BeneficiaryService($this->store,$this->user))->owned((int)$a['patient_id']);
        if(!in_array($a['status'],['expected','confirmed','attended'],true)) throw new HttpException(409,'INVALID_STATE','Consulta indisponível.');
        $start=strtotime($a['scheduled_at']); $end=strtotime($a['ends_at']??$a['scheduled_at'])+3600;
        if(time()<$start-1800 || time()>$end) throw new HttpException(409,'OUTSIDE_ACCESS_WINDOW','Link liberado de 30 minutos antes até uma hora após o término previsto.');
        $link=$this->store->one('SELECT link_encrypted FROM appointment_access WHERE appointment_id=?',[$id]);
        if(!$link) throw new HttpException(409,'LINK_PENDING','Aguarde o link fornecido pelo profissional.');
        Audit::record($this->store,$this->user,'telehealth_access_read','appointment',$id); return ['meeting_url'=>Crypto::decrypt($link['link_encrypted'])];
    }
    public function professionalAgenda(): array
    {
        $access=new Access($this->store,$this->user);$access->roles(['doctor','nurse']);
        $rows=$this->store->all('SELECT a.id,a.patient_id,a.scheduled_at,a.status,a.modality,p.name AS patient_name,l.name AS service_unit FROM appointments a JOIN patients p ON p.id=a.patient_id LEFT JOIN service_units l ON l.id=a.service_unit_id WHERE a.company_id=? AND a.professional_id=? AND a.scheduled_at>=? AND a.scheduled_at<=? ORDER BY a.scheduled_at',[$this->user['company_id'],$this->user['id'],date('Y-m-d',time()-7*86400).' 00:00:00',date('Y-m-d',time()+90*86400).' 23:59:59']);
        $ids=array_map('intval',array_column($access->patients(),'id'));return array_values(array_filter($rows,fn($a)=>in_array((int)$a['patient_id'],$ids,true)));
    }
}
