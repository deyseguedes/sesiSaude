# Reconhecimento da retomada do cuidado

Em **Gestão → Gamificação do cuidado → Bônus do médico por retomada**, a empresa pode ativar o bônus e ajustar o intervalo e os pontos. O padrão é **180 dias e 30 pontos**. A gamificação da empresa também precisa estar ativa. Empresas existentes mantêm a escolha de ativar; a migração não liga o programa automaticamente.

O intervalo é contado entre dois registros de atendimento realizado ou acompanhamento profissional na plataforma. Agendamento e confirmação não geram bônus. Uma primeira consulta sem histórico anterior não comprova afastamento. Registros de enfermagem também reiniciam o intervalo, mas o bônus é exclusivo do médico que efetivamente realizou o atendimento ou escreveu a evolução/revisão do retorno. Dependentes não participam desta regra para trabalhadores.

Cada intervalo elegível gera um único reconhecimento, mesmo quando o médico registra consulta e evolução no mesmo retorno ou atualiza a tela várias vezes. Só retornos registrados a partir da ativação da regra são reconhecidos. Alterações de parâmetros valem para novos retornos; os anteriores conservam os pontos e o limite usados originalmente. Correções nos registros de origem invalidam reconhecimentos sem evidência e ficam auditadas.

O painel inicial do médico mostra seus pontos e até três trabalhadores da sua equipe com intervalo conhecido, com acesso direto ao acompanhamento. Não há ranking nem valor financeiro. A gestão configura o programa, sem receber a lista individual de pontos. A pontuação não altera prioridade clínica e a falta de registros não comprova falta de cuidado fora da plataforma.

A API usa GET `/api/v1/professional/reactivation` e POST `/api/v1/professional/reactivation/sync`, com autenticação, restrição ao médico, escopo de equipe/empresa e CSRF na sincronização. Os parâmetros opcionais `reactivation_enabled`, `reactivation_days` (1–3650) e `reactivation_points` (1–1000) usam o PUT de gestão já existente, com versão para impedir sobrescrita concorrente. Clientes antigos que enviam somente `enabled` e `version` preservam os parâmetros.

Execute `composer migrate` em `backend` para aplicar `010_doctor_reactivation.sql`. Os scripts SQL completos também incluem a alteração. O link público contém apenas exemplos fictícios e continua somente para consulta.
