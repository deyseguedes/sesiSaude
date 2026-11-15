# Importação APS SESI — evolução após o diagnóstico

Referência: 15/11/2026. Implementação incremental na aplicação existente.
O diagnóstico original permanece como registro da situação anterior.

## Entrega implementada

- Extração autoritativa de CSV/XLSX no PHP: o servidor lê o original e ignora linhas, cabeçalhos, calendário e números de linha enviados pelo cliente. A seleção do arquivo abre a prévia automaticamente, sem gravar.
- Leitura XLSX segue a primeira aba na ordem do workbook e suas relações, em vez de ordenar nomes de arquivos internos.
- Linhas sem conteúdo são descartadas antes do limite de 1.000; observações sem identidade permanecem no lote e recebem revisão separada.
- Os 32 cabeçalhos do arquivo diagnosticado têm destinos distintos. Cabeçalhos literais e colunas sem interpretação são preservados.
- Linhas XLSX conservam seus números originais, inclusive a observação da linha 392.
- Calendários Excel 1900 e 1904 são interpretados explicitamente; o dia fictício 29/02/1900 é rejeitado.
- FEMININO/MASCULINO e RISCO 1 a RISCO 4 são convertidos explicitamente, sem classificação gerada.
- CPF com dígitos inválidos gera aviso, preserva o valor recebido e não recebe hash de vínculo.
- Cinco estados são reconhecidos: registrado, vazio, não coletado, não aplicável e não informado na exportação. Os três últimos exigem marcadores explícitos; célula vazia nunca comprova ausência de atendimento.
- Campos vazios ou ausentes não apagam conteúdo anterior.
- A interface mantém o gerador fictício desativado inicialmente, inclusive na demonstração; ativá-lo é uma escolha explícita e invalida a prévia.
- Na confirmação, o original enviado pela interface, seu SHA-256, as linhas, cabeçalhos, mapeamento, observações órfãs e versões anteriores são conservados em arquivo AES-256-GCM no banco. Não há endpoint de download desse arquivo.
- Prévia e relatório público não expõem esse arquivo nem os valores originais sem máscara.
- Lotes têm identificação por conteúdo, versão de transformação, original, mapeamento e escopo (empresa operadora/visita). Repetir um lote, mesmo após outro processamento, reaproveita seu resultado sem alterar o cadastro nem duplicar a carga.
- Arquivo novo com conteúdo cadastral equivalente pode gerar um novo arquivo de origem, mas não altera a pessoa se a representação normalizada e a proveniência forem iguais.
- Arquivo, alterações de pessoas e apontamentos de revisão são gravados na mesma transação. Uma falha de persistência desfaz todos.
- Fórmulas e caches têm metadados no arquivo cifrado; nenhuma fórmula é calculada.
- Revisão integrada à tela de importação, com filtro de motivo/situação, paginação, valor de origem e anotação cifrada. CPF e telefone são mascarados.
- A revisão possui controle de versão e auditoria. Marcar como revisado não altera a origem, o cadastro ou qualquer evento de cuidado. A observação órfã não ganha vínculo automático.
- Acesso à revisão limitado a administrador/enfermagem, na empresa operadora e visita atuais; acessos de outro perfil/escopo são rejeitados.

## Instalação

A migração 014 cria aps_source_archives com conteúdo cifrado LONGTEXT e chave única de escopo/fingerprint.
A migração 015 cria aps_import_reviews e amplia o relatório de TEXT para LONGTEXT, necessário para as 1.000 linhas com avisos. Preserva as cargas existentes.
Executar composer migrate no diretório backend antes de usar a nova confirmação.
Scripts SQL completos são regenerados por composer schema:build.

