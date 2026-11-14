# VIVA+ ELO V3 — fases B a F

Este documento registra a base clínica. A expansão atual de indústrias, contratos,
trabalhadores, dependentes, agendas e campanhas está em
[Gestão industrial e famílias](industry-and-family.md), incluindo os novos níveis
de gestão e recursos adicionados após as fases B–F.

As fases foram autorizadas em conjunto e executadas em sequência. A base recebida
na Fase A não continha entidades, consultas, importadores ou módulos legados. As
tabelas de identidade e consultas foram criadas uma única vez; não houve migração
de dados anteriores nem implementação presumida de importadores ausentes.

## Diretriz recebida em 14/11/2026

Consulte [Painéis da solução](paineis-solucao-2026-11-14.md) para a evolução
SOC/WhatsApp, canal do trabalhador, lista autorizada do líder, indicadores e
arquitetura de segurança. Esse documento registra o alvo e a análise de lacunas;
a infraestrutura implementada continua sendo a descrita abaixo até migração
validada. A [regra de prêmio coletivo](premio-equipe-saude.md) é uma proposta em discussão.

## Entrega por fase

| Fase | Arquivos e alterações centrais | Impacto |
| --- | --- | --- |
| B | `001_identity.sql`, `AuthController`, `Session`, `Access`, `Store`, `Audit`, login/store frontend, roteador | Seis perfis, sessão, CSRF e escopo assistencial por empresa |
| C | `002_care.sql`, `CareService`, `ClinicalController`, `Input`, `Crypto` | Planos, metas, histórico, revisões, evoluções e adesão |
| D | `003_indicators.sql`, `IndicatorService` | Indicadores configuráveis, medições por origem e avaliação de metas |
| E | `004_continuity.sql`, `ContinuityService`, sincronizador | Pendências administrativas, barreiras, reagendamento, avisos APS e encaminhamentos restritos |
| F | `DashboardService`, views Vue, `FormPanel`, `TrendChart`, testes e documentação | Dashboards, formulários, filtros e gráficos Chart.js |

Os planos de cada fase foram apresentados antes dos respectivos arquivos serem
criados. As telas integradas e os gráficos foram reunidos na Fase F após os serviços
e controles de acesso estarem disponíveis.

## Camadas e dados

Entrada HTTP → roteador → controller autenticado → service → repository PDO → MySQL.
O frontend usa somente `/api/v1`. Todos os dados enviados para o DOM usam interpolação
Vue; não há `v-html` ou conexão do navegador ao banco. Nenhum service recomenda
diagnóstico, conduta ou alvo clínico. Metas e protocolos dependem da equipe habilitada.

`companies`, `users`, `teams`, `team_members`, `patients`, `appointments` são a base.
`care_team_assignments` delimita os pacientes acessíveis aos membros de uma equipe.
Planos, indicadores e casos guardam a empresa e o paciente. Os dez tipos de entidade
clínica solicitados estão contemplados nas migrações, com FKs e autoria.

As migrações são incrementais e registradas em `schema_migrations`. Execute apenas
uma instância do migrador por vez. DDL do MySQL faz commit implícito: se uma migração
falhar parcialmente, interrompa e inspecione o esquema antes de repetir; não marque
uma migração incompleta como aplicada. Não executar sobre banco legado sem mapeamento.

## Permissões

| Perfil | Recursos |
| --- | --- |
| Médico | Dashboard médico; pacientes, planos, indicadores e encaminhamentos vinculados; continuidade |
| Enfermeiro | Dashboard de enfermagem; pacientes, planos e indicadores vinculados; evoluções de enfermagem, sinais vitais, educação; continuidade |
| Navegador | Casos e ações administrativas de pacientes vinculados; dashboard de continuidade |
| Trabalhador | Somente seus horários e situações de consultas |
| Administrador SESI e gestor empresarial | Agregados da própria empresa, com supressão de grupos pequenos; sem prontuários |

Cada requisição reconsulta usuário ativo. Recurso clínico exige médico/enfermeiro,
empresa correspondente e vínculo ativo; conhecer o ID não concede acesso. Um vínculo
revogado remove o acesso imediatamente. Responsáveis e destinatários de encaminhamentos
devem ser profissionais ativos de uma equipe vinculada ao paciente. Não há privilégio
clínico automático para administrador. Atribuição de equipes e usuários nesta entrega
é feita pela carga fictícia ou por provisionamento institucional controlado, sem tela
de administração de identidade. Protocolos e habilitação profissional reais exigem
validação institucional antes de qualquer utilização fora do Hackathon.

## Regras de indicadores e métricas

- Tipos: numérico, percentual (0–100), categórico, frequência, faixa, adesão,
  comparecimento e continuidade. Os últimos três permitem contagens configuradas
  pelo profissional; não representam automaticamente percentuais.
- Cada indicador pertence a um paciente; unidade, método, frequência, categorias e
  referência são configurados. Cada medição registra coleta, registro, método,
  origem, registrador e profissional responsável quando a origem é profissional.
- Autorrelato não possui autoria profissional da coleta; guarda quem o registrou.
  Nesta versão, o profissional registra o autorrelato recebido. Não há submissão
  clínica direta por trabalhador nem importação sem origem.
- Cada meta referencia um indicador do mesmo paciente e uma origem específica.
  Fontes distintas aparecem em séries separadas; não há média que misture fontes.
- A avaliação usa a medição mais recente por coleta e ID, somente da origem escolhida,
  coletada após a criação/último ajuste da meta e registrada após o marco de evidência
  dessa versão. O marco por ID também impede reutilizar medições anteriores feitas
  no mesmo segundo do ajuste.
- Sem `min_samples` válidas: `insufficient_data`, nunca `attained`. Uma coleta
  duplicada para indicador/origem/instante retorna 409 e não aumenta amostras.
