# Risco confirmado e fila de retorno

No prontuário, o médico informa o risco confirmado (1 a 4) e a data do próximo retorno. A equipe é preselecionada somente quando há uma única opção. Com múltiplas equipes, o médico escolhe a referência. Só médico vinculado pode gravar; enfermagem vinculada pode consultar. Gestão, trabalhador, dependente e navegação não recebem esses dados clínicos.

## Datas e protocolo

A data é sempre enviada e confirmada pelo médico. A sugestão inicial segue a proposta recebida: riscos 1/2, seis meses de calendário; risco 3, três meses; risco 4 exige data definida pelo médico. Fins de mês são limitados ao último dia do mês de destino, inclusive em anos bissextos. Uma data já informada não é sobrescrita silenciosamente por outra sugestão.

O protocolo precisa ser validado pelo SESI; o sistema não atribui risco nem impõe intervalo como recomendação universal. Risco nunca é inferido de faltas, pontos, check-ins ou atraso.

Cada nova confirmação usa por padrão a data atual como avaliação. Em “Data da avaliação e observação”, o médico pode registrar uma avaliação anterior; a data não pode ser futura, e o retorno deve ser posterior. Dados de antes dessa confirmação não são importados automaticamente de textos de prontuário.

## Autoria e proteção

Cada alteração exige a versão atual, registra médico, equipe, data e histórico com antes/depois. Conflito retorna 409. Rebaixar o risco exige justificativa. O risco e os conteúdos dos eventos são cifrados com a infraestrutura AES-256-GCM existente; acesso exige vínculo assistencial atual e empresa correspondente.

A migração 012 cria clinical_return_plans e clinical_return_events sem atribuir risco aos pacientes existentes. O histórico permanece por versão. A tela exibe as últimas 20 confirmações; registros anteriores continuam no banco.

GET/PUT /api/v1/clinical/patients/{id}/return seguem sessão e autorização clínica; escrita também exige CSRF. PUT contém risk, due_date, team_id e version; assessment_date e reason são opcionais no contrato. Primeira criação usa version=0. Enfermagem não pode modificar risco.

## Fila automática e ficha única

Ao abrir ou atualizar a fila, o sincronizador identifica retornos sem comparecimento médico posterior e com prazo vencido, hoje ou até sete dias à frente. É uma janela operacional; não é tolerância clínica nem regra de prêmio.

A fila mantém uma única pendência por retorno, reaproveitando seu histórico em revisões. Repetir sincronização não duplica itens. Uma alteração do prazo atualiza a origem e a versão; se sair da janela, a pendência deixa a fila ativa. A ordenação permanece administrativa, por prazo/pedido de apoio, sem transformar risco em pontuação de prioridade.

Na própria ficha, a enfermagem pode registrar contato, agendar com médico da equipe e registrar comparecimento de consulta já ocorrida. As ações mantêm “Salvar e abrir próxima”. Agendar não conclui o retorno, e a pendência clínica não pode ser encerrada com uma justificativa administrativa genérica.

O comparecimento elegível é consulta médica da empresa, do médico que confirmou o retorno ou de outro médico da equipe de referência, com status attended, data não futura e em dia posterior à avaliação. Consulta no mesmo dia da avaliação não é contada como retorno subsequente; consulta de enfermagem não conclui este retorno médico. Esta distinção não desvaloriza o acompanhamento de enfermagem, que continua registrado no plano.

O comparecimento registrado pela equipe retira o retorno da fila ativa na sincronização. Se a evidência for cancelada/corrigida, ele volta à fila quando ainda estiver na janela. A confirmação de presença usa a consulta existente e não cria atendimento fictício.

“Devidos hoje” filtra pelo dia previsto. “Contato perdido” filtra pendências cujo último contato documentado foi sem resposta ou contato inválido; novo contato realizado retira o item desse filtro. Falta de tentativa não equivale a contato perdido.

## Limites

Este fluxo fornece risco e retorno de origem para futura apuração coletiva. Ainda não congela carteiras, calcula linha de base ou autoriza prêmio. Não integra SOC/WhatsApp. A demo pública contém somente dados fictícios, com gravação desabilitada.

Validações cobrem acesso por perfil/empresa/vínculo, versões, rebaixamento, datas, criptografia, duplicidade, filtros, agendamento sem conclusão, comparecimento posterior e reabertura após correção.
