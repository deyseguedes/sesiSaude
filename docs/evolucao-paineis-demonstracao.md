# Evolução demonstrativa dos painéis — 15/11/2026

## Cenários entregues

- Trabalhador abre diretamente no canal de celular da demonstração, sem cadastro adicional.
- Carla confirma ou pede outro horário pelos botões; Seu José ouve e envia uma fala sintetizada de exemplo, com pedido de contato.
- Botões de ajuda e ligação criam solicitações no banco demonstrativo. Não há integração externa com WhatsApp, transcrição de microfone ou telefonia.
- Cada resposta gera evento cifrado e auditado. Confirmar não registra comparecimento.
- Enfermagem consulta a fila a cada dois segundos, sem recarregar a página. A consulta automática pausa durante a edição de ficha/tarefa.
- Uma linha por pessoa agrupa pendências, preservando a ordem administrativa da primeira pendência. É possível expandir todas; a resposta do canal aparece na linha agrupada.
- Médico abre na carteira; o paciente abre com resumo e campos de risco e retorno.
- Administrador SESI e gestor da empresa veem alcance, agendamento, comparecimento e continuidade, com três níveis de avaliação. Adesão considera atendimento registrado após avaliação e até a data devida, entre retornos já devidos. É fotografia, sem carteira congelada oficial.
- Resultado de risco não é apresentado como melhora sem medidas comparáveis.
- Exemplo independente da Equipe Norte: 280/510 = 54,9%; base 46,0%; ganho 8,9 pontos; faixa 3%; satisfação 94% e demais travas aprovadas. Não é apuração das 100 pessoas e não autoriza pagamento.
- Prêmio sai do topo assistencial. O exemplo também preenche o quadro da equipe na gestão.
- Líder demonstrativo recebe apenas nome e horário autorizados na última resposta. Nova resposta sem autorização retira o aviso. Não recebe prontuário, risco ou motivo clínico.
- Critérios semestrais substituem os pontos na apresentação pública do trabalhador. Código é ilustrativo e escolha é local; não há apuração oficial, registro de consentimento real ou resgate no RH.
- Tela de segurança distingue controles implantados de requisitos futuros.
- Enfermagem/coordenação não acessa contratos e convites; bloqueio aplicado na navegação, nas rotas e na API.
- Datas de apresentação: dd/mm/aaaa; horários sem segundos; vírgula decimal nos percentuais.

## Base sintética

A organização informou não possuir a planilha citada. Foi criado o arquivo
[100-pessoas-sinteticas.csv](../backend/database/fixtures/100-pessoas-sinteticas.csv),
com 100 nomes identificados como teste, risco de 1 a 4, prazos relativos, objetivos,
intervenções e observações variadas.

O instalador [seed-experience.php](../backend/bin/seed-experience.php) funciona
apenas via CLI com APP_ENV=demo, PUBLIC_DEMO_ACCESS=true e nome de banco
viva_elo_demo_ seguido de 12 caracteres hexadecimais. A transação completa a base
fictícia até 100 pessoas e mantém IDs/vínculos existentes; a repetição preserva
respostas e registros. Não roda no banco real do Laragon.

A publicação usa os comandos existentes de build do servidor e demonstração;
o segundo agora instala esse cenário de forma idempotente. Nenhuma migração
destrutiva ou alteração de banco real é necessária.

## Validação

- npm test: formatação, navegação, guia, requisições e demais regras do frontend.
- composer test: cadeia de testes em memória, incluindo fronteira entre demo e
  produção, autorização por perfil, consentimento do líder, sigilo do payload,
  confirmação sem comparecimento e cálculo do exemplo.
- frontend/scripts/experience-qa.mjs: navegador no link dinâmico, celular, ações
  por botões, persistência, atualização automática da fila, bloqueios de acesso,
  carteira/resumo e retirada de autorização.
- frontend/scripts/all-screens-guide-qa.mjs: guia nas telas dos oito perfis e
  formulários com rascunho, sem salvamento automático.

## Pendências de operação real

Integração WhatsApp/SOC/telefonia; recebimento e transcrição reais de áudio;
consentimento institucional com vigência e finalidade; apuração semestral,
carteira congelada, fechamento/auditoria do prêmio; integração com RH;
boletim completo e comparação por implantação escalonada; infraestrutura
pós-quântica. Os cenários demonstrativos não substituem esses serviços.
