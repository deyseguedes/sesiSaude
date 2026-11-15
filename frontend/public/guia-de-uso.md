# VIVA+ ELO — Guia completo de uso
Manual operacional por perfil • edição de 14/11/2026

Este guia orienta o uso da versão atual: acesso, cadastro, gestão, atendimento, continuidade, educação, campanhas e demonstração. As decisões clínicas e os procedimentos institucionais continuam sob responsabilidade da equipe habilitada.

## 1. Comece por aqui

O VIVA+ ELO organiza o cuidado de trabalhadores das indústrias contratantes e seus dependentes. O fluxo central é: **cadastro validado → agendamento → atendimento realizado → risco e retorno confirmados pelo médico → acompanhamento da enfermagem → retorno registrado**.

### 1.1 Identifique o ambiente

| Ambiente | Entrada | Grava alterações? | Dados |
| --- | --- | --- | --- |
| Demonstração interativa no ngrok | Seleção entre oito perfis, sem usuário ou senha | Roteiro por visita; cadastros legados compartilhados | Fictícios, em banco separado |
| Aplicação local no computador | http://vivaelo.test; seleção por perfil habilitada nesta instalação | Sim | Banco da instalação local |
| Aplicação pela mesma rede Wi-Fi | http://192.168.106.96; login cadastrado | Sim | Banco da instalação local |
| Demonstração estática anterior do Sites | Seleção de perfil | Não | Cenário fictício fixo, somente consulta |

**Link interativo atual:** https://acclaim-heftiness-clever.ngrok-free.dev

O endereço ngrok pode mudar. Se aparecer a tela do serviço, toque em **Visit Site**. Para o link funcionar, o computador, Apache, MySQL e ngrok precisam estar ligados.

**Na demonstração interativa, use apenas dados inventados.** Não informe nomes, contatos, diagnósticos ou situações reais. Outras pessoas podem ver e alterar os mesmos registros fictícios.

### 1.2 Primeiro acesso

1. Abra o link e leia o aviso do ambiente.
2. Escolha Administrador SESI, Gestor empresarial, Médico, Enfermeiro, Navegador de Cuidado, Trabalhador ou Dependente.
3. Confira o perfil exibido no topo.
4. Explore os menus disponíveis.
5. Para mudar, use **Trocar perfil** ou **Sair** e selecione outro.

A seleção demonstrativa não comprova identidade ou habilitação profissional. Em uma implantação com dados reais, use as contas e permissões institucionais.

### 1.3 Por onde começar?

| Objetivo | Caminho |
| --- | --- |
| Conhecer a experiência do trabalhador | Trabalhador → Agendar e retornos |
| Demonstrar o ciclo assistencial | Médico → Pacientes; depois Enfermeiro → Fila do dia |
| Preparar uma indústria | Administrador SESI → Gestão |
| Gerenciar cadastros e campanhas | Gestor empresarial → Gestão e Campanhas |
| Acolher dificuldades e retornos | Navegador → Retornos e Acolhimento |
| Conhecer o reconhecimento coletivo | Gestão → Prêmio da equipe de saúde |

## 2. Perfis e responsabilidades

O **perfil** descreve a função; o **nível de gestão** acrescenta responsabilidades administrativas. Gestão não concede acesso automático ao prontuário.

| Perfil | Uso principal | Limite |
| --- | --- | --- |
| Administrador SESI | Indústrias autorizadas, estrutura, profissionais, equipes, contratos e acessos | Não recebe prontuários apenas por ser administrador |
| Gestor empresarial | Cadastro, vínculo empresarial, contratos, campanhas e agregados da empresa | Sem prontuário, risco, plano ou motivos clínicos |
| Médico | Pacientes vinculados, resumo, evolução, plano, risco e retorno | Exige vínculo assistencial |
| Enfermeiro | Fila, acompanhamento, registros de enfermagem, contatos e retornos | Consulta risco confirmado, mas não o altera |
| Navegador de Cuidado | Acolhimento administrativo, barreiras e campanhas vinculadas | Sem notas clínicas ou alteração de risco |
| Trabalhador | Seus atendimentos, apoio, conteúdos e campanhas | Próprio espaço e representações autorizadas |
| Dependente | Seu espaço individual conforme cobertura | Dados de adulto não são compartilhados automaticamente com o titular |

### 2.1 Níveis de gestão

- **Sem gestão:** recursos próprios do perfil.
- **Coordenação APS:** médico/enfermeiro com funções administrativas adicionais; agendas e vínculos limitados às equipes autorizadas.
- **Gestão empresarial:** operação administrativa da indústria autorizada.
- **Gestão da plataforma:** estrutura, profissionais e níveis nas empresas autorizadas.

Botões e abas variam conforme permissões. Antes de pedir ajuste, confira perfil e empresa selecionados.

### 2.2 Vínculos que precisam ser conferidos

Cadastro na empresa, cobertura contratual e vínculo assistencial com equipe são condições diferentes. Um trabalhador pode ter cadastro ativo e ainda não estar autorizado para determinada equipe.

Antes de atender ou registrar: confirme pessoa, empresa, equipe e autorização. Conhecer o endereço de um prontuário não concede acesso.

## 3. Navegação e atalhos

