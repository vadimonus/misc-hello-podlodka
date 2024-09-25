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

## Ingress

Включаем контроллер Ingress в кластере
```bash
minikube addons enable ingress
```

Применяем конфиг нашего приложения
```bash
kubectl apply -f k8s/hello-podlodka.yaml
```

```
kubectl get ingress
```

Должно быть примерно так
```
NAME             CLASS   HOSTS                ADDRESS        PORTS   AGE
hello-podlodka   nginx   hello-podlodka.lcl   192.168.49.2   80      20s
```

Прописываем в hosts (`sudo editor /etc/hosts`) полученный IP адрес 
```
192.168.49.2   hello-podlodka.lcl
```

Сайт должен открыться по адресу http://hello-podlodka.lcl:80

Можно в отдельном терминале выполнить  
```
minikube dashboard
```

В dashboard можно увидеть все созданные объекты: Pod, Replicaset, Deployment, Service, Ingress. При закрытии терминала порт закроется, и dashboard станет недоступна.

## Локальные файлы и сессии 

Соберем новую версию приложения и загрузим образ в кластер
```bash
docker build --file=docker/php-fpm/Dockerfile -t hello-podlodka-php-fpm:0.0.2 .
minikube image load hello-podlodka-php-fpm:0.0.2
kubectl apply -f k8s/hello-podlodka.yaml
```

Много раз обновляя страницу http://hello-podlodka.lcl:80 можно увидеть, что счетчик работает неправильно, потому что сессии хранятся не в общем хранилище, а на каждом узле отдельно.

Аналогичный эффект можно наблюдать и в docker compose при использовании scale на странице http://localhost:80. 
```
docker compose up -d --scale php-fpm=3
```

Заменяем в `k8s/hello-podlodka.yaml` `SESSION_DRIVER=file` на `SESSION_DRIVER=cookie`. Тогда сессия будет храниться в cookie у клиента.
```bash
kubectl apply -f k8s/hello-podlodka.yaml
```

Открываем http://hello-podlodka.lcl:80. Перестав хранить сессии, приложение стало stateless. Счетчик теперь должен работать правильно, вне зависимости от того, на какой именно pod пришел запрос из браузера.  

Удаляем приложение из кластера и выключаем docker compose
```bash
kubectl delete -f k8s/hello-podlodka.yaml
docker compose down
```

## Создадим Helm chart

```bash
helm create k8s/hello-podlodka
```

Helm использует шаблоны из папки `templates` и подставляет в них переменные из `values.yaml` и `Chart.yaml`. Значения из `values.yaml` доступны в шаблоне в переменной `.Values`, из `Chart.yaml` в переменной `.Chart`.

Посмотреть результат обработки шаблонов можно командой `helm template`
```bash
helm template k8s/hello-podlodka > k8s/hello-podlodka-helm.yaml
```

В полученном файле `k8s/hello-podlodka-helm.yaml` можно увидеть, среди прочего, Deployment и Service.

## Опишем Pod

Helm создал chart для одного контейнера. Но для php нужны отдельные контейнеры php-fpm и nginx. Отредактируем в `templates/deployment.yaml` секцию `spec.template.spec.containers`, скопировав строки 34-52.

Сделаем следующие замены

|      Значение       |           nginx           |           php-fpm           |
|:-------------------:|:-------------------------:|:---------------------------:|
| `{{ .Chart.Name }}` | `{{ .Chart.Name }}-nginx` | `{{ .Chart.Name }}-php-fpm` |
|   `.Values.image`   |   `.Values.imageNginx`    |     `.Values.imagePhp`      |
| `.Values.resources` | `.Values.resourcesNginx`  |   `.Values.resourcesPhp`    |
|        ports        |         оставить          |           убрать            |
|    livenessProbe    |         оставить          |           убрать            |
|   readinessProbe    |         оставить          |           убрать            |

Отредактируем `values.yaml`. Вместо разделов image и resources, сделаем imageNginx, imagePhp, resourcesNginx, resourcesPhp.

|    Значение     |         nginx          |         php-fpm          |
|:---------------:|:----------------------:|:------------------------:|
|      image      | `hello-podlodka-nginx` | `hello-podlodka-php-fpm` |
| imagePullPolicy |        `Never`         |         `Never`          |
|       tag       |        `0.0.1`         |         `0.0.2`          |

Проверим результат, сравнив результат шаблонизации с конфигом kubernetes, сделанным вручную.
```
helm template k8s/hello-podlodka > k8s/hello-podlodka-helm.yaml
```

Добавим в `templates/deployment.yaml` секцию `spec.template.spec.containers.{{ .Chart.Name }}-nginx`
```yaml
    env:
    - name: PHP_FPM_HOST
      value: 127.0.0.1
```

Заменим в `values.yaml` `ingress.enabled` на `true` и пропишем в `host` значение `hello-podlodka.lcl`. В `replicaCount` пропишем `3`. Пропишем в `livenessProbe` и `readinessProbe` значение `/up` (healthcheck Laravel). 

Проверим, что шаблонизация проходит без ошибок, и установим полученный chart.
```
helm template k8s/hello-podlodka > k8s/hello-podlodka-helm.yaml
helm install hello-podlodka k8s/hello-podlodka
```

