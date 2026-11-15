-- Life+ - industria, trabalhadores e dependentes - MySQL 8+
-- Instalar SOMENTE em banco vazio. Nao apaga tabelas ou dados.
-- Para uma base V3 existente, usar composer migrate ou upgrade_industry.sql.
CREATE DATABASE IF NOT EXISTS viva_elo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE viva_elo;
SET NAMES utf8mb4;
SET default_storage_engine = InnoDB;
CREATE TABLE schema_migrations (name VARCHAR(190) PRIMARY KEY, applied_at DATETIME NOT NULL);

-- 001_identity.sql
CREATE TABLE companies (id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(150) NOT NULL);
CREATE TABLE users (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, name VARCHAR(150) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role VARCHAR(30) NOT NULL, active INTEGER NOT NULL DEFAULT 1, FOREIGN KEY(company_id) REFERENCES companies(id));
CREATE TABLE teams (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, name VARCHAR(150) NOT NULL, FOREIGN KEY(company_id) REFERENCES companies(id));
CREATE TABLE team_members (team_id INTEGER NOT NULL, user_id INTEGER NOT NULL, PRIMARY KEY(team_id,user_id), FOREIGN KEY(team_id) REFERENCES teams(id), FOREIGN KEY(user_id) REFERENCES users(id));
CREATE TABLE patients (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, user_id INTEGER UNIQUE, name VARCHAR(150) NOT NULL, FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(user_id) REFERENCES users(id));
CREATE TABLE care_team_assignments (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, patient_id INTEGER NOT NULL, team_id INTEGER NOT NULL, active INTEGER NOT NULL DEFAULT 1, UNIQUE(patient_id,team_id), FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(patient_id) REFERENCES patients(id), FOREIGN KEY(team_id) REFERENCES teams(id));
CREATE TABLE appointments (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, patient_id INTEGER NOT NULL, professional_id INTEGER NOT NULL, scheduled_at DATETIME NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'expected', FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(patient_id) REFERENCES patients(id), FOREIGN KEY(professional_id) REFERENCES users(id));
CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, user_id INTEGER NOT NULL, event VARCHAR(80) NOT NULL, resource_type VARCHAR(80) NOT NULL, resource_id INTEGER NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(user_id) REFERENCES users(id));
CREATE TABLE login_attempts (id INTEGER PRIMARY KEY AUTO_INCREMENT, attempt_key VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL);
CREATE INDEX idx_login_attempt ON login_attempts(attempt_key,created_at);
CREATE INDEX idx_assignments ON care_team_assignments(company_id,patient_id,active);
INSERT INTO schema_migrations (name, applied_at) VALUES ('001_identity.sql', CURRENT_TIMESTAMP);