| Perfil | Menus mais usados |
| --- | --- |
| Trabalhador e dependente | Início, Agendar e retornos, Meu cuidado |
| Médico | Início, Pacientes, Minha agenda, Retornos; demais conforme acesso |
| Enfermeiro | Início com Fila do dia, Pacientes, Minha agenda, Retornos |
| Navegador | Início, Retornos, Acolhimento |
| Administração e gestão | Início, Gestão, Campanhas, Conteúdos |

### 3.1 Guia interativo na própria tela

Toque em **Guia na tela** para iniciar uma orientação sobre a página atual. O guia destaca uma área e mostra sua função. Use **Próximo**, **Anterior** e **Concluir guia**, ou feche no botão de fechar/Escape. Nenhum dado é preenchido ou salvo automaticamente.

Você pode clicar e preencher enquanto o guia permanece aberto. Ele acompanha a troca de tela, abas, seções do prontuário, fichas e formulários. Em formulários, oferece orientações campo a campo; **Preencher este campo** posiciona o cursor, sem alterar o conteúdo. Os atalhos para abrir telas ou fichas exigem seu clique. Salvar, confirmar, enviar ou publicar continuam sob seu controle.

Em **Explorar outra tela**, escolha uma página disponível para seu perfil e continue a orientação. O guia não copia os valores preenchidos para suas explicações.

O conteúdo acompanha seu perfil: enfermagem recebe o roteiro da fila; médico recebe orientações clínicas; trabalhador recebe orientações de atendimento; gestão recebe seu fluxo administrativo. Dentro de uma ficha ou formulário, o ícone de livro no cabeçalho abre o **Guia deste painel**.

Para consultar os 18 capítulos sem sair da aplicação, escolha **Ver manual completo aqui** no guia. O manual abre em um painel sobre a tela atual. Feche o painel para continuar seu trabalho no mesmo lugar.

### 3.2 Roteiro da banca, importação e resultados

A atualização de 15/11/2026 inclui 100 pessoas sintéticas e Marcos (DEMO-0001). Não foi usada uma planilha da organização. Na entrada, **Começar a demonstração** restaura o roteiro da visita e abre a fila. Os três botões permitem seguir para celular ao vivo e resultados.

- **Enfermagem:** Marcos abre a convocação sem resposta. Clique em “Por que aparece nesta posição?” para ver prazo e tempo sem resposta. Confirmar o tira dessa convocação; uma barreira voluntária o mantém na fila. Outras pendências assistenciais continuam disponíveis.
- **Trabalhador:** o celular do Marcos oferece Confirmo, Preciso remarcar, Não posso ir e apoio. Remarcar oferece horários da tabela de disponibilidade após o turno. O envio externo é simulado e a escolha não ocupa agenda real. Carla e Seu José continuam no link “Outros exemplos”.
- **Modo ao vivo:** a equipe autorizada vê o celular e a fila lado a lado, atualizada a cada dois segundos. O trabalhador sozinho não recebe a fila de outras pessoas.
- **Gestão SESI:** funil de devidos, mensagens, respostas, confirmação/remarcação e comparecimento; participação, adesão e risco; estimativa operacional e barreiras agregadas. Há filtros de período e empresa.
- **Gestor empresarial:** boletim separado, sem risco, plano ou motivos clínicos. Recortes menores que dez pessoas são suprimidos na API.
- **Campanhas:** cinco históricos simulados e uma convocação ao vivo. Abra um cartão para conferir fórmula, comparação, intervalo de confiança, travas e sugestão. A coordenação registra decisão e justificativa; uma nova versão preserva o histórico.
- **Importar exportação:** CSV/XLSX, mapeamento e prévia. Matrícula, nome, empresa e status são obrigatórios. A gravação importa válidas e informa erros por linha. O gerador só completa cuidado vazio na demonstração.
- **Médico:** abre na carteira e no resumo antes da consulta.
- **Líder:** o cenário acessível de Carla e Seu José mostra somente nome e horário autorizados. Nova resposta sem autorização retira o aviso.
- **Prêmio coletivo:** Equipe Norte é exemplo independente: 54,9% contra 46,0%, ganho de 8,9 pontos e faixa de 3%. Não é pagamento nem apuração das 100 pessoas.

**Restaurar demonstração** reinicia o roteiro por sessão. Importações, respostas e decisões de campanhas são individuais; prontuários, cadastros e agendas legados continuam compartilhados. A restauração não apaga esses registros. O banco real do Laragon não recebe a carga sintética.

Para gravação, use /entrar/enfermeiro?ir=/live&restaurar=1&gravacao=1. O modo mantém o selo Simulação e oculta ajuda e banners. Vídeos são produzidos pela equipe fora da plataforma; links YouTube e Drive podem ser incorporados no editor. Sem vídeo configurado, o bloco não aparece.

### 3.3 Menos cliques

- **O que você precisa fazer?** localiza ações do perfil.
- **Ctrl + K** abre a busca de ações no computador.
- A busca clínica permite abrir o paciente diretamente em evolução ou medição.
- Ações na linha de uma pessoa já abrem o contexto correto.
- **Ctrl + Enter** salva formulários que apresentam esse aviso.
- **Salvar e abrir próxima** continua o trabalho na fila.
- Uma opção obrigatória única pode vir selecionada; autorizações devem ser conferidas por você.

