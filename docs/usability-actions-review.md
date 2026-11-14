# Ações diretas e redução de cliques

Esta entrega simplifica os fluxos existentes, sem avançar para o motor do Plano
Vivo da Etapa 3. Não exige migration nem alteração de contas ou senhas.

## Fluxos alterados

| Fluxo | Antes | Agora |
| --- | --- | --- |
| Confirmar retorno futuro | Abrir ficha, escolher ação, salvar | Botão na linha da fila |
| Assumir caso | Abrir ficha, atribuir, escolher responsável, salvar | Uma ação atribui ao profissional conectado e inicia acompanhamento |
| Registrar contato | Abrir ficha, escolher ação, preencher e salvar | Botão da fila abre diretamente o formulário |
| Continuar a fila | Salvar, fechar ficha, localizar e abrir outro item | Salvar e abrir próxima |
| Filtros comuns | Abrir filtros e escolher valores | Atalhos Todas abertas, Minhas, Prazo passado e Pedidos de apoio |
| Teleconsulta profissional | Consultar acesso e clicar no link retornado | Entrar na sala verifica acesso e abre a sala |
| Reavaliar plano | Entrar em registros e abrir seção de revisão | Atalho abre a seção e posiciona a página |

Contagens de cliques de navegação não incluem digitação, escolhas dos campos ou
permissão do navegador. Se popups forem bloqueados, a teleconsulta oferece um
link alternativo após a autorização. O provedor continua externo e autorizado
pela instituição; nenhum provedor foi acionado nos testes.

## Fila e ficha

Ações frequentes aparecem na linha da fila e no topo da ficha. Ações secundárias
ficam em Outras ações. Nome da pessoa abre a ficha completa. Última tentativa de
contato fica visível sem expandir o histórico. Finalização continua exigindo
justificativa e comparecimento continua sendo uma ação explícita.

Assumir caso é atômico, usa a identidade da sessão e respeita vínculo e equipe.
Não permite assumir silenciosamente uma pendência atribuída a outro profissional.
As ações mantêm CSRF, versão e auditoria. A API informa quais atalhos estão
disponíveis; a interface não presume essas permissões.

Salvar e abrir próxima utiliza a ordem dos itens filtrados, descarta itens que
saíram do resultado e não repete o item atual. Não avança quando o salvamento ou
a atualização necessária da fila falha. Contatos, atribuições e registros de
enfermagem não exigem uma sincronização completa desnecessária após salvar.

Filtros são guardados na sessão do navegador, separados por usuário e empresa.
A restauração aceita apenas os campos/valores previstos. Nomes buscados e notas
clínicas não são persistidos nessa preferência. O último canal de contato
selecionado pode ser reaproveitado; o resultado do contato não é presumido.

Criação de tarefa mantém a pessoa da ficha e sugere o próprio profissional
quando existe uma única combinação válida de profissional/equipe. A seleção
continua editável e é validada no backend.

## Prontuário e demonstração

O resumo do paciente inicia compacto, exibindo atendimento, retorno, reavaliação,
plano em destaque e contagem de metas. Detalhes completos continuam acessíveis.
O atalho Reavaliar plano abre a revisão existente, sem registrar avaliação
automaticamente.

Na demonstração pública, formulários compartilhados ficam desabilitados com
aviso antes do preenchimento. Ações de gravação na fila e acesso a salas externas
também ficam desabilitados. A aplicação local mantém as gravações normais.

## Arquivos e validação

Backend: `NursingWorklistService.php`, testes de domínio, HTTP e fixtures.
Frontend: `NursingWorklistView.vue`, `PatientView.vue`, `PatientSummary.vue`,
`ProfessionalAgendaView.vue`, `FormPanel.vue`, `AppointmentCard.vue`,
`FindCareView.vue`, estilos, adaptador da demonstração e utilitário `worklist.js`.

Validação executada:

- 180 verificações combinadas de domínio/resumo/fila, incluindo MySQL temporário.
- 283 verificações HTTP, incluindo identidade da sessão, CSRF e bloqueio de tomada
  de caso atribuído a outro profissional.
- 28 testes frontend, incluindo restauração de filtros e escolha do próximo item.
- Build de produção e checagem de navegador nos sete perfis.
- No navegador: assumir e confirmar sem abrir ficha, preservar filtro ao navegar,
  contato direto da fila, salvar e seguir, reavaliação direta e abertura autorizada
  de teleconsulta em uma ação. O popup foi simulado para não acessar provedor real.
- Demonstração: mesmos perfis, leitura do resumo e formulários bloqueados antes
  do preenchimento.