-- 002_care.sql
CREATE TABLE care_plans (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, patient_id INTEGER NOT NULL, professional_id INTEGER NOT NULL, team_id INTEGER NOT NULL, created_at DATETIME NOT NULL, start_date DATE NOT NULL, reassessment_date DATE NOT NULL, objectives TEXT NOT NULL, interventions TEXT NOT NULL, status VARCHAR(30) NOT NULL, clinical_notes TEXT NOT NULL, version INTEGER NOT NULL DEFAULT 1, FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(patient_id) REFERENCES patients(id), FOREIGN KEY(professional_id) REFERENCES users(id), FOREIGN KEY(team_id) REFERENCES teams(id));
CREATE TABLE care_plan_goals (id INTEGER PRIMARY KEY AUTO_INCREMENT, plan_id INTEGER NOT NULL, description TEXT NOT NULL, initial_value TEXT, target_value TEXT, target_min DOUBLE, target_max DOUBLE, unit VARCHAR(40) NOT NULL, direction VARCHAR(20) NOT NULL, frequency VARCHAR(100) NOT NULL, deadline DATE NOT NULL, responsible_id INTEGER NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'active', min_samples INTEGER NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, version INTEGER NOT NULL DEFAULT 1, FOREIGN KEY(plan_id) REFERENCES care_plans(id), FOREIGN KEY(responsible_id) REFERENCES users(id));
CREATE TABLE care_plan_reviews (id INTEGER PRIMARY KEY AUTO_INCREMENT, plan_id INTEGER NOT NULL, author_id INTEGER NOT NULL, notes TEXT NOT NULL, outcome VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY(plan_id) REFERENCES care_plans(id), FOREIGN KEY(author_id) REFERENCES users(id));
CREATE TABLE care_plan_events (id INTEGER PRIMARY KEY AUTO_INCREMENT, plan_id INTEGER NOT NULL, author_id INTEGER NOT NULL, event_type VARCHAR(40) NOT NULL, payload TEXT NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY(plan_id) REFERENCES care_plans(id), FOREIGN KEY(author_id) REFERENCES users(id));
CREATE TABLE clinical_evolutions (id INTEGER PRIMARY KEY AUTO_INCREMENT, plan_id INTEGER NOT NULL, author_id INTEGER NOT NULL, kind VARCHAR(30) NOT NULL, notes TEXT NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY(plan_id) REFERENCES care_plans(id), FOREIGN KEY(author_id) REFERENCES users(id));
CREATE TABLE care_plan_adherence (id INTEGER PRIMARY KEY AUTO_INCREMENT, plan_id INTEGER NOT NULL, author_id INTEGER NOT NULL, action_description TEXT NOT NULL, completed INTEGER NOT NULL, source VARCHAR(30) NOT NULL, occurred_at DATETIME NOT NULL, FOREIGN KEY(plan_id) REFERENCES care_plans(id), FOREIGN KEY(author_id) REFERENCES users(id));
CREATE INDEX idx_plans_patient ON care_plans(company_id,patient_id,status);
ALTER TABLE care_plan_goals ADD COLUMN evidence_after_id INTEGER NOT NULL DEFAULT 0;
INSERT INTO schema_migrations (name, applied_at) VALUES ('002_care.sql', CURRENT_TIMESTAMP);

-- 003_indicators.sql
CREATE TABLE clinical_indicators (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, patient_id INTEGER NOT NULL, name VARCHAR(150) NOT NULL, type VARCHAR(30) NOT NULL, unit VARCHAR(40) NOT NULL, reference_value TEXT NOT NULL, measurement_method VARCHAR(200) NOT NULL, frequency VARCHAR(100) NOT NULL, categories TEXT NOT NULL, created_by INTEGER NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(patient_id) REFERENCES patients(id), FOREIGN KEY(created_by) REFERENCES users(id));
CREATE TABLE care_goal_indicators (goal_id INTEGER PRIMARY KEY, indicator_id INTEGER NOT NULL, measurement_source VARCHAR(30) NOT NULL, FOREIGN KEY(goal_id) REFERENCES care_plan_goals(id), FOREIGN KEY(indicator_id) REFERENCES clinical_indicators(id));
CREATE TABLE indicator_measurements (id INTEGER PRIMARY KEY AUTO_INCREMENT, indicator_id INTEGER NOT NULL, value_num DOUBLE, value_category VARCHAR(150), source VARCHAR(30) NOT NULL, professional_id INTEGER, recorded_by INTEGER NOT NULL, collected_at DATETIME NOT NULL, recorded_at DATETIME NOT NULL, method VARCHAR(200) NOT NULL, FOREIGN KEY(indicator_id) REFERENCES clinical_indicators(id), FOREIGN KEY(professional_id) REFERENCES users(id), FOREIGN KEY(recorded_by) REFERENCES users(id));
CREATE INDEX idx_measurements ON indicator_measurements(indicator_id,source,collected_at);
CREATE UNIQUE INDEX idx_unique_collection ON indicator_measurements(indicator_id,source,collected_at);
INSERT INTO schema_migrations (name, applied_at) VALUES ('003_indicators.sql', CURRENT_TIMESTAMP);

