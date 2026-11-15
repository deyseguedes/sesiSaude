<?php
declare(strict_types=1);

namespace App\Services;

final class PatientSummaryService
{
    public function __construct(private readonly CareService $care) {}

    public function summarize(int $patientId): array
    {
        // Authorize before querying or decrypting any clinical record.
        $patient = $this->care->access->patient($patientId);
        $store = $this->care->store;
        $scope = [$patientId, $this->care->user['company_id']];
        $now = date('Y-m-d H:i:s');
        $plans = $store->all(
            'SELECT p.*,u.name AS professional_name,t.name AS team_name FROM care_plans p JOIN users u ON u.id=p.professional_id JOIN teams t ON t.id=p.team_id WHERE p.patient_id=? AND p.company_id=? ORDER BY p.created_at DESC,p.id DESC',
            $scope
        );
        $active = array_values(array_filter($plans, fn(array $p): bool => $p['status'] === 'active'));
        $ongoing = array_values(array_filter($plans, fn(array $p): bool => in_array($p['status'], ['active', 'reviewed'], true)));
        usort($active, fn(array $a, array $b): int => strcmp($a['reassessment_date'], $b['reassessment_date']) ?: (int)$b['id'] <=> (int)$a['id']);
        usort($ongoing, fn(array $a, array $b): int => strcmp($a['reassessment_date'], $b['reassessment_date']) ?: (int)$b['id'] <=> (int)$a['id']);
        $focus = $active[0] ?? $ongoing[0] ?? $plans[0] ?? null;
        $focusPlan = $focus ? [
            'id' => (int)$focus['id'], 'status' => $focus['status'],
            'professional_name' => $focus['professional_name'], 'team_name' => $focus['team_name'],
            'created_at' => $focus['created_at'], 'start_date' => $focus['start_date'],
            'reassessment_date' => $focus['reassessment_date'], 'objectives' => $focus['objectives'],
            'interventions' => $focus['interventions'], 'clinical_notes' => Crypto::decrypt($focus['clinical_notes']),
        ] : null;

        $goalScope = ' FROM care_plan_goals g JOIN care_plans p ON p.id=g.plan_id WHERE p.patient_id=? AND p.company_id=? AND p.status IN (?,?) AND g.status=?';
        $goalParameters = [...$scope, 'active', 'reviewed', 'active'];
        $goalCount = (int)$store->one('SELECT COUNT(*) n' . $goalScope, $goalParameters)['n'];
        $goals = $store->all('SELECT g.*' . $goalScope . ' ORDER BY g.deadline,g.id LIMIT 5', $goalParameters);
        $engine = new IndicatorService($this->care);
        foreach ($goals as &$goal) {
            $goal['responsible_name'] = $store->one('SELECT name FROM users WHERE id=?', [$goal['responsible_id']])['name'] ?? null;
            $link = $store->one(
                'SELECT i.id,i.name,i.type,l.measurement_source FROM care_goal_indicators l JOIN clinical_indicators i ON i.id=l.indicator_id WHERE l.goal_id=? AND i.patient_id=? AND i.company_id=?',
                [$goal['id'], ...$scope]
            );
            $goal['indicator'] = $link;
            $goal['progress'] = $link ? $engine->progress($goal) : ['state' => 'insufficient_data', 'samples' => 0, 'latest' => null];
        }
        unset($goal);

        $indicators = $store->all('SELECT id,name,type,unit FROM clinical_indicators WHERE patient_id=? AND company_id=?', $scope);
        foreach ($indicators as &$indicator) {
            $indicator['professional'] = $this->latestMeasurement((int)$indicator['id'], 'professional');
            $indicator['self_reported'] = $this->latestMeasurement((int)$indicator['id'], 'self_reported');
            $indicator['last_collected_at'] = max($indicator['professional']['collected_at'] ?? '', $indicator['self_reported']['collected_at'] ?? '');
        }
        unset($indicator);
        usort($indicators, fn(array $a, array $b): int => strcmp($b['last_collected_at'], $a['last_collected_at']) ?: (int)$b['id'] <=> (int)$a['id']);

        $latestEvolution = $store->one(
            'SELECT e.id,e.plan_id,e.kind,e.notes,e.created_at,u.name AS author_name,u.role AS author_role FROM clinical_evolutions e JOIN care_plans p ON p.id=e.plan_id JOIN users u ON u.id=e.author_id WHERE p.patient_id=? AND p.company_id=? ORDER BY e.created_at DESC,e.id DESC LIMIT 1',
            $scope
        );
        if ($latestEvolution) $latestEvolution['notes'] = Crypto::decrypt($latestEvolution['notes']);
        $latestReview = $store->one(
            'SELECT r.id,r.plan_id,r.notes,r.outcome,r.created_at,u.name AS author_name FROM care_plan_reviews r JOIN care_plans p ON p.id=r.plan_id JOIN users u ON u.id=r.author_id WHERE p.patient_id=? AND p.company_id=? ORDER BY r.created_at DESC,r.id DESC LIMIT 1',
            $scope
        );
        if ($latestReview) $latestReview['notes'] = Crypto::decrypt($latestReview['notes']);
        $latestAdherence = $store->one(
            'SELECT a.id,a.plan_id,a.action_description,a.completed,a.source,a.occurred_at,u.name AS author_name FROM care_plan_adherence a JOIN care_plans p ON p.id=a.plan_id JOIN users u ON u.id=a.author_id WHERE p.patient_id=? AND p.company_id=? ORDER BY a.occurred_at DESC,a.id DESC LIMIT 1',
            $scope
        );
        $appointmentSql = 'SELECT a.id,a.scheduled_at,a.status,a.modality,u.name AS professional_name FROM appointments a JOIN users u ON u.id=a.professional_id WHERE a.patient_id=? AND a.company_id=?';
        $lastAttended = $store->one($appointmentSql . ' AND a.status=? AND a.scheduled_at<=? ORDER BY a.scheduled_at DESC,a.id DESC LIMIT 1', [...$scope, 'attended', $now]);
        $nextAppointment = $store->one($appointmentSql . ' AND a.status IN (?,?) AND a.scheduled_at>=? ORDER BY a.scheduled_at,a.id LIMIT 1', [...$scope, 'expected', 'confirmed', $now]);
        $unconfirmed = (int)$store->one('SELECT COUNT(*) n FROM appointments WHERE patient_id=? AND company_id=? AND status=?', [...$scope, 'expected'])['n'];
        $referrals = $store->all(
            'SELECT r.id,r.reason,r.status,r.created_at,u.name AS recipient_name FROM referrals r JOIN users u ON u.id=r.to_professional_id WHERE r.patient_id=? AND r.company_id=? ORDER BY r.created_at DESC,r.id DESC LIMIT 3',
            $scope
        );
        foreach ($referrals as &$referral) $referral['reason'] = Crypto::decrypt($referral['reason']);
        unset($referral);

        $reassessment = $ongoing[0] ?? null;
        Audit::record($store, $this->care->user, 'patient_summary_read', 'patient', $patientId);
        return [
            'patient' => ['id' => (int)$patient['id'], 'name' => $patient['name']],
            'generated_at' => $now,
            'clinical_return' => (new ClinicalReturnService($this->care))->view($patientId,false),
            'plans' => ['total' => count($plans), 'active' => count($active), 'focus' => $focusPlan],
            'goals' => ['total' => $goalCount, 'items' => $goals],
            'indicators' => ['total' => count($indicators), 'items' => array_slice($indicators, 0, 6)],
            'last_attended' => $lastAttended, 'next_appointment' => $nextAppointment,
            'reassessment' => $reassessment ? ['plan_id' => (int)$reassessment['id'], 'date' => $reassessment['reassessment_date'], 'overdue' => $reassessment['reassessment_date'] < date('Y-m-d')] : null,
            'unconfirmed_returns' => $unconfirmed,
            'latest_evolution' => $latestEvolution, 'latest_review' => $latestReview,
            'latest_adherence' => $latestAdherence, 'recent_referrals' => $referrals,
        ];
    }

    private function latestMeasurement(int $indicatorId, string $source): ?array
    {
        return $this->care->store->one(
            'SELECT m.id,m.value_num,m.value_category,m.source,m.collected_at,m.recorded_at,m.method,u.name AS professional_name,r.name AS recorded_by_name FROM indicator_measurements m LEFT JOIN users u ON u.id=m.professional_id JOIN users r ON r.id=m.recorded_by WHERE m.indicator_id=? AND m.source=? ORDER BY m.collected_at DESC,m.id DESC LIMIT 1',
            [$indicatorId, $source]
        );
    }
}
