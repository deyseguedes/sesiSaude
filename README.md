# VIVA+ ELO — Hackathon+ SESI Saúde 2026

Camada de engajamento ligada ao ecossistema do SESI.
A equipe apresenta o absenteísmo na APS como desafio; a referência de cerca de 50% é premissa a validar no piloto.
O processo quebra entre mensagem enviada e resposta do trabalhador.
O protótipo leva o retorno ao celular e organiza a fila antes do vencimento.
O SESI acompanha participação, adesão e risco, com campanhas avaliadas por efeito, custo e travas.

## Arquitetura

Exportação Klingo/SOC/S+ → importação validada → base consolidada →
regras de retorno/convocação → canal do trabalhador → exportação CSV →
painéis e avaliação das campanhas.

Stack preservada: Vue 3 + Pinia + Vue Router + Bootstrap + Vite; API PHP 8.2+
com PDO; MySQL; sessões e CSRF. O CSV de devolução não é integração automática
com o sistema da APS. O canal público utiliza banco fictício separado do banco
local da operação.

## Funciona de verdade × simulado

| Componente | Estado |
| --- | --- |
| CSV/XLSX, mapeamento, datas Excel, validação por linha e relatório | Funciona; grava a base consolidada |
| Dados do cuidado | Gerador determinístico, apenas campos vazios, dados_de_teste=true |
| Regra de convocação, ordenação e explicação | Funciona; parâmetros propostos em backend/config/outreach.php |
| Confirmar, escolher horário e informar barreira | Grava eventos reais na sessão demonstrativa; não envia mensagem externa |
| Fila ao vivo e funil | Atualizam por consulta a cada 2 segundos |
| Horários para remarcar | Consultam a mesma tabela de disponibilidade da agenda; escolha do roteiro é reservada na sua sessão, sem ocupar agenda real |
| Devolução à APS | CSV funcional; layout ainda a ajustar ao SESI |
| Estatística, diagnóstico e sugestão de decisão | Calculados pelo motor; cinco históricos são simulados e o sexto usa o roteiro |
| Decisões e versões | Gravadas e auditadas na visita; não aprovam pagamento |
| WhatsApp, recibo de leitura e áudio de Seu José | Simulados; fala sintetizada no navegador |
| Telefonia | Pedido de ligação; não faz chamada |
| PPR da Equipe Norte | Exemplo ilustrativo; não é apuração das 100 pessoas |
| Benefício do semestre | Critérios e exemplo; sem resgate real no RH |
| Pós-quântica | Roadmap; não implantada |

## Dados, importação e restauração

Somente dados fictícios/teste na demonstração. Não existe planilha da organização
no repositório. A pasta dados-evento/ é ignorada pelo Git; o agente não a inspeciona.
O importador aceita até 1000 linhas e 5 MB, com prévia antes da gravação.
Obrigatórias: matrícula, nome, empresa e status. O relatório mascara CPF e telefone;
o payload consolidado fica cifrado.

O roteiro, as importações, as respostas e as decisões de campanhas são separados
por um identificador aleatório da sessão. Restaurar troca esse identificador,
sem apagar nem copiar bancos. **Cadastros, prontuários, campanhas educativas e
agendas legadas permanecem compartilhados**. O isolamento não é de toda a aplicação.

A base padrão tem 100 pessoas sintéticas, incluindo Marcos (DEMO-0001).
Ele aparece primeiro pela data devida e pelo tempo sem resposta, sem exceção pelo nome.
As janelas de 30 dias e 3 dias sem resposta são propostas a calibrar com o SESI.

Para salvar um snapshot da base consolidada fictícia, a equipe pode executar:

```powershell
php scripts/criar-snapshot-demo.php
# Opcional: ID da visita que realizou a importação
php scripts/criar-snapshot-demo.php <id-da-visita>
```

O comando utiliza a configuração privada da demonstração e salva um arquivo
cifrado em dados-evento/demo-snapshot.enc. A restauração lê esse snapshot quando
ele existe; sem ele, usa a base sintética. O snapshot não inclui tabelas legadas.
Não execute contra dados reais e não publique o arquivo.

## Rotas da demonstração

Os links abaixo são relativos ao servidor que hospeda a demonstração dinâmica.
A entrada direta só funciona quando o backend já autoriza os perfis demonstrativos;
não libera acesso em produção. Acrescente &restaurar=1&gravacao=1 aos links com ?ir=.