### 3.3 No celular

1. Abra o endereço no navegador.
2. Use a navegação inferior para os acessos frequentes.
3. Toque em **Mais** para os outros menus.
4. Role as fichas para consultar ações e histórico.
5. Aguarde **Salvando…** ou **Atualizando os registros…** antes de continuar.
6. Evite tocar várias vezes em salvar ou confirmar.

Se houve demora, procure o registro antes de repetir a ação.

## 4. Administrador SESI — preparar a operação

**Caminho: Gestão.** As abas são **Pessoas**, **Unidades e equipes**, **Contratos**, **Agenda** e **Acessos**, conforme nível.

### 4.1 Sequência de implantação

1. Selecione ou cadastre a indústria autorizada.
2. Cadastre a unidade industrial.
3. Cadastre contrato vigente e coberturas.
4. Cadastre profissionais.
5. Crie equipe e vincule os membros.
6. Cadastre local de atendimento.
7. Disponibilize horários.
8. Emita convites.
9. Valide cadastros recebidos.
10. Confira os vínculos assistenciais.

Essa ordem evita listas vazias no cadastro e no agendamento.

### 4.2 Indústria e unidades

Use **Nova indústria** quando disponível. Informe o nome, salve e confira a empresa selecionada.

Em **Unidade industrial**, preencha nome, endereço e CEP. Informe latitude/longitude nas opções complementares somente quando verificadas.

A unidade industrial representa o vínculo do trabalhador. O local de atendimento representa onde a consulta acontece; podem ser lugares diferentes.

### 4.3 Contrato APS

1. Abra **Contratos**.
2. Informe identificação e vigência.
3. Defina a situação.
4. Confira cobertura presencial, teleconsulta e dependentes.
5. Salve.
6. Para alterar, edite o contrato existente e confira a versão atual.

Contrato vencido, pausado ou sem a modalidade pode impedir novas reservas. Cobertura não substitui a validação individual de elegibilidade.

### 4.4 Profissionais e níveis

Em **Usuários e níveis** ou **Acessos**:

1. Informe nome, e-mail e senha inicial exigida.
2. Escolha o perfil profissional.
3. Defina nível de gestão compatível.
4. Salve.
5. Vincule a pessoa à equipe.

A senha inicial de uma conta é diferente do seletor demonstrativo. Não conceda administração a todos para contornar falta de vínculo.

### 4.5 Equipes e autorização assistencial

1. Use **Criar equipe**.
2. Em **Vincular profissional**, selecione equipe e membro.
3. Em **Vínculo assistencial**, escolha beneficiário e equipe.
4. Verifique a autorização antes de marcá-la como conferida.
5. Salve o vínculo ativo.
6. Para revogar, desmarque o vínculo conforme o procedimento institucional.

A revogação retira o acesso ao paciente; não deve ser interpretada como exclusão do histórico.

### 4.6 Locais e horários

1. Cadastre **Local de atendimento** com tipo, endereço e CEP.
2. Em **Disponibilizar horário**, escolha profissional, equipe e local.
3. Defina modalidade, início e término.
4. Salve e confira a agenda.

O profissional deve estar ativo e na equipe. Conflitos exigem revisão do horário. Não duplique o profissional para contorná-los.

Para vários cadastros, utilize **Continuar cadastrando com os mesmos vínculos** quando disponível.

## 5. Gestor empresarial — cadastro e elegibilidade

O gestor organiza a operação da empresa. Informações clínicas permanecem com a equipe de saúde.

### 5.1 Convidar trabalhador ou dependente adulto

1. Confira a empresa.
2. Use **Convidar trabalhador ou dependente** ou **Convite de cadastro**.
3. Escolha o tipo de pessoa.
4. Informe o e-mail.
5. Selecione contrato vigente e unidade.
6. Para dependente, escolha o titular.
7. Salve e copie o link.
8. Entregue-o apenas ao destinatário pelo canal apropriado.

O convite atual é nominal, de uso único e válido por 48 horas. Não há envio automático por WhatsApp, SMS ou e-mail. Se expirar, emita outro.

### 5.2 Validar cadastro

1. Em **Pessoas**, filtre **Para validar**.
2. Localize nome/e-mail e abra **Validar cadastro**.
3. Verifique o contato.
4. Confirme vínculo e cobertura.
5. Marque somente as verificações realizadas.
6. Use **Validar e liberar**.

Receber um cadastro não comprova, sozinho, vínculo ou titularidade do contato.

| Situação | Significado |
| --- | --- |
| Pendente | Aguarda verificações |
| Ativo | Vínculo liberado, sujeito às demais regras |
| Suspenso | Pode impedir novas reservas; histórico permanece |

Use os filtros **Todos**, **Para validar**, **Ativos** e **Suspensos**. Suspenda somente a pessoa correta e conforme o procedimento; suspensão não apaga prontuário.

### 5.3 Dependente menor

1. Abra **Cadastrar dependente menor**.
2. Escolha o titular.
3. Informe nome e nascimento.
4. Registre a referência da verificação institucional.
5. Marque a representação depois de conferi-la.
6. Salve.

O cadastro autônomo desta versão é destinado a adultos. Não use uma conta adulta compartilhada para representar um menor sem autorização registrada.

