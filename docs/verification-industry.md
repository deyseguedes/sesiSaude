# Verificação da expansão industrial — 14/11/2026

## Resultados finais

| Verificação | Resultado |
| --- | --- |
| `composer validate --strict` | Passou |
| `composer test` | 9 verificações core/HTTP + 94 de domínio passaram |
| `php tests/mysql.php` | As mesmas 94 verificações de domínio passaram no MySQL |
| `php tests/http.php` | 185 verificações HTTP reais com MySQL passaram |
| `php tests/sql-bundles.php` | 9 verificações de instalação e atualização SQL passaram |
| Sintaxe PHP | 46 arquivos sem erro |
| `npm run build` | Passou; 60 módulos transformados |
| `npm ls --depth=0` | Dependências diretas resolvidas |

As 94 verificações incluem os 52 testes da base clínica anterior, preservados, e
42 verificações adicionais de gestão, elegibilidade, agendas, privacidade e campanhas.
O teste rápido provoca deliberadamente um controller inexistente: a mensagem
`API failure: Error` é esperada e verifica a resposta 500 sem exposição interna.

Ambiente: PHP 8.5.7, Composer 2.10.1, Node 24.18.0, npm 11.16.0, MySQL 8.4.3.
Dependências frontend mantidas: Vue 3.5.45, Vue Router 4.6.5, Pinia 3.0.4,
Bootstrap 5.3.9, Chart.js 4.6.1, Vite 7.3.8 e plugin Vue 6.0.11.

O MySQL de teste foi iniciado em `127.0.0.1:3307` com datadir descartável dentro do
projeto. Cada teste criou base própria com prefixo `viva_elo_test_` e removeu somente
essa base ao final. Nenhum banco do usuário foi alterado. Servidor e datadir
temporários foram encerrados/removidos após as verificações.

## Fluxos exercitados

- Empresas autorizadas ao administrador e rejeição de seleção de outra indústria
  pelo gestor; níveis independentes do perfil, sem concessão de prontuário ao gestor.
- Unidades, contratos, criação de profissionais, associação às equipes e controle
  de versão dos contratos. Médico não vira gestor por possuir perfil clínico.
- Convites nominais com hash, uso único, validação de e-mail do convite, cadastro
  pendente, ativação somente com verificações explícitas e bloqueio de menor no
  autoatendimento. Tentativas de cadastro continuam protegidas por CSRF.
- Dependente adulto com conta própria e proteção contra leitura pelo titular;
  menor acessível somente por representação explicitamente verificada.
- Busca por distância calculada no backend, horários disponíveis e cobertura
  contratual. Horário depois do fim do contrato não é oferecido ou reservado.
- Rejeição de publicação sobreposta, reserva com autorização explícita, conflito
  de reserva repetida, modalidade presencial e teleconsulta, e vínculo da equipe
  gerado pelo agendamento.
- Revogação assistencial pela gestão remove acesso clínico imediatamente; nova
  ativação exige verificação de autorização. Gestor empresarial não usa esse recurso.
- Link da teleconsulta criptografado, restrito ao responsável/paciente/representante,
  recusado ao gestor e fora da janela; liberado ao paciente dentro da janela.
- Biblioteca APS publicada somente após revisão clínica. Conclusão pessoal não
  compartilhada com dependente adulto e vinculada à versão revisada do conteúdo.
- Campanhas por unidade/público, publicação, convites internos, indicadores de
  grupos pequenos suprimidos, conclusão de ciclo condicionada aos conteúdos.
- Fila nominal somente para equipe vinculada; tarefas administrativas com autoria,
  notas criptografadas, responsável, conclusão e respeito à recusa de participação.
- Pedido de apoio do trabalhador chega ao ELO Reconecta/APS sem encaminhar conteúdo
  clínico ao navegador ou gestor.
- Instalação SQL consolidada com seis migrações registradas e atualização de V3
  mantendo registro preexistente; o migrador executado novamente não duplica tabelas.
- Regressão dos planos, metas, indicadores, revisões, fontes de medição, perfis e
  regras de continuidade já entregues.

## Limites da validação

Não houve inspeção visual nem automação de navegador disponível nesta sessão.
Build e testes HTTP não comprovam aparência, acessibilidade completa ou usabilidade
em dispositivos. Geolocalização do navegador, reprodução de vídeo e chamada em
provedor externo precisam de validação prática com a configuração da instituição.

Não foram integrados envio de e-mail/SMS/WhatsApp, geocodificação por CEP, streaming,
upload de gravações ou um provedor de videoconferência. A entrega fornece os fluxos
e controles para cadastro, agendas, links restritos e conteúdos já hospedados.
A carga contém roteiros em texto, não gravações inventadas. A entrega é local,
sem deploy externo, teste de carga, pentest ou homologação clínica/regulatória.

Para utilizar os novos módulos, aplicar `composer migrate` sobre a base configurada.
Para dados fictícios de instalação nova: `composer seed`; para complementar uma
antiga base fictícia Aurora: `composer seed:industry`, com senha e chave configuradas.
O banco temporário de teste não é a configuração de execução da aplicação.
