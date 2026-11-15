# Publicar no servidor próprio — aplicação com gravação

Esta publicação serve o Vue compilado e a API PHP na mesma origem. Não usa o adaptador estático do Sites. As consultas e alterações são gravadas no MySQL configurado no servidor. O endereço público depende do domínio e da infraestrutura fornecidos pelo proprietário.

## Gerar o pacote

Na raiz do projeto, com dependências instaladas:

```powershell
node frontend/scripts/build-server-release.mjs
```

O pacote fica em server-release/. O script força VITE_SHARE_DEMO=false, remove o snapshot fictício demo-data.json e copia somente os arquivos necessários ao backend, incluindo dependências Composer já instaladas. Não copia .env, sessões, logs, bancos locais ou cargas de demonstração. A pasta server-release/ é gerada novamente a cada execução; não guarde configurações ou dados nela.

## Instalação

Requisitos: Apache 2.4 com mod_rewrite e AllowOverride All, PHP 8.2+ com PDO MySQL, OpenSSL e mbstring, MySQL 8 e certificado HTTPS. Nginx é possível, mas exige configuração equivalente e endereço real do PHP-FPM.

1. Transfira o conteúdo do pacote para um diretório da aplicação no servidor.
2. Configure o domínio com raiz pública apontando **exclusivamente para a pasta public/** do pacote. A pasta backend/ fica fora da raiz pública.
3. Crie backend/.env a partir de backend/.env.example e preencha a conexão privada do banco.
4. Defina APP_KEY. Para dados existentes, preserve a chave original; para banco novo, gere com php bin/key.php na pasta backend e guarde o valor no .env privado.
5. Dê ao usuário do PHP permissão de gravação somente em backend/storage/logs e backend/storage/sessions. O .env precisa ser legível pelo PHP, sem acesso público.
6. Faça backup da base existente e execute php bin/migrate.php na pasta backend. Não execute cargas fictícias ou importação do SQL completo sobre banco populado.
7. Ative o HTTPS e confira os acessos com contas existentes do banco.

O frontend acessa /api/v1 na própria origem. O Apache encaminha /api/... para public/api.php, que carrega a aplicação PHP fora da raiz pública. Demais rotas Vue retornam index.html; os arquivos assets são servidos diretamente. Não publique a raiz inteira do repositório.

## Verificação

- GET https://SEU-DOMINIO/api/v1/health retorna JSON.
- A tela de login carrega e consegue iniciar sessão.
- Um usuário autorizado grava uma alteração e a encontra depois de atualizar a página.
- Rotas internas, como /patients, abrem diretamente.
- backend/.env e arquivos de código privados não são acessíveis pela web.
- A interface não mostra o aviso de demonstração somente para consulta.

Em produção, a seleção local sem senha continua desabilitada: os perfis e permissões pertencem a usuários autenticados. Um ambiente de demonstração gravável e sem senha deve usar base exclusivamente fictícia e ter sua exposição definida antes da publicação.

## Laragon ou hospedagem

Para instalar o frontend compilado junto ao backend e banco já existentes neste projeto, execute na raiz:

```powershell
node frontend/scripts/build-server-release.mjs --install-laragon
```

A opção copia a interface para public/ e utiliza backend/.env existente, sem copiar ou sobrescrever credenciais. O Vite pode ficar desligado; Apache e MySQL precisam estar ligados. Após alterações no frontend, execute novamente o comando para atualizar a publicação.

No Laragon, configure um virtual host cuja raiz seja a pasta public/ do pacote. Isso entrega uma instalação compilada independente do Vite. Disponibilidade externa exige domínio/DNS, HTTPS e acesso de rede até o servidor; criar um virtual host local não cria um endereço público.

### Configuração aplicada neste computador

- URL local: http://vivaelo.test
- URL da rede Wi-Fi atual: http://192.168.106.96
- Virtual host: C:/laragon/etc/apache2/sites-enabled/vivaElo.test.conf
- Raiz pública: C:/laragon/www/vivaElo/public
- API: /api/v1, executada pelo Apache/PHP usando backend/.env existente.
- Configuração automática anterior preservada com extensão .disabled; o arquivo customizado evita sobrescrita pelo Laragon.

A instalação --install-laragon limita o acesso HTTP ao loopback e endereços de redes privadas. Não configura domínio público, túnel ou encaminhamento de portas no roteador.

O IP da rede pode mudar por DHCP. Se mudar, atualize o ServerAlias do virtual host e recarregue o Apache. O teste pela própria máquina confirmou o endereço de rede; acesso de outro aparelho depende de conectividade e regras da rede. A seleção sem senha permanece disponível somente por loopback; acesso pela rede utiliza autenticação existente.

A pasta raiz do projeto tem bloqueio HTTP, e só public/ permite acesso. O pacote gerado também contém esse bloqueio. Isso impede leitura de código e configurações privadas por caminhos alternativos do Apache.

Em hospedagem com public_html fixa, aponte o domínio para a pasta public/ quando o painel permitir. Se for necessário separar os arquivos em public_html e uma pasta privada diferente, ajuste public/api.php para o caminho privado real antes da publicação.

Dados, chave, usuários, backup, certificado, domínio e diretório final são configurações do servidor. O pacote não inventa esses valores e não contém credenciais.
