# Diagnóstico da planilha APS SESI — fase 1

Evolução posterior: [importação e preservação da origem](../etl-aps-sesi.md).
Os achados abaixo retratam o importador antes desse incremento.

**Referência:** 15/11/2026. **Estado:** diagnóstico do arquivo concluído; validações semânticas indicadas para a próxima fase.

## 1. Identificação e método

Arquivo: GESTÃO DO CUIDADO - APS SESI dados fictícios.xlsx.
Tamanho: 57.063 bytes.
SHA-256: 9d1ac1365c424e56a6bdf02e96f6545a62ee45235a509e0992a80d0d4ab2096e.

Leitura direta e somente leitura do pacote XLSX, XML de células, estilos, validações,
relações do workbook e caches de fórmulas. Nenhuma fórmula foi executada, nenhum
valor foi alterado e nenhuma linha foi importada para o banco. Textos da planilha
foram tratados como dados, não como instruções.

A origem permaneceu no caminho autorizado de Downloads. O XLSX, nomes, matrículas,
CPFs, telefones e nomes de profissionais não foram copiados para o repositório.
Os artefatos publicados contêm cabeçalhos, estatísticas e referências de células.

| Estrutura observada | Resultado |
| --- | --- |
| Abas | 1, visível: Coordenação do cuidado |
| Cabeçalho | Linha 1, 32 posições preenchidas de A a AF |
| Dimensão declarada | A1:AF1392 |
| Cadastros | 100, nas linhas 2 a 101 |
| Linha não vinculada | Linha 392, somente a célula M392 preenchida |
| Linhas XML vazias/formatadas | 1.290, além do cabeçalho e das 101 linhas não vazias |
| Fórmulas | 100 células de idade, E2:E101; fórmulas compartilhadas |
| Calendário Excel | 1900 |
| Mesclagens / macros VBA / vínculos externos detectados | 0 / 0 / 0 |

A contagem de trabalhadores exige contexto cadastral em A:K. A linha 392 não foi
descartada: foi classificada separadamente para revisão. Não há 1.391 trabalhadores
nem 101 trabalhadores identificados.

## 2. Relatório de preenchimento e qualidade

Denominador principal: **100 cadastros × 32 colunas = 3.200 células**.
A célula isolada M392 não participa desse denominador.

| Medida | Resultado |
| --- | --- |
| Células preenchidas nos cadastros | 1.100/3.200 = 34,38% |
| Células vazias nos cadastros | 2.100/3.200 = 65,63% |
| Campos cadastrais A:K | 100/100 preenchidos em cada coluna |
| Campos assistenciais L:AF | 0/100 preenchidos em cada uma das 21 colunas |
| Status | 60 ATIVO; 40 INATIVO |
| Empresas distintas | 6; tamanhos dos grupos: 25, 18, 17, 16, 15 e 9 |
| Referências médicas distintas | 4; tamanhos dos grupos: 29, 26, 24 e 21 |
| Sexo registrado | 50 FEMININO; 50 MASCULINO |
| Matrículas, nomes, CPFs e telefones repetidos | Nenhum repetido em cada uma dessas quatro colunas |
| Matrículas ou nomes vazios entre os cadastros | 0 |
| CPFs com 11 dígitos após normalização | 100/100 |
| CPFs com dígitos verificadores válidos | 0/100 |
| Telefones com quantidade de dígitos fora de 10 ou 11 | 0/100 |
| Datas inválidas/futuras em nascimento ou entrada | 0 entre os 200 valores |
| Entrada APS anterior ao nascimento | 0 |
| Idades em cache diferentes da idade por aniversário em 15/11/2026 | 5/100, nas linhas 17, 26, 56, 78 e 80 |

As 100 idades são resultados em cache de fórmulas, não entradas manuais independentes.
Os percentuais arredondados de preenchido/vazio podem somar 100,01%.
Formato de telefone não comprova que o número está ativo. CPF com formato de onze
dígitos não equivale a CPF válido.