-- 004_continuity.sql
CREATE TABLE continuity_cases (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, patient_id INTEGER NOT NULL, appointment_id INTEGER NOT NULL UNIQUE, status VARCHAR(30) NOT NULL DEFAULT 'pending', barrier VARCHAR(40), barrier_resolved INTEGER NOT NULL DEFAULT 0, assigned_navigator_id INTEGER, rescheduled_appointment_id INTEGER, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, version INTEGER NOT NULL DEFAULT 1, FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(patient_id) REFERENCES patients(id), FOREIGN KEY(appointment_id) REFERENCES appointments(id), FOREIGN KEY(assigned_navigator_id) REFERENCES users(id), FOREIGN KEY(rescheduled_appointment_id) REFERENCES appointments(id));
CREATE TABLE continuity_actions (id INTEGER PRIMARY KEY AUTO_INCREMENT, case_id INTEGER NOT NULL, author_id INTEGER NOT NULL, action_type VARCHAR(40) NOT NULL, administrative_note TEXT NOT NULL, payload TEXT NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY(case_id) REFERENCES continuity_cases(id), FOREIGN KEY(author_id) REFERENCES users(id));
CREATE TABLE referrals (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, patient_id INTEGER NOT NULL, from_professional_id INTEGER NOT NULL, to_professional_id INTEGER NOT NULL, reason TEXT NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'requested', created_at DATETIME NOT NULL, FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(patient_id) REFERENCES patients(id), FOREIGN KEY(from_professional_id) REFERENCES users(id), FOREIGN KEY(to_professional_id) REFERENCES users(id));
CREATE TABLE team_notifications (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, patient_id INTEGER NOT NULL, case_id INTEGER NOT NULL, event_type VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(patient_id) REFERENCES patients(id), FOREIGN KEY(case_id) REFERENCES continuity_cases(id));
CREATE INDEX idx_cases_company ON continuity_cases(company_id,patient_id,status);
INSERT INTO schema_migrations (name, applied_at) VALUES ('004_continuity.sql', CURRENT_TIMESTAMP);