### 5.4 Agregados da empresa

Confira empresa e período antes de interpretar resultados. Grupos pequenos podem ter indicadores suprimidos.

**Grupo pequeno não significa zero.** Não tente identificar pessoas comparando filtros. O boletim empresarial completo com o novo mínimo proposto de dez pessoas ainda está previsto como evolução.

## 6. Trabalhador — seu cuidado

### 6.1 Cadastro por convite

1. Abra o link recebido e confira se corresponde a você.
2. Informe nome, nascimento, contato, CEP e preferência de período.
3. Crie a senha quando solicitada.
4. Leia o aviso e conclua.
5. Aguarde validação da gestão.

Para conhecer a demonstração, basta escolher **Trabalhador**. Não cadastre dados reais para testar.

### 6.2 Encontrar atendimento

**Caminho: Agendar e retornos.**

1. Confira quem será atendido.
2. Escolha presencial ou teleconsulta.
3. Selecione médico, enfermeiro ou ambos, quando disponível.
4. Escolha primeiro horário ou proximidade.
5. Ajuste a referência: unidade industrial ou localização autorizada.
6. Se desejar, marque **Mostrar somente minha equipe APS**.
7. Aguarde os resultados; a busca acompanha os filtros.

Distâncias dependem das coordenadas disponíveis e são aproximadas, em linha reta. Não representam trajeto ou tempo de viagem. Se a localização falhar, utilize a unidade industrial ou o primeiro horário.

### 6.3 Reservar horário

1. Selecione um horário disponível.
2. Confira pessoa, profissional, modalidade, local e data.
3. Leia a autorização de vínculo assistencial.
4. Marque somente se concordar.
5. Toque em **Confirmar agendamento**.
6. Aguarde sucesso e confira sua lista.

Um horário exibido ainda não é uma reserva. Se outra pessoa o ocupar antes, atualize e escolha outro.

### 6.4 Confirmar retorno e pedir apoio

Nos seus atendimentos:

- **Confirmar retorno:** confirma a programação quando disponível.
- **Preciso de apoio:** registra dificuldade para a equipe acompanhar.
- **Entrar na teleconsulta:** verifica acesso à sala configurada.

Ao pedir apoio, você pode indicar horário/rotina, transporte, contato, questão pessoal ou preferência por não informar. Evite diagnósticos e exames no texto administrativo.

O pedido não inicia conversa instantânea nem envia mensagem externa automaticamente. Pedir apoio não altera, sozinho, a data da consulta. Confira a remarcação com a equipe.

### 6.5 Conteúdos e campanhas

**Caminho: Meu cuidado.**

1. Leia os ciclos disponíveis.
2. Use **Quero participar** quando tiver interesse.
3. Abra o material ou vídeo.
4. Consulte **Texto / transcrição acessível** se preferir.
5. Marque **Concluí este conteúdo** depois de acompanhá-lo.
6. Ao terminar os materiais do ciclo, use **Concluí os conteúdos do ciclo**.
7. Se não quiser participar, use **Não participar agora**.

Concluir material é uma declaração de participação, não comprovação de melhora clínica ou consulta realizada.

### 6.6 Minha jornada de cuidado

Quando a empresa adere:

| Conquista | Pontos | Evidência |
| --- | --- | --- |
| Consulta marcada | 10 | Uma consulta registrada para o próprio trabalhador |
| Presença confirmada | 20 | Comparecimento registrado após o horário |
| Cuidado em continuidade | 30 | Evolução profissional/revisão em outro dia, depois do primeiro comparecimento |

Cada conquista vale uma vez; máximo de 60 pontos nessa jornada. Agendar repetidamente ou atualizar a tela não multiplica pontos.

São pontos simbólicos, sem valor financeiro ou mudança de prioridade clínica. Na demonstração pública, essa jornada aparece ao lado dos critérios de elegibilidade semestral. O progresso de Marcos (2 de 4 check-ins) é sintético; não constitui apuração oficial nem benefício válido no RH.

## 7. Dependentes e família

Dependente adulto tem conta e espaço individuais. O titular não recebe automaticamente consultas, conteúdos ou prontuário desse adulto.

Quando houver cobertura, o trabalhador pode emitir convite de dependente adulto no fluxo de família. Confira o e-mail e entregue o link apenas ao destinatário.

Menores exigem representação verificada pela gestão. No agendamento, o responsável só escolhe pessoas que aparecem como autorizadas.

**Confira sempre quem será atendido.** Não registre um familiar no cadastro de outro. A jornada do trabalhador não soma utilização dos dependentes.

## 8. Médico — prontuário, risco e retorno

### 8.1 Abrir o paciente

1. No início clínico, busque a pessoa ou abra **Pacientes**.
2. Abra o acompanhamento e confira identidade/vínculo.
3. Consulte **Risco confirmado e próximo retorno**.
4. Leia o **Resumo do prontuário**.

Se alguém não aparecer, peça revisão do vínculo à coordenação. Não crie cadastro duplicado para contornar acesso.

### 8.2 Resumo antes da consulta

O resumo pode reunir último comparecimento, próximo atendimento, risco/retorno, plano, reavaliação, evolução, revisão, metas, medições e encaminhamentos.

