<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\HttpException;
use App\Repositories\Store;
final class Access
{
    public function __construct(private Store $store, public readonly array $user) {}
    public function roles(array $roles): void
    {
        if (!in_array($this->user['role'], $roles, true)) throw new HttpException(403, 'FORBIDDEN', 'Recurso não permitido para este perfil.');
    }
    public function patient(int $id, bool $clinical = true): array
    {
        $this->roles($clinical ? ['doctor', 'nurse'] : ['doctor', 'nurse', 'navigator']);
        $patient = $this->store->one('SELECT p.id,p.name,p.company_id FROM patients p WHERE p.id=? AND p.company_id=? AND EXISTS (SELECT 1 FROM care_team_assignments a JOIN teams t ON t.id=a.team_id JOIN team_members m ON m.team_id=a.team_id WHERE t.company_id=p.company_id AND a.patient_id=p.id AND a.company_id=p.company_id AND a.active=1 AND m.user_id=?)', [$id, $this->user['company_id'], $this->user['id']]);
        if (!$patient) throw new HttpException(404, 'NOT_FOUND', 'Paciente não encontrado.');
        return $patient;
    }
    public function patients(): array
    {
        $this->roles(['doctor', 'nurse', 'navigator']);
        return $this->store->all('SELECT p.id,p.name FROM patients p WHERE p.company_id=? AND EXISTS (SELECT 1 FROM care_team_assignments a JOIN teams t ON t.id=a.team_id JOIN team_members m ON m.team_id=a.team_id WHERE t.company_id=p.company_id AND a.patient_id=p.id AND a.company_id=p.company_id AND a.active=1 AND m.user_id=?) ORDER BY p.name', [$this->user['company_id'], $this->user['id']]);
    }
    public function team(int $patient, int $team, int $responsible): void
    {
        $this->patient($patient);
        $row = $this->store->one('SELECT a.id FROM care_team_assignments a JOIN teams t ON t.id=a.team_id JOIN team_members m ON m.team_id=t.id JOIN users u ON u.id=m.user_id WHERE a.patient_id=? AND a.team_id=? AND a.active=1 AND t.company_id=? AND a.company_id=t.company_id AND u.company_id=t.company_id AND u.active=1 AND u.id=? AND u.role IN (?,?) AND EXISTS (SELECT 1 FROM team_members own WHERE own.team_id=t.id AND own.user_id=?)', [$patient, $team, $this->user['company_id'], $responsible, 'doctor', 'nurse', $this->user['id']]);
        if (!$row) throw new HttpException(422, 'INVALID_TEAM', 'Equipe ou responsável sem vínculo assistencial válido.');
    }
}
