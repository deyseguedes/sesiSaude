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
