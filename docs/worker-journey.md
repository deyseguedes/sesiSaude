# Minha jornada de cuidado — gamificação do trabalhador

O início do trabalhador apresenta pontos simbólicos, nível pessoal e três
conquistas. Elas são reconhecidas automaticamente ao abrir ou retornar ao espaço,
sem resgate, justificativas ou formulário adicional.

| Conquista | Pontos | Evidência |
| --- | --- | --- |
| Consulta marcada | 10 | Existe consulta registrada para o próprio trabalhador |
| Presença confirmada | 20 | Consulta em data passada com status de comparecimento registrado pela equipe |
| Cuidado em continuidade | 30 | Evolução profissional ou revisão do plano em outro dia, depois do primeiro comparecimento |

Cada conquista vale uma vez nesta jornada, com máximo de 60 pontos. Não se ganha
mais por marcar várias consultas, reagendar ou atualizar a página. O registro
da própria consulta no mesmo dia não é confundido com um acompanhamento posterior.
Plano isolado, confirmação do horário e autorrelato não comprovam acompanhamento.

Níveis: Começando minha jornada (0), Primeiro passo (10), Presença que conta (30)
e Cuidado em continuidade (60). Concluir a jornada não encerra o acompanhamento:
o trabalhador continua seguindo o plano pactuado com sua equipe. Não há prazo,
sequência diária obrigatória, ranking, multa, recompensa financeira, pontuação
por exames/resultados clínicos ou estímulo a consultas desnecessárias.

Cancelar uma consulta não apaga o reconhecimento de que houve um agendamento.
Reagendar ou solicitar apoio não gera punição. Correções dos registros de origem
são reconciliadas para que uma conquista não declare um comparecimento ou
acompanhamento que deixou de estar documentado. Essas correções são auditadas e
não criam novos registros de conquista nem pontos adicionais.

## Persistência e acesso

Migration `008_worker_journey.sql` cria `worker_journey_awards`, com origem da
evidência, pontos, reconhecimento, status e versão. A chave única por empresa,
paciente e conquista e o bloqueio transacional do paciente evitam duplicidade.
Os scripts SQL completos foram regenerados; a migration foi aplicada localmente.

O paciente vem exclusivamente da identidade autenticada do trabalhador, com
mesma empresa. IDs e pontos enviados pelo cliente não escolhem a pessoa nem
definem o prêmio. Dependentes representados não contribuem para os pontos do
titular. Médico, enfermeiro, gestor, administrador, navegador e dependente não
acessam esta API individual.

O retorno não contém prontuário, notas clínicas, resultados de indicadores,
IDs de evidência ou ranking. A conquista não é uma medida de saúde ou desempenho
profissional e não altera elegibilidade, acesso ou prioridade assistencial.

## API e interface

- `GET /api/v1/me/journey`: consulta os reconhecimentos sem criá-los; verifica
  também se as evidências continuam válidas.
- `POST /api/v1/me/journey/sync`: reconcilia e retorna a jornada; exige sessão e
  CSRF. O serviço define todos os valores, sem aceitar uma declaração do cliente.
- `WorkerJourneyService.php`: regras, cronologia, persistência e auditoria.
- `WorkerJourney.vue`: pontos, conquistas, nível e acesso direto aos retornos.

No celular, as conquistas aparecem lado a lado; as regras completas ficam em
Como as conquistas funcionam. O atendimento permanece acessível sem precisar
concluir ou abrir as regras do jogo.

A demonstração pública exporta apenas respostas de um banco temporário fictício.
Seu trabalhador possui exemplo das três conquistas, com comparecimento e
acompanhamento históricos fictícios. Nenhum registro demonstrativo foi adicionado
ao banco de uso local. No local, os reconhecimentos usam os registros existentes.

## Verificação

- 213 verificações combinadas de domínio, incluindo jornada, aprovadas em SQLite
  e MySQL temporário.
- 315 verificações HTTP, incluindo permissões, CSRF, identidade e tentativa de
  adulterar pontos ou consultar outra pessoa.
- 13 verificações dos scripts SQL, incluindo atualização preservando registros.
- 28 testes frontend e build de produção.
- Navegador: jornada no início do trabalhador, três conquistas com evidência,
  nenhuma ação de resgate, celular sem transbordamento e regressão dos sete perfis.
- Casos sem dados, múltiplos agendamentos, cancelamento, comparecimento futuro,
  evolução no mesmo dia, autorrelato, revisão, correção e restauração de evidências.