Todos os códigos de CPF falharam na verificação. Isso deve constar como problema de
qualidade da massa fictícia; não autoriza inventar CPFs substitutos, descartar as
pessoas ou vinculá-las automaticamente a pessoas de um prontuário real.

O padrão de fórmula de idade observado é:
IFERROR(INT((TODAY()-D{linha})/365.25),"").
Há quatro âncoras e 96 ocorrências compartilhadas. A divisão por 365,25 é uma
aproximação. Nas cinco divergências, o cache está um ano abaixo da idade por
aniversário na data de referência. Não é possível atribuir a diferença somente
à fórmula ou à data de recálculo sem avaliar a origem. Preservar cache/fórmula;
se houver idade calculada no sistema, explicitar sua data de referência.

### Observação sem trabalhador

M392 contém um serial Excel com formato de data, interpretável como **23/03/2026**,
na coluna de última consulta de nutrição. A linha não possui matrícula, nome,
CPF ou outros dados cadastrais. Manter em staging/revisão, com referência à célula.
Não associar por proximidade à última pessoa cadastrada e não somar a um atendimento
de trabalhador sem identificação confirmada.

### Cinco estados de informação

Nos 100 cadastros, as colunas L:AF existem no cabeçalho e estão vazias.
Mesmo quando uma célula é omitida fisicamente do XML esparso, a coluna presente
não deve ser rotulada automaticamente como “não informada na exportação”.

- Vazio: ausência de conteúdo observada.
- Não coletado: exige marcador ou confirmação; não foi estabelecido por esta leitura.
- Não aplicável: exige marcador/contexto; não foi estabelecido por esta leitura.
- Não informado na exportação: depende do contrato da origem ou de indicação explícita.
- Registrado: conteúdo presente, com qualidade validada separadamente; CPF inválido
  continua sendo conteúdo registrado com inconsistência.

**Não concluir “não houve consulta”, “não aderiu”, “está sem cuidado” ou “precisa
retornar” a partir desses vazios.**

## 3. Dicionário e mapeamento integral das 32 colunas

Os cabeçalhos abaixo são os efetivamente encontrados. Os destinos são propostas
de modelagem, não campos já implantados nem colunas adicionais presumidas do SOC.
Tipos sem valores observados são propostas e ficam sujeitos à confirmação.