-- 005_industry_access.sql
ALTER TABLE users ADD COLUMN management_level VARCHAR(30) NOT NULL DEFAULT 'none';
UPDATE users SET management_level='platform' WHERE role='admin';
UPDATE users SET management_level='company' WHERE role='manager';
CREATE TABLE management_company_access (user_id INTEGER NOT NULL, company_id INTEGER NOT NULL, PRIMARY KEY(user_id,company_id), FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(company_id) REFERENCES companies(id));
INSERT INTO management_company_access (user_id,company_id) SELECT id,company_id FROM users WHERE role='admin';
CREATE TABLE industry_units (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, name VARCHAR(150) NOT NULL, address VARCHAR(300) NOT NULL, postal_code VARCHAR(9) NOT NULL, latitude DOUBLE, longitude DOUBLE, active INTEGER NOT NULL DEFAULT 1, FOREIGN KEY(company_id) REFERENCES companies(id));
CREATE TABLE service_contracts (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, name VARCHAR(150) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'active', allow_in_person INTEGER NOT NULL, allow_telehealth INTEGER NOT NULL, allow_dependents INTEGER NOT NULL, FOREIGN KEY(company_id) REFERENCES companies(id));
CREATE TABLE beneficiary_links (id INTEGER PRIMARY KEY AUTO_INCREMENT, patient_id INTEGER NOT NULL UNIQUE, company_id INTEGER NOT NULL, contract_id INTEGER NOT NULL, industry_unit_id INTEGER NOT NULL, kind VARCHAR(20) NOT NULL, sponsor_patient_id INTEGER, status VARCHAR(20) NOT NULL DEFAULT 'pending', created_at DATETIME NOT NULL, FOREIGN KEY(patient_id) REFERENCES patients(id), FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(contract_id) REFERENCES service_contracts(id), FOREIGN KEY(industry_unit_id) REFERENCES industry_units(id), FOREIGN KEY(sponsor_patient_id) REFERENCES patients(id));
CREATE TABLE beneficiary_profiles (patient_id INTEGER PRIMARY KEY, contact_encrypted TEXT NOT NULL, postal_code VARCHAR(9) NOT NULL, preferred_period VARCHAR(30) NOT NULL, birth_date_encrypted TEXT NOT NULL, notice_version VARCHAR(30) NOT NULL, accepted_at DATETIME NOT NULL, FOREIGN KEY(patient_id) REFERENCES patients(id));
CREATE TABLE patient_representatives (patient_id INTEGER NOT NULL, user_id INTEGER NOT NULL, authorization_reference TEXT NOT NULL, verified_by INTEGER NOT NULL, active INTEGER NOT NULL DEFAULT 1, PRIMARY KEY(patient_id,user_id), FOREIGN KEY(patient_id) REFERENCES patients(id), FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(verified_by) REFERENCES users(id));
CREATE TABLE enrollment_invitations (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, contract_id INTEGER NOT NULL, industry_unit_id INTEGER NOT NULL, sponsor_patient_id INTEGER, kind VARCHAR(20) NOT NULL, email VARCHAR(190) NOT NULL, token_hash VARCHAR(64) NOT NULL UNIQUE, status VARCHAR(20) NOT NULL DEFAULT 'open', expires_at DATETIME NOT NULL, created_by INTEGER NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(contract_id) REFERENCES service_contracts(id), FOREIGN KEY(industry_unit_id) REFERENCES industry_units(id), FOREIGN KEY(sponsor_patient_id) REFERENCES patients(id), FOREIGN KEY(created_by) REFERENCES users(id));
CREATE TABLE service_units (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, name VARCHAR(150) NOT NULL, kind VARCHAR(20) NOT NULL, address VARCHAR(300) NOT NULL, postal_code VARCHAR(9) NOT NULL, latitude DOUBLE, longitude DOUBLE, active INTEGER NOT NULL DEFAULT 1, FOREIGN KEY(company_id) REFERENCES companies(id));
CREATE TABLE appointment_slots (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, professional_id INTEGER NOT NULL, team_id INTEGER NOT NULL, service_unit_id INTEGER NOT NULL, modality VARCHAR(20) NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'available', appointment_id INTEGER UNIQUE, version INTEGER NOT NULL DEFAULT 1, FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(professional_id) REFERENCES users(id), FOREIGN KEY(team_id) REFERENCES teams(id), FOREIGN KEY(service_unit_id) REFERENCES service_units(id), FOREIGN KEY(appointment_id) REFERENCES appointments(id));
ALTER TABLE appointments ADD COLUMN modality VARCHAR(20) NOT NULL DEFAULT 'in_person';
ALTER TABLE appointments ADD COLUMN service_unit_id INTEGER;
CREATE TABLE appointment_access (appointment_id INTEGER PRIMARY KEY, link_encrypted TEXT NOT NULL, updated_by INTEGER NOT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY(appointment_id) REFERENCES appointments(id), FOREIGN KEY(updated_by) REFERENCES users(id));
CREATE INDEX idx_slots_search ON appointment_slots(company_id,status,starts_at);
CREATE INDEX idx_beneficiary_scope ON beneficiary_links(company_id,status,contract_id);
ALTER TABLE service_contracts ADD COLUMN version INTEGER NOT NULL DEFAULT 1;
INSERT INTO schema_migrations (name, applied_at) VALUES ('005_industry_access.sql', CURRENT_TIMESTAMP);

