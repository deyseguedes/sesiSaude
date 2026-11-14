# Indústrias, trabalhadores e dependentes

## O que foi incorporado

O projeto recebeu três módulos integrados à base clínica V3:

1. Gestão de indústrias contratantes, unidades industriais, contratos APS,
   profissionais, equipes, níveis de gestão e elegibilidade.
2. Cadastro remoto por convite, representação verificada de menores, busca de
   atendimento, reserva presencial/teleconsulta e acesso restrito ao link da sala.
3. Educação rápida, publicação revisada, campanhas por ciclos e fila administrativa
   de acolhimento com etapas, tarefas, responsáveis e histórico.

Nenhuma tabela clínica anterior foi recriada. As migrações 005 e 006 são incrementais.
Médicos/enfermeiros continuam precisando de vínculo ativo por paciente para ler
prontuários. Navegadores e gestores não recebem observações clínicas.

## Fluxo de gestão e permissões

O perfil profissional (`role`) é independente do nível de gestão (`management_level`).

| Nível | Permissões entregues |
| --- | --- |
| Sem gestão | Recursos próprios do perfil: assistência vinculada, navegação administrativa ou espaço individual |
| Coordenação APS | Médico/enfermeiro: gestão administrativa da empresa e campanhas; agendas e vínculos assistenciais limitados às próprias equipes |
| Gestão empresarial | Gestor: unidades industriais, contratos, convites, validação de elegibilidade, campanhas e resultados agregados; sem agendas clínicas, prontuários ou fila nominal de acompanhamento |
| Gestão da plataforma | Administrador: gestão operacional, contas e níveis, equipes e locais de atendimento; indústrias sob autorização explícita |

Somente administrador da plataforma provisiona profissionais ou muda níveis. Ele
não altera seu próprio acesso nem outros administradores por esse endpoint. Cada
usuário tem empresa principal; gestão adicional de outra indústria exige registro
em `management_company_access`. Criar uma indústria concede ao criador esse vínculo
administrativo, sem transferir acesso clínico. Não existe consulta livre de empresas.

A primeira gestão deve cadastrar unidade industrial, contrato vigente, profissionais,
equipe e membros, local de atendimento e horários disponíveis. O contrato define
presencial, teleconsulta e cobertura de dependentes. Contratos podem ser revisados
com controle de versão. Empresa e coordenação administram dados de elegibilidade,
como nome/e-mail/titular, sem dados clínicos nem progresso educativo nominal.

Vínculos assistenciais podem ser ativados/revogados pela plataforma ou coordenação
da equipe. Ativação exige verificação humana explícita da autorização; mudanças
são auditadas. Suspender um vínculo empresarial impede novas reservas, mas não
apaga consultas ou prontuários anteriores. Gestão empresarial não concede acesso
assistencial a uma equipe.

## Cadastro remoto

O convite é nominal para um e-mail, tem 48 horas de validade e uso único. O token
é exibido uma vez à pessoa que o emitiu; apenas SHA-256 é armazenado no banco. O
operador entrega o link pelo canal institucional. Não há envio automático de
e-mail, WhatsApp, SMS ou geração de QR Code por serviço externo.

Na página `/register`, o destinatário informa nome, contato, nascimento, CEP,
preferência de período e senha. O código é removido da barra de endereço após a
abertura. O backend valida CSRF, vínculo do convite e dados; a confirmação do e-mail
digitado contra o convite não é tratada como prova de titularidade da caixa postal.
O cadastro fica pendente. Antes de ativar, a gestão deve verificar contato e vínculo
com a empresa conforme seu procedimento. Há limitação de tentativas no cadastro.

Dependentes adultos recebem convite e conta individuais. O titular pode gerar
convite de dependente somente no próprio contrato ativo com cobertura familiar.
Ser titular não concede acesso ao cadastro, consultas, conteúdo assistido ou
prontuário de dependente adulto.