Use **Ver resumo completo**, **Ler registro completo** e os atalhos para a origem. Confira autoria, data e origem. Autorrelato não é medição profissional; ausência de registro não comprova ausência de cuidado fora da plataforma.

### 8.3 Confirmar risco e próximo retorno

1. Selecione risco de 1 a 4.
2. Confira a data do retorno.
3. Se houver mais de uma equipe, selecione a referência.
4. Abra **Data da avaliação e observação** se precisar informar avaliação anterior.
5. Confira observação e datas.
6. Use **Salvar risco e retorno**.
7. Aguarde sucesso e confira o resumo atualizado.

A avaliação não pode ser futura, e o retorno deve ser posterior.

A referência da proposta sugere seis meses para riscos 1/2, três meses para risco 3 e data definida pelo médico para risco 4. **A referência não substitui protocolo institucional ou decisão profissional.** A data sempre exige confirmação médica.

Uma data já informada pode ser preservada quando o risco muda. Confira o campo antes de salvar; não presuma recálculo automático.

### 8.4 Revisões e histórico

Cada alteração gera versão e mantém antes/depois. Rebaixar o risco numérico exige justificativa. Se houver conflito, atualize o cartão, leia a mudança e decida o que registrar.

O sistema não infere risco de faltas, pontos ou campanhas.

### 8.5 Registros assistenciais

| Ação | O que conferir |
| --- | --- |
| Criar plano de cuidado | Equipe, responsável, início, reavaliação, objetivos, intervenções e situação |
| Registrar evolução | Paciente, plano selecionado, tipo permitido e texto com autoria |
| Registrar medição | Indicador, valor, origem, data/hora e método |
| Acompanhar metas | Alvo, direção, prazo, responsável e origem das medições |
| Reavaliar plano | Registro da revisão, resultado e próxima reavaliação |
| Programar retorno | Equipe, profissional, data e horário |
| Encaminhar | Destinatário e motivo no campo clínico restrito |

Com vários planos, confira qual está selecionado antes de salvar. **Dados insuficientes** não equivalem a meta atingida ou resultado zero. Preserve a distinção entre medição profissional e autorrelato.

### 8.6 Rotina recomendada

Antes: confira resumo, plano, metas e retorno anterior. Durante: registre evolução e medições pertinentes. Ao finalizar: revise o plano, confirme risco/data e confira quem organizará o agendamento.

Registrar risco e data prevista não reserva um horário automaticamente.

## 9. Enfermagem — fila do dia

**Caminho: Enfermeiro → Início / Fila do dia.**

A fila reúne retornos médicos, reavaliações, pedidos de apoio, consultas pendentes, encaminhamentos e tarefas. A posição é administrativa, não uma classificação de gravidade.

### 9.1 Começo do turno

1. Aguarde atualização da fila.
2. Confira pedidos de apoio e prazos.
3. Busque pessoa ou motivo.
4. Escolha o filtro.
5. Assuma ou abra o caso.
6. Registre contato e próximo passo.
7. Use **Salvar e abrir próxima** para continuar.

| Filtro | O que mostra |
| --- | --- |
| Todas abertas | Itens abertos e em acompanhamento |
| Minhas | Itens atribuídos a você |
| Prazo passado | Data prevista ultrapassada |
| Pedidos de apoio | Pedido expresso de ajuda |
| Devidos hoje | Data prevista igual a hoje |
| Contato perdido | Último contato: sem resposta ou contato inválido |
| Mais filtros | Equipe, profissional, unidade, prazo e situação |

Sem tentativa registrada não significa contato perdido. Um novo contato realizado retira o item desse filtro.

Filtros podem ser lembrados no mesmo navegador, por usuário/empresa. Se faltar alguém, volte a **Todas abertas** e confira os filtros adicionais.

### 9.2 Ficha de acompanhamento

Confira motivo, prazo, responsável, próximo atendimento, último comparecimento e histórico. Conforme acesso, consulte risco confirmado, plano e metas.

Diferencie tentativa, contato realizado, agendamento, confirmação e comparecimento.

### 9.3 Assumir e registrar contato

1. Use **Assumir caso** quando disponível.
2. Abra **Registrar contato**.
3. Escolha ligação, presencial ou outro canal registrado.
4. Selecione contato realizado, sem resposta ou contato inválido.
5. Registre o necessário.
6. Salve ou use **Salvar e abrir próxima**.

Não marque contato realizado apenas porque tentou. Registros administrativos não devem receber diagnósticos; use o espaço assistencial apropriado.

### 9.4 Retorno médico automático

Entra na fila o retorno vencido, devido hoje ou nos próximos sete dias, sem comparecimento médico posterior elegível.

A janela de sete dias organiza trabalho; não é tolerância clínica ou regra de prêmio.

Na ficha, use **Agendar retorno**: escolha médico da equipe, confira data/horário e salve. Verifique a programação.

**Agendar não encerra o retorno devido.**

### 9.5 Registrar comparecimento

Quando houver consulta elegível já ocorrida, aparece **Registrar comparecimento**:

1. Selecione a consulta correta.
2. Confira profissional e data.
3. Registre presença.
4. Atualize a fila quando necessário.

