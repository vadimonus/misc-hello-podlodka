# Перевозим PHP-приложение в Kubernetes

Лучше смотреть все действия по шагам по [истории commit](https://github.com/vadimonus/misc-hello-podlodka/commits/master/).

## Упаковка приложения в docker-контейнер

Установка зависимостей
```
docker run --rm --interactive --tty \
  --volume $PWD:/app \
  --user $(id -u):$(id -g) \
  composer install
```

```bash
docker build --file=docker/nginx/Dockerfile -t hello-podlodka-nginx:0.0.1 .
docker build --file=docker/php-fpm/Dockerfile -t hello-podlodka-php-fpm:0.0.1 .
docker compose up -d
```
Сайт должен открыться по адресу http://localhost:80

Выключаем приложение
```bash
docker compose down
```
