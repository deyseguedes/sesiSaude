# Resumo do prontuário por paciente

Na tela de cada paciente, médicos e enfermeiros vinculados veem um resumo antes
das ações do prontuário. A visão geral abre os detalhes automaticamente; atalhos
diretos de registro mantêm os indicadores rápidos visíveis e permitem expandir.

O resumo reúne plano em destaque, objetivos e observações, última evolução,
metas ativas, medições recentes, comparecimento, próximo atendimento, reavaliação,
última ação pactuada, revisão e encaminhamentos. Atalhos abrem o plano, registro,
meta ou indicador correspondente. Salvar alterações atualiza o resumo.

## Regras de leitura

- Apenas registros existentes: nenhum diagnóstico ou orientação é gerado.
- Textos mantêm o conteúdo e a autoria; trechos são identificados e expansíveis.
- O plano ativo com reavaliação mais próxima recebe destaque; sem plano ativo,
  usa-se o revisado mais próximo ou o último plano histórico, com seu status.
- Metas ativas vêm de planos ativos ou revisados. O progresso usa as mesmas
  regras de origem, janela de evidência e amostras mínimas do acompanhamento.
- Medições profissionais e autorrelatadas aparecem em colunas separadas, com
  data de coleta, método e autoria. A ordem usa a coleta, preservando valores zero.
- Consulta prevista ou confirmada não comprova comparecimento. Apenas status
  de comparecimento em data passada aparece como último atendimento realizado.
- Reavaliação vencida indica a data programada, sem presumir agravamento clínico.
- Campos vazios indicam ausência de registro, sem presumir condições do paciente.
- A visão rápida limita a seis indicadores, cinco metas e três encaminhamentos;
  o histórico completo permanece disponível nos atalhos existentes.

## Acesso e API

`GET /api/v1/clinical/patients/{id}/summary` exige sessão e vínculo assistencial
de médico ou enfermeiro. A autorização ocorre antes da leitura e descriptografia;
o acesso gera evento de auditoria `patient_summary_read`. Gestores, administradores,
navegadores, trabalhadores e dependentes não recebem este resumo clínico.

O recurso é somente leitura e utiliza o esquema existente. Não exige migração.

## Validação

Testes de domínio e MySQL verificam isolamento, revogação de vínculo, ausência
de registros, autoria, ordenação, fontes de medição, valores zero e evidência
insuficiente. Testes HTTP exercitam sessão, perfis e vínculos. A checagem em Chrome
usa banco temporário com dados fictícios para conferir expansão de textos,
atualização após salvar e leitura em desktop e celular sem transbordamento da página.
