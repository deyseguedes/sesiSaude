# APS SESI — diagnóstico técnico preliminar

Data: 15/11/2026.
Referência: pedido “Sistema Inteligente de Gestão do Cuidado — APS SESI”.
Estado: relatório preliminar histórico. O XLSX foi fornecido posteriormente e seu diagnóstico está concluído em [Diagnóstico da fonte APS](diagnostico-aps-sesi/README.md).

## 1. Escopo e evidências

O anexo desta solicitação contém somente o texto do pedido. O arquivo
“GESTÃO DO CUIDADO - APS SESI dados fictícios.xlsx” não foi encontrado nos
locais consultados: arquivos de dados do repositório fora de dados-evento/
e a pasta do anexo desta mensagem.

A pasta dados-evento/ não foi aberta nem inspecionada, respeitando a restrição
anterior. Não foram consultadas bases clínicas nem alterados dados ou telas.

O arquivo backend/database/fixtures/100-pessoas-sinteticas.csv é uma massa
anterior do protótipo. Não representa os cabeçalhos, conteúdos ou preenchimento
do XLSX solicitado e não será usado como substituto.

Não estão confirmados: nome efetivo da aba, quantidade de linhas/colunas,
cabeçalhos, tipos observados, preenchimento, duplicidades e inconsistências.
A indicação de aproximadamente 100 registros e 32 colunas é informação do
pedido, não uma medição realizada.

## 2. Situação das dez entregas obrigatórias

| Entrega | Situação |
| --- | --- |
| Dicionário de dados | Estrutura definida; nomes e semântica dependem do XLSX |
| Mapeamento das 32 colunas | Pendente; nenhuma correspondência foi inventada |
| Tipos de dados | Tipos conceituais propostos; tipos observados pendentes |
| Relatório de preenchimento | Pendente de leitura das células |
| Inconsistências | Lacunas do código verificadas; qualidade do arquivo não medida |
| Modelo relacional | Proposta inicial abaixo, sujeita ao diagnóstico |
| Mapeamento ETL | Contrato de rastreabilidade proposto; correspondências pendentes |
| Regras para validação humana | Listadas abaixo |
| Arquitetura técnica | Estado atual e decisão proposta documentados |
| Plano de implementação | Fases e critérios de saída definidos |

## 3. Dicionário e mapeamento: contrato da entrega final

Cada coluna efetivamente encontrada terá uma entrada com:

- Nome exato do cabeçalho, aba, posição e linha do cabeçalho.
- Tipo da célula Excel, tipo observado e tipo interno proposto.
- Significado confirmado, domínio e obrigatoriedade validada.
- Estado de informação e regra de normalização.
- Entidade/campo de destino, sensibilidade e perfis autorizados.
- Quantidades preenchidas, vazias, inválidas e sem correspondência.
- Referência à origem e observações para validação humana.

Grupos mencionados no pedido: identificação, empresa/vínculo, referência
profissional, consultas, contatos mensais, retorno, demandas, risco, plano,
adesão e coordenação multiprofissional. São grupos conceituais; não equivalem
a uma lista verificada de 32 cabeçalhos.

Matrícula deve ser tratada como identificador textual, preservando zeros.
CPF é identificador, não número para cálculo. Datas serão normalizadas sem
alterar o valor de origem. Conteúdo de nutrição e psicologia não será tratado
como texto genérico livre para todos os perfis.

## 4. Estado do ETL atual: fatos verificados

Fontes revisadas:

- frontend/src/utils/import-aps.js.
- frontend/src/views/ImportView.vue.
- backend/src/Services/ApsImportService.php.
- backend/src/Services/DemoWorkspace.php.
- backend/database/migrations/013_hackathon.sql.

