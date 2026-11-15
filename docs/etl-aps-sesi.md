# Importação APS SESI — evolução após o diagnóstico

Referência: 15/11/2026. Implementação incremental na aplicação existente.
O diagnóstico original permanece como registro da situação anterior.

## Entrega implementada

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
- Arquivo e alterações de pessoas são gravados na mesma transação. Uma falha de persistência desfaz ambos.

## Instalação

A migração 014 cria aps_source_archives com conteúdo cifrado LONGTEXT e chave única de escopo/fingerprint.
Executar composer migrate no diretório backend antes de usar a nova confirmação.
Scripts SQL completos são regenerados por composer schema:build.

Não houve importação automática do XLSX fornecido. A interface exige prévia e confirmação da carga.
Clientes antigos sem original_base64 continuam aceitos; nesses casos o arquivo de origem contém apenas as linhas recebidas e sinaliza original ausente.

## Validação executada

- Testes de API/domínio: normalização, calendários, CPF inválido, cinco estados, preservação de vazio, histórico cifrado, original e hash, observação órfã, repetição consecutiva e reprocessamento de arquivo anterior sem reversão.
- Testes de frontend: todos os 32 cabeçalhos com destinos únicos e descarte de 1.290 linhas vazias sem perder observações.
- Leitura do XLSX fornecido no Chrome: 32 colunas reconhecidas, 101 linhas não vazias (100 cadastros e uma observação), última linha 392; sem envio ao banco.
- Compilação de produção.

## Limites e próximas etapas

Este incremento não conclui a arquitetura operacional descrita no documento de projeto.
A extração XLSX ainda ocorre no navegador; o servidor valida as linhas recebidas, mas não reconcilia essas linhas com o binário original. Um ETL independente no servidor permanece necessário.
As fórmulas não são executadas; são utilizados caches. O original preserva as fórmulas, mas ainda não existe catálogo consultável de fórmulas e células.
Os arquivos cifrados permitem rastrear versões anteriores, mas ainda não há interface de revisão, reconciliação ou restauração.
A matrícula mantém o escopo técnico anterior de empresa operadora/visita. A regra de vínculo por indústria ainda precisa de homologação.
Não há autorização nova por categoria profissional para nutrição e psicologia, nem promoção automática dos registros para o prontuário interno.
Não foram criados retornos, adesão, riscos ou eventos clínicos a partir dos vazios. As fases de cuidado e indicadores precisam revisar também os consumidores legados da base consolidada.
Laravel, TypeScript e Pandas não foram introduzidos neste incremento; a arquitetura atual permanece PHP/Vue/JavaScript.