-- 006_education_campaigns.sql
CREATE TABLE education_contents (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, title VARCHAR(180) NOT NULL, summary TEXT NOT NULL, transcript TEXT NOT NULL, category VARCHAR(30) NOT NULL, video_url TEXT NOT NULL, duration_seconds INTEGER NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'draft', created_by INTEGER NOT NULL, reviewed_by INTEGER, review_note TEXT, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, version INTEGER NOT NULL DEFAULT 1, FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(reviewed_by) REFERENCES users(id));
CREATE TABLE content_progress (content_id INTEGER NOT NULL, user_id INTEGER NOT NULL, content_version INTEGER NOT NULL, completed_at DATETIME NOT NULL, PRIMARY KEY(content_id,user_id,content_version), FOREIGN KEY(content_id) REFERENCES education_contents(id), FOREIGN KEY(user_id) REFERENCES users(id));
CREATE TABLE campaigns (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, title VARCHAR(180) NOT NULL, objective TEXT NOT NULL, cycle_label VARCHAR(100) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, industry_unit_id INTEGER, audience VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'draft', created_by INTEGER NOT NULL, created_at DATETIME NOT NULL, version INTEGER NOT NULL DEFAULT 1, FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(industry_unit_id) REFERENCES industry_units(id), FOREIGN KEY(created_by) REFERENCES users(id));
CREATE TABLE campaign_contents (campaign_id INTEGER NOT NULL, content_id INTEGER NOT NULL, PRIMARY KEY(campaign_id,content_id), FOREIGN KEY(campaign_id) REFERENCES campaigns(id), FOREIGN KEY(content_id) REFERENCES education_contents(id));
CREATE TABLE campaign_participants (id INTEGER PRIMARY KEY AUTO_INCREMENT, campaign_id INTEGER NOT NULL, patient_id INTEGER NOT NULL, stage VARCHAR(30) NOT NULL DEFAULT 'invited', updated_at DATETIME NOT NULL, version INTEGER NOT NULL DEFAULT 1, UNIQUE(campaign_id,patient_id), FOREIGN KEY(campaign_id) REFERENCES campaigns(id), FOREIGN KEY(patient_id) REFERENCES patients(id));
CREATE TABLE campaign_tasks (id INTEGER PRIMARY KEY AUTO_INCREMENT, participant_id INTEGER NOT NULL, assigned_to INTEGER NOT NULL, created_by INTEGER NOT NULL, kind VARCHAR(20) NOT NULL, due_date DATE NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'open', administrative_note TEXT NOT NULL, version INTEGER NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, FOREIGN KEY(participant_id) REFERENCES campaign_participants(id), FOREIGN KEY(assigned_to) REFERENCES users(id), FOREIGN KEY(created_by) REFERENCES users(id));
CREATE TABLE campaign_events (id INTEGER PRIMARY KEY AUTO_INCREMENT, participant_id INTEGER NOT NULL, author_id INTEGER NOT NULL, event_type VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY(participant_id) REFERENCES campaign_participants(id), FOREIGN KEY(author_id) REFERENCES users(id));
INSERT INTO schema_migrations (name, applied_at) VALUES ('006_education_campaigns.sql', CURRENT_TIMESTAMP);

-- 007_nursing_worklist.sql
CREATE TABLE care_pending_items (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, patient_id INTEGER NOT NULL, source_type VARCHAR(30) NOT NULL, source_id INTEGER, source_revision VARCHAR(80) NOT NULL DEFAULT '', kind VARCHAR(40) NOT NULL, title VARCHAR(180) NOT NULL, reason VARCHAR(500) NOT NULL, next_action VARCHAR(250) NOT NULL, team_id INTEGER, service_unit_id INTEGER, professional_id INTEGER, assigned_to INTEGER, due_at DATETIME NOT NULL, help_requested INTEGER NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'open', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, version INTEGER NOT NULL DEFAULT 1, UNIQUE(company_id,source_type,source_id,kind), FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(patient_id) REFERENCES patients(id), FOREIGN KEY(team_id) REFERENCES teams(id), FOREIGN KEY(service_unit_id) REFERENCES service_units(id), FOREIGN KEY(professional_id) REFERENCES users(id), FOREIGN KEY(assigned_to) REFERENCES users(id));
CREATE TABLE care_pending_events (id INTEGER PRIMARY KEY AUTO_INCREMENT, item_id INTEGER NOT NULL, author_id INTEGER NOT NULL, event_type VARCHAR(40) NOT NULL, note TEXT NOT NULL, payload TEXT NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY(item_id) REFERENCES care_pending_items(id), FOREIGN KEY(author_id) REFERENCES users(id));
CREATE INDEX idx_pending_queue ON care_pending_items(company_id,status,due_at,patient_id);
CREATE INDEX idx_pending_patient ON care_pending_items(patient_id,company_id);
CREATE INDEX idx_pending_events ON care_pending_events(item_id,created_at,id);
INSERT INTO schema_migrations (name, applied_at) VALUES ('007_nursing_worklist.sql', CURRENT_TIMESTAMP);