Não houve importação automática do XLSX fornecido. A interface exige prévia e confirmação da carga.
Os endpoints HTTP de importação exigem original_base64 e filename. A chamada interna antiga permanece disponível para compatibilidade e testes, sem criar rota que dispense o original.
O servidor PHP requer dom, mbstring, openssl e zlib. Usa ext-zip quando disponível; se estiver desabilitada, usa o leitor limitado ApsZipReader para ZIP armazenado/deflate, com validação de tamanho e CRC. Não depende de Python para a extração.
A instância pública do Apache foi validada sem ext-zip: prévia do XLSX fornecido com 100 trabalhadores válidos e a observação 392 separada, sem importação. O ngrok foi conferido servindo os novos arquivos da interface.
O catálogo backend/config/aps-columns.json é sincronizado por node scripts/sync-aps-columns.mjs; o teste de frontend detecta divergência entre os catálogos.

## Validação executada

- Testes de API/domínio: normalização, calendários, CPF inválido, cinco estados, preservação de vazio, histórico cifrado, original e hash, observação órfã, repetição consecutiva e reprocessamento de arquivo anterior sem reversão.
- Testes de frontend: todos os 32 cabeçalhos com destinos únicos e descarte de 1.290 linhas vazias sem perder observações.
- Leitura do XLSX fornecido no Chrome: 32 colunas reconhecidas, 101 linhas não vazias (100 cadastros e uma observação), última linha 392; sem envio ao banco.
- Extração do mesmo XLSX pelo PHP, sem conexão com o banco: 32 colunas, 100 trabalhadores, observação 392 e 100 caches de fórmulas.
- Testes do servidor: tentativa de adulterar linhas/cabeçalhos/calendário do cliente, aba por relacionamento, fórmulas sem execução, 1.290 linhas formatadas, XML com DTD e aba externa rejeitados; escopo, notas cifradas, conflito de versão e rollback.
- npm --prefix frontend run test:import-ui: prévia sem gravação, confirmação, revisão da observação sem vínculo, carga repetida e layout móvel em banco MySQL isolado com dados fictícios.
- Compilação de produção.

O roteiro geral test:ui foi tentado e interrompeu em uma verificação antiga que espera o prêmio no topo do médico. A tela atual abre a carteira; essa parte legada do roteiro precisa de atualização. O novo fluxo possui execução própria test:import-ui, sem dispensar suas verificações.

## Contrato HTTP

- POST /api/v1/aps/import/inspect: filename e original_base64, mapping opcional; retorna cabeçalhos, mapeamento sugerido, contagens e prévia protegida. Não grava.
- POST /api/v1/aps/import: mesmos dados e preview=false para confirmar; a extração é refeita no servidor.
- GET /api/v1/aps/import/reviews: status pending/reviewed/all, code opcional e page; 50 itens por página, com totais por motivo/situação.
- GET /api/v1/aps/import/reviews/{id}: apontamento e células de origem pertinentes, com CPF/telefone mascarados.
- PUT /api/v1/aps/import/reviews/{id}: status, note e version; uma versão desatualizada recebe 409. Alterações exigem sessão e CSRF, seguindo as rotas existentes.

Não há endpoint para vincular automaticamente uma observação órfã ou converter uma conferência em evento clínico.

## Limites e próximas etapas

Este incremento não conclui a arquitetura operacional descrita no documento de projeto.
A extração no servidor e a conferência técnica estão disponíveis. Ainda não há fila agendada de cargas diárias, interface de reconciliação entre versões ou restauração.
Os apontamentos são criados para cargas novas confirmadas. Cargas históricas anteriores à migração permanecem preservadas e não são reprocessadas automaticamente.
Fórmulas usam valores salvos; a divergência de idade é sinalizada na data da validação, sem substituir o valor recebido.
A matrícula mantém o escopo técnico anterior de empresa operadora/visita. A regra de vínculo por indústria ainda precisa de homologação.
Não há autorização nova por categoria profissional para nutrição e psicologia, nem promoção automática dos registros para o prontuário interno.
Não foram criados retornos, adesão, riscos ou eventos clínicos a partir dos vazios. As fases de cuidado e indicadores precisam revisar também os consumidores legados da base consolidada.
Laravel, TypeScript e Pandas não foram introduzidos neste incremento; a arquitetura atual permanece PHP/Vue/JavaScript.