| Cena | Link direto |
| --- | --- |
| Comece por aqui | /login |
| Última importação e relatório | /entrar/admin-sesi?ir=/import |
| Fila da enfermagem | /entrar/enfermeiro?ir=/ |
| Modo ao vivo | /entrar/enfermeiro?ir=/live |
| Celular do Marcos | /entrar/trabalhador?ir=/worker-channel |
| Carla e Seu José | /entrar/trabalhador?ir=/accessible-channel |
| Painel SESI | /entrar/admin-sesi?ir=/ |
| Boletim empresarial | /entrar/gestor-empresa?ir=/ |
| Campanhas | /entrar/admin-sesi?ir=/campaign-management |
| Campanha ao vivo | /entrar/admin-sesi?ir=/campaign-management/6 |
| Carteira médica | /entrar/medico?ir=/ |
| Agenda do trabalhador | /entrar/trabalhador?ir=/find-care |
| Conteúdos do trabalhador | /entrar/trabalhador?ir=/my-care |
| Navegador de cuidado | /entrar/navegador?ir=/continuity |
| Dependente | /entrar/dependente?ir=/ |
| Privacidade | /entrar/enfermeiro?ir=/privacy |

Exemplo para gravação:
`/entrar/enfermeiro?ir=/live&restaurar=1&gravacao=1`.
O modo oculta ajuda, banners e animações e mantém os selos de simulação.
As cenas do roteiro usam relógio fixado pela base demonstrativa.
Use ?gravacao=0 para sair desse modo.

Marca e vídeo são configurados em frontend/src/config.js:
APP_NAME (VITE_APP_NAME), subtítulo e VIDEO_URL (VITE_VIDEO_URL).
Vídeo vazio não cria bloco. YouTube e Google Drive usam embed; MP4/WebM usam video.
A equipe grava os vídeos fora do Codex.

## Campanhas: medir e decidir

O público fica congelado no início. A/B usa hash estável da matrícula; implantação
escalonada é alternativa; comparação histórica recebe aviso de menor força.
Resposta em até 72h e tempo mediano têm denominadores distintos do total de respostas.
Efeito, intervalo de confiança de 95%, amostra e custo mostram suas fórmulas.
Sem ganho, não há custo por retorno recuperado.

Parâmetros propostos: mínimo 100 por grupo, meta 4 pontos, teto R$ 126,
saída máxima 5% e satisfação mínima 90%. A regra considera primeiro as travas,
depois período/amostra e, então, efeito, intervalo e custo. Intervalo inconclusivo
gera espera, não redesenho por falta de dados. A coordenação registra a decisão
e a justificativa; a sugestão automática não substitui essa escolha.

Os seis casos esperados são: Manter; Redesenhar; Pausar e revisar; Ajustar;
Aguardar dados; Aguardar dados (ao vivo). Os cinco históricos agregados têm selo
de simulação e não são atribuídos às 100 pessoas.

## Privacidade e LGPD

O protótipo público usa dados sintéticos, acesso por perfil e registros de acesso.
Gestor empresarial recebe somente agregados da sua empresa, com mínimo de dez
pessoas também no recorte de período; não recebe risco, plano ou motivos clínicos.
A equipe assistencial depende de vínculo. O líder demonstrativo recebe apenas
nome e horário das autorizações do cenário acessível.

Para a operação assistencial, a hipótese de tutela da saúde do art. 11, II, f,
e consentimentos específicos do art. 11, I, devem ser validados pelo controlador.
Aviso ao empregador e resgate de benefício não são autorizados automaticamente
pelo cuidado. [LGPD, texto atualizado](https://www2.camara.leg.br/legin/fed/lei/2018/lei-13709-14-agosto-2018-787077-normaatualizada-pl.html).

A RN 499/2022 é referência para programas de operadoras de planos privados;
não valida automaticamente uma política de benefícios trabalhistas do SESI.
[Texto da RN 499/2022](https://bvsms.saude.gov.br/bvs/saudelegis/ans/2022/res0499_01_04_2022.html).
O protótipo usa participação, não resultado de saúde, como critério do incentivo.

Payloads da importação, respostas, risco e notas clínicas usam AES-256-GCM.
Objetivos/intervenções dos planos legados ainda exigem ampliação dessa proteção.
O HTTPS público depende do túnel; não há garantia de criptografia híbrida.
Não usar o seletor público de perfis com dados reais.

## Roadmap

- MVP proposto em até um mês: homologar layout APS, critérios, consentimentos,
  autenticação, isolamento completo, monitoramento e integrações autorizadas.
- Piloto SESI Bahia: implantação escalonada por estabelecimento, denominadores
  congelados e janelas comparáveis; medir efeitos no programa inteiro.
- Versão 2: fechamento auditável do prêmio, integração com RH, canais reais,
  reforço criptográfico e operação em escala.
- Expansão: validar o piloto antes de adaptar para outros SESI.

## Diretrizes dos painéis — 14/11/2026

A [especificação dos painéis e integrações](docs/paineis-solucao-2026-11-14.md)
consolida o canal do trabalhador, a lista autorizada do líder, SOC/WhatsApp,
métricas e requisitos de segurança. Ela distingue recursos existentes de
requisitos futuros e decisões de propostas em discussão. A
[proposta original de prêmio da equipe](docs/premio-equipe-saude.md) está
preservada para revisão; não representa pagamento implementado. O
[reconhecimento coletivo](docs/team-recognition.md) substitui o bônus individual
do médico e oferece simulação por equipe, com travas e acesso por perfil.

O [fluxo de risco e retorno](docs/clinical-returns.md) permite confirmação pelo
médico e organiza automaticamente os retornos na fila da enfermagem, com
contato, agendamento e registro de comparecimento na própria ficha.

## Atualizar uma base V3

Preserve a chave `APP_KEY` e faça backup do banco. Na pasta `backend`, execute
`composer migrate`: somente as migrações ainda não registradas serão aplicadas.
O [SQL de atualização](backend/database/upgrade_industry.sql) é uma alternativa
para importar uma única vez sobre V3 com 001–004 aplicadas. O
[SQL completo](backend/database/viva_elo.sql) é somente para banco vazio.

Para complementar uma antiga demonstração Aurora, configure `DEMO_PASSWORD` pelo
mesmo procedimento privado abaixo e execute `composer seed:industry`. Não use
`composer seed` novamente em banco populado. A carga complementar exige o ambiente
local e os dados fictícios Aurora; não migra bases reais. Saia e entre novamente
para atualizar os níveis exibidos na navegação.

[Fluxos, permissões e limites da expansão industrial](docs/industry-and-family.md).

## Preparar o backend

Requisitos: PHP 8.2+ com PDO MySQL, OpenSSL e mbstring; Composer; MySQL 8.
Os testes rápidos também usam PDO SQLite; testes HTTP usam cURL e MySQL.
Frontend: Node 22.12+ ou 24+, npm.

Crie um banco MySQL vazio chamado `viva_elo`, com charset `utf8mb4`, e um usuário
com acesso a esse banco. Configure o MySQL antes de executar as migrações.

```powershell
cd backend
composer install
Copy-Item .env.example .env
php bin/key.php
```

Copie a chave gerada para `APP_KEY` no arquivo privado `backend/.env`; configure
`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` nesse arquivo.
Não sobrescreva uma chave já usada para gravar dados. Guarde-a com segurança.

```powershell
composer migrate
```

Para uma demonstração com dados fictícios, em um banco vazio e `APP_ENV=local`,
defina uma senha própria de 12 a 72 bytes. Nenhuma senha padrão é gravada no código.

```powershell
$env:DEMO_PASSWORD = [System.Net.NetworkCredential]::new('', (Read-Host 'Senha das contas fictícias' -AsSecureString)).Password
composer seed
Remove-Item Env:DEMO_PASSWORD
composer serve
```

A carga exige base sem empresas, cria três trabalhadores/pacientes com planos
demonstrativos e um dependente adulto com conta própria, indicadores de participação
educativa com gráficos, casos administrativos, contrato, unidades e agendas.
Também cria um tutorial em texto publicado, um roteiro APS para revisão e um ciclo
de campanha. Gravações de vídeo e links reais de salas devem ser fornecidos pela equipe.
Os valores são fictícios e não constituem metas clínicas universais.

Contas: `doctor@demo.vivaelo.test`, `nurse@demo.vivaelo.test`,
`navigator@demo.vivaelo.test`, `worker@demo.vivaelo.test`,
`admin@demo.vivaelo.test` e `manager@demo.vivaelo.test`.
O dependente usa `dependent@demo.vivaelo.test`.
Todas usam a senha definida no ambiente durante a carga.
Na demonstração, o administrador tem gestão de plataforma, o gestor tem gestão
empresarial e a enfermeira tem coordenação APS. Os níveis não liberam prontuários.

## Cenários dos painéis

A demonstração dinâmica inclui 100 pessoas sintéticas, canal acessível do
trabalhador, atualização automática da fila, painel de resultados, exemplo do
prêmio coletivo e perfil demonstrativo do líder. Veja [escopo, limites e
validação](docs/evolucao-paineis-demonstracao.md). A carga sintética é restrita
ao banco separado da demonstração.

## Guia de uso

O [guia completo editável](docs/guia-completo-de-uso.md) cobre os perfis disponíveis,
fluxos, atalhos, celular, problemas frequentes e limites da versão.
O botão **Guia na tela** oferece orientação contextual com destaque visual e
passos por perfil em todas as telas, abas e seções. É possível clicar e preencher
com o guia aberto; ele acompanha a navegação e orienta campo a campo nos formulários.
O seletor de telas oferece apenas destinos permitidos ao perfil. Atalhos abrem
fichas ou posicionam o cursor; salvar e enviar exigem ação do usuário.
O ícone de livro nos painéis explica o formulário/ficha atual;
o manual completo também abre dentro da aplicação.
A versão online fica em `/guia-de-uso.html`, com busca e índice; o PDF em
`/guia-de-uso.pdf`. A plataforma apresenta o link na entrada, no rodapé e no menu do celular.
Para atualizar HTML, texto para download e PDF após editar o guia, execute
`npm run guide:build` na pasta `frontend` e publique a aplicação novamente.

## Publicar no próprio Laragon

Para servir a aplicação completa pelo Apache, com gravação no banco existente:

```powershell
cd frontend
npm run publish:laragon
```

A instalação compilada usa a pasta `public/` e o backend existente. Neste
computador, o virtual host já está configurado em **http://vivaelo.test**;
na rede Wi-Fi atual, **http://192.168.106.96**. Mantenha Apache e MySQL ligados.
O IP pode mudar quando o computador trocar de rede. As permissões existentes
continuam em vigor; seleção local por perfil só está disponível no próprio computador.

[Configuração e atualização da publicação](docs/server-deployment.md).

## Executar o frontend

Em outro terminal:

```powershell
cd frontend
npm ci
npm run dev
```

Abra `http://127.0.0.1:5173`. O Vite encaminha `/api` ao backend em
`http://127.0.0.1:8000`. Não use origens/hostnames diferentes para login e API.
Mantenha os dois processos em execução. Se a interface abrir, mas indicar serviço
indisponível, execute `composer serve` no terminal de `backend` e atualize a página.
A API pode ser conferida em `http://127.0.0.1:8000/api/v1/health`.
A rota `/system` preserva a tela de verificação da Fase A; o health check não
verifica disponibilidade do banco.

Médico e enfermeiro acessam pacientes e planos vinculados. A tela individual permite
programar retorno, criar/revisar plano, configurar indicadores, pactuar/ajustar metas,
registrar medições e evoluções, acompanhar adesão e solicitar encaminhamentos.
O ELO Reconecta oferece confirmação, comparecimento, barreiras voluntárias,
acolhimento, navegação, reagendamento, reconexão e aviso à equipe APS.
Trabalhador vê seus retornos; administrador e gestor recebem apenas agregados
minimizados. No exemplo com quatro beneficiários, agregados empresariais são suprimidos.

As novas áreas de navegação incluem Gestão, Encontrar atendimento, Meu cuidado,
Minha agenda, Conteúdos educativos, Ciclos de campanhas e Acolhimento e campanhas.
O cadastro remoto é feito em `/register` com convite nominal de uso único. O contrato
e o vínculo precisam estar ativos para reservar. A busca pode ordenar por distância
aproximada ou horário. Teleconsulta usa o link HTTPS exclusivo fornecido pelo
profissional em um provedor institucional; não há videoconferência embutida.

## Operação

```powershell
cd backend
php bin/sync-continuity.php
php bin/retention.php
```

Agende a sincronização periodicamente no ambiente para consultas preexistentes ou
importadas. Também existe botão de sincronização no ELO Reconecta. Retornos criados
pela API já geram pendência na mesma transação. O comando de retenção mostra tentativas
de login com mais de 30 dias; acrescente `--apply` para remover apenas essas tentativas.
Não remove histórico clínico ou auditoria.

## Verificação

```powershell
cd backend
composer validate --strict
composer test
```

Para integração MySQL, use um servidor de teste. O usuário precisa poder criar e
remover bancos temporários com prefixo `viva_elo_test_`. Não aponte esses testes a
um servidor de produção. Cada execução cria uma base única e remove somente essa
base no final, sem alterar o banco configurado em `.env`.

```powershell
$env:MYSQL_TEST_HOST = '127.0.0.1'
$env:MYSQL_TEST_PORT = '3307'
# MYSQL_TEST_USER e MYSQL_TEST_PASSWORD podem ser fornecidos pelo ambiente privado.
php tests/mysql.php
php tests/http.php
php tests/sql-bundles.php
```

```powershell
cd frontend
npm test
npm run build
```

Resultados: [verificação V3](docs/verification-v3.md).
Expansão atual: [verificação industrial](docs/verification-industry.md).
Revisão de interface: [fluxos simplificados e validação](docs/usability-review.md).
Ações diretas: [redução de cliques na fila, prontuário e teleconsulta](docs/usability-actions-review.md).
Trabalhador: [gamificação da jornada de cuidado](docs/worker-journey.md).
Prontuário: [resumo por paciente e regras de acesso](docs/patient-summary.md).
Enfermagem: [Central de Continuidade — entrega da Etapa 2](docs/nursing-continuity-stage2.md).
Demonstração compartilhável: [link público e seleção de perfis](docs/share-demo.md).

## Estrutura e publicação

- `backend/public`: único DocumentRoot público da API.
- `backend/src/Controllers`, `Services`, `Repositories`: HTTP, domínio e persistência.
- `backend/src/Core` e `Middleware`: roteamento, sessão, validação, conexão e segurança.
- `backend/database/migrations`: esquema incremental e relações.
- `frontend/src`: interface Vue, roteamento, stores, cliente HTTP e gráficos Chart.js.

Sirva `frontend/dist` com fallback SPA para `index.html` e encaminhe `/api/` para
`backend/public`. No Apache, habilite mod_rewrite/AllowOverride para o `.htaccess`.
Nunca exponha a raiz do repositório pelo Laragon. O servidor de preview do Vite não
substitui o proxy de produção. Use mesma origem e HTTPS no ambiente publicado.
Credenciais permanecem no backend; não colocar segredos em variáveis `VITE_*`.
Nenhuma versão externa foi publicada nesta entrega.

[Arquitetura, métricas e segurança](docs/architecture-v3.md) ·
[Contrato da API](docs/api-v1.md) · [Entrega histórica da Fase A](docs/phase-a.md).
[Gestão industrial e famílias](docs/industry-and-family.md) ·
[Novos endpoints](docs/industry-api.md).

Somente dados fictícios no Hackathon. O protótipo exige validação de segurança,
privacidade, habilitação profissional, protocolos assistenciais e conformidade
antes de qualquer uso clínico real.

## Criptografia pós-quântica (roadmap)

Dado de saúde tem vida longa; a arquitetura de produção deverá considerar
tráfego capturado hoje para possível decifração futura. Estão previstos TLS 1.3
com X25519MLKEM768, proteção híbrida das chaves fora da base, AES-256-GCM,
trilha encadeada assinada com ML-DSA-65 e arquivo à APS cifrado/assinado.
[FIPS 203 — ML-KEM](https://csrc.nist.gov/pubs/fips/203/final) e
[FIPS 204 — ML-DSA](https://csrc.nist.gov/pubs/fips/204/final) são referências.

Não implementado no protótipo. Negociação híbrida exige validação do cliente,
servidor e bibliotecas; não equivale a autenticação pós-quântica e não protege
automaticamente o trecho do WhatsApp. Não há promessa de certificação.

## Equipe

Equipe do protótipo VIVA+ ELO — Hackathon+ SESI Saúde 2026.
Os nomes e as funções serão incluídos quando informados pela equipe;
o repositório não atribui autoria nominal sem confirmação.