Para esse retorno médico, conta consulta médica com comparecimento registrado, não futura e em dia posterior à avaliação. A consulta do mesmo dia não é retorno subsequente. O acompanhamento de enfermagem permanece no plano, mas não conclui esse retorno médico específico.

A sincronização retira a pendência da fila ativa. Se a presença for corrigida/cancelada, ela pode voltar. Não se encerra um retorno devido com justificativa administrativa genérica.

### 9.6 Outras ações e tarefas

Conforme origem/permissão, a ficha oferece atribuição, reagendamento, dificuldade, encaminhamento e registro de enfermagem. Antes do registro assistencial, confira o plano ativo.

Para tarefa: selecione pessoa, tipo, título, prazo, responsável/equipe; salve e confira a inclusão.

Uma tarefa administrativa concluída pode ser finalizada com justificativa. Isso não substitui comparecimento médico.

### 9.7 Fim do turno

Revise **Minhas**, contatos sem resposta, responsáveis e prazos. Deixe próximo passo claro. Não finalize itens apenas para reduzir a fila.

## 10. Navegador de Cuidado — acolhimento

### 10.1 Retornos e barreiras

1. Abra **Retornos**.
2. Ajuste período e situação.
3. Abra a pessoa ou use ação direta.
4. Registre acolhimento, confirmação, barreira, reagendamento ou reconexão conforme o caso.
5. Atribua navegador vinculado quando necessário.
6. Salve e confira o histórico.

Respeite a preferência por não informar uma barreira. Se houver necessidade clínica, solicite revisão pela APS. Não registre diagnóstico em nota administrativa, nem altere risco/plano.

### 10.2 Acolhimento das campanhas

1. Abra **Acolhimento**.
2. Localize a pessoa e use **Abrir acompanhamento**.
3. Atualize a etapa segundo a manifestação dela.
4. Crie próximo passo com prazo e responsável.
5. Conclua ou adie tarefas atribuídas a você.

Se a pessoa optou por não participar, novos contatos da campanha ficam bloqueados até manifestação dela. Não crie outra tarefa para contornar a escolha.

## 11. Agenda e teleconsulta

### 11.1 Profissional

Em **Minha agenda**, confira paciente, modalidade, data e situação. A visão inclui atendimentos vinculados dos últimos sete dias aos próximos noventa dias.

Para teleconsulta:

1. Abra o atendimento.
2. Use **Configurar link restrito**, se necessário.
3. Informe a sala HTTPS do provedor autorizado e guarde.
4. Utilize **Entrar na sala**.

A plataforma organiza o acesso; a chamada ocorre no provedor informado. Guardar link não cria videoconferência automaticamente.

### 11.2 Trabalhador ou dependente

1. Abra o atendimento.
2. Confira horário e modalidade.
3. Use **Entrar na teleconsulta** quando disponível.
4. Permita abertura da sala.
5. Se houver bloqueio de janela, use a alternativa exibida.

No celular, confira conexão, bateria, câmera e microfone conforme o provedor. Não compartilhe sala com pessoas fora do atendimento.

### 11.3 Sala indisponível

Confira link configurado, situação do atendimento e autorização. Solicite apoio da equipe. Não substitua por endereço de consulta de outra pessoa.

## 12. Educação e campanhas por ciclos

### 12.1 Preparar conteúdo

**Caminho: Conteúdos.**

1. Abra criação de conteúdo.
2. Informe título e finalidade: plataforma ou continuidade da APS.
3. Preencha duração, resumo e texto/transcrição.
4. Adicione vídeo, quando houver.
5. Salve como rascunho.
6. Realize a revisão apropriada.
7. Publique pelo fluxo permitido ao perfil.

Conteúdo assistencial exige revisão clínica habilitada. Não marque revisão apenas para liberar publicação. A plataforma não grava vídeos por você; sem vídeo, o material pode ser disponibilizado em texto.

### 12.2 Criar ciclo

**Caminho: Campanhas → Novo ciclo.**

1. Informe nome, edição e objetivo.
2. Defina início/fim.
3. Selecione unidade ou todas as unidades.
4. Escolha público: trabalhadores, dependentes ou ambos.
5. Associe conteúdos publicados.
6. Salve rascunho.
7. Confira e use **Ativar ciclo**.

Convites ficam dentro da plataforma; não há disparo externo automático.

### 12.3 Acompanhar e encerrar

Leia resultados agregados. Em ciclo ativo, **Incluir novos elegíveis** atualiza inclusões conforme vínculo. Ao concluir a edição, use **Encerrar ciclo**.

O histórico permanece. Participação, conteúdo concluído e resultado clínico são medidas distintas.

## 13. Gamificação e prêmio coletivo

### 13.1 Jornada do trabalhador

Em **Gestão → Gamificação do cuidado**, gestor empresarial ou administrador autorizado ativa/pausa para a empresa selecionada.

Ativar disponibiliza a jornada aos trabalhadores da empresa. Desativar oculta a jornada e pausa novos reconhecimentos, preservando histórico.

Isso não muda elegibilidade ou prioridade clínica e não libera dados individuais à gestão.

### 13.2 Bônus do médico substituído

Os antigos 30 pontos individuais por retomada não são mais gerados. O reconhecimento passou à equipe de saúde. O histórico não foi convertido em dinheiro ou prêmio coletivo.

### 13.3 Simular reconhecimento coletivo

