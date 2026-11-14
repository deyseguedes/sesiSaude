UPDATE companies SET reactivation_enabled=0, gamification_version=gamification_version+1 WHERE reactivation_enabled<>0;
