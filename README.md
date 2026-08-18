# Training Payment System
Учебная демонстрационная платёжная система.

[Техническое задание](https://docs.google.com/document/d/11eqsxuavwpzYEZJjuhYl0OcH8CObd8zxePcz1771Gzg/edit?usp=sharing)

## Инструкции по установке
1. Склонировать репозиторий
2. Перейти в папку docker внутри проекта
3. Скопировать .env.example в просто .env, при необходимости поменять данные
4. Запустить в папке docker команду ```docker compose up -d```
5. При наличии файлов в папке application запустить composer ```docker compose exec -u www-data php composer install```
6. Запустить команду docker compose exec -u www-data -it php bash для работы внутри контейнера Docker - там будем устанавливать Laravel
7. Внутри контейнера запускаем composer global require laravel/installer (см. доки Laravel) - установщика Laravel для проекта
8. Можно ввести вспом. команду ~/.composer/vendor/bin/laravel help new для уточняющего просмотра настроек установки Laravel
9. Установка Laravel в папку proj без npm, phpunit тестировщика, встроенного ИИ Boost - ~/.composer/vendor/bin/laravel new --no-node --phpunit --no-boost proj
10. mv proj/* .  и  mv proj/.* .  - перенести файлы Laravel, в т.ч. скрытые, в главную папку из папки proj.
11. rmdir proj - удалить папку proj