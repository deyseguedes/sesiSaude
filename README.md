# VIVA+ ELO — arquitetura V3

Protótipo de continuidade do cuidado na APS para indústrias contratantes,
trabalhadores e dependentes, com backend PHP 8.2+ próprio e frontend Vue 3.
Inclui gestão por níveis, cadastro remoto, agendas presenciais/teleconsulta,
educação e campanhas, integrados a planos, metas, indicadores e ELO Reconecta.

O diretório inicial tinha apenas `backend/` e `frontend/` vazios. Não havia código
legado, esquema SQL ou importadores para migrar. As funcionalidades de versões
anteriores não fornecidas não fazem parte desta implementação.

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

## Guia de uso

O [guia completo editável](docs/guia-completo-de-uso.md) cobre os sete perfis,
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
