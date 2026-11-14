# API industrial e de engajamento

Prefixo `/api/v1`, contrato JSON e sessão/CSRF da API V3 preservados. Mutações exigem
`Content-Type: application/json` e `X-CSRF-Token`. A inscrição usa o token de sessão
pública obtido em `/auth/session`, mesmo sem login.

## Gestão

Nos recursos de gestão, `company_id` opcional na query seleciona uma indústria
autorizada ao administrador. Sem ele, usa a empresa principal. Gestor/coordenação
não selecionam outra empresa. A verificação é feita no backend.

| Método | Recurso | Uso |
| --- | --- | --- |
| GET, POST | `/management/companies` | Empresas autorizadas / nova indústria |
| GET | `/management/overview` | Unidades, contratos, elegibilidade, equipes e diretório administrativo |
| POST | `/management/industry-units` | Unidade industrial com nome/endereço/CEP e coordenadas opcionais |
| POST | `/management/service-units` | Local de atendimento, `kind=sesi|industry` |
| POST | `/management/contracts` | Vigência, situação e coberturas |
| PUT | `/management/contracts/{id}` | Revisão do contrato com `version` |
| POST | `/management/users` | Conta profissional, perfil e nível |
| PUT | `/management/users/{id}` | Nível e ativação; não altera administradores |
| POST | `/management/teams` | Equipe APS |
| POST | `/management/team-members` | `{team_id,user_id}` |
| POST | `/management/care-assignments` | `{patient_id,team_id,active,authorization_verified}` |
| POST | `/management/invitations` | Convite nominal; retorna token somente nessa resposta |
| PUT | `/management/beneficiaries/{id}` | `status=active|suspended`, verificações humanas para ativar |
| POST | `/management/represented-dependents` | Menor com titular e representação verificada |
| GET, POST | `/management/slots` | Listar/publicar disponibilidades |
| POST | `/management/slots/{id}/block` | Bloqueio de horário livre com `version` |

Contrato:

```json
{"name":"Contrato APS e família","start_date":"2026-11-14","end_date":"2027-11-13","status":"active","allow_in_person":true,"allow_telehealth":true,"allow_dependents":true}
```

Convite:

```json
{"contract_id":1,"industry_unit_id":1,"kind":"dependent","sponsor_patient_id":1,"email":"dependente@example.org"}
```

Disponibilidade (IDs numéricos; datas no fuso America/Bahia):

```json
{"professional_id":5,"team_id":1,"service_unit_id":1,"modality":"telehealth","starts_at":"2026-11-20 10:00:00","ends_at":"2026-11-20 10:30:00"}
```

## Beneficiário e agendamento

| Método | Recurso | Uso |
| --- | --- | --- |
| POST | `/enrollment` | Inscrição por convite de uso único |
| GET | `/me/beneficiaries` | Próprio cadastro e representações explícitas |
| POST | `/me/beneficiaries/{id}/dependent-invitations` | Titular convida adulto pelo e-mail |
| GET | `/availability` | Busca autorizada de horários |
| POST | `/availability/{id}/book` | `{patient_id,version,authorize_team:true}` |
| GET | `/me/appointments` | Consultas próprias / representadas, sem link de sala |
| POST | `/me/appointments/{id}/confirm` | Confirmação de retorno |
| POST | `/me/appointments/{id}/support` | Barreira opcional e pedido administrativo à APS |
| GET | `/professional/appointments` | Agenda do profissional e pacientes vinculados |
| PUT | `/appointments/{id}/access` | Responsável define `{meeting_url}` HTTPS |
| GET | `/appointments/{id}/access` | Acesso restrito à sala dentro da janela temporal |

Busca: `patient_id`, `modality=in_person|telehealth`, `professional_role=doctor|nurse|all`,
`near=industry|home`, `sort=earliest|distance`, `my_team=0|1`. Para `home` com distância,
enviar latitude/longitude numéricas. O backend calcula distâncias, verifica cobertura
e retorna apenas horários futuros disponíveis dos próximos 90 dias. Não há geocodificação.

Inscrição:

```json
{"invitation_token":"64 caracteres hexadecimais recebidos no convite","name":"Nome fictício","email":"dependente@example.org","password":"senha privada de 12 a 72 bytes","birth_date":"2000-01-01","contact":"contato fictício","postal_code":"40000-000","preferred_period":"evening","accept_notice":true}
```

Ativação:

```json
{"status":"active","contact_verified":true,"affiliation_verified":true}
```

Pedido de apoio: `barrier=transport|schedule|contact|personal|declined`, e
`administrative_note` opcional, sem detalhes clínicos. Não aciona canal externo.

## Educação, campanhas e relacionamento

| Método | Recurso | Uso |
| --- | --- | --- |
| GET, POST | `/management/contents` | Biblioteca editorial e rascunho |
| PUT | `/management/contents/{id}` | Editar com `version`, retirando publicação |
| POST | `/management/contents/{id}/publish` | `{version,review_note}`; APS exige clínico |
| GET | `/me/contents` | Conteúdos publicados e conclusão pessoal da versão |
| POST | `/me/contents/{id}/complete` | Conclusão autorrelatada |
| GET, POST | `/management/campaigns` | Campanhas e indicadores agregados / criar ciclo |
| POST | `/management/campaigns/{id}/status` | `{version,status:active|closed}` |
| GET | `/me/campaigns` | Convites ativos do próprio beneficiário |
| PUT | `/me/campaign-participations/{id}` | `{version,stage}`; escolha da pessoa |
| GET | `/engagement/pipeline` | Fila de participantes vinculados à equipe |
| PUT | `/engagement/participants/{id}` | Etapa administrativa com versão |
| GET | `/engagement/participants/{id}/assignees` | Responsáveis assistencialmente vinculados |
| POST | `/engagement/participants/{id}/tasks` | Próximo passo e responsável |
| PUT | `/engagement/tasks/{id}` | Concluir/adiar tarefa do próprio responsável |

Conteúdo: `title`, `summary`, `transcript`, `category=platform|aps`, `video_url`
HTTPS opcional, `duration_seconds` inteiro de 1 a 600. Não há upload de arquivos.

Campanha: `title`, `objective`, `cycle_label`, `start_date`, `end_date`,
`industry_unit_id` opcional, `audience=worker|dependent|all`, `content_ids` com
1–20 IDs numéricos de conteúdos publicados da empresa.

Tarefa: `assigned_to`, `kind=contact|welcome|followup`, `due_date`,
`administrative_note` opcional. Atualizar: `version`, `status=done|deferred` e nota.
Recusa (`opted_out`) impede novo contato de campanha pela equipe.
