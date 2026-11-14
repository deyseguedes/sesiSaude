ALTER TABLE companies ADD COLUMN reactivation_enabled INTEGER NOT NULL DEFAULT 0;
ALTER TABLE companies ADD COLUMN reactivation_days INTEGER NOT NULL DEFAULT 180;
ALTER TABLE companies ADD COLUMN reactivation_points INTEGER NOT NULL DEFAULT 30;
ALTER TABLE companies ADD COLUMN reactivation_policy_at DATETIME;
CREATE TABLE doctor_reactivation_awards (id INTEGER PRIMARY KEY AUTO_INCREMENT, company_id INTEGER NOT NULL, patient_id INTEGER NOT NULL, professional_id INTEGER NOT NULL, baseline_type VARCHAR(30) NOT NULL, baseline_id INTEGER NOT NULL, return_type VARCHAR(30) NOT NULL, return_id INTEGER NOT NULL, gap_days INTEGER NOT NULL, threshold_days INTEGER NOT NULL, points INTEGER NOT NULL, policy_at DATETIME NOT NULL, earned_at DATETIME NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'active', UNIQUE(company_id,patient_id,baseline_type,baseline_id), FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(patient_id) REFERENCES patients(id), FOREIGN KEY(professional_id) REFERENCES users(id));
CREATE INDEX idx_doctor_reactivation ON doctor_reactivation_awards(company_id,professional_id,status);