-- 008_worker_journey.sql
CREATE TABLE worker_journey_awards (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, patient_id INTEGER NOT NULL, milestone VARCHAR(30) NOT NULL, points INTEGER NOT NULL, source_type VARCHAR(30) NOT NULL, source_id INTEGER NOT NULL, evidence_at DATETIME NOT NULL, earned_at DATETIME NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'active', version INTEGER NOT NULL DEFAULT 1, UNIQUE(company_id,patient_id,milestone), FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(patient_id) REFERENCES patients(id));
CREATE INDEX idx_worker_journey ON worker_journey_awards(company_id,patient_id,status);
INSERT INTO schema_migrations (name, applied_at) VALUES ('008_worker_journey.sql', CURRENT_TIMESTAMP);

-- 009_company_gamification.sql
ALTER TABLE companies ADD COLUMN gamification_enabled INTEGER NOT NULL DEFAULT 0;
ALTER TABLE companies ADD COLUMN gamification_version INTEGER NOT NULL DEFAULT 1;
INSERT INTO schema_migrations (name, applied_at) VALUES ('009_company_gamification.sql', CURRENT_TIMESTAMP);

-- 010_doctor_reactivation.sql
ALTER TABLE companies ADD COLUMN reactivation_enabled INTEGER NOT NULL DEFAULT 0;
ALTER TABLE companies ADD COLUMN reactivation_days INTEGER NOT NULL DEFAULT 180;
ALTER TABLE companies ADD COLUMN reactivation_points INTEGER NOT NULL DEFAULT 30;
ALTER TABLE companies ADD COLUMN reactivation_policy_at DATETIME;
CREATE TABLE doctor_reactivation_awards (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, patient_id INTEGER NOT NULL, professional_id INTEGER NOT NULL, baseline_type VARCHAR(30) NOT NULL, baseline_id INTEGER NOT NULL, return_type VARCHAR(30) NOT NULL, return_id INTEGER NOT NULL, gap_days INTEGER NOT NULL, threshold_days INTEGER NOT NULL, points INTEGER NOT NULL, policy_at DATETIME NOT NULL, earned_at DATETIME NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'active', UNIQUE(company_id,patient_id,baseline_type,baseline_id), FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(patient_id) REFERENCES patients(id), FOREIGN KEY(professional_id) REFERENCES users(id));
CREATE INDEX idx_doctor_reactivation ON doctor_reactivation_awards(company_id,professional_id,status);
INSERT INTO schema_migrations (name, applied_at) VALUES ('010_doctor_reactivation.sql', CURRENT_TIMESTAMP);

-- 011_collective_recognition.sql
UPDATE companies SET reactivation_enabled=0, gamification_version=gamification_version+1 WHERE reactivation_enabled<>0;
INSERT INTO schema_migrations (name, applied_at) VALUES ('011_collective_recognition.sql', CURRENT_TIMESTAMP);

-- 012_clinical_returns.sql
CREATE TABLE clinical_return_plans (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, patient_id INTEGER NOT NULL, team_id INTEGER NOT NULL, doctor_id INTEGER NOT NULL, risk_cipher TEXT NOT NULL, assessment_date DATE NOT NULL, due_date DATE NOT NULL, version INTEGER NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE(company_id,patient_id), FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(patient_id) REFERENCES patients(id), FOREIGN KEY(team_id) REFERENCES teams(id), FOREIGN KEY(doctor_id) REFERENCES users(id));
CREATE INDEX idx_clinical_returns_due ON clinical_return_plans(company_id,due_date);
CREATE TABLE clinical_return_events (id INTEGER PRIMARY KEY AUTO_INCREMENT, return_plan_id INTEGER NOT NULL, author_id INTEGER NOT NULL, version INTEGER NOT NULL, event_type VARCHAR(40) NOT NULL, payload TEXT NOT NULL, created_at DATETIME NOT NULL, UNIQUE(return_plan_id,version), FOREIGN KEY(return_plan_id) REFERENCES clinical_return_plans(id), FOREIGN KEY(author_id) REFERENCES users(id));
INSERT INTO schema_migrations (name, applied_at) VALUES ('012_clinical_returns.sql', CURRENT_TIMESTAMP);