| Coluna | Cabeçalho literal | Preenchimento | Tipo/destino proposto |
| --- | --- | --- | --- |
| A | MATRÍCULA | 100/100 | Texto identificador → worker_source.registration |
| B | NOME COMPLETO | 100/100 | Texto → worker_source.name |
| C | STATUS | 100/100 | Enum → worker_company_link.source_status |
| D | DATA DE NASCIMENTO | 100/100 | Data → worker_source.birth_date |
| E | IDADE | 100/100 | Inteiro calculado / cache → source_observation.age_cache |
| F | CPF | 100/100 | Texto CPF → worker_source.cpf |
| G | SEXO | 100/100 | Enum textual → worker_source.sex |
| H | DATA DE ENTRADA APS | 100/100 | Data → worker_company_link.aps_entry_date |
| I | TELEFONE | 100/100 | Texto telefone → worker_contact.source_phone |
| J | EMPRESA | 100/100 | Texto de referência → company_source.label |
| K | MÉDICO DE REFERÊNCIA | 100/100 | Texto de referência → reference_assignment.source_professional_label |
| L | ATENDIMENTO COM EQUIPE MULTI? | 0/100 | Enum proposto; lista explícita → multidisciplinary_observation.source_flag |
| M | DATA ÚLTIMA CONSULTA NUTRICIONISTA | 0/100 | Data proposta; formato de data observado na célula isolada → encounter_observation.nutrition_last_date |
| N | DATA ÚLTIMA CONSULTA PSICOLOGIA | 0/100 | Data proposta; sem valores observados → encounter_observation.psychology_last_date |
| O | DATA ÚLTIMA CONSULTA MÉDICA | 0/100 | Data proposta; sem valores observados → encounter_observation.medical_last_date |
| P | HISTÓRICO CONTATO DE CONSTADOS DE 2026 | 0/100 | Indeterminado; texto provisório → contact_source.history_observation |
| Q | CONTATO DE JULHO/ 2026 | 0/100 | Indeterminado; observação de julho/2026 → contact_observation[2026-07] |
| R | CONTATO DE AGOSTO / 2026 | 0/100 | Indeterminado; observação de agosto/2026 → contact_observation[2026-08] |
| S | CONTATO DE SETEMBRO / 2026 | 0/100 | Indeterminado; observação de setembro/2026 → contact_observation[2026-09] |
| T | CONTATO DE OUTUBRO / 2026 | 0/100 | Indeterminado; observação de outubro/2026 → contact_observation[2026-10] |
| U | CONTATO DE NOVEMBRO / 2026 | 0/100 | Indeterminado; observação de novembro/2026 → contact_observation[2026-11] |
| V | CONTATO DE DEZEMBRO / 2026 | 0/100 | Indeterminado; observação de dezembro/2026 → contact_observation[2026-12] |
| W | PREVISÃO DE RETORNO  CONFORME SOLICITAÇÃO MÉDICA | 0/100 | Data proposta; sem valores observados → return_observation.requested_due_date |
| X | DEMANDAS GERADAS NA ÚLTIMA CONSULTA MÉDICA | 0/100 | Indeterminado; texto provisório → care_demand.source_observation |
| Y | CLASSIFICAÇÃO DE RISCO ATUAL | 0/100 | Enum textual; lista explícita → risk_observation.current_source_value |
| Z | CLASSIFICAÇÃO DE RISCO INICIAL | 0/100 | Enum textual; lista explícita → risk_observation.initial_source_value |
| AA | RETORNO DO ATENDIMENTO MÉDICO PARA VERIFICAR CLASSIFICAÇÃO DE RISCO | 0/100 | Indeterminado → source_observation.return_for_risk_review |
| AB | DATA ENVIO DO PLANO | 0/100 | Data proposta; sem valores observados → care_plan_metadata.sent_at |
| AC | VERIFICAR ADESÃO DAS METAS | 0/100 | Indeterminado → source_observation.goal_adherence_check |
| AD | STATUS PLANO DE CUIDADO | 0/100 | Enum textual; lista explícita → care_plan_metadata.source_status |
| AE | DATA DA COORD CUIDADO | 0/100 | Data proposta; sem valores observados → coordination_observation.recorded_date |
| AF | DATA DA PRÓXIMA COORD CUIDADO2 | 0/100 | Data proposta; sem valores observados → coordination_observation.next_date |

O arquivo [dicionario-32-colunas.csv](dicionario-32-colunas.csv) detalha cada
transformação e a validação humana. O arquivo [mapeamento-etl.json](mapeamento-etl.json)
inclui os nomes literais, contagens, tipos observados e compatibilidade atual.

### Domínios explícitos do workbook

| Coluna | Domínio confirmado pela validação da planilha |
| --- | --- |
| C | ATIVO; INATIVO |
| L | SIM; NÃO |
| Y e Z | RISCO 1; RISCO 2; RISCO 3; RISCO 4 |
| AD | ADERIU PARCIALMENTE; AGUARDANDO RESPOSTA; ADERIU TOTALMENTE; NÃO ADERIU |

Não há valores assistenciais preenchidos que demonstrem a aplicação desses domínios.
Y é risco **atual** e Z é risco **inicial**; não inverter a ordem.
AC não deve ser tratado como sinônimo de AD.
P, AA e AC precisam de definição semântica antes de interpretação operacional.
O sufixo “2” de AF e o termo “CONSTADOS” de P devem permanecer na origem.

