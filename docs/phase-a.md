# Entrega da Fase A — 14/11/2026

## Inspeção e plano executado

O diretório disponibilizado continha apenas duas pastas vazias, `backend/` e
`frontend/`. Não havia arquivos AGENTS.md, fontes legadas, testes ou esquema SQL.
O plano foi apresentado antes da criação de arquivos: estruturar API PHP própria,
frontend Vue separado, configuração local e documentação, sem avançar às fases B–F.
Nenhum arquivo preexistente ou banco de dados foi alterado.

Foram criados bootstrap Composer/PSR-4, front controller, roteador, respostas JSON,
middleware de cabeçalhos, controller/service de saúde e fábrica PDO. Repositories e
migrações ficaram reservados e documentados, pois não há domínio persistente nesta fase.
No frontend, foram criados Vue 3, Vite, Bootstrap 5, Vue Router, store Pinia e cliente
HTTP. Os lockfiles fixam as versões resolvidas nas instalações.

## Verificações executadas

Ambiente: PHP 8.5.7, Composer 2.10.1, Node 24.18.0, npm 11.16.0.

| Verificação | Resultado |
| --- | --- |
| Instalação Composer e npm | Concluída; lockfiles gerados |
| `composer validate --strict` | Passou sem avisos |
| `composer test` | 8 verificações passaram |
| `php -l` em todos os fontes PHP | Sem erros de sintaxe |
| `npm run build` | Passou; 38 módulos transformados |
| GET real `/api/v1/health` | 200, contrato JSON correto |
| Cabeçalho HTTP `X-Content-Type-Options` | `nosniff` |
| GET via proxy Vite `/api/v1/health` | 200, backend alcançado |
| Entrada HTTP do frontend | 200, referência ao módulo Vue |
| Acesso HTTP a `.env`, `composer.json` e fonte PHP privado | 404 nos três casos |

Os testes do backend verificam saúde, quatro rotas inexistentes (incluindo caminhos
privados), método inválido, erro interno sanitizado e configuração obrigatória do
banco. A linha `API failure: Error` durante `composer test` é esperada: o teste
provoca uma exceção para validar a resposta 500 sem exposição de detalhes.

## Limitações e aprovação

O build e os testes HTTP não substituem inspeção visual em navegador. Não foi
realizado teste com MySQL, pois não foi disponibilizado esquema nem credenciais
e o health check é deliberadamente independente do banco. Compatibilidade com
PHP 8.2 é declarada, mas a execução ocorreu apenas no PHP 8.5.7 disponível.
Apache e configuração de produção não foram testados.

Não havia testes anteriores disponíveis; foram adicionadas verificações da fundação
HTTP. Testes dos fluxos clínicos permanecem no escopo das próximas fases.
Não há controle de acesso clínico implementado e nenhuma rota clínica foi exposta.

A Fase A está entregue para revisão. A Fase B depende da aprovação do usuário;
nenhuma fase posterior foi iniciada.
