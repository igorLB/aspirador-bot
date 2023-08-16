

# Create or Edit An Entity
php bin/console make:entity

# Generate Migration
php bin/console make:migration

# Flush Migration
php bin/console doctrine:migrations:migrate



# If you prefer to add new properties manually, the make:entity command can generate the getter & setter methods for you:
php bin/console make:entity --regenerate

# ùtil
php bin/console dbal:run-sql 'SELECT * FROM product'