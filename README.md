# Training Payment System
Учебная демонстрационная платёжная система.

[Техническое задание](https://docs.google.com/document/d/11eqsxuavwpzYEZJjuhYl0OcH8CObd8zxePcz1771Gzg/edit?usp=sharing)

## Инструкции по установке
1. Склонировать репозиторий
2. Перейти в папку docker внутри проекта
3. Скопировать .env.example в просто .env, при необходимости поменять данные
4. Запустить в папке docker команду ```docker compose up -d```
5. При наличии файлов в папке application запустить composer ```docker compose exec -u www-data php composer install```