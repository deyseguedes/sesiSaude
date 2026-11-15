# Migrações

As migrações 001–004 criam a base clínica V3; 005–006 acrescentam gestão industrial,
elegibilidade, agendas, educação e campanhas. Execute `composer migrate`
em uma única instância; `schema_migrations` evita reaplicação. O diretório inicial
estava vazio. Para integrar qualquer base legada, primeiro mapear trabalhadores,
profissionais, equipes e consultas; não executar estas migrações sem esse mapeamento.

`../viva_elo.sql`: instalação completa em banco vazio.
007 acrescenta a fila de enfermagem e seu histórico auditável, preservando as
tabelas e registros anteriores.

008 acrescenta as conquistas individuais da jornada do trabalhador.

009 acrescenta a decisão de adoção da gamificação por empresa, desativada por padrão.

`../upgrade_industry.sql`: 005–009 para V3 existente; importar uma vez.
Ambos podem ser regenerados com `composer schema:build` e registram as migrações
aplicadas, para não duplicá-las na próxima execução do migrador.

014 acrescenta o arquivo cifrado das origens APS, com identificação única por
empresa/visita/conteúdo. Não importa planilhas nem altera registros existentes.