## 4. Compatibilidade com o importador existente

A verificação utilizou a função real mapHeaders de frontend/src/utils/import-aps.js.

**Reconhecidos automaticamente: 9/32 (28,13%)**:
A, B, C, D, F, G, I, J e K.

**Sem correspondência automática: 23/32 (71,88%)**:
E, H e todas as posições L:AF. Idade e entrada na APS estão preenchidas e seriam
perdidas pelo mapeamento genérico atual; as outras colunas também precisam manter
seus estados e sua proveniência, mesmo vazias.

| Bloqueio | Evidência / consequência |
| --- | --- |
| Limite de linhas aplicado ao XML | O parser atual adiciona linhas formatadas vazias ao total e interrompe após 1.001 linhas; este arquivo tem 1.392 linhas XML, embora só 100 cadastros |
| Sexo | O arquivo contém FEMININO/MASCULINO; o validador atual aceita M/F/OUTRO/NÃO INFORMADO. Os 100 cadastros falhariam nessa regra depois da extração |
| Risco | A validação do workbook usa RISCO 1 a RISCO 4; o backend espera 1 a 4. É preciso mapear o rótulo, sem gerar classificação |
| Nutrição/psicologia e campos próprios | Nem todos têm destino permitido no payload atual; informação não mapeada é descartada |
| Original e proveniência | Backend recebe linhas JSON, sem preservar o XLSX original nem a representação de cada célula |
| Estados de informação | null, célula ausente e string vazia são colapsados no fluxo atual |
| Idempotência | Há atualização por matrícula, mas a confirmação repetida cria outro relatório de carga e atualiza datas, mesmo sem conteúdo novo |
| CPF | Hoje só normaliza comprimento e duplicidade; faltam dígitos verificadores |
| Gerador de demonstração | O fluxo público anterior pode completar risco, retorno, plano e contatos. Não deve ser aplicado a este arquivo no novo ETL |

O arquivo não foi enviado à tela de importação nem ao banco nesta fase.
Os bloqueios foram verificados na estrutura e no código, sem simular sucesso
de importação.

## 5. Indicadores que esta exportação permite — e os que não permite

| Pergunta | Resposta sustentada pelo arquivo |
| --- | --- |
| Quantos cadastros e ativos? | 100 cadastros; 60 marcados ATIVO |
| Quantos inativos? | 40 marcados INATIVO |
| Quantas empresas e referências médicas? | 6 e 4 rótulos distintos; resolver identidade profissional exige confirmação |
| Qual o preenchimento estrutural? | 34,38%, incluindo caches de idade |
| Quantos têm CPF inválido? | 100, conforme algoritmo de DVs |
| Quantos retornos estão pendentes? | Não apurável: não há previsão preenchida nem execução vinculada |
| Quantos estão sem contato realizado? | Não apurável: campos vazios não comprovam ausência de contato |
| Quantos planos existem ou estão sem atualização? | Não apurável: não há conteúdo do plano, envio, status ou atualização preenchidos |
| Qual a adesão e a evolução de risco? | Não apurável: campos correspondentes estão vazios |
| Quantos acompanhamentos multiprofissionais aconteceram? | Não apurável: zero registros nos cadastros; a data isolada de nutrição não tem pessoa vinculada |

Para itens não apuráveis, o painel deve mostrar “informação não disponível nesta
exportação”, e não zero atendimentos, zero adesão ou cem pessoas em atraso.
O arquivo não contém descrição de metas individuais ou narrativa do plano:
essas informações não podem ser produzidas a partir do cabeçalho de status.

A empresa com nove cadastros precisa de supressão em uma visão empresarial que
use o mínimo de dez participantes definido para o projeto. Este relatório não
publica nomes de empresas ou participantes.

## 6. Proposta de modelo relacional e rastreabilidade

