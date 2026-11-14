<?php
declare(strict_types=1);
$directory=dirname(__DIR__).'/database';
$full=<<<'SQL'
-- VIVA+ ELO - industria, trabalhadores e dependentes - MySQL 8+
-- Instalar SOMENTE em banco vazio. Nao apaga tabelas ou dados.
-- Para uma base V3 existente, usar composer migrate ou upgrade_industry.sql.
CREATE DATABASE IF NOT EXISTS viva_elo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE viva_elo;
SET NAMES utf8mb4;
SET default_storage_engine = InnoDB;
CREATE TABLE schema_migrations (name VARCHAR(190) PRIMARY KEY, applied_at DATETIME NOT NULL);
SQL;
$upgrade=<<<'SQL'
-- VIVA+ ELO - atualizacao de uma base V3 com migracoes 001 a 004 aplicadas.
-- Executar UMA unica vez. Fazer backup antes da importacao.
-- Preserva registros existentes. Para migracao incremental, preferir composer migrate.
USE viva_elo;
SET NAMES utf8mb4;
SET default_storage_engine = InnoDB;
SQL;
foreach(glob($directory.'/migrations/*.sql') as $file) {
    $name=basename($file);$escaped=str_replace("'","''",$name);
    $block="\n\n-- {$name}\n".trim(file_get_contents($file))."\nINSERT INTO schema_migrations (name, applied_at) VALUES ('{$escaped}', CURRENT_TIMESTAMP);";
    $full.=$block;if((int)substr($name,0,3)>=5) $upgrade.=$block;
}
foreach(['viva_elo.sql'=>$full,'upgrade_industry.sql'=>$upgrade] as $name=>$sql) {
    if(file_put_contents($directory.'/'.$name,$sql."\n")===false) throw new RuntimeException('Schema output failed');
}
echo "Scripts SQL atualizados a partir das migrações.\n";
