# Следующие шаги после Repair v2

1. Убедиться, что репозиторий очищен от `.env`, `vendor`, SQL dump и установочных папок.
2. На сервере выполнить:

```bash
composer dump-autoload
php artisan migrate
php artisan route:list | grep b2b
```

3. Создать тестового B2B оператора через `docs/b2b/CREATE_TEST_OPERATOR.md`.
4. Проверить `/api/b2b/v1/health` без подписи.
5. Проверить подписанные запросы:
   - `GET /api/b2b/v1/games`
   - `POST /api/b2b/v1/games/launch`
6. Потом делать следующую функциональную доработку: `GoldsvetInternalProvider` + привязка B2B session к `launcher/{game}/{token}`.