| Entidade proposta | Dados / vínculo |
| --- | --- |
| source_file | Hash, bytes originais protegidos, metadados, acesso e retenção |
| import_batch | Origem, escopo, versão do mapa/processamento, estado e resultado |
| source_sheet / source_row / source_cell | Aba, linha, coluna, cabeçalho literal, valor, tipo/estilo/fórmula/cache e estado |
| mapping_version | Correspondência homologada e transformação explícita |
| quality_issue | Regra, localização, severidade, tratamento e revisão humana |
| worker_identity | Identidade interna; CPF validado não é chave automática sem regra de vínculo |
| worker_company_link | Empresa, matrícula de origem, status e entrada na APS |
| professional_reference | Referência original e vínculo resolvido sem criar conta por nome |
| encounter_observation | Data de última consulta por categoria; só promover evento com pessoa/contexto confirmados |
| contact_observation | Histórico e referências mensais; distinguir observação, tentativa e contato confirmado |
| return_observation / risk_observation | Previsão e classificações explicitamente registradas, sem inferência clínica |
| care_plan_metadata / goal_adherence_observation | Envio/status e observação de adesão; não inventar texto de plano ou metas |
| coordination_observation | Coordenação registrada e previsão próxima, separadas de consulta |
| operational_activity | Tipo, evidência, responsável, estado, prazo aprovado e histórico de resolução |
| record_change | Versão anterior/nova, lote, processo/autor e referência de origem |

Reaproveitar tabelas de identidade e cuidado existentes quando a equivalência for
validada. Não duplicar todo o prontuário e não unir pessoas somente por nome.
A chave de matrícula por empresa/origem é proposta: sua abrangência precisa de
homologação. O valor numérico do XLSX deve ser preservado, com representação
textual interna, sem acrescentar zeros não demonstrados.

## 7. Mapeamento ETL proposto

Extract:
guardar o arquivo original protegido e seu hash; identificar a aba pelo workbook
e suas relações; reconhecer cabeçalho A1:AF1; separar linhas 2:101, M392 e linhas
puramente formatadas. Linha em branco não consome limite de cadastros; observação
sem identidade permanece rastreável, sem vínculo presumido.

Transform:
preservar cada valor original; aplicar nomes canônicos pela posição+cabeçalho
homologados; normalizar matrícula/CPF sem substituí-los; validar DVs e datas;
mapear FEMININO/MASCULINO e RISCO n explicitamente; conservar os cinco estados.
Os contatos mensais recebem o período do cabeçalho, não uma data de atendimento
inventada. Campos de significado pendente ficam como observações sem interpretação.

Load:
validar empresa/equipe/perfil; manter staging e inconsistências; usar transação;
resolver vínculo pela regra homologada; comparar a versão normalizada antes de
gravar alterações. Nunca limpar dado anterior só porque uma coluna não veio ou
uma nova exportação omitiu a pessoa.

Idempotência:
escopo + origem + hash do arquivo + versão do mapa + versão da transformação
identificam um processamento. Repetir a mesma entrada reaproveita o resultado,
sem nova versão do trabalhador ou evento. Conteúdo igual em um arquivo novo não
gera alteração clínica repetida. Tentativas técnicas, se auditadas, ficam separadas
do histórico de cargas processadas.

A política para CPF fictício inválido deve ser explícita: preservar a origem,
apontar inconsistência e não usá-lo como vínculo confiável. Não descartar os cem
cadastros nem “corrigir” os CPFs automaticamente.

## 8. Regras que dependem de validação humana

