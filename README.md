# AspiradorBot 1.0.0

### Descrição
Projetinho feito com Symfony 6 na sua versão de microsserviço.
Completamente Dockerizado usando boas práticas na criação do Dockerfile

### Skills atuais

- Buscar promoções da dolce gusto
- Buscar vagas de TI no APInfo
- Buscar a cotação USD/BRL
- Buscar as 8 notícias mais lidas do TecMundo

### Command Daily Report
Pega a cotação do dolar do dia corrente e as notícias mais lidas do TecMundo de uma API gratuita e envia como mensagem para o Telegram.

Ex.:
`docker run cedroigor/aspirador-bot bin/console app:daily-report`

### Dependências 
- phpwebdriver
- postgres database



<details>
  <summary>

### Usefull commands for development

  </summary>

##### Create or Edit An Entity
`php bin/console make:entity`

##### Generate Migration
`php bin/console make:migration`

##### Flush Migration
`php bin/console doctrine:migrations:migrate`

##### If you prefer to add new properties manually, the make:entity command can generate the getter & setter methods for you:
`php bin/console make:entity --regenerate`

##### Run raw sql on terminal
`php bin/console dbal:run-sql 'SELECT * FROM product'`


#### Create VAULT Key
`php bin/console secrets:set DATABASE_PASSWORD`
`APP_RUNTIME_ENV=prod php bin/console secrets:set DATABASE_PASSWORD`


#### Show info logging
`SHELL_VERBOSITY=2 bin/console app:daily-report`

</details>