O autoatendimento desta versão exige idade de 18 anos ou mais. Menores são cadastrados
pela gestão após verificação explícita da representação e referência institucional.
Somente esse vínculo de representação autoriza o responsável a agendar para o
menor. Não é criada conta compartilhada ou prontuário familiar. Representação real
e o procedimento de validação precisam de homologação institucional antes de uso.

## Agendas e busca

Cada disponibilidade pertence à empresa, profissional, equipe e local de atendimento,
com modalidade, início, término, situação e versão. O profissional deve estar ativo
e ser membro da equipe. Coordenação só organiza agendas de suas equipes. A gestão
empresarial não publica horários clínicos. Horários ocupados não podem ser bloqueados
como se estivessem livres.

O beneficiário escolhe quem será atendido entre seu cadastro e representações
verificadas. Pode buscar médico, enfermeiro ou ambos; o padrão da interface é médico.
A busca inclui disponibilidades futuras dos próximos 90 dias e permite:

- Primeiro horário disponível.
- Mais próximo da unidade industrial, pelas coordenadas cadastradas.
- Mais próximo da localização obtida uma única vez, com permissão do navegador.
- Somente a equipe APS já vinculada.
- Presencial ou teleconsulta, conforme cobertura vigente.

O cálculo de distância (Haversine) é feito no backend, em linha reta, com resultados
aproximados em quilômetros. Localização é usada somente na requisição de busca;
não há rastreamento contínuo. CEP e endereço são cadastrados, mas não existe
geocodificação automática ou mapa de rotas integrado. Sem coordenadas não se promete
um profissional "mais próximo"; continua disponível a ordenação por horário.

Antes de reservar, o beneficiário confirma o vínculo assistencial com a equipe
escolhida. O backend revalida elegibilidade, modalidade, profissional, local, versão
e disponibilidade. A transação bloqueia recursos relevantes, impede reserva repetida,
registra consulta/caso confirmado, vincula a equipe e avisa a APS. Conflitos retornam
409 para nova busca. Publicação de horários sobrepostos é rejeitada. Agendamentos
administrativos antigos bloqueiam a disponibilidade correspondente; o reagendamento
preserva a modalidade e o histórico anterior.

O trabalhador pode confirmar retornos e solicitar acolhimento por barreiras opcionais
(horário, transporte, contato, questão pessoal ou preferência por não informar).
O pedido entra no ELO Reconecta e gera aviso à equipe. Não é uma conversa síncrona
nem envio externo de mensagens.

## Teleconsulta

`/my-agenda` mostra os atendimentos do profissional autenticado, de sete dias atrás
a noventa dias à frente, apenas de pacientes ainda vinculados. Somente o profissional
responsável pode informar um link HTTPS exclusivo da teleconsulta. Ele é criptografado
e fica fora das listagens de gestão.

O endpoint de acesso libera o link somente ao profissional responsável ou ao paciente/
representante autorizado, de 30 minutos antes até uma hora depois do término previsto.
Consultas encerradas por reagendamento não liberam o link antigo. Cancelamento ou
revogação de acesso no provedor deve seguir sua operação institucional: a janela da
API não revoga um endereço que já tenha sido copiado.

O projeto organiza agenda e acesso ao link; não implementa videoconferência, gravação,
prescrição remota ou integração com um fornecedor específico. O provedor e os protocolos
assistenciais precisam ser definidos pela instituição. Não há chamada de vídeo simulada.

## Educação e campanhas

Conteúdos têm título, apresentação, roteiro/transcrição, duração prevista de até
dez minutos, categoria e URL HTTPS de vídeo opcional. MP4/WebM podem ser reproduzidos
na página; outras URLs abrem o vídeo publicado. Não há upload/armazenamento de gravações
ou serviço de streaming neste incremento. A equipe deve anexar suas gravações já
hospedadas em um provedor autorizado.

Tutoriais da plataforma podem ser publicados pela gestão editorial. Conteúdos APS
exigem revisão explícita de médico ou enfermeiro, registrada com autoria e nota.
Alterações retornam o conteúdo para rascunho e exigem nova revisão. Transcrições
mantêm o material acessível. Não há diagnóstico ou conduta gerados automaticamente.