1. A quem pertence M392? Sem confirmação, manter a observação não vinculada.
2. P significa histórico livre, resumo de tentativas ou outra informação?
3. AA e AC esperam data, situação, instrução ou anotação?
4. O sufixo 2 de AF é apenas um nome técnico da planilha?
5. Quais campos são obrigatórios para cadastro, acompanhamento e promoção ao cadastro interno?
6. Qual é o escopo de matrícula e a regra para vínculos em empresas diferentes?
7. Como tratar os CPFs inválidos desta massa de teste sem modificar a origem?
8. Que protocolo aprovado define intervalo sem contato, retorno devido e plano sem atualização?
9. Quem pode acessar cada informação de nutrição, psicologia, enfermagem e medicina?
10. Quando um vazio pode limpar um valor anterior, se isso for autorizado?
11. Qual evento comprova atendimento realizado e como se vincula à previsão?
12. Qual é a referência temporal da idade e da comparação de risco?

Nenhuma dessas respostas foi presumida para gerar uma fila clínica.
As primeiras filas que este arquivo sustenta são de qualidade/revisão cadastral,
com motivos técnicos, não classificações de urgência assistencial.

## 9. Arquitetura técnica

A aplicação atual usa Vue 3/JavaScript, API PHP própria, PDO/MySQL e auditoria.
O pedido prefere Laravel, TypeScript e Python/Pandas. A fase 1 não inicia troca
de framework nem altera o sistema em uso.

Evolução proposta: extração/ETL no servidor, isolada dos serviços de regras
operacionais; staging protegido; persistência versionada; contratos REST de
qualidade e leitura; interface reaproveitada por adaptação gradual.
Python/Pandas pode compor o ETL conforme preferência do projeto; dependências,
ambiente e integração devem ser fixados na fase 2. Laravel/TypeScript exigem
plano incremental, sem presumir que já estejam implantados.

A camada de indicadores consulta registros aceitos e documenta denominadores.
Regras operacionais não transformam validação de formato em decisão clínica.
Controle de empresa/equipe, auditoria e segregação de categorias profissionais
devem acompanhar cada endpoint; uma referência médica textual não concede acesso.

## 10. Plano por fases e critérios de saída

| Fase | Entrega / critério |
| --- | --- |
| 1 — Diagnóstico | Concluído neste pacote: estrutura real, dicionário de 32 posições, qualidade, lacunas e propostas; decisões humanas permanecem explicitadas |
| 2 — ETL | Corrigir extração de linhas/abas, mapear as 32 colunas sem perdas, preservar original e estados, validar CPF/datas, versionar e comprovar idempotência |
| 3 — Cuidado | Vincular trabalhadores e observações; histórico, planos e fila operacional só com evidência e protocolos definidos; manter M392 em revisão até resolução |
| 4 — Indicadores | Publicar apenas métricas sustentadas; retornos/adesão/risco não apuráveis enquanto a origem não trouxer informação suficiente |
| 5 — Homologação | Validar permissões por empresa/equipe/categoria, auditoria, retenção, backup/recuperação e reconciliação com a origem |

Testes necessários na fase 2:
arquivo idêntico duas vezes; arquivo novo com conteúdo igual; alteração pontual;
coluna ausente versus célula vazia; CPF inválido sem correção inventada; linhas
formatadas; observação órfã; datas Excel 1900/1904; caches de fórmulas;
rótulos de sexo/risco; preservação de colunas desconhecidas; rollback e escopo.

## Artefatos e reprodução

- [Dicionário das 32 colunas](dicionario-32-colunas.csv).
- [Mapa ETL detalhado](mapeamento-etl.json).
- [Qualidade agregada](qualidade-agregada.json).
- Auditor somente leitura: scripts/diagnosticar-planilha-aps.py.

Comandos no diretório do projeto:

python scripts/diagnosticar-planilha-aps.py --self-test

python scripts/diagnosticar-planilha-aps.py "caminho-do-arquivo.xlsx" --reference-date 2026-11-15 --output relatorio-agregado.json

O auditor usa apenas a biblioteca padrão do Python, não calcula fórmulas e não
conecta ao banco. Sua saída publicada não contém as linhas pessoais do arquivo.
Este pacote não é uma implementação do novo ETL: é a primeira entrega diagnóstica
exigida antes dessa implementação.
