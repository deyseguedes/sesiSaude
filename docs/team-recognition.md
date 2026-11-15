# Reconhecimento coletivo da equipe de saúde

Decisão confirmada pelo usuário em 14/11/2026: o reconhecimento coletivo **substitui** os pontos individuais do médico. Não há coexistência de novos bônus individuais. O histórico antigo permanece no banco, sem conversão em dinheiro ou em prêmio coletivo.

A migração 011 desativa flags individuais existentes. A API impede reativação mesmo por cliente antigo. Os endpoints legados retornam enabled=false e replacement=team_recognition, sem criar ou recalcular pontos. A gamificação simbólica do trabalhador continua independente.

## Implementação atual

Gestão → Gamificação do cuidado apresenta uma linha por equipe, com RP, linha de base e faixa ainda não apuráveis quando não há base consolidada. “Simular cenário da equipe” abre o formulário no mesmo painel. Médico, enfermagem e acolhimento/navegação consultam apenas suas próprias equipes; gestão empresarial e administrador autorizado consultam a empresa selecionada.

A simulação usa totais agregados informados explicitamente. Não infere riscos, não lê prontuários para atribuir prêmio e não representa uma apuração oficial. Os resultados não são persistidos como desempenho observado. Nenhuma simulação autoriza pagamento.

RP = retornos no prazo / (devidos congelados − exclusões com fonte). O mínimo conservador é 300 **após exclusões**. Trimestre insuficiente pede semestre; semestre insuficiente pede regional; regional insuficiente permanece bloqueada. Denominador zero é não apurável. Quantidade de retornos no prazo não pode exceder o denominador.

Ganho em pontos percentuais sobre a linha de base: [4,6) → 1%; [6,8) → 2%; [8,10) → 3%; [10,12) → 4%; ≥12 → 5%; abaixo de 4 → 0%. Excelência proposta em RP de 80% gera faixa teórica de 5%. A faixa é calculada antes do arredondamento da apresentação.

Todas as verificações começam desmarcadas: carteira/exclusões verificadas, linha de base verificada, nenhuma queixa procedente após verificação, auditoria de risco/exclusões liberada e consentimentos respeitados. Satisfação deve ser pelo menos 90%. Sem confirmação, a simulação fica bloqueada mesmo quando a faixa teórica é positiva. Uma verificação na simulação é declaração de cenário, não evidência institucional.

O mesmo percentual proposto é aplicado aos integrantes elegíveis, proporcional aos dias na equipe; o sistema não coleta salários nem executa folha nesta entrega.

## API e controles

- GET /api/v1/recognition/teams: equipes no escopo, sem nomes de pacientes ou remuneração.
- POST /api/v1/recognition/teams/simulate: restrito à gestão empresarial/administrador autorizado, com sessão e CSRF.
- company_id opcional segue a autorização de empresa já existente; team_id precisa pertencer à empresa.
- Leituras e simulações ficam auditadas. Pagamento sempre retorna payment_authorized=false.
- O link estático do Sites continua somente para consulta. Na demonstração interativa do ngrok, equipes são fictícias e o simulador pode ser utilizado pelos perfis autorizados, sem liberar pagamento.

## Próxima apuração oficial

A proposta original em [premio-equipe-saude.md](premio-equipe-saude.md) continua em discussão quanto a pagamento e calibração. Risco confirmado versionado e retorno devido por pessoa já existem no [fluxo clínico](clinical-returns.md). Para apuração automática faltam: congelamento da carteira, exclusões com fonte, linha de base fixa e revisão anual, evidências de satisfação/queixas, auditoria de rebaixamentos e exclusões, autorização de contato, membros/dias na equipe e fechamento validado.

Não usar consultas ou planos existentes como substituto desses dados. A simulação não publica indicadores observados nem preenche lacunas com zero. Os limiares de 15 dias, 80% e demais pontos propostos precisam de confirmação institucional antes de virar regra de pagamento.
