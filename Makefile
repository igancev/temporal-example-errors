install:
	docker-compose build
	docker-compose run app composer install
	docker-compose run app composer load:binaries
	docker-compose up -d

clear:
	docker-compose stop
	docker-compose down
	docker volume rm temporal-load-test_mysql_data

start:
	docker-compose up -d

restart:
	make clear
	make start

load:
	docker-compose exec app php app.php failed-example 2000

again:
	make restart
	sleep 10
	make load
