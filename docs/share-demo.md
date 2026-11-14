# Demonstração pública por níveis de acesso

Link: https://viva-elo-demonstracao.verbalrhea1.chatgpt.site

Publicado com Sites, usando somente dados fictícios e seleção direta entre
administrador, gestor, médico, enfermeiro, navegador, trabalhador e dependente.
O seletor apresenta os espaços de cada perfil; não autentica identidades reais.
Todo o conteúdo deste ambiente é público. Alterações e agendamentos não são gravados.

## Separação do ambiente real

O backend PHP não possui rota de entrada sem senha. O login normal continua
exigindo credenciais. Apenas a compilação com `VITE_SHARE_DEMO=true` utiliza o
adaptador estático; não conecta à API nem ao banco de uso local.

`frontend/scripts/export-share-demo.mjs` cria um banco temporário pelo helper de
QA, aplica migrações e carga fictícia, consulta os endpoints autenticados para
cada perfil e exporta suas respostas. O banco temporário é removido ao final.
Senhas, tokens e sessões são excluídos do artefato público. Nunca exportar o banco
de uso real para esta demonstração.

As respostas são um cenário fixo de apresentação. Horários e métricas refletem
a geração da demonstração, sem atualização ao vivo. A busca de disponibilidade
usa horários fictícios por beneficiário, modalidade e equipe, com filtros de
profissão. Distâncias usam a unidade industrial fictícia, sem cálculo por GPS.

## Gerar novamente

Com MySQL local disponível e dependências instaladas:

```powershell
cd frontend
node scripts/export-share-demo.mjs
$env:VITE_SHARE_DEMO='true'
npm run build -- --outDir ../share-demo/dist
node scripts/share-demo-qa.mjs
Remove-Item Env:VITE_SHARE_DEMO
```

Para publicação, reutilizar o Site existente e seguir o workflow do skill Sites.
Identidade: `appgprj_6af8b25e0e008191aef8f0741f61ff3c`.
Manifesto local: `share-demo/.openai/hosting.json`, com `static.directory=dist`.
O diretório de publicação tem seu próprio repositório e não inclui `.env`, banco,
sessões ou arquivos do backend. As credenciais de publicação são temporárias e
não devem ser salvas em arquivos nem no Git.

## Validação

26 testes frontend aprovados, incluindo seleção, isolamento da autenticação
normal, bloqueio de gravação e respostas por perfil. Chrome confirmou os sete
perfis, navegação, persistência ao atualizar, troca de perfil, prontuário médico,
seletor no celular e ausência de erros de execução no navegador.
