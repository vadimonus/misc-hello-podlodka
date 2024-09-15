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

## Pod, Replicaset, Deployment

Запускаем minikube
```bash
minikube start
```

Отправляем собранные образы в кластер kubernetes
```bash
minikube image load hello-podlodka-nginx:0.0.1
minikube image load hello-podlodka-php-fpm:0.0.1
```

Применяем конфиг нашего приложения
```bash
kubectl apply -f k8s/hello-podlodka.yaml
```

Проверяем, что создался deployment
```bash
kubectl get deployments
```

Должно быть примерно так
```
NAME                 READY   UP-TO-DATE   AVAILABLE   AGE
hello-podlodka-php   3/3     3            3           20s
```

Проверяем, что создался replicaset
```bash
kubectl get replicasets
```

Должно быть примерно так
```
NAME                            DESIRED   CURRENT   READY   AGE
hello-podlodka-php-5669d95797   3         3         3       30s
```

Проверяем, что создались pod
```bash
kubectl get pods
```

Должно быть примерно так
```
NAME                                  READY   STATUS    RESTARTS       AGE
hello-podlodka-php-5669d95797-bvpfm   2/2     Running   0              40s
hello-podlodka-php-5669d95797-nq9vw   2/2     Running   0              40s
hello-podlodka-php-5669d95797-wwmsd   2/2     Running   0              40s
```

Наблюдаем за списком pod 
```
watch -n0.5 kubectl get pods
```

И в отдельном терминале делаем (имя пода взять из вывода предыдущей команды) 
```
kubectl delete pod hello-podlodka-php-5669d95797-bvpfm
```

Кратковременно можно увидеть такой момент 
```
NAME                                  READY   STATUS              RESTARTS        AGE
hello-podlodka-php-5669d95797-bvpfm   2/2     Terminating         0               1m
hello-podlodka-php-5669d95797-wtv2r   0/2     ContainerCreating   0               1s
hello-podlodka-php-5669d95797-nq9vw   2/2     Running             0               1m
hello-podlodka-php-5669d95797-wwmsd   2/2     Running             0               1m
```

Можно поменять в файле `k8s/hello-podlodka.yaml` параметр `replicas`, и выполнить
```bash
kubectl apply -f k8s/hello-podlodka.yaml
```

Число pod должно обновиться в соответствии с настройкой.

Удаляем всё, что создали
```bash
kubectl delete -f k8s/hello-podlodka.yaml
```

Команды должны возвращать пустой результат 
```bash
kubectl get deployments
kubectl get replicasets
kubectl get pods
```

## Service

Применяем конфиг нашего приложения
```bash
kubectl apply -f k8s/hello-podlodka.yaml
```

```
kubectl get services
```

Должно быть примерно так
```
NAME                            TYPE        CLUSTER-IP       EXTERNAL-IP   PORT(S)    AGE
hello-podlodka-php-service      ClusterIP   10.104.37.12     <none>        80/TCP     10s
```