Должен быть примерно так
```
NAME: hello-podlodka
LAST DEPLOYED: Tue Sep 24 12:51:33 2024
NAMESPACE: default
STATUS: deployed
REVISION: 1
NOTES:
1. Get the application URL by running these commands:
  http://hello-podlodka.lcl/
```

Проверим приложение, открыв http://hello-podlodka.lcl:80. 

Если что-то пошло не так, можно удалить приложение, чтобы попробовать заново.
```bash
helm delete hello-podlodka
```

## Добавим Redis

Допишем в `Chart.yaml`

```yaml
dependencies:
- repository: https://charts.bitnami.com/bitnami
  name: redis
  version: 20.1.4
```

Загрузим зависимости 
```bash
helm dependency update k8s/hello-podlodka
```

Пропишем deployment.yaml в контейнер `php-fpm`.
```yaml
env:
  - name: SESSION_DRIVER
    value: redis
  - name: REDIS_CLIENT
    value: predis
  - name: REDIS_HOST
    value: "{{ .Chart.Name }}-redis-master"
```

Добавим в `values.yaml` конфигурацию для redis. Полный список возможных параметров можно посмотреть в документации конкретного chart https://artifacthub.io/packages/helm/bitnami/redis#parameters.
```yaml
redis:
  architecture: standalone
  auth:
    enabled: false
```

Проверим, что шаблонизация проходит без ошибок, и установим полученный chart.
```
helm template k8s/hello-podlodka > k8s/hello-podlodka-helm.yaml
helm upgrade hello-podlodka k8s/hello-podlodka
```

## Автомаcштабирование

По умолчанию в Kubernetes при масштабировании настроено ожидание в 1-2 минуты, а также сбор статистики потребления ресурсов раз в минуту. Чтобы локально было удобнее наблюдать, установим меньшие значения.
```bash
minikube start \
  --extra-config=controller-manager.horizontal-pod-autoscaler-initial-readiness-delay=2s \
  --extra-config=controller-manager.horizontal-pod-autoscaler-cpu-initialization-period=2s \
  --extra-config=controller-manager.horizontal-pod-autoscaler-upscale-delay=2s \
  --extra-config=controller-manager.horizontal-pod-autoscaler-downscale-delay=2s \
  --extra-config=controller-manager.horizontal-pod-autoscaler-sync-period=2s\
  --extra-config=controller-manager.horizontal-pod-autoscaler-downscale-stabilization=2s 

minikube addons enable ingress
minikube addons enable metrics-server
```

Команда `kubectl get apiservices` должна содержать строчку 
```
v1beta1.metrics.k8s.io                 kube-system/metrics-server   True        1m
```

Отредактируем настройки metrics-server
```bash
kubectl -n kube-system edit deployments.apps metrics-server
```
Заменим
```
--metric-resolution=60s
```
на (15 секунд - минимально возможное значение) 
```
--metric-resolution=15s
```

Проверим, что метрики собираются (может потребоваться немного подождать).
```bash
kubectl top pod
```

Должно быть примерно так. `m` в CPU означает одну тысячную. 
```
NAME                             CPU(cores)   MEMORY(bytes)   
hello-podlodka-6bddd4664-kkl4q   17m          44Mi            
hello-podlodka-6bddd4664-nj8vs   30m          60Mi            
hello-podlodka-6bddd4664-vlszw   18m          60Mi            
hello-podlodka-redis-master-0    18m          4Mi  
```

Установим `values.yaml` ограничения для ресурсов (должны превышать потребление без нагрузки), и включим автомасштабирование.
```yaml
resourcesNginx:
  requests:
    cpu: 10m
resourcesPhp:
  requests:
    cpu: 100m

autoscaling:
  enabled: true
  minReplicas: 2
  maxReplicas: 5
  targetCPUUtilizationPercentage: 80
```

Проверим, что шаблонизация проходит без ошибок, и установим полученный chart.
```
helm template k8s/hello-podlodka > k8s/hello-podlodka-helm.yaml
helm upgrade hello-podlodka k8s/hello-podlodka
```

В Kubernetes должен появиться новый объект HorizontalPodAutoscaler
```bash
kubectl get hpa
```

Должно быть примерно так
```
NAME             REFERENCE                   TARGETS        MINPODS   MAXPODS   REPLICAS   AGE
hello-podlodka   Deployment/hello-podlodka   cpu: 11%/80%   2         5         2          1m
```

Если в cpu значение `unknown`, нужно подождать, пока сервис метрик соберет метрики, либо где-то в предыдущих шагах произошла ошибка.

Наблюдаем за списком pod
```
watch -n0.5 kubectl top pods
```

В другом терминале наблюдаем за HorizontalPodAutoscaler
```
watch -n0.5 kubectl get hpa
```

И в отдельном терминале делаем, чтобы создать нагрузку в течение 60 с.
```
wrk -t4 -c30 -d60s http://hello-podlodka.lcl/
```

Через некоторое время сервер мониторинга соберет обновленную статистику, и HorizontalPodAutoscaler увеличит количество pod. Через некоторое время после прекращения нагрузки количество pod снова уменьшится.
