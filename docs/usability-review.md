# Revisão de usabilidade — 14/11/2026

Objetivo: reduzir etapas desnecessárias para chegar à ação, facilitar leitura e
manter explícitas as decisões que autorizam acesso, validam vínculos ou registram
cuidado. Não foram alteradas regras clínicas ou permissões do backend.

## Problemas encontrados e mudanças

| Situação anterior | Experiência entregue |
| --- | --- |
| Menu horizontal extenso, com ações de pesos semelhantes | Navegação por perfil, tarefas frequentes em primeiro plano, organização em grupo separado e atalhos móveis |
| Início administrativo genérico, com link para outro menu | Pendências visíveis, validação direta, convite e criação de campanha/conteúdo/horário a partir do início |
| Gestão abria com um formulário e várias listas longas abaixo | Lista de trabalho por área; formulários aparecem em painel lateral somente quando solicitados |
| Era necessário descobrir/digitar IDs de titulares e navegadores | Seleção por nome, com opções limitadas aos dados autorizados |
| Escolhas únicas ainda exigiam abrir e selecionar uma opção | Preenchimento de opção única obrigatória; valores existentes, `false` e `null` são preservados |
| Mudanças de filtro exigiam clicar em Buscar novamente | Busca de horários automática, com debounce, cancelamento e proteção contra resposta antiga |
| Reserva dependia de uma autorização global distante do horário escolhido | Resumo do horário e pessoa atendida no painel de confirmação, com autorização explícita desmarcada |
| Teleconsulta exigia consultar acesso e depois abrir o link | Um botão verifica a permissão e abre a sala; se o navegador bloquear a janela, mantém link alternativo |
| Abrir o paciente ainda exigia selecionar seu plano | Plano solicitado/ativo aberto automaticamente, sem impedir consultar outro plano |
| Evolução e medição exigiam atravessar menus | Busca no início clínico e atalhos diretos para registro no paciente |
| Confirmar retorno exigia abrir o caso e escolher o tipo de ação | Confirmação diretamente na linha do retorno; acolhimento e reagendamento abrem o painel na ação escolhida |
| Troca de página perdia a indústria selecionada | Contexto de gestão preservado em memória entre início, gestão, campanhas e editorial |
| Dados apareciam com datas técnicas e pouca hierarquia | Datas legíveis, estados por texto/cor, ícones, espaçamento, cabeçalhos e indicação de próximo passo |

## Comparação de ações de navegação

São contagens dos caminhos visíveis na interface, não um estudo cronometrado.
Não incluem digitação, conferência dos dados, consentimentos ou envio final.

| Tarefa | Antes | Agora |
| --- | --- | --- |
| Abrir convite a partir do início administrativo | Abrir gestão → selecionar convite | Botão Convidar, abrindo o formulário direto |
| Ver horários ao abrir a busca de atendimento | Abrir página → clicar Buscar | Abrir página; os resultados são carregados |
| Reaplicar uma modalidade/filtro | Alterar filtro → clicar Buscar | Alterar filtro; resultados atualizados |
| Abrir evolução de paciente a partir do início clínico | Ir a pacientes → abrir pessoa → escolher plano → escolher registros | Digitar nome → abrir Registrar evolução; plano ativo já selecionado |
| Confirmar retorno na lista administrativa | Abrir caso → escolher confirmação → registrar | Confirmar retorno na própria linha |
| Preparar campanha ou conteúdo a partir do início | Abrir área → localizar o formulário | Ação direta, com formulário lateral aberto |

Atalho `Ctrl+K` abre uma busca de ações disponíveis ao perfil. Opções de formulários
não são inventadas: uma escolha única só é antecipada quando não há ambiguidade.
Campos complementares ficam recolhidos; campos relevantes ao tipo escolhido aparecem
quando necessários. O cadastro de menor e a validação de contato/vínculo continuam
dependendo de verificação humana.

## Segurança e integridade preservadas

- Autorizações assistenciais e verificações de cadastro começam desmarcadas.
- Mutações continuam explícitas, com CSRF, autorização e controle de versão no backend.
- Busca automática faz apenas leitura, não reserva, confirma ou revisa cuidados.
- Permissões por empresa/paciente são mantidas. Atalhos não concedem novos acessos.
- Não foram criados alvos clínicos padrão ou conclusão automática de metas.
- Identificadores permanecem na API e são substituídos por nomes na apresentação.
- O diretório de navegadores no caso retorna somente profissionais ativos com vínculo
  àquele paciente e à empresa já autorizada. Não amplia acesso a dados clínicos.
- O contexto da indústria fica em memória, é reiniciado ao sair/trocar de usuário e
  continua sendo validado no servidor. Nenhum dado clínico foi colocado em localStorage.
- Resultados atrasados de filtros são descartados; trocar rapidamente de modalidade
  não deve mostrar horários da busca anterior. A reserva revalida tudo no backend.

## Verificações

- `npm test`: 25 testes passaram, incluindo navegação por perfil, preenchimentos,
  campos de consentimento, plano ativo e resultados concorrentes.
- `npm run build`: compilação concluída.
- `composer test`: 9 verificações core e 94 de domínio passaram.
- `tests/http.php`: 185 verificações HTTP com MySQL passaram, usando uma base
  temporária e preservando o banco da aplicação.
- Automação local em Chrome sem interface, com perfil de navegador e banco
  temporários: início administrativo, convite direto, verificação explícita de
  cadastro, atalhos de conteúdo/campanha, busca automática, reserva com autorização,
  navegação clínica direta e visualização móvel.
- Sete perfis exercitados no Chrome: administrador, gestor empresarial, médico,
  enfermeiro com coordenação, navegador, trabalhador e dependente. Conferidas as
  diferenças de menus e controles; a gestão empresarial não mostra agenda clínica
  ou administração de contas, e os beneficiários não recebem links de gestão.
- Capturas inspecionadas: início administrativo, gestão de pessoas, agendamento
  no celular (390 × 844) e registro clínico. Sem rolagem horizontal da página no
  cenário móvel verificado. Isso não substitui homologação em todos os dispositivos.

## Reproduzir a checagem de interface

```powershell
cd frontend
npm run test:ui
```

Exige MySQL local configurado, PHP no PATH e Chrome instalado. Pode-se informar
`CHROME_PATH` e `PHP_BINARY` no ambiente. O executor usa um banco aleatório com
prefixo `viva_elo_test_`, cria somente dados fictícios e remove essa base ao final.
Não usa a senha nem a sessão do usuário. Capturas ficam em diretório ignorado
`frontend/node_modules/.cache/elo-ux-*`; o perfil temporário é descartado.

Sem migrações novas nesta revisão. Atualize a página após receber o código.
O trabalho foi validado localmente; não houve publicação em hospedagem externa.
