<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{HttpException,Input};
use App\Repositories\Store;
final class EnrollmentService
{
    public function __construct(private Store $store) {}
    public function invite(IndustryService $industry,array $d,?int $selfSponsor=null): array
    {
        if($selfSponsor===null) $industry->manage();
        else {
            $own=(new BeneficiaryService($this->store,$industry->user))->eligible($selfSponsor);
            if($own['kind']!=='worker' || (int)$own['user_id']!==(int)$industry->user['id']) Input::fail('titular');
            $d=array_replace($d,['contract_id'=>(int)$own['contract_id'],'industry_unit_id'=>(int)$own['industry_unit_id'],'sponsor_patient_id'=>$selfSponsor,'kind'=>'dependent']);
        }
        $contract=$industry->activeContract(Input::id($d,'contract_id')); $unit=Input::id($d,'industry_unit_id');
        if(!$this->store->one('SELECT id FROM industry_units WHERE id=? AND company_id=? AND active=1',[$unit,$industry->company])) Input::fail('unidade industrial');
        $kind=Input::choice($d,'kind',['worker','dependent']); $sponsor=null;
        if($kind==='dependent') {
            $sponsor=Input::id($d,'sponsor_patient_id');
            if(!(int)$contract['allow_dependents'] || !$this->store->one('SELECT id FROM beneficiary_links WHERE patient_id=? AND company_id=? AND contract_id=? AND kind=? AND status=?',[$sponsor,$industry->company,$contract['id'],'worker','active'])) Input::fail('titular/cobertura');
        }
        $email=strtolower(Input::text($d,'email',190)); if(!filter_var($email,FILTER_VALIDATE_EMAIL)) Input::fail('email');
        $token=bin2hex(random_bytes(32));
        $id=$this->store->insert('enrollment_invitations',['company_id'=>$industry->company,'contract_id'=>$contract['id'],'industry_unit_id'=>$unit,'sponsor_patient_id'=>$sponsor,'kind'=>$kind,'email'=>$email,'token_hash'=>hash('sha256',$token),'expires_at'=>date('Y-m-d H:i:s',time()+48*3600),'created_by'=>$industry->user['id'],'created_at'=>date('Y-m-d H:i:s')]);
        $industry->audit('invitation_created','invitation',$id);
        return ['id'=>$id,'invitation_token'=>$token,'registration_path'=>'/register?invite='.$token,'expires_in_hours'=>48];
    }
    public function register(array $d): array
    {
        $key=hash('sha256','enrollment|'.($_SERVER['REMOTE_ADDR']??''));
        $n=(int)$this->store->one('SELECT COUNT(*) n FROM login_attempts WHERE attempt_key=? AND created_at>=?',[$key,date('Y-m-d H:i:s',time()-900)])['n'];
        if($n>=10) throw new HttpException(429,'RATE_LIMITED','Aguarde antes de tentar novamente.');
        $this->store->insert('login_attempts',['attempt_key'=>$key,'created_at'=>date('Y-m-d H:i:s')]);
        $token=Input::text($d,'invitation_token',64); if(!preg_match('/^[a-f0-9]{64}$/D',$token)) Input::fail('convite');
        $invite=$this->store->one('SELECT * FROM enrollment_invitations WHERE token_hash=? AND status=? AND expires_at>?',[hash('sha256',$token),'open',date('Y-m-d H:i:s')]);
        if(!$invite) throw new HttpException(422,'INVITATION_INVALID','Convite expirado, utilizado ou inválido.');
        $email=strtolower(Input::text($d,'email',190)); if($email!==$invite['email']) Input::fail('email diferente do convite');
        $name=Input::text($d,'name',150); $password=Input::password($d);
        $birth=Input::date($d,'birth_date');
        if($birth>date('Y-m-d') || (new \DateTimeImmutable($birth))->diff(new \DateTimeImmutable('today'))->y<18) throw new HttpException(422,'REPRESENTATIVE_REQUIRED','Cadastro de menor deve ser feito pela gestão após verificar o responsável.');
        if(($d['accept_notice']??false)!==true) Input::fail('ciência do aviso de privacidade');
        $contact=Input::text($d,'contact',40); $postal=IndustryService::postal($d); $period=Input::choice($d,'preferred_period',['morning','afternoon','evening','flexible']);
        return $this->store->transaction(function()use($invite,$email,$name,$password,$birth,$contact,$postal,$period){
            $context=['id'=>$invite['created_by'],'company_id'=>$invite['company_id'],'role'=>'worker','management_level'=>'none'];
            $contract=(new IndustryService($this->store,$context))->activeContract((int)$invite['contract_id']);
            if($invite['kind']==='dependent' && (!(int)$contract['allow_dependents'] || !$this->store->one('SELECT id FROM beneficiary_links WHERE patient_id=? AND company_id=? AND contract_id=? AND kind=? AND status=?',[$invite['sponsor_patient_id'],$invite['company_id'],$invite['contract_id'],'worker','active']))) Input::fail('cobertura do titular');
            if(!$this->store->one('SELECT id FROM industry_units WHERE id=? AND company_id=? AND active=1',[$invite['industry_unit_id'],$invite['company_id']])) Input::fail('unidade industrial');
            $claim=$this->store->db->prepare('UPDATE enrollment_invitations SET status=? WHERE id=? AND status=? AND expires_at>?');$claim->execute(['used',$invite['id'],'open',date('Y-m-d H:i:s')]);
            if($claim->rowCount()!==1) throw new HttpException(409,'INVITATION_USED','Convite já utilizado.');
            $role=$invite['kind']==='worker'?'worker':'dependent';
            $user=$this->store->insert('users',['company_id'=>$invite['company_id'],'name'=>$name,'email'=>$email,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$role]);
            $patient=$this->store->insert('patients',['company_id'=>$invite['company_id'],'user_id'=>$user,'name'=>$name]);
            $this->store->insert('beneficiary_links',['patient_id'=>$patient,'company_id'=>$invite['company_id'],'contract_id'=>$invite['contract_id'],'industry_unit_id'=>$invite['industry_unit_id'],'kind'=>$invite['kind'],'sponsor_patient_id'=>$invite['sponsor_patient_id'],'status'=>'pending','created_at'=>date('Y-m-d H:i:s')]);
            $this->store->insert('beneficiary_profiles',['patient_id'=>$patient,'contact_encrypted'=>Crypto::encrypt($contact),'postal_code'=>$postal,'preferred_period'=>$period,'birth_date_encrypted'=>Crypto::encrypt($birth),'notice_version'=>'v1','accepted_at'=>date('Y-m-d H:i:s')]);
            Audit::record($this->store,['id'=>$user,'company_id'=>$invite['company_id']],'remote_enrollment','patient',$patient);
            return ['patient_id'=>$patient,'status'=>'pending','message'=>'Cadastro recebido. A gestão validará o contato e o vínculo antes de liberar agendamentos.'];
        });
    }
    public function validate(IndustryService $industry,int $id,array $d): array
    {
        $industry->manage(); $row=$this->store->one('SELECT * FROM beneficiary_links WHERE id=? AND company_id=?',[$id,$industry->company]);
        if(!$row) throw new HttpException(404,'NOT_FOUND','Vínculo não encontrado.');
        $status=Input::choice($d,'status',['active','suspended']);
        if($status==='active') {
            if(($d['contact_verified']??false)!==true || ($d['affiliation_verified']??false)!==true) Input::fail('verificação de contato e vínculo');
            $contract=$industry->activeContract((int)$row['contract_id']);
            if($row['kind']==='dependent' && !(int)$contract['allow_dependents']) Input::fail('cobertura de dependentes');
            if($row['kind']==='dependent' && !$this->store->one('SELECT id FROM beneficiary_links WHERE patient_id=? AND company_id=? AND contract_id=? AND status=? AND kind=?',[$row['sponsor_patient_id'],$industry->company,$row['contract_id'],'active','worker'])) Input::fail('titular ativo');
        }
        return $this->store->transaction(function()use($industry,$id,$status){$this->store->execute('UPDATE beneficiary_links SET status=? WHERE id=?',[$status,$id]);$industry->audit('beneficiary_'.$status,'beneficiary_link',$id);return ['id'=>$id,'status'=>$status];});
    }
    public function representedDependent(IndustryService $industry,array $d): array
    {
        $industry->manage(); $sponsor=Input::id($d,'sponsor_patient_id');
        $link=$this->store->one('SELECT b.*,p.user_id FROM beneficiary_links b JOIN patients p ON p.id=b.patient_id WHERE b.patient_id=? AND b.company_id=? AND b.kind=? AND b.status=?',[$sponsor,$industry->company,'worker','active']);
        if(!$link || !$link['user_id']) Input::fail('titular');
        $c=$industry->activeContract((int)$link['contract_id']); if(!(int)$c['allow_dependents']) Input::fail('cobertura');
        $name=Input::text($d,'name',150); $birth=Input::date($d,'birth_date');
        if($birth>date('Y-m-d') || (new \DateTimeImmutable($birth))->diff(new \DateTimeImmutable('today'))->y>=18) Input::fail('menor de idade; adultos usam convite próprio');
        $reference=Crypto::encrypt(Input::text($d,'authorization_reference',500));
        if(($d['representation_verified']??false)!==true) Input::fail('representação verificada');
        return $this->store->transaction(function()use($industry,$link,$name,$birth,$reference){
            $patient=$this->store->insert('patients',['company_id'=>$industry->company,'name'=>$name]);
            $id=$this->store->insert('beneficiary_links',['patient_id'=>$patient,'company_id'=>$industry->company,'contract_id'=>$link['contract_id'],'industry_unit_id'=>$link['industry_unit_id'],'kind'=>'dependent','sponsor_patient_id'=>$link['patient_id'],'status'=>'active','created_at'=>date('Y-m-d H:i:s')]);
            $this->store->insert('beneficiary_profiles',['patient_id'=>$patient,'contact_encrypted'=>Crypto::encrypt('Contato pelo responsável verificado'),'postal_code'=>'00000-000','preferred_period'=>'flexible','birth_date_encrypted'=>Crypto::encrypt($birth),'notice_version'=>'v1-represented','accepted_at'=>date('Y-m-d H:i:s')]);
            $this->store->insert('patient_representatives',['patient_id'=>$patient,'user_id'=>$link['user_id'],'authorization_reference'=>$reference,'verified_by'=>$industry->user['id']]);
            $industry->audit('representation_verified','patient',$patient); return ['id'=>$id,'patient_id'=>$patient];
        });
    }
}
