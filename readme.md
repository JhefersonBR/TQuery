# TQuery
## Sobre o Componente

A classe TQuery builder foi desenvolvida para trazer para o Adianti framework uma abordagem diferente na montagem e execução das querys.

Com ela é possível executar arquivos **'.sql'** sendo possível separar as instruções SQL do código PHP.

Com isso temos uma melhor legibilidade do código SQL.
## Instalação

Como se trata de um pacote composer, basta usar o comando:
```shell
 composer require 'jheferson-br/txtfile'
```

## Exemplo de Uso

Para entendermos melhor como a classe funciona, este exemplo simples.

Suponhamos que precisamos executar a seguinte instrução no banco de dados para limpar as tabelas do sistema:
```sql
TRUNCATE TABLE cliente;
TRUNCATE TABLE produto;
TRUNCATE TABLE fornecedor;
```
Que está salvo na pasta **app/querys/** do projeto, no arquivo **LimpaTabelas.sql**.

Usando a classe TQuery, ficaria assim:
```php
TTransaction::open('ERP');

// instanciando o objeto TQuery.
$sql = new TQuery("LimpaTabelas.sql");
//Definindo o local onde os arquivos '.sql' serão encontrados.
$sql->setBasePathQuerys("app/querys/");
//Definindo um separador de querys para a execução de varios comandos dentro do mesmo arquivo .sql
$sql->setMultiQuerySeparator(";");
//Executando as querys.
$afected_rows = $sql->execute();
//Imprimindo na tela a quantidade de linhas que sofreram alterações com a execução da query.
print_r($afected_rows);
```

Note que a classe TQuery precisa de uma transação aberta com o **TTransaction** para ser executada, mantendo o padrão do Adianti Framework.

Além disso todos os comandos executados com o metodo **execute()** serão logadas no sistema de logs do Adianti.

A TQuery também é capaz de excutar querys de consultas, como joins complexos. Muitas vezes utilizar uma View como é recomentando para o framework se torna algo oneroso de dar manutenção.

Pensando nisso foi desenvolvido o metodo **load()** que pode receber um **TCriteria** nativo do framework como parâmetro, mantendo a compatibilidade com o framework.

Imagine a necessidade de trazer um join complexo como este:
```sql
    SELECT 
    `mes`.`men_mesano` AS `men_mesano`,
    `mes`.`men_insc_muni` AS `men_insc_muni`,
    `mes`.`men_conta` AS `men_conta`,
    `pla`.`pla_bacen`,
    `mes`.`cod_trib_desif` AS `cod_trib_desif`,
    SUM(if(COALESCE(`mes`.`men_mes_santer`,0) < 0, 0, COALESCE(`mes`.`men_mes_santer`,0))) AS `sald_inic`,
    SUM(COALESCE(`mes`.`men_cred_mes`,0)) AS `men_cred_mes`,
    SUM(COALESCE(`mes`.`men_deb_mes`,0)) AS `men_deb_mes`,
    SUM(COALESCE(`mes`.`men_tributavel`,0)) AS `men_tributavel`,
    SUM(COALESCE(`mes`.`men_deducao`,0)) AS `men_deducao`,
    SUM(COALESCE(`mes`.`men_tributavel`,0)) - SUM(`mes`.`men_deducao`) AS `base_calc`,
    (
      SUM(COALESCE(`mes`.`men_tributavel`,0)) - SUM(COALESCE(`mes`.`men_deducao`,0))
    ) * COALESCE(`mes`.`men_aliquota`,0) / 100 AS `valr_issqn_retd`,
    `mes`.`men_desc_dedu` AS `men_desc_dedu`,
    `mes`.`men_aliquota` AS `men_aliquota`,
    `mes`.`men_incentivo` AS `men_incentivo`,
    `mes`.`men_desc_incen` AS `men_desc_incen`,
    `mes`.`men_motivo_nao_exig` AS `men_motivo_nao_exig`,
    `mes`.`men_processo_nao_exig` AS `men_processo_nao_exig`,
    `mes`.`men_mes_compensacao` AS `men_mes_compensacao`,
    `mes`.`men_vlr_compensacao` AS `men_vlr_compensacao`,
    `pac`.`cnpj` AS `cnpj` 
  FROM
    (
      des_plano AS pla 
      LEFT JOIN `des_mensal` AS `mes` 
        ON (pla.pla_conta = mes.men_conta) 
        
      LEFT JOIN `des_pacs` AS `pac` 
        ON (
          `mes`.`men_insc_muni` = `pac`.`insc_muni`
        )
    ) 
      {{WHERE}} 

    GROUP BY `mes`.`men_mesano`,
  `pac`.`insc_muni`,
    `mes`.`men_conta`,
    `mes`.`men_aliquota`
```

Note que nas condições da query existe um minemonico denominado **{{WHERE}} **.
Este minemonico é padrão no **TQuery** e seu objetivo é definir o local onde será incluido as condições **WHERE** da consulta.

Veja como ficaria o codigo em php:

```php
TTransaction::open('ERP');

//Definindo os critérios de busca
$criteria = new TCriteria();
$criteria->add(new TFilter('men_mesano', 'LIKE', "%" . date('Y')));

// instanciando o objeto TQuery.
$sql = new TQuery("QryRelatorio.sql");
//Definindo o local onde os arquivos '.sql' serão encontrados.
$sql->setBasePathQuerys("app/querys/");
//Executando a query e carregando os objetos
$rows = $sql->load($criteria);
//Imprimindo na tela os resultados da consulta.
print_r($rows);
```
Note que a **TQuery** se comporta de forma semelhante a **TRepository**

Tambem é possível passar parâmetros para as querys usando mineomonicos customizados.
Isso pode ser útil quando querendo enviar um trecho de código SQL para o TQueyr incluir na query.

Veja:
```sql
SELECT
    '12/{{mes_ano}}' AS men_mesano,
    '{{men_insc_muni}}' AS men_insc_muni,
    pla_conta AS men_conta,
    pla.pla_bacen AS pla_bacen,
    pla.cod_trib_desif AS cod_trib_desif,
    0 AS sald_inic,
    0 AS men_cred_mes,
    0 AS men_deb_mes,
    0 AS men_tributavel,
    0 AS men_deducao,
    0 AS base_calc,
    0 AS valr_issqn_retd,
    0 AS men_desc_dedu,
    aliq.`alq_taxa` AS men_aliquota,
    0 AS men_incentivo,
    0 AS men_desc_incen,
    0 AS men_motivo_nao_exig,
    0 AS men_processo_nao_exig,
    0 AS men_mes_compensacao,
    0 AS men_vlr_compensacao,
    '{{cnpj}}' AS cnpj
  FROM
    des_plano AS pla
    LEFT JOIN des_aliquota AS aliq ON aliq.`cid_cod` = (SELECT cid_cod FROM des_coop WHERE coo_cnpj = '{{cnpj}}') AND aliq.`cod_desif` = pla.`cod_trib_desif`
  WHERE pla_conta IN
    (SELECT
      men_conta
    FROM
      des_mensal as mct
    WHERE (
      CAST(CONCAT(SUBSTR(mct.men_mesano, 4,4),SUBSTR(mct.men_mesano, 1,2))AS UNSIGNED INTEGER) 
      between 
      CAST(CONCAT(SUBSTR('01/{{mes_ano}}', 4,4),SUBSTR('01/{{mes_ano}}', 1,2))AS UNSIGNED INTEGER)
      and CAST(CONCAT(SUBSTR('12/{{mes_ano}}', 4,4),SUBSTR('12/{{mes_ano}}', 1,2))AS UNSIGNED INTEGER)
      )  and mct.poa_cod = '{{poa_cod}}')
    AND pla_conta LIKE '7%'
    and pla.pla_grau = 6
    and pla.pla_arquivo = 1

    GROUP BY `men_mesano`,
    men_insc_muni,
    `men_conta`,
    `men_aliquota` 
```

Note que a query em questão, possui varios mineomonicos customizados como **{{poa_cod}}** e **{{cnpj}}**
Para enviar para a TQuery os dados para seu mineomonico customizado, basta enviar como segundo parametro no construtor do objeto, um array associativo com as chaves mantendo o mesmo nome do mineomonico.

Veja um exemplo para a execução da query anterior:

 ```php
TTransaction::open('ERP');

//Definindo os critérios de busca
$criteria = new TCriteria();
$criteria->add(new TFilter('men_mesano', 'LIKE', "%" . date('Y')));

//Defininto vetor com mineomonicos customizados
$params = [
	"mes_ano"=> $mes_ano,
	"cnpj"=> $PA->cnpj,
	"poa_cod"=> $PA->poa_cod,
	"men_insc_muni"=> $PA->insc_muni,
];

// instanciando o objeto TQuery informando os parâmtros q serão utilizados.
$sql = new TQuery("QryRelatorio.sql", $params);
//Definindo o local onde os arquivos '.sql' serão encontrados.
$sql->setBasePathQuerys("app/querys/");
//Executando a query e carregando os objetos
$rows = $sql->load($criteria);
//Imprimindo na tela os resultados da consulta.
print_r($rows);
```
Tambem é possível setar os valores dos mineomonicos atraves do método **setParams** quer recebe como parâmetro o vetor com os valores.
Veja como ficaria:
```php
$sql->setParams($params);
```

# Atenção
#### Nunca use TQuery em telas com inputs de usuários, para a listagem de dados, pois a mesma não possui proteção contra SQL Injection, afinal existe a possibilidade de enviar trechos de códigos para dentro da instrução SQL.
#### A proteção contra SQL Injection só funciona no método load que recebe um TCretéria que por sua vez, ja tem essa proteção.
#### Então tome cuidado ao implementar a TQuery, para não abrir uma falha de segurança no seu sistema.
#### A TQuery é mais indicada para rotinas internas, sem a interferencia dos usuários.
