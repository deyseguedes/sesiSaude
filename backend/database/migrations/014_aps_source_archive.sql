CREATE TABLE aps_source_archives (
 id INTEGER PRIMARY KEY AUTO_INCREMENT,
 company_id INTEGER NOT NULL,
 visit_key VARCHAR(64) NOT NULL,
 fingerprint VARCHAR(64) NOT NULL,
 archive LONGTEXT NOT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE (company_id, visit_key, fingerprint)
);
