# Repositories

`Store` recebe PDO por injeção e executa statements parametrizados e transações.
Services constroem consultas com escopo de empresa e vínculo assistencial autorizado.
Identificadores de tabelas/colunas em `insert` são constantes internas, nunca entradas
da API. Não chamar a persistência a partir do frontend ou ignorar o `Access` nos services.
