# API v1

Endpoints adicionais de gestão industrial, cadastro, dependentes, agendas e campanhas:
[API industrial e de engajamento](industry-api.md).

Contrato uniforme: `{ "data": ..., "error": null }` ou
`{ "data": null, "error": { "code": "...", "message": "..." } }`.
Criações: 201; consultas/alterações: 200; JSON inválido/dados inválidos: 422;
tipo de conteúdo incorreto: 415; corpo excedido: 413; não autenticado: 401;
perfil/CSRF: 403; recurso fora do escopo: 404; método: 405; versão/coleta
duplicada: 409; limite de login: 429. Detalhes internos não são enviados no 500.

| Método | Caminho após `/api/v1` | Uso |
| --- | --- | --- |
| GET | `/health` | Liveness público, sem informação de banco |
| GET | `/auth/session` | Usuário atual ou null e `csrf_token` |
| POST | `/auth/login` | `{email,password}`; CSRF obtido antes pelo session |
| POST | `/auth/local-profile` | `{role}`; CSRF obrigatório; somente loopback, `APP_ENV=local` e `LOCAL_PROFILE_ACCESS=true`; contas de demonstração ativas |
| POST | `/auth/logout` | Finaliza sessão; CSRF obrigatório |
| GET | `/clinical/patients` | Pacientes vinculados, médico/enfermeiro |
| GET | `/clinical/patients/{id}` | Histórico, equipes e profissionais autorizados |
| GET | `/clinical/patients/{id}/summary` | Resumo factual do prontuário; médico/enfermeiro vinculado ([regras](patient-summary.md)) |
| POST | `/clinical/patients/{id}/appointments` | `{team_id,professional_id,date,time}`; cria retorno e caso |
| GET, POST | `/care-plans` | Lista e criação de planos |
| GET, PUT | `/care-plans/{id}` | Detalhe e atualização com `version` |
| GET, POST | `/care-plans/{id}/goals` | Metas com avaliação e criação |
| PUT | `/care-goals/{id}` | Ajuste com `version` e histórico |
| POST | `/care-plans/{id}/reviews` | `{version,notes,outcome,reassessment_date}` |
| POST | `/care-plans/{id}/evolutions` | `{kind,notes}` |
| POST | `/care-plans/{id}/adherence` | `{action_description,completed,source,occurred_at}` |
| GET | `/clinical/patients/{id}/indicators` | Catálogo do paciente |
| POST | `/indicators` | Configura indicador individual |
| GET, POST | `/indicators/{id}/measurements` | Histórico e coleta |
| POST | `/clinical/referrals` | `{patient_id,team_id,to_professional_id,reason}` |
| GET | `/clinical/dashboard` | Médico |
| GET | `/nursing/dashboard` | Enfermeiro |
| GET | `/continuity/dashboard` | Médico, enfermeiro ou navegador vinculado |
| GET | `/continuity/cases` | Casos administrativos vinculados |
| GET | `/continuity/cases/{id}` | Caso e histórico administrativo |
| POST | `/continuity/cases/{id}/actions` | Ação com `type`, `version` e campos específicos |
| POST | `/continuity/sync` | Identificação idempotente de retornos não confirmados |
| GET | `/portal` | Trabalhador ou agregados de admin/gestor |

Dashboards aceitam `from=YYYY-MM-DD`, `to=YYYY-MM-DD` e `patient_id` opcional.
Mutações usam `Content-Type: application/json` e `X-CSRF-Token`. Token é rotacionado
no login; use o token retornado para as próximas operações. Datas/horas de coleta
usam `YYYY-MM-DD HH:mm:ss` no fuso America/Bahia; não aceitar coleta futura.

## Exemplos de corpos

Plano (PUT requer também `version`, e paciente não pode mudar):

```json
{"patient_id":1,"professional_id":5,"team_id":1,"start_date":"2026-11-14","reassessment_date":"2026-11-21","objectives":"Objetivo definido pelo profissional","interventions":"Ação pactuada","status":"active","clinical_notes":"Observação restrita"}
```

Indicador (categorias obrigatórias apenas para tipo categórico):

```json
{"patient_id":1,"name":"Indicador fictício","type":"numeric","unit":"unidades","reference_value":"Referência contextual","measurement_method":"Método definido","frequency":"Pactuada","categories":[]}
```

Meta (números e IDs como números JSON; valores apenas demonstrativos):

```json
{"indicator_id":1,"description":"Meta fictícia pactuada","initial_value":0,"target_value":3,"target_min":null,"target_max":null,"direction":"increase","measurement_source":"professional","min_samples":2,"frequency":"Semanal","deadline":"2026-11-21","responsible_id":5,"status":"active"}
```

Para faixa, usar `direction=range`, `target_min` e `target_max`; alvo simples pode
ser null. Para categoria, `direction=category` e alvo deve estar nas categorias
configuradas. A unidade é herdada do indicador. Atualização requer o corpo completo
e a `version` retornada pela consulta.

Medição:

```json
{"value":2,"source":"self_reported","collected_at":"2026-11-14 09:00:00","method":"Autorrelato recebido"}
```

Reagendamento administrativo:

```json
{"type":"reschedule","version":1,"date":"2026-11-21","time":"10:00","administrative_note":"Horário pactuado com a pessoa."}
```

Outros tipos: `confirm`, `attendance`, `welcome`, `barrier` (campo `barrier`:
`transport|schedule|contact|personal|declined`), `assign_navigator` (`navigator_id`),
`reconnect`, `resolve_barrier`, `clinical_review`. Nota administrativa é opcional;
nunca enviar detalhes clínicos neste recurso.