O beneficiário marca "Concluí este conteúdo"; a conclusão é autorrelatada e individual,
associada à versão revisada. Uma revisão posterior não herda a conclusão anterior.
O empregador não recebe o histórico individual de vídeos, tempo assistido ou dados
clínicos. Não há conclusão automática de metas de cuidado por assistir a conteúdo.

Campanhas possuem ciclo/edição, período, objetivo, unidade industrial opcional,
público (trabalhadores, dependentes ou ambos) e conteúdos publicados. Ativar um ciclo
cria convites internos para beneficiários elegíveis. Reativar enquanto aberto inclui
novos elegíveis sem duplicar os existentes. Um ciclo encerrado não é reaberto: deve-se
criar a nova edição. A pessoa pode demonstrar interesse, participar ou recusar agora.
Para concluir o ciclo por autoatendimento, precisa ter concluído as versões publicadas
dos conteúdos. Concluir ciclo não conclui consulta, adesão ou meta clínica.

A fila `/engagement` mostra somente participantes vinculados à equipe profissional.
Etapas: convidado, interesse, acolhido, participando, acompanhamento, conteúdos
concluídos e não participar agora. Há tarefas de contato/acolhimento/acompanhamento,
prazo, responsável autorizado e notas administrativas criptografadas. Recusa bloqueia
novos contatos de campanha e movimentação pela equipe até manifestação da pessoa.
Somente o responsável pode concluir sua tarefa. Etapas e tarefas têm histórico.

Gestores veem campanhas com métricas agregadas. Grupos ou etapas abaixo de cinco
são suprimidos; totais também são suprimidos quando permitiriam deduzir uma etapa
pequena. Essa minimização não constitui garantia de anonimização ou certificação.

## Atualizar uma instalação existente

Faça backup da base e preserve a chave `APP_KEY` já utilizada. Não reimporte o
SQL completo sobre tabelas existentes.

```powershell
cd backend
composer migrate
```

O comando aplica somente migrações ainda não registradas. Alternativamente,
`backend/database/upgrade_industry.sql` contém apenas 005/006 para uma base V3
com 001–004 já aplicadas, e deve ser importado uma única vez. O arquivo usa o nome
`viva_elo`; adapte `USE` caso seu banco tenha outro nome. DDL MySQL não é transacional:
em caso de erro parcial, inspecione o estado antes de repetir.

Para instalação vazia, `backend/database/viva_elo.sql` foi atualizado com todas as
seis migrações e seus registros. Pode ser regenerado por `composer schema:build`.

A carga `composer seed` já inclui a demonstração industrial. Para uma antiga base
fictícia Aurora, `composer seed:industry` complementa os cadastros sem recriar os
planos. Exige `APP_ENV=local`, chave válida e `DEMO_PASSWORD` de 12–72 bytes para
criar a conta do dependente; não redefine senhas existentes. O comando se recusa a
adaptar uma base que não seja a demonstração Aurora. Não é ferramenta de migração
de dados de uma indústria real.

Após atualizar, saia e entre novamente para atualizar o nível de gestão no menu.
As contas fictícias incluem `dependent@demo.vivaelo.test`, administrador com nível
plataforma, gestor com nível empresa e enfermeira com coordenação. Há horários nas
duas modalidades, tutorial em texto publicado, roteiro APS em rascunho e um ciclo
inicial. Não foram inventadas gravações ou links de salas reais.

## Limites operacionais

Somente dados fictícios. Antes de produção, validar identidade/representação,
vínculos, protocolos, privacidade, permissões, retenção, suporte e provedor de vídeo.
Credenciais e chaves continuam no backend. Cabeçalhos, cookies/CSRF, SQL parametrizado
e auditoria da base anterior foram mantidos. Aplicar HTTPS e configuração de logs que
não retenha tokens de convite ou coordenadas presentes em URLs. Não há envio externo,
acesso ao banco do usuário ou publicação automática neste trabalho local.

Contrato técnico: [API industrial](industry-api.md). Evidências:
[verificação da expansão](verification-industry.md).
