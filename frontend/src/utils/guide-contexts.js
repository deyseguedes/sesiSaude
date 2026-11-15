const fieldDescriptions={
 name:'Informe o nome que identifica este cadastro; confira se já existe um registro para evitar duplicidade.',
 email:'Confira o e-mail e o destinatário. Um convite nominal não deve ser entregue a outra pessoa.',
 password:'Defina a senha exigida pelo cadastro. O guia não lê nem armazena o que você digita.',
 role:'Escolha a função da pessoa. O perfil não deve ser ampliado apenas para contornar uma falta de vínculo.',
 management_level:'Defina o nível compatível com a responsabilidade. Gestão não concede acesso automático ao prontuário.',
 team_id:'Selecione a equipe correta e confira seus profissionais e vínculos.',
 professional_id:'Escolha o profissional vinculado à equipe e ao atendimento que você está organizando.',
 patient_id:'Confirme a pessoa certa antes de registrar qualquer informação.',
 contract_id:'Selecione o contrato vigente e confira a cobertura.',
 sponsor_patient_id:'Escolha o titular correto. Dependente adulto mantém seu próprio espaço individual.',
 start_date:'Confira o início do período. Ele deve ser compatível com o término e a situação do cadastro.',
 end_date:'Confira o término do período e a vigência necessária para a operação.',
 due_date:'Defina o prazo do próximo passo; ele não comprova que a ação foi realizada.',
 assessment_date:'Informe a data em que a avaliação ocorreu. Não use uma avaliação futura.',
 'return-risk':'Somente o médico confirma o risco. Não use faltas ou pontuação para inferir a classificação.',
 'return-date':'Confira a data do próximo retorno segundo a avaliação e o protocolo institucional. Isso não reserva um horário.',
 'return-assessment':'Confira a data da avaliação médica; o retorno deve ser posterior.',
 objectives:'Registre os objetivos pactuados no plano correto.',
 interventions:'Descreva as intervenções planejadas e seu acompanhamento.',
 clinical_notes:'Registre informações clínicas apenas no espaço assistencial autorizado.',
 notes:'Confira o tipo, a autoria e o contexto do registro antes de escrever.',
 administrative_note:'Use apenas informações administrativas de acolhimento; não inclua diagnósticos ou exames.',
 authorization_verified:'Marque somente depois de verificar a autorização assistencial.',
 representation_verified:'Marque somente depois de verificar a representação e a autorização institucional.',
 contact_verified:'Marque somente depois de verificar o contato da pessoa.',
 affiliation_verified:'Marque somente depois de confirmar o vínculo e a cobertura do contrato.',
 channel:'Informe o canal efetivamente utilizado no contato.',
 outcome:'Registre o que realmente aconteceu. Tentativa sem resposta não é contato realizado.',
 on_time_count:'Informe quantos retornos foram realizados no prazo, sem exceder os devidos após exclusões.',
 due_count:'Informe os retornos devidos da carteira congelada do cenário.',
 excluded_count:'Informe apenas exclusões com fonte objetiva; recusa por telefone ou contato perdido não justificam exclusão.',
 baseline_percent:'Informe a referência verificada do cenário. Digitar um valor não comprova a linha de base.',
 satisfaction_percent:'Informe o percentual do cenário; a proposta exige pelo menos 90% para passar na simulação.',
 source:'Diferencie informação profissional e autorrelato.',
 measurement_source:'Escolha a origem das medições que serão usadas para avaliar esta meta.',
 value:'Confira o valor, a unidade, a origem e o método da medição.'
}
const s=(target,title,text,action)=>({target,title,text,...(action?{action}:{})})
export function formGuide(form){
 return [
  s(form.target,'Preencha com o guia aberto','Você pode clicar e preencher os campos normalmente. O guia acompanha o formulário; não copia valores, não marca autorizações e não envia dados por você.'),
  ...form.fields.map(f=>s(f.target,f.label,(f.disabled?'Este campo está indisponível neste momento. ':f.readOnly?'Este valor é apenas para consulta. ':fieldDescriptions[f.key]?fieldDescriptions[f.key]+' ':f.type==='checkbox'?'Leia e marque somente se a condição ou autorização foi realmente verificada. ':f.type==='password'?'Informe a senha exigida. O guia não lê nem guarda o que você digita. ':f.type==='select'?'Escolha a opção correta para este cadastro. Confira o contexto antes de continuar. ':f.type==='date'||f.type==='datetime-local'?'Confira a data e o período antes de salvar. ':f.type==='number'?'Informe o valor solicitado e confira a unidade e os limites do campo. ':'Preencha este campo com as informações apropriadas ao registro. ')+(f.required?'É obrigatório.':'Confira se precisa ser informado.'),!f.disabled&&!f.readOnly?{kind:'focus',label:'Preencher este campo',target:f.target}:null)),
  s(form.target,'Confira antes de salvar','Revise os dados e utilize o botão de salvar da própria tela quando estiver pronto. Salvar, confirmar, publicar ou concluir continua sendo uma decisão sua.')
 ]
}
export function detailGuide(user,path,context){
 if(context.notFound)return [s('#main-content h1','Página não encontrada','O endereço não corresponde a uma tela disponível. Você pode voltar ao início pelo guia.'),s('a[href="/"]','Volte para seu espaço','O início reúne os recursos permitidos ao seu perfil.',{kind:'open',label:'Voltar ao início',target:'a[href="/"]'})]
 const main='.workspace-content'
 if(path==='/care-dashboard'&&['doctor','nurse'].includes(user?.role))return [
  s(main+' h1','Indicadores assistenciais','Confira o período e o escopo da equipe. Ausência de dados não é resultado zero.'),
  s(main+' form','Ajuste o período','Os filtros atualizam a leitura dos indicadores. Você pode preenchê-los com o guia aberto.'),
  s(main,'Leia as origens','Metas, medidas, autorrelatos e comparecimentos representam informações diferentes. Confira o plano e as evidências antes de interpretar.')
 ]
 if(path==='/system')return [
  s('#main-content h1','Status da plataforma','Esta tela informa se a API está respondendo.'),
  s('#connection-title','Confira a conexão','A conexão da API não comprova, sozinha, que todos os dados e permissões estão corretos.'),
  s('#main-content .card','Verifique novamente','Se houver falha, confira os serviços com o responsável técnico. Não digite credenciais em mensagens de erro.')
 ]
 if(path==='/management'&&user?.management_level&&user.management_level!=='none'&&context.tab&&context.tab!=='people'){
  const descriptions={structure:['Unidades e equipes','Organize unidades industriais, equipes e membros autorizados. Local de atendimento e unidade industrial podem ser diferentes.'],contracts:['Contratos e cobertura','Confira vigência, situação e modalidades. Uma alteração de cobertura pode afetar novas reservas.'],agenda:['Disponibilidade de atendimento','Confira profissional, equipe, local e período antes de disponibilizar horários.'],access:['Profissionais e níveis','Perfil e nível de gestão são diferentes. Aumentar o nível não autoriza acesso automático a prontuário.']}
  const [title,text]=descriptions[context.tab]||['Gestão','Confira a empresa e o contexto.']
  const buttons={structure:'[data-guide-open="unit"]',contracts:'[data-guide-open="contract"]',agenda:'[data-guide-open="slot"]',access:'[data-guide-open="account"]'}
  return [s('.section-tabs',title,text),s(main+' .card',title==='Contratos e cobertura'?'Revise o contrato existente':'Confira os registros','Use as ações da própria área. O guia acompanha as abas e os formulários que você abrir.'),s(buttons[context.tab]||main,'Abra um formulário','Se precisar cadastrar, abra o formulário e siga as orientações de cada campo.',buttons[context.tab]?{kind:'open',label:'Abrir formulário',target:buttons[context.tab]}:null)]
 }
 if(/^\/patients\/\d+$/.test(path)&&['doctor','nurse'].includes(user?.role)&&context.section&&context.section!=='overview'){
  const sections={plan:['Plano de cuidado','Confira o plano selecionado, responsável, objetivos e intervenções.'],records:['Evolução e acompanhamento','Confira autoria, tipo de registro e plano. Notas clínicas pertencem ao espaço assistencial.'],indicators:['Indicadores e medições','Confira indicador, método, unidade e origem. Autorrelato não é medição profissional.'],goals:['Metas do plano','Confira indicador, alvo, prazo e origem usados para avaliar a meta.'],referral:['Encaminhamento','Selecione a equipe e o profissional vinculados. O motivo fica no registro clínico restrito.']}
  const [title,text]=sections[context.section]||['Acompanhamento','Confira a pessoa e o plano.']
  return [s('.clinical-quick-actions',title,text),s('.care-plan-option.btn-success','Confira o plano ativo','Se houver vários planos, confira qual está selecionado. Não registre no plano de outra pessoa.'),s(main+' .workflow-form','Preencha com orientação','Clique em um campo para receber a explicação do formulário. Nada é enviado automaticamente.')]
 }
 return null
}
