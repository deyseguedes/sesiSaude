export const labels = {
  dependent: 'Dependente', worker: 'Trabalhador', suspended: 'Suspenso', available: 'Disponível', blocked: 'Bloqueado', reserved: 'Reservado', published: 'Publicado', draft: 'Rascunho', invited: 'Convidado', interested: 'Interesse demonstrado', welcomed: 'Acolhido', participating: 'Participando', followup: 'Acompanhar', completed: 'Conteúdos concluídos', opted_out: 'Não participar agora', contact: 'Contato', done: 'Concluída', deferred: 'Adiada', open: 'Aberta', task_created: 'Próximo passo criado', task_done: 'Tarefa concluída', task_deferred: 'Tarefa adiada', support_requested: 'Pedido de apoio', self_booking: 'Agendamento pelo beneficiário',
  active: 'Ativo', paused: 'Pausado', closed: 'Encerrado', reviewed: 'Revisado', cancelled: 'Cancelado',
  pending: 'Pendente', confirmed: 'Confirmado', attended: 'Compareceu', expected: 'Previsto', rescheduled: 'Reagendado', reconnected: 'Reconectado',
  attained: 'Atingida', in_progress: 'Em acompanhamento', insufficient_data: 'Medições insuficientes',
  professional: 'Profissional', self_reported: 'Autorrelato',
  numeric: 'Numérico', percentage: 'Percentual', categorical: 'Categórico', frequency: 'Frequência', range: 'Faixa-alvo', adherence: 'Adesão', attendance: 'Comparecimento', continuity: 'Continuidade',
  increase: 'Aumentar', decrease: 'Reduzir', maintain: 'Manter valor', category: 'Categoria-alvo',
  transport: 'Transporte', schedule: 'Horário', contact: 'Contato', personal: 'Questão pessoal', declined: 'Preferiu não informar',
  confirm: 'Confirmar retorno', welcome: 'Registrar acolhimento', assign_navigator: 'Encaminhar ao navegador', reschedule: 'Reagendar', reconnect: 'Registrar reconexão', resolve_barrier: 'Registrar barreira resolvida', clinical_review: 'Solicitar avaliação da APS', barrier: 'Registrar barreira voluntária',
  clinical: 'Evolução clínica', nursing: 'Evolução de enfermagem', vital_signs: 'Sinais vitais', education: 'Ação educativa',
  plan_created: 'Plano criado', plan_updated: 'Plano atualizado', plan_reviewed: 'Plano reavaliado', goal_created: 'Meta criada', goal_updated: 'Meta ajustada', evolution_recorded: 'Evolução registrada', adherence_recorded: 'Adesão registrada', requested: 'Solicitado',
}
export const label = (value) => labels[value] || value
export const options = (values) => values.map(value => ({ value, label: label(value) }))
export const today = () => (typeof sessionStorage!=='undefined'&&sessionStorage.getItem('elo-demo-clock')?.slice(0,10))||new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Bahia' }).format(new Date())
export const timestamp = () => new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Bahia', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).format(new Date()).replace(',', '')