| Capacidade | Estado atual | Diferença para o novo pedido |
| --- | --- | --- |
| CSV e XLSX | Extração no navegador e validação no servidor | Falta arquivo original protegido e extração reprocessável no servidor |
| Abas/cabeçalhos | Seleciona um XML de planilha e assume primeiro cabeçalho | Falta descobrir abas, relações do workbook e posição real do cabeçalho |
| Sinônimos | Lista genérica, ignorando caixa/acentos | Ainda não mapeada aos cabeçalhos reais do SOC |
| Obrigatórias | Matrícula, nome, empresa e status no código | São regras do protótipo; precisam ser homologadas para este arquivo |
| CPF | Remove pontuação, exige 11 dígitos e detecta duplicados | Não calcula dígitos verificadores |
| Matrícula | Texto aparado, chave por empresa/visita | Falta normalização homologada e preservação explícita do valor original |
| Datas | dd/mm/aaaa, ISO simples e número serial Excel | Falta validar calendário 1900/1904 do workbook e semântica temporal de cada coluna |
| Estados de informação | Converte null/célula ausente para texto vazio | Não distingue os cinco estados exigidos |
| Informações não mapeadas | Não entram na lista permitida do payload | Não são preservadas integralmente para rastreabilidade |
| Atualização | Atualiza o payload inteiro da matrícula existente | Pode sobrescrever informação anterior com vazio ou campo não exportado |
| Duplicidade | Evita outra pessoa com a mesma matrícula dentro do escopo | Não assegura idempotência de importação e histórico |
| Reimportação | Atualiza data e insere novo relatório a cada confirmação | Repetir o mesmo arquivo pode criar histórico redundante |
| Histórico | Há relatório de carga e auditoria de ação | Falta versão por campo com antes/depois e localização de origem |
| Qualidade | Erros, avisos e prévia | Não é ainda o painel de qualidade solicitado |
| Dados gerados | Existe gerador de demonstração, ativado por padrão na interface pública | Não deve integrar o fluxo SOC deste novo escopo |

Não foi executada importação nesta análise. A interface de demonstração existente
continua com suas regras anteriores; este relatório não afirma que já foi adaptada.

## 5. Estados de informação

A modelagem deve representar separadamente:

| Estado | Evidência necessária |
| --- | --- |
| Vazio | Coluna presente, célula efetivamente vazia |
| Não coletado | Marcador explícito da origem ou confirmação do responsável |
| Não aplicável | Marcador explícito e contexto validado |
| Não informado na exportação | Campo ausente da estrutura exportada ou indicação explícita |
| Registrado | Valor presente, com validação registrada separadamente |

Valor inválido não é sinônimo de vazio: conservar o conteúdo e associar uma
inconsistência. “Não coletado” e “não aplicável” não serão deduzidos de células vazias.
Fórmula sem resultado em cache, linha ausente e cabeçalho ausente também precisam
de tratamento distinto.

Sem data de retorno exportada, registrar incompletude informacional; não criar
automaticamente uma falta, um retorno vencido ou uma classificação clínica.

## 6. Relatório de qualidade: como será calculado

O relatório final será produzido por arquivo e por aba, com denominadores visíveis:

- Linhas de dados identificadas, válidas, rejeitadas, em revisão e repetidas.
- Cabeçalhos encontrados, duplicados, obrigatórios ausentes e não mapeados.
- Por coluna: cinco estados de informação, formatos inválidos e valores distintos.
- Matrículas/CPFs repetidos, CPF com dígitos inválidos e datas inconsistentes.
- Campos e registros completos, conforme um conjunto obrigatório homologado.
- Divergências entre importações, sem apagar o registro anterior.

Preenchimento observado = células com conteúdo ÷ células observadas nas colunas
avaliadas. Completude operacional = campos obrigatórios válidos e registrados ÷
campos obrigatórios esperados, com tratamento de “não aplicável” homologado.

Nenhuma dessas taxas foi calculada sem a planilha. Completude não mede qualidade
do atendimento e um campo preenchido pode conter informação inválida.

## 7. Modelo relacional proposto

Nomes abaixo são propostas de entidades técnicas, não colunas presumidas do SOC.

| Entidade | Finalidade |
| --- | --- |
| Arquivo de origem | Original protegido, hash, tamanho, nome e política de retenção |
| Lote de importação | Arquivo, empresa autorizada, versão do mapeamento/processamento e resultado |
| Aba e linha de origem | Localização exata de cada registro e vínculo com o original |
| Mapeamento de coluna | Cabeçalho original, destino, normalização e versão homologada |
| Inconsistência | Campo/linha, código do problema, severidade e resolução |
| Trabalhador e vínculo | Identidade textual e relação com empresa, com histórico |
| Profissional/equipe | Referência e vínculos efetivamente identificados |
| Consulta/contato/retorno | Eventos observados e sua origem, sem converter vazio em ausência |
| Plano, versão e meta | Conteúdo registrado, versões e adesão explicitamente informada |
| Acompanhamento multiprofissional | Categoria, responsável e registro, com acesso segregado |
| Atividade operacional | Motivo, responsável, prazo aprovado, estado e histórico de resolução |
| Histórico de alteração | Antes/depois, autor/processo, lote e localização de origem |

