import {detailGuide,formGuide} from './guide-contexts.js'
const step=(target,title,text,action)=>({target,title,text,...(action?{action}:{})})
export function guideFor(user,path,panel='',context={}) {
 if(context.form?.fields?.length)return formGuide(context.form)
 const detailed=detailGuide(user,path,context);if(detailed)return detailed
 if(panel)return [
  step('dialog.side-panel[open] .side-panel-header','Confira o contexto',panel==='Ficha de acompanhamento'?'Esta ficha pertence à pessoa indicada no cabeçalho. Confira o nome antes de registrar.':'Você está em “'+panel+'”. Confira a pessoa, a empresa e o objetivo do formulário.'),
  step('dialog.side-panel[open] .side-panel-body',panel==='Ficha de acompanhamento'?'Acompanhe pela mesma ficha':'Preencha e confira',panel==='Ficha de acompanhamento'?'Leia prazo, próximo atendimento e histórico. Use Registrar contato, Agendar retorno ou Registrar comparecimento quando disponíveis. Agendamento não comprova presença.':'Preencha os campos necessários. Confira as opções já selecionadas e marque autorizações somente depois de verificá-las.'),
  step('dialog.side-panel[open] .form-actions','Salve quando estiver pronto','Use Salvar para registrar. Na fila, Salvar e abrir próxima continua o trabalho. Este guia apenas orienta; ele não preenche nem envia o formulário.')
 ]
 if(path==='/register')return [step('#main-content h1','Cadastro por convite','Confira se o convite recebido corresponde a você. Na demonstração, utilize apenas dados fictícios.'),step('#main-content form','Preencha seus dados','Confira nome, nascimento, contato e as informações solicitadas. A ativação depende da validação da gestão.'),step('#main-content form','Leia antes de concluir','Confira o aviso e conclua quando os dados estiverem corretos. Convite inválido ou expirado exige uma nova emissão.')]
 if(!user||path==='/login')return [
  step('.demo-entry, #email','Entre no espaço correto','Na demonstração, escolha um dos perfis disponíveis. Em um ambiente com contas reais, utilize seu acesso cadastrado.'),
  step('.demo-profile, #password','Confira o ambiente','A demonstração interativa usa dados fictícios e grava alterações compartilhadas. Não informe dados reais nela.'),
  step('.demo-entry, form','Comece pelo seu perfil','Feche o guia e escolha seu perfil. Depois, use Guia na tela novamente para receber orientações sobre a página em que estiver.')
 ]
 if(path==='/worker-channel')return [step('#channel-person','Escolha Carla ou Seu José','Este canal simula mensagens neutras. Carla usa botões; Seu José pode ouvir e enviar um áudio de exemplo.'),step('[data-testid="worker-channel"]','Ouça e responda','Use Ouvir mensagem ou os botões. Cada resposta escolhida atualiza somente a fila demonstrativa. Não há envio ao WhatsApp nem gravação do microfone.'),step('.workspace-content','Confira a fila','Na enfermagem, a resposta aparece automaticamente em até dois segundos. Confirmação não comprova comparecimento.')]
 if(path==='/'&&user.role==='leader')return [step('.workspace-content h1','Apoie sem acessar prontuários','O líder recebe somente horários autorizados; não recebe diagnóstico nem risco.'),step('.workspace-content section','Confira a autorização','A lista usa a autorização da última resposta no canal simulado. Enviar nova resposta sem autorização remove o aviso.'),step('.workspace-content','Conheça o kit do líder','Facilite a rotina sem pressionar. A participação é agregada e o reconhecimento não é isolado.')]
 if(path==='/security')return [step('.workspace-content h1','Conheça os limites de acesso','Confira o que cada perfil pode consultar.'),step('.workspace-content section','Proteções atuais e propostas','Leia o que já existe e o que ainda depende de implantação, incluindo a proposta pós-quântica.')]
 const clinical=['doctor','nurse'].includes(user.role)
 if(/^\/patients\/\d+$/.test(path)&&clinical)return [
  step('.workspace-content h1','Confira o paciente','Confirme a pessoa antes de consultar ou registrar. Seu acesso depende do vínculo assistencial.'),
  step('[data-testid="clinical-return"]',user.role==='doctor'?'Confirme risco e retorno':'Consulte o retorno médico',user.role==='doctor'?'Selecione o risco confirmado, confira a próxima data e salve. Rebaixar o risco exige justificativa. A data precisa ser confirmada pelo médico.':'Consulte o risco e a data definidos pelo médico. A enfermagem organiza o acompanhamento, mas não altera o risco confirmado.'),
  step('[data-testid="patient-summary"]','Leia o resumo','Veja comparecimentos, plano e últimos registros. Use Ver resumo completo para expandir e confira autoria, data e origem.'),
  step('.clinical-quick-actions','Registre no contexto certo','Use os atalhos de evolução, medição, revisão e metas. Confira o plano selecionado antes de salvar.')
 ]
 if(clinical&&(path==='/nursing-worklist'||(path==='/'&&user.role==='nurse')))return [
  step('.workspace-content h1','Sua fila do dia','Aqui estão os próximos passos de acompanhamento. A ordem é administrativa; não representa gravidade clínica.'),
  step('.filter-chips','Encontre quem precisa de acompanhamento','Use Minhas, Prazo passado, Pedidos de apoio, Devidos hoje ou Contato perdido. Um contato realizado depois de uma tentativa sem resposta retira o item de Contato perdido.'),
  step('#queue-search','Busque uma pessoa','Digite o nome ou o motivo da pendência. Se a lista parecer incompleta, confira também os filtros.'),
  step('.worklist-row','Abra a ficha','Clique no nome ou em Registrar contato. Na ficha, faça contato, organize o agendamento e registre o próximo passo. O guia continuará dentro da ficha.',{kind:'open',label:'Abrir ficha comigo',target:'.worklist-person button'})
 ]
 if(path==='/find-care'&&['worker','dependent'].includes(user.role))return [
  step('.workspace-content h1','Organize seu atendimento','Confira quem será atendido e escolha presencial ou teleconsulta. A cobertura depende do vínculo e do contrato.'),
  step('.workspace-content .form-select','Ajuste a busca','Escolha pessoa, profissional e modalidade nos filtros disponíveis. Os resultados se atualizam sem um botão extra de pesquisa.'),
  step('.workspace-content','Escolha e confirme','Abra um horário, confira os dados e confirme a autorização da equipe antes de reservar. Se faltar horário, revise os filtros ou peça apoio.'),
  step('.appointment-card','Acompanhe o retorno','No atendimento, confirme a programação, peça apoio ou entre na teleconsulta quando a ação estiver disponível. Pedir apoio não remarca sozinho.')
 ]
 if(path==='/management'&&user.management_level&&user.management_level!=='none')return [
  step('.workspace-content h1','Confira a empresa','Antes de cadastrar ou alterar, confirme a indústria selecionada e seu nível de gestão. Gestão não concede acesso ao prontuário.'),
  step('.section-tabs','Escolha a área','Use Pessoas, Unidades e equipes e Contratos. Agenda e Acessos aparecem somente para quem tem permissão.',{kind:'open',label:'Conhecer Contratos',target:'.section-tabs [data-guide-tab="contracts"]'}),
  step('#people-search','Localize o cadastro','Busque por nome ou e-mail e use os filtros de situação. Valide contato e vínculo antes de liberar o cadastro.'),
  ...(['admin','manager'].includes(user.role)?[step('[data-testid="company-gamification"]','Gamificação e reconhecimento','A empresa decide sobre a jornada do trabalhador. O prêmio coletivo é uma simulação e não autoriza pagamento.')]:[])
 ]
 if(path==='/my-care')return [
  step('.workspace-content h1','Seu cuidado','Aqui ficam os conteúdos e campanhas disponíveis para seu vínculo.'),
  step('.workspace-content','Participe no seu ritmo','Leia o ciclo, registre interesse e acompanhe os materiais. Você também pode escolher não participar agora.'),
  step('.workspace-content video, .workspace-content details','Leia ou assista','Use o vídeo ou a transcrição disponível. Marque conclusão depois de acompanhar. Isso não comprova melhora clínica nem consulta realizada.')
 ]
 if(path==='/my-agenda'&&clinical)return [
  step('.workspace-content h1','Confira sua agenda','Veja seus atendimentos vinculados, datas, modalidade e situação.'),
  step('.workspace-content .card','Abra o atendimento','Confira a pessoa. Na teleconsulta, configure o link restrito do provedor autorizado quando necessário.'),
  step('.workspace-content','Entre na sala','Use Entrar na sala no momento apropriado. A chamada acontece no provedor configurado, fora da plataforma.')
 ]
 if(path==='/continuity'&&(clinical||user.role==='navigator'))return [
  step('.workspace-content h1','Acompanhe os retornos','Confira o período e a situação antes de interpretar os casos.'),
  step('.workspace-content','Acolha e reconecte','Abra a pessoa e registre confirmação, barreira, reagendamento ou acolhimento, conforme a situação real.'),
  step('.workspace-content','Separe o administrativo do clínico','Não escreva diagnósticos em notas administrativas. Encaminhe necessidades clínicas à APS e respeite a opção de não informar barreira.')
 ]
 if(path==='/engagement'&&(clinical||user.role==='navigator'))return [
  step('.workspace-content h1','Acolhimento por campanha','Localize a pessoa e abra o acompanhamento do ciclo.'),
  step('.workspace-content','Pactue o próximo passo','Registre a etapa, prazo e responsável. Conclua ou adie suas tarefas com histórico.'),
  step('.workspace-content','Respeite a escolha','Se a pessoa optou por não participar, não contorne o bloqueio criando outra tarefa de contato.')
 ]
 if(path==='/campaign-management'&&user.management_level&&user.management_level!=='none')return [
  step('.workspace-content h1','Organize por ciclo','Confira a empresa e os ciclos existentes.'),
  step('.workspace-content .page-heading','Crie um novo ciclo','Use Novo ciclo para definir objetivo, período, público e conteúdos já publicados. Salve como rascunho.'),
  step('.workspace-content','Confira antes de ativar','Ative o ciclo quando estiver pronto. Os convites aparecem na plataforma; não há envio automático por WhatsApp.')
 ]
 if(path==='/education-editor'&&(clinical||['admin','manager'].includes(user.role)))return [
  step('.workspace-content h1','Prepare conteúdo educativo','Escolha empresa, finalidade e material. Um conteúdo pode ter vídeo e texto/transcrição.'),
  step('.workspace-content','Revise e publique','Salve o rascunho e faça a revisão exigida. Conteúdo assistencial depende de revisão clínica habilitada.'),
  step('.workspace-content','Use nos ciclos','Depois de publicado, o material pode ser associado a uma campanha. A plataforma não grava o vídeo por você.')
 ]
 if((path==='/patients'||(path==='/'&&user.role==='doctor'))&&clinical)return [
  step('.workspace-content h1','Pacientes vinculados','A lista contém apenas pessoas com vínculo assistencial autorizado para sua equipe.'),
  step('#patient-search','Busque pelo nome','Digite o nome e abra o acompanhamento. Se não encontrar, confira o vínculo com a coordenação.'),
  step('.patient-link','Abra o prontuário','Consulte o resumo e o plano. Dentro do paciente, Guia na tela explica risco, retorno e registros.',{kind:'open',label:'Abrir um paciente',target:'.patient-link'})
 ]
 if(path==='/'&&['worker','dependent'].includes(user.role))return [
  step('.workspace-content h1','Seu espaço de cuidado','Confira o perfil e acompanhe seus atendimentos, conteúdos e campanhas.'),
  step('a[href="/find-care"].btn, .welcome-hero','Encontre atendimento','Use Encontrar atendimento ou Agendar e retornos para buscar um horário.'),
  step('.appointment-card, .workspace-content','Acompanhe o próximo passo','Confira a programação e peça apoio quando necessário. Confirmar horário não é registrar comparecimento.'),
  step('a[href="/my-care"].action-card, .workspace-content','Conheça os conteúdos','Abra Meu cuidado para participar dos ciclos e acessar materiais.')
 ]
 if(path==='/'&&['admin','manager'].includes(user.role))return [
  step('.workspace-content h1','Organize a operação','Confira a empresa e as pendências. Seu acesso é administrativo, sem prontuários.'),
  step('.task-strip','Veja o que precisa de ação','Confira cadastros para validar, pessoas e contratos. Um número agregado não descreve a saúde de uma pessoa.'),
  step('.command-trigger','Resolva com um atalho','Busque convidar, campanha ou conteúdo para abrir o formulário direto. Confira o contexto antes de salvar.')
 ]
 if(path==='/'&&user.role==='navigator')return [
  step('.workspace-content h1','Acolher e reconectar','Seu espaço reúne casos administrativos de continuidade e campanhas vinculadas.'),
  step('.workspace-content','Abra o caso','Registre barreiras e próximos passos. Necessidades clínicas devem ser encaminhadas à APS.'),
  step('.command-trigger','Use os atalhos','Encontre Retornos e Acolhimento conforme as ações do seu perfil.')
 ]
 return [
  step('.workspace-content h1, #main-content h1','Entenda esta tela','Confira seu perfil, o contexto e as informações antes de agir.'),
  step('#main-content','Precisa de mais detalhes?','Abra o manual completo aqui no painel. Ele reúne os sete perfis, rotinas, exemplos e solução de problemas.')
 ]
}