**Caminho: Gestão → Prêmio da equipe de saúde → Simular cenário da equipe.**

1. Escolha equipe e período: trimestre, semestre ou regional.
2. Informe devidos na carteira congelada.
3. Informe exclusões com fonte e retornos no prazo.
4. Informe linha de base e satisfação.
5. Leia as verificações de carteira, linha de base, pressão, auditoria e autorizações.
6. Marque apenas condições correspondentes ao cenário.
7. Use **Simular prêmio coletivo**.

**RP = retornos no prazo ÷ devidos após exclusões.**

| Ganho sobre a linha de base | Faixa proposta |
| --- | --- |
| Menos de 4 pontos percentuais | 0% |
| De 4 até menos de 6 | 1% |
| De 6 até menos de 8 | 2% |
| De 8 até menos de 10 | 3% |
| De 10 até menos de 12 | 4% |
| A partir de 12 | 5% |

Excelência proposta: RP de 80%. Mínimo conservador do simulador: 300 retornos depois das exclusões; abaixo, orienta semestre e depois regional. Satisfação abaixo de 90% ou verificações não confirmadas bloqueiam o cenário.

### 13.4 Interpretar corretamente

| Resultado | Significado |
| --- | --- |
| Não apurável | Não há dados válidos suficientes |
| Bloqueado | Alguma condição informada não foi atendida |
| Faixa teórica | Percentual calculado para o cenário digitado |
| Pagamento não autorizado | Condição mantida por toda simulação |

Exemplo fictício: 320 devidos, 12 exclusões, 169 no prazo e linha de base de 46% resultam em denominador 308, RP aproximado de 54,87%, ganho de 8,87 pontos percentuais e faixa teórica de 3%, sujeita às verificações.

A simulação não comprova evidências, não arquiva apuração oficial, não calcula folha nem realiza pagamento. Parâmetros/regulamento propostos dependem de confirmação institucional.

## 14. Roteiros completos para treinamento

Use somente situações inventadas. Respostas e decisões do roteiro são por sessão; alterações legadas são compartilhadas.

### 14.1 Do cadastro ao atendimento

1. Como administrador/gestor autorizado, confira unidade e contrato.
2. Emita convite fictício e faça cadastro de adulto fictício.
3. Volte à gestão e pratique a validação demonstrativa.
4. Confira equipe, local e horário.
5. Como trabalhador, demonstre a escolha de horário no seu espaço.
6. Confira o resultado na agenda profissional.

O seletor público tem oito contas predefinidas, incluindo o líder demonstrativo. Uma pessoa criada pelo convite não vira automaticamente um novo botão de perfil; teste seu acesso próprio com apoio do responsável pelo treinamento.

### 14.2 Da consulta ao retorno

1. Como médico, abra paciente fictício e leia resumo/plano.
2. Registre evolução no plano correto.
3. Confirme risco/data apenas como exercício.
4. Como enfermeiro, localize o retorno na janela operacional.
5. Registre tentativa de contato e teste filtros.
6. Agende pela ficha.
7. Registre presença apenas em consulta fictícia já ocorrida e elegível.

Em ambiente real, não registre comparecimento inexistente para remover pendência.

### 14.3 Da campanha à participação

1. Prepare e publique conteúdo fictício.
2. Crie e ative ciclo com o material.
3. Como trabalhador elegível, registre interesse.
4. Acompanhe conteúdo e conclua o exercício.
5. Confira agregado na gestão e acompanhamento no perfil autorizado.

### 14.4 Apresentação em dez minutos

- Trabalhador: busca de atendimento e pedido de apoio.
- Médico: resumo e risco/retorno.
- Enfermagem: fila, filtros e ficha única.
- Navegador: barreiras e acolhimento administrativo.
- Gestão: cadastros, campanhas e simulação coletiva.
- Encerramento: explique dados fictícios e integrações futuras.

## 15. Solução de problemas

| Situação | O que conferir | Próximo passo |
| --- | --- | --- |
| Sem horários | Cadastro ativo, cobertura, modalidade e filtros | Atualize a busca; peça revisão de disponibilidades |
| Paciente ausente | Empresa, equipe e vínculo assistencial | Solicite revisão à coordenação |
| Horário indisponível | Reserva ou disponibilidade alterada | Busque outro |
| Registro alterado por outro profissional | Sua versão ficou antiga | Atualize, leia e decida antes de salvar |
| Permissão insuficiente | Perfil, nível e empresa | Confira seu espaço; solicite ajuste autorizado |
| Cadastro pendente/suspenso | Vínculo não liberado | Procure a gestão |
| Convite inválido/expirado | Validade ou uso anterior | Solicite novo convite nominal |
| Grupo pequeno | Proteção contra identificação | Não interprete como zero |
| Não apurável | Dados insuficientes | Reúna dados; não preencha lacunas com zero |
| Serviço indisponível | Conexão/API | Confira internet e avise responsável técnico |
| Sessão inválida | Sessão/segurança expirou | Atualize e entre novamente |
| Link ngrok indisponível | Serviços desligados ou endereço mudou | Solicite endereço atual e conferência do servidor |

### 15.1 Computador funciona, celular não

