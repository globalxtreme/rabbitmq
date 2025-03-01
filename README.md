GlobalXtreme RabbitMQ
======

### For GlobalXtreme Only (Microservice)

### Standar Penulisan exchange & queue:
Exhange: <-service->-<-domain / feature->-<-sub feature->-<-action->-exchange\
Queue: <-service->-<-domain / feature->-<-sub feature->-<-action->-queue\
Example: [Publish & Consume](https://github.com/globalxtreme/rabbitmq/tree/v2.1/example/Example.php)


## Configuration:

### 1. Jalankan command publisher
```shell
# Command ini untuk copy configurasi yang wajib dimiliki
php artisan vendor:publish --tag=gx-rabbitmq-config

# Command yang dibawah boleh dijalankan jika perlu karena tidak wajib
# Ini berfungsi untuk copy standar consumer command yang sudah disediakan untuk koneksi global & local
php artisan vendor:publish --tag=gx-rabbitmq-command
```

### 2. Setting database connection
```php
// Buka file config/database.php dan tambahkan connection di bawah
return [

    // ... Konfigurasi lainnya

    "connections" => [
        
        // ... Koneksi lainnya

        'rabbitmq' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_RABBITMQ_HOST', '127.0.0.1'),
            'port' => env('DB_RABBITMQ_PORT', '3306'),
            'database' => env('DB_RABBITMQ_DATABASE', 'forge'),
            'username' => env('DB_RABBITMQ_USERNAME', 'forge'),
            'password' => env('DB_RABBITMQ_PASSWORD', ''),
            'unix_socket' => env('DB_RABBITMQ_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
        
        // ... Koneksi lainnya

    ],
    
    // ... Konfigurasi lainnya

];
```

### 3. Edit environment
```shell
# Ini untuk koneksi ke database message borker
DB_RABBITMQ_CONNECTION=rabbitmq
DB_RABBITMQ_HOST=127.0.0.1
DB_RABBITMQ_PORT=3306
DB_RABBITMQ_DATABASE=database_name
DB_RABBITMQ_USERNAME=root
DB_RABBITMQ_PASSWORD=root

# Ini untuk koneksi ke rabbitmq global yang ada di message broker
RABBITMQ_GLOBAL_HOST=127.0.0.1
RABBITMQ_GLOBAL_PORT=5672
RABBITMQ_GLOBAL_USER=root
RABBITMQ_GLOBAL_PASSWORD=root

# Ini untuk koneksi ke rabbitmq local yang ada di server local per project
# Tambah jika diperlukan saja
RABBITMQ_LOCAL_HOST=127.0.0.1
RABBITMQ_LOCAL_PORT=5672
RABBITMQ_LOCAL_USER=root
RABBITMQ_LOCAL_PASSWORD=root
```

### 4. Consumer generator
```shell
# Command ini untuk generate consumer class
php artisan make:rabbitmq-consumer WorkOrder\\WorkOrderCreateConsumer
```

### 5. Cara penggunaan
```php

/** --- CONSUMER --- */

use GlobalXtreme\RabbitMQ\Constant\GXRabbitConnectionType;
use GlobalXtreme\RabbitMQ\Models\GXRabbitConnection;
use GlobalXtreme\RabbitMQ\Queue\Contract\GXRabbitMQConsumerContract;
use GlobalXtreme\RabbitMQ\Queue\GXRabbitMQConsumer;
use GlobalXtreme\RabbitMQ\Queue\GXRabbitMQPublish;

class WorkOrderCreateConsumer implements GXRabbitMQConsumerContract
{
    public static function consume(array|string $data)
    {   
        // Logic yang kamu buat
        // Usahakan tidak menggunakan try catch dan menggunakan new throw jika terjadi error
        // New throw bisa menggunakan error() dan sejenisnya
    }
}


/** --- PUBLISH --- */

// Hanya bisa mengirim ke salah satu, queue atau exchange

// Push ke queue secara spesifik
$queue = "business.notification.employee.push.queue";
GXRabbitMQPublish::dispatch(['message' => "Hallo $queue Queue"])
    ->onQueue($queue);

// Push ke exchange
$exchange = "business.product.variant.justification.approval.exchange";
GXRabbitMQPublish::dispatch(['message' => "Hallo $exchange Exchange"])
    ->onExchange($exchange);

// Kirim ke rabbitmq local
$queue = "business.product.variant.report.generate.queue";
GXRabbitMQPublish::dispatch(['message' => "Hallo $queue Queue"])
    ->onConnection(GXRabbitConnectionType::LOCAL)
    ->onQueue($queue);

// Kirim dengan default timeout saat publish message
$queue = "business.product.variant.report.generate.queue";
GXRabbitMQPublish::dispatch(['message' => "Hallo $queue Queue"])
    ->onConnection(GXRabbitConnectionType::LOCAL)
    ->connectionTimeout(60 * 60) // 1 Jam
    ->onQueue($queue);
    
// Kirim dengan connection yang ada di database
// Biasanya yang menggunakan hanya message broker untuk resend message yang gagal consume
$connection = GXRabbitConnection::first();
$queue = "business.product.variant.report.generate.queue";
GXRabbitMQPublish::dispatch(['message' => "Hallo $queue Queue"])
    ->onConnection($connection)
    ->onQueue($queue);


/** --- CONSUMER --- */

// Ini akan diletakan didalam command consumer yang kamu buat
// Untuk cara penulisan akan sama saja, yang membedakan kita dapat menentukan
// Queue atau exchange tertentu yang akan dijalankan di command tersebut

$consumer = new GXRabbitMQConsumer();

// Daftar exchange beserta consumer class
$consumer->setExchanges([
    'business.product.variant.justification.approval.exchange' => TestingOneConsumer::class,
    'business.product.variant.update.exchange' => TestingTwoConsumer::class,
]);

// Daftar queue beserta consumer class
$consumer->setQueues([
    'business.product.variant.justification.create.queue' => TestingOneConsumer::class,
    'business.notification.employee.push.queue-' => TestingTwoConsumer::class,
]);

// Value default adalah "global", namun bisa dirubah dengat tipe koneksi lain "local"
$consumer->consume(); // local / global

```
