# Verificação V3 — 14/11/2026

Fases B–F implementadas sequencialmente após aprovação global. Código local;
nenhum deploy e nenhuma alteração em banco do usuário.

## Ambiente

PHP 8.5.7, Composer 2.10.1, Node 24.18.0, npm 11.16.0.
MySQL 8.4.3 iniciado exclusivamente para os testes em `127.0.0.1:3307`, com
datadir temporário dentro de `backend/storage/mysql-test`. Os testes criaram
bases únicas com prefixo `viva_elo_test_`, removidas ao final. O servidor
temporário foi encerrado após as verificações.

## Resultados

| Verificação | Resultado |
| --- | --- |
| `composer validate --strict` | Passou |
| `composer test` | 9 verificações HTTP/core + 52 de domínio em SQLite passaram |
| `php tests/mysql.php` | As mesmas 52 verificações de domínio passaram no MySQL 8.4 |
| `php tests/http.php` | 81 verificações HTTP com MySQL passaram |
| Migrações reais 001–004 | Aplicadas sem erro; segunda execução não reaplicou |
| Carga fictícia e sincronizador | Executados com sucesso; sincronização sem duplicidade |
| `php -l` nos 33 arquivos PHP | Sem erros de sintaxe |
| `npm run build` | Passou; 52 módulos transformados |
| `npm ls --depth=0` | Dependências diretas resolvidas, sem dependências ausentes |
| Atualização do lock Composer | Sem alteração de versões; sem avisos de vulnerabilidades nessa consulta |

O frontend foi compilado com Vue 3.5.45, Vue Router 4.6.5, Pinia 3.0.4,
Bootstrap 5.3.9, Chart.js 4.6.1, Vite 7.3.8 e plugin Vue 6.0.11.

## Cobertura exercitada

- Contrato JSON, 200/201/401/403/404/405/409/422/429/500 e caminhos privados.
- Sessão real, CSRF no login e mutações, logout e limitação de tentativas.
- Médico, enfermeiro, navegador, trabalhador, administrador e gestor.
- Escopo de paciente/equipe, empresa diferente, paciente sem vínculo e revogação
  imediata de vínculo. Gestor e navegador sem acesso a dados clínicos.
- Criação/alteração/revisão de plano, criptografia de notas, autoria e eventos,
  conflito de versões e proibição de mudar paciente do plano.
- Indicadores, origem profissional/autorrelatada, mínimo de amostras, ausência
  de medição, ajuste de alvo sem reaproveitar evidência, categorias, direção de
  aumento/redução/manutenção/faixa e rejeição de coleta futura/duplicada.
- Evolução de enfermagem, tipos de registro por perfil, ações pactuadas,
  encaminhamento clínico e programação de retorno com caso administrativo.
- Pendência idempotente, barreira voluntária, resolução, reagendamento sem
  perder histórico, aviso APS, conflito concorrente e confirmação.
- Métricas assistenciais e administrativas, denominador sem consulta original
  reagendada, taxa atualizada pela confirmação e supressão de pequenos grupos.

A mensagem `API failure: Error` no teste rápido é esperada: um controller
inexistente é provocado para verificar a resposta 500 sanitizada.

## Limites

Não havia testes anteriores nem código legado disponibilizado. Não houve
inspeção visual ou automação de navegador, pois esta sessão não oferece ferramenta
de navegador apropriada. Build não comprova aparência ou usabilidade em dispositivos.
Apache, proxy reverso e HTTPS de produção não foram executados. PHP 8.2 é a versão
mínima declarada, mas os testes usaram o PHP 8.5 disponível. Não foram realizados
testes de carga, pentest, homologação assistencial ou avaliação regulatória.

O MySQL temporário é descartável e não é a configuração de uso da aplicação.
Para executar a demonstração, provisionar MySQL e `backend/.env` conforme o README.
O protótipo exige dados fictícios; os controles entregues não certificam aptidão
para uso clínico real.