As tabelas atuais de identidade e cuidado devem ser reaproveitadas onde houver
equivalência comprovada. A relação entre registro importado e paciente do
prontuário não será criada somente por semelhança de nome.

## 8. Contrato ETL e idempotência propostos

Extract:
receber original → calcular hash → validar estrutura → identificar abas e
cabeçalhos → registrar localização de origem → manter staging sem perdas.

Transform:
mapear somente correspondências validadas → normalizar sem apagar o original →
registrar estado de informação → validar formato/domínio → identificar conflitos.
Sem geração de diagnósticos, risco, atendimento ou adesão.

Load:
validar escopo de acesso → aplicar transação → identificar pessoa/vínculo com
chave homologada → comparar versão canônica → gravar apenas alterações reais →
preservar proveniência e emitir relatório.

Identidade de processamento proposta: escopo autorizado + origem + hash do
arquivo + versão do mapeamento + versão da transformação. Repetir a mesma
identidade reaproveita o resultado, sem nova versão de pessoa ou evento clínico.

Arquivo com conteúdo novo, mas registro normalizado idêntico, não cria nova
alteração desse registro. Reprocessamento com regra nova registra a nova versão
do processamento e seu vínculo com o arquivo original.

Tentativas técnicas, se auditadas, devem ficar separadas do histórico de cargas
processadas, sem inflar os indicadores. Uma linha ausente numa nova exportação
não desativa nem exclui um trabalhador automaticamente.

## 9. Regras que precisam de validação humana

1. Cabeçalhos efetivos, códigos e obrigatoriedade por coluna/estado.
2. Escopo de unicidade da matrícula: empresa, estabelecimento ou contrato.
3. Como resolver conflito matrícula × CPF e duplicidades entre empresas.
4. Significado de cada célula de contato, consulta, plano e adesão.
5. Marcadores para não coletado, não aplicável e não exportado.
6. Quando um vazio pode limpar informação anterior, se permitido.
7. Qual evento comprova retorno realizado e como vinculá-lo à previsão.
8. Intervalos de contato/revisão, responsáveis e protocolos aprovados pelo SESI.
9. Acesso de nutrição e psicologia e quais informações podem ser compartilhadas.
10. Escopo de indicadores, datas de referência, retenção, backup e recuperação.

As janelas de demonstração de 30 dias/3 dias e o risco sintético do projeto
anterior não constituem protocolos clínicos aprovados para este novo fluxo.

## 10. Arquitetura e plano de implementação

Estado atual: Vue 3/JavaScript, API PHP própria, PDO/MySQL, sessões, CSRF e auditoria.
Laravel, TypeScript e Python/Pandas são preferências do novo documento, ainda
não implantadas. A escolha de execução será detalhada depois do diagnóstico;
esta fase não inicia uma reescrita da aplicação.

Proposta: manter a interface e os contratos em funcionamento; separar extração,
staging, transformação, validação, persistência, indicadores e regras operacionais.
Decidir o uso de um serviço Python/Pandas e eventual evolução para Laravel após
o diagnóstico, com critérios de volume, rastreabilidade e manutenção.

| Fase | Entrega e critério de saída |
| --- | --- |
| 1 — Diagnóstico | XLSX inspecionado; dicionário e mapeamento efetivos; qualidade medida; dúvidas semânticas registradas |
| 2 — ETL | Original preservado; cinco estados; versões; reprocessamento e idempotência comprovados por testes |
| 3 — Cuidado | Trabalhador/histórico/fila/planos ligados à origem; protocolos humanos e permissões por equipe |
| 4 — Indicadores | Fórmulas, denominadores, filtros e reconciliação com casos conhecidos; sem números inventados |
| 5 — Homologação | Auditoria, segregação, testes de acesso, retenção e recuperação validadas no ambiente apropriado |

A implementação da nova fase 2 deve aguardar a conclusão da fase 1.

## Próxima entrada necessária

Disponibilizar o XLSX indicado em anexo ou informar um caminho autorizado.
Se estiver em dados-evento/, a autorização deve indicar especificamente esse
arquivo, pois a restrição anterior de leitura continua vigente. Sem essa entrada,
não é possível entregar honestamente o mapeamento real das 32 colunas nem as taxas
de preenchimento e inconsistências.