-- 013_hackathon.sql
CREATE TABLE aps_people (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, patient_id INTEGER, visit_key VARCHAR(64) NOT NULL DEFAULT 'template', registration VARCHAR(80) NOT NULL, cpf_hash VARCHAR(64), payload TEXT NOT NULL, test_data INTEGER NOT NULL DEFAULT 0, updated_at DATETIME NOT NULL, UNIQUE(company_id,visit_key,registration), UNIQUE(company_id,visit_key,cpf_hash));
CREATE TABLE aps_imports (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, visit_key VARCHAR(64) NOT NULL, author_id INTEGER NOT NULL, filename VARCHAR(190) NOT NULL, report TEXT NOT NULL, created_at DATETIME NOT NULL);
CREATE TABLE outreach_events (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, visit_key VARCHAR(64) NOT NULL, patient_id INTEGER NOT NULL, campaign_id INTEGER, variant VARCHAR(10), event_type VARCHAR(40) NOT NULL, payload TEXT NOT NULL, created_at DATETIME NOT NULL);
CREATE INDEX idx_outreach_patient ON outreach_events(company_id,visit_key,patient_id,event_type,created_at);
CREATE TABLE campaign_experiments (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, visit_key VARCHAR(64) NOT NULL DEFAULT 'template', parent_id INTEGER, version INTEGER NOT NULL DEFAULT 1, name VARCHAR(190) NOT NULL, kind VARCHAR(40) NOT NULL, status VARCHAR(30) NOT NULL, payload TEXT NOT NULL, created_at DATETIME NOT NULL);
CREATE TABLE campaign_decisions (id INTEGER PRIMARY KEY AUTO_INCREMENT, campaign_id INTEGER NOT NULL, visit_key VARCHAR(64) NOT NULL, author_id INTEGER NOT NULL, decision VARCHAR(40) NOT NULL, justification TEXT NOT NULL, created_at DATETIME NOT NULL);
INSERT INTO schema_migrations (name, applied_at) VALUES ('013_hackathon.sql', CURRENT_TIMESTAMP);

-- 014_aps_source_archive.sql
CREATE TABLE aps_source_archives (
 id INTEGER PRIMARY KEY AUTO_INCREMENT,
 company_id INTEGER NOT NULL,
 visit_key VARCHAR(64) NOT NULL,
 fingerprint VARCHAR(64) NOT NULL,
 archive LONGTEXT NOT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE (company_id, visit_key, fingerprint)
);
INSERT INTO schema_migrations (name, applied_at) VALUES ('014_aps_source_archive.sql', CURRENT_TIMESTAMP);

-- 015_aps_import_reviews.sql
ALTER TABLE aps_imports MODIFY COLUMN report LONGTEXT NOT NULL;
CREATE TABLE aps_import_reviews (
 id INTEGER PRIMARY KEY AUTO_INCREMENT,
 archive_id INTEGER NOT NULL,
 company_id INTEGER NOT NULL,
 visit_key VARCHAR(64) NOT NULL,
 source_line INTEGER NOT NULL,
 code VARCHAR(64) NOT NULL,
 field VARCHAR(64) NOT NULL,
 severity VARCHAR(16) NOT NULL,
 message TEXT NOT NULL,
 status VARCHAR(16) NOT NULL,
 note TEXT NULL,
 reviewer_id INTEGER NULL,
 reviewed_at DATETIME NULL,
 version INTEGER NOT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE (archive_id, source_line, code, field),
 FOREIGN KEY (archive_id) REFERENCES aps_source_archives(id)
);
CREATE INDEX aps_reviews_scope ON aps_import_reviews (company_id,visit_key,status,code);
INSERT INTO schema_migrations (name, applied_at) VALUES ('015_aps_import_reviews.sql', CURRENT_TIMESTAMP);
