# Создание тестового B2B оператора

После `php artisan migrate` можно создать первого оператора через `php artisan tinker`.

```php
use Illuminate\Support\Facades\Crypt;
use VanguardLTE\B2B\Models\B2BOperator;
use VanguardLTE\B2B\Models\B2BOperatorApiKey;

$operator = B2BOperator::create([
    'operator_uid' => 'op_demo',
    'name' => 'Demo Operator',
    'shop_id' => 1,
    'status' => 'active',
    'default_currency' => 'USD',
    'allowed_currencies' => ['USD'],
    'ip_whitelist' => [],
]);

$secret = 'demo_secret_change_me';

B2BOperatorApiKey::create([
    'operator_id' => $operator->id,
    'key_id' => 'demo_key',
    'secret_encrypted' => Crypt::encryptString($secret),
    'status' => 'active',
]);
```

Тестовая подпись считается так:

```text
payload = X-Timestamp + "." + X-Nonce + "." + raw_json_body
signature = HMAC-SHA256(payload, secret)
```

Заголовки:

```text
X-Operator-Id: op_demo
X-Api-Key: demo_key
X-Timestamp: <unix timestamp>
X-Nonce: <random unique string>
X-Signature: <hmac sha256>
```
