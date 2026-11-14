# Etapa 2 — Central de Continuidade da Enfermagem

## Resultado e acesso

O início do perfil de enfermagem abre a Fila do Dia. A mesma fila está em
`/nursing-worklist` para médico ou enfermeiro vinculado. O painel anterior
permanece em `/care-dashboard`, pelo botão **Indicadores assistenciais**.

A fila reúne retornos previstos sem confirmação nos próximos sete dias,
retornos passados sem comparecimento registrado, pedidos de apoio, barreiras
não resolvidas, reavaliações previstas até sete dias, encaminhamentos solicitados,
tarefas de campanha abertas/adiadas e tarefas operacionais criadas pela equipe.
Retornos passados confirmados também entram, inclusive quando ainda não havia
caso de continuidade. Confirmação não é tratada como comparecimento.

Cada linha mostra pessoa, equipe, unidade quando registrada, profissional de
origem, atribuição, motivo, status, prazo, próximo retorno e próximo passo.
Busca por nome fica visível; filtros de equipe, profissional, unidade, prazo,
situação e atribuição ficam recolhidos para reduzir o espaço antes da lista.

A ficha lateral reúne consultas, comparecimentos, plano em destaque, objetivos,
intervenções, metas, última adesão registrada, barreiras, contatos,
encaminhamentos e próximas atividades. Atalho abre o prontuário completo.
Os limites do resumo existente continuam identificados; a ficha não substitui
o histórico completo.

## Ações persistidas

- Registrar contato humano, canal e resultado, sem presumir mensagem enviada.
- Atribuir a médico/enfermeiro ativo com vínculo compatível de equipe.
- Confirmar retorno ou comparecimento verificado; comparecimento futuro é negado.
- Reagendar pelo serviço existente, preservando regras de conflito de agenda.
- Registrar dificuldade voluntária, inclusive a opção de não informar motivo.
- Encaminhar ao médico vinculado e registrar evolução de enfermagem no plano.
- Criar tarefas, solicitações de reagendamento/acolhimento e falhas de comunicação.
- Marcar acompanhamento, finalizar com justificativa ou reabrir com justificativa.

Finalizar uma pendência não declara automaticamente um plano revisado, uma tarefa
de campanha concluída, uma barreira resolvida ou um atendimento realizado.
Esses registros mantêm suas ações próprias.

## Regras do motor

Sincronização é explícita por POST e acontece ao abrir/atualizar a fila ou salvar
uma ação. Leituras GET não criam pendências. Não há agendador de execução automática
nesta etapa. Origens automáticas usam chave única por empresa, tipo, ID e natureza;
sincronizar novamente não duplica o caso. Tarefas manuais têm identidade própria.

Ordenação: prazo ascendente, pedido expresso de ajuda no empate, tipo da pendência,
data de criação e ID. A interface explica o motivo. Não existe pontuação de risco
clínico nem estimativa de probabilidade de falta.

Encerramento manual permanece encerrado em sincronizações repetidas. Um novo
pedido de apoio ou registro de barreira pode reabrir o item correspondente. Mudar
a data de reavaliação pode gerar um novo ciclo de acompanhamento. Quando a origem
deixa de apresentar a pendência, o motor finaliza o item e registra a razão.

Unidade de atendimento vem do registro de origem. Não se presume uma unidade
a partir do endereço da indústria. Na ausência de equipe inequívoca na origem,
a linha informa que a equipe não está especificada.

## Banco, transações e permissões

Migration `007_nursing_worklist.sql` adiciona `care_pending_items` e
`care_pending_events`, com referências, índices, chave de deduplicação e versão.
Scripts `viva_elo.sql` e `upgrade_industry.sql` foram regenerados. A migration foi
aplicada no ambiente local; nenhum paciente existente foi alterado pela migração.

Históricos de notas e detalhes dos eventos são criptografados. Acessos e alterações
são auditados. Alterações exigem CSRF e versão atual; versão obsoleta retorna 409.
Operações compostas usam transações e savepoints, para não deixar prontuário ou
agenda parcialmente alterados quando a operação falha.

Acesso exige médico/enfermeiro ativo, mesma empresa e vínculo assistencial vigente.
Navegador mantém o acesso administrativo existente no Reconecta e não recebe esta
ficha clínica. Gestor, administrador, trabalhador e dependente não acessam a fila.

## Endpoints

Todos sob `/api/v1`, com o contrato JSON existente:

| Método | Caminho | Função |
| --- | --- | --- |
| GET | `/nursing/worklist` | Fila e filtros disponíveis no escopo |
| POST | `/nursing/worklist/sync` | Identificar/atualizar pendências sem duplicar |
| GET | `/nursing/worklist/{id}` | Ficha de acompanhamento autorizada |
| POST | `/nursing/worklist/{id}/actions` | Ação com `type`, `version` e campos específicos |
| POST | `/nursing/worklist/tasks` | Criar tarefa com paciente, equipe, responsável e prazo |

Filtros: `team_id`, `professional_id`, `service_unit_id`, `assigned_to`,
`status=active|open|in_progress|resolved|all` e
`deadline=overdue|today|next7`.

## Arquivos da entrega

Criados:

- `backend/src/Services/NursingWorklistService.php`.
- `backend/database/migrations/007_nursing_worklist.sql`.
- `backend/bin/seed-nursing.php`.
- `backend/tests/nursing-worklist.php` e `backend/tests/http-worklist.php`.
- `frontend/src/views/NursingWorklistView.vue`.
- Este relatório.

Modificados:

- Backend: rotas, `ClinicalController`, transações de `Store`, scripts SQL,
  configuração Composer e suites de testes/fixtures.
- Frontend: início da enfermagem, rotas, estilos e checagens de navegador.
- Demonstração: exportador de respostas fictícias, adaptador estático e sua QA.
- Documentação: README e contrato da API.

Componente Vue principal: `NursingWorklistView.vue`, reutilizando `SidePanel`,
`FormPanel`, `RecordText`, `AppIcon` e o controle de consultas concorrentes.

## Cenário fictício Maria

`php bin/seed-nursing.php`, executado dentro de `backend`, adiciona Maria somente
em ambiente local com contas de demonstração já instaladas. A carga é opcional e
não sobrescreve pacientes existentes; executá-la novamente não duplica Maria.
Ela contém comparecimento anterior, plano e dificuldade voluntária de horário.
Não gera diagnósticos, riscos ou metas clínicas universais.

O cenário é incluído nos bancos temporários da QA e na demonstração pública.
Não foi inserido automaticamente no banco de uso local. No link público, somente
a consulta do cenário é disponibilizada; a aplicação local persiste as ações.

## Verificação e próximas etapas

- 15 verificações de núcleo.
- 173 verificações combinadas de domínio, resumo e fila, aprovadas em SQLite e MySQL.
- 274 verificações HTTP em MySQL temporário.
- 11 verificações dos scripts SQL, incluindo atualização preservando registros.
- 26 testes frontend, build e checagem Chrome dos sete perfis.
- Navegador: fila inicial, celular sem transbordamento da página, ficha, registro
  de contato e criação de tarefa persistida, mantendo os fluxos anteriores.

Mensagens, áudio, check-ins e classificação de risco não foram inventados nem
apresentados como funcionalidades prontas. Ações pactuadas estruturadas, novos
estados de adesão/metas e gráficos do Plano Vivo pertencem à Etapa 3. Expansão do
painel médico, Reconecta, comunicação, indicadores institucionais e SOC seguem
as etapas posteriores e dependem de aprovação.