- Com IP local, use o mesmo Wi-Fi.
- Com ngrok, pode usar outra rede com internet.
- Confira endereço completo/atual e avance em **Visit Site**, quando aparecer.
- Informe a tela e a mensagem ao responsável, sem enviar senha ou captura com dados pessoais.

### 15.2 Antes de repetir uma ação

Confira sucesso e histórico. Se o registro existe, não repita. Em conflito, atualize antes. Em falha persistente, siga o procedimento institucional de suporte.

### 15.3 Por que a função não aparece?

Pode depender de perfil, nível de gestão, empresa, equipe, cobertura, vínculo ou situação do registro. A demonstração pode ter sido alterada por outro visitante.

Não há botão global de restauração na interface. Para reiniciar um cenário fictício de treinamento, procure o responsável técnico.

## 16. Dados e uso responsável

- Confira identidade antes de registrar contato, atendimento, medição ou vínculo.
- Use somente pacientes e funções autorizados.
- Coloque informações clínicas no prontuário, não em notas administrativas.
- Respeite a opção de não participar ou não informar barreira.
- Não distribua convites nominais, salas ou capturas de prontuário fora do fluxo.
- Use **Sair** ao terminar em dispositivo compartilhado.
- Na demonstração sem senha, mantenha tudo fictício.

A plataforma registra autoria/histórico. Isso ainda não representa auditoria pós-quântica assinada.

## 17. Disponível, simulado e previsto

| Recurso | Situação nesta edição |
| --- | --- |
| Empresas, contratos, unidades, profissionais e equipes | Disponível por permissão |
| Cadastro remoto e validação | Disponível |
| Busca, agendamento e teleconsulta por link | Disponível |
| Prontuário, resumo, evolução, metas e indicadores | Disponível à equipe vinculada |
| Risco confirmado e próximo retorno | Disponível; confirmação médica |
| Fila, contatos, tarefas e agendamento na ficha | Disponível |
| Conteúdos e campanhas | Disponível; materiais precisam de preparação/revisão |
| Jornada simbólica do trabalhador | Disponível quando a empresa adere |
| Prêmio coletivo | Simulação manual; sem apuração oficial ou pagamento |
| Importação APS e devolução | CSV/XLSX validados e CSV de retorno; integração automática com SOC/S+ não implantada |
| WhatsApp e áudio | Canal simulado disponível na demonstração; sem integração externa ou transcrição automática |
| Painel do líder e autorização de aviso | Cenário demonstrativo com nome e horário; a última resposta com autorização desmarcada retira o aviso |
| Elegibilidade semestral e benefício/RH | Critérios e código ilustrativo disponíveis na demonstração; apuração oficial e resgate real pendentes |
| Público congelado de campanhas | Disponível no início do experimento; fechamento oficial de prêmio ainda não disponível |
| Boletim completo com mínimo proposto de dez pessoas | Previsto |
| PostgreSQL e arquitetura pós-quântica completa | Evolução documentada; instalação atual usa MySQL |

Uma proposta escrita não equivale a uma função entregue. Confira este quadro antes de apresentar um recurso.

## 18. Glossário e checklists

### 18.1 Glossário

| Termo | Significado |
| --- | --- |
| APS | Atenção Primária à Saúde |
| Elegibilidade | Condição administrativa para utilizar a cobertura |
| Vínculo assistencial | Relação autorizada entre paciente e equipe |
| Retorno previsto | Data definida; pode não ter horário reservado |
| Agendamento | Reserva de profissional, data e horário |
| Confirmação | Confirmação da programação; não é presença |
| Comparecimento | Atendimento realizado e registrado |
| Evolução | Registro assistencial com autoria e data |
| Indicador | Medida com tipo, método e origem |
| Meta | Objetivo acompanhado por regra e indicador |
| Autorrelato | Informação declarada, separada da coleta profissional |
| RP | Proporção de retornos no prazo no cenário |
| Linha de base | Referência de desempenho do cenário |
| Pontos percentuais | Diferença entre percentuais, distinta do aumento percentual relativo |

### 18.2 Checklist do trabalhador

- Pessoa, horário e modalidade conferidos.
- Apoio solicitado se necessário.
- Participação registrada após acompanhar o material.
- Compreensão de que pontos e campanhas não substituem cuidado.

### 18.3 Checklist assistencial

- Identidade/vínculo conferidos.
- Resumo e plano adequados consultados.
- Autoria, origem e fatos registrados.
- Confirmação, agendamento e presença diferenciados.
- Próximo passo, prazo e responsável definidos.
- Risco/retorno confirmados no fluxo médico.

### 18.4 Checklist da gestão

- Empresa/contrato corretos.
- Contatos, cobertura e representação verificados.
- Equipes e disponibilidades organizadas.
- Materiais revisados antes da publicação.
- Indicadores lidos como agregados.
- Prêmio apresentado como simulação.

### 18.5 Checklist do servidor

- Computador conectado.
- Apache e MySQL ativos.
- ngrok ativo quando necessário.
- Endereço atual informado.
- Base fictícia separada da base local.
- Atualizações aplicadas pelo fluxo correto.

Comece pelo capítulo do seu perfil e pratique com dados fictícios. Para suporte, informe tela, ação tentada e mensagem, sem compartilhar senhas ou dados clínicos fora do fluxo institucional.
