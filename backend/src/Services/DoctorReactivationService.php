<?php
declare(strict_types=1);
namespace App\Services;

// Compatibility endpoint: historical records remain, but individual awards are retired.
final class DoctorReactivationService
{
    public function __construct(private readonly CareService $care) {}
    public function view(): array
    {
        $this->care->access->roles(['doctor']);
        return ['enabled'=>false,'replacement'=>'team_recognition'];
    }
    public function sync(): array { return $this->view(); }
}