- Aumentar: último valor ≥ alvo; reduzir: ≤ alvo; faixa: limites inclusivos;
  manter: igualdade numérica com tolerância computacional de 0,000001; categoria:
  correspondência exata. A tolerância não é uma recomendação clínica.
- Alterar meta exige versão atual e reinicia a janela de evidência. Medições antigas
  permanecem no histórico. Pausadas/canceladas não são consideradas atingidas.
- Frequência e prazo são informações do plano, não um motor autônomo de alerta ou
  interpretação clínica. Não se infere evolução favorável sem medições.

Dashboards clínicos usam pacientes e planos atualmente autorizados. Pacientes,
planos ativos, metas e sua avaliação são o retrato atual. Medições, ações realizadas,
evoluções, avisos e revisões são contados no intervalo `from`–`to` (inclusivo, horário
America/Bahia). Planos revisados contam IDs distintos com revisão no intervalo;
reavaliações concluídas contam revisões registradas por profissional. Reavaliações
previstas contam planos não encerrados com data prevista no período. Consultas do dia
contam consultas do profissional autenticado dentro do intervalo. O período máximo
aceito é 366 dias. O gráfico mostra valores coletados, sem inferência diagnóstica.

Na continuidade, o período usa a data agendada do retorno. Retornos previstos excluem
originais reagendados/cancelados. Confirmados incluem `confirmed` e `attended`.
Taxa = 100 × confirmados / previstos; denominador zero retorna `null` e a tela exibe
`—`. Essa taxa mede confirmação administrativa, não adesão clínica nem comparecimento
efetivo. Pendências, reagendamentos, reconexões e barreiras resolvidas contam casos
pela situação atual; não somam repetidamente ações no mesmo caso.

## ELO Reconecta

Retornos criados pela API geram caso pendente na mesma transação. Para consultas
preexistentes/importadas, `php bin/sync-continuity.php` identifica as não confirmadas,
com unicidade por consulta. Agende esse comando no ambiente ou use o botão da tela.
Não há scheduler instalado automaticamente. A sincronização inclui retornos futuros;
pendente significa não confirmado, sem classificar automaticamente falta.

Confirmar não significa comparecer. Comparecimento anterior ao horário previsto é
rejeitado. Reagendar preserva a consulta original e suas ações, cria nova consulta e
novo caso pendente. Confirmar um caso já reagendado é rejeitado. Ações exigem a versão
atual para evitar sobrescrita concorrente. Barreiras são opcionais, categorizadas e
podem ser recusadas. Toda ação gera aviso administrativo à equipe APS.

Solicitar revisão clínica apenas avisa a equipe; navegador não recebe observações,
indicadores, metas, evoluções ou motivo clínico de encaminhamento. Textos administrativos
não são preenchidos copiando conteúdo clínico e a interface orienta a não inserir
dados clínicos. Encaminhamentos clínicos usam recurso separado e motivo criptografado.

## Segurança e operação

- Sessões com strict mode, cookie HttpOnly/SameSite Strict, rotação no login/logout,
  expiração por inatividade de 30 minutos e armazenamento fora de `public`.
- CSRF em login, logout e mutações protegidas. Sem CORS aberto; frontend e API na
  mesma origem. Token só em memória; senha não é persistida no navegador.
- Limite de dez tentativas por e-mail/IP em 15 minutos. Hash de senha com
  `password_hash`/`password_verify`. Usuário inativo não pode autenticar.
- Validação de tipo, tamanho, datas e valores; limite de corpo JSON de 64 KB.
  SQL parametrizado e prepared statements nativos. Conflitos de versão retornam 409.
- Observações clínicas, textos de evolução/revisão, payloads históricos, motivos de
  encaminhamento e notas administrativas usam AES-256-GCM com chave de 32 bytes.
  A chave fica somente no backend, deve ter backup seguro e nunca ser trocada sem
  recriptografar os dados. Perder a chave impede recuperar esses textos.
- Auditoria registra autoria, empresa, recurso e horário de acessos e alterações.
  Não registra senhas, tokens, corpos de requisição ou SQL. Leituras de recursos
  protegidos passam pelo registro de acesso do controller.
- Exportações clínicas não são oferecidas: endpoints inexistentes retornam 404.
  Não há bypass por parâmetros de consulta. Qualquer futura exportação exige
  permissão, escopo, minimização e auditoria específicos.
- Gestores não recebem listas nominais. Agregados empresariais exigem pelo menos
  cinco pacientes; cada contagem por situação inferior a cinco é suprimida (`null`).
  Isso é uma redução de risco no protótipo, não garantia de anonimização.
- Retenção do Hackathon: usar somente dados fictícios. Histórico assistencial e
  auditoria não têm exclusão pela API. `bin/retention.php` mostra tentativas de login
  com mais de 30 dias; `--apply` remove apenas essas tentativas. Sessões expiram por
  inatividade. Encerrado o evento, o operador deve remover a base fictícia e backups
  conforme o procedimento institucional. Não há prazo legal de prontuário codificado.
- Produção exige HTTPS (`APP_ENV=production` recusa sessão sem HTTPS e emite HSTS),
  segredos privados, acesso mínimo ao banco, criptografia de volume/backups, conexão
  privada/TLS ao MySQL, gerenciamento de chaves e revisão dos controles operacionais.
  Outros campos estruturados permanecem legíveis ao banco; criptografia de campos
  selecionados não substitui proteção do armazenamento e dos backups.

O protótipo não está validado para cuidado real. Fluxos, privacidade, habilitação,
protocolos e conformidade precisam de avaliação independente. Não foi publicada
nenhuma versão externa nem acessado banco do usuário.
