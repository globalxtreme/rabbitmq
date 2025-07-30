GlobalXtreme RabbitMQ
======

### For GlobalXtreme Only (Microservice)

### Standar Penulisan exchange & queue pada rabbitmq:
Exhange: <-service->.<-domain/feature->.<-sub_feature->.<-action->.exchange\
Queue: <-service->.<-domain/feature->.<-sub_feature->.<-action->.queue\
Example: [Publish & Consume](https://github.com/globalxtreme/rabbitmq/blob/v2.1/example/RabbitMQExample.php)

### Standar Penulisan pada async workflow (ASA):
Action: <-service->.<-domain/feature->.<-sub_feature->.<-action->\
Queue: <-service->.<-domain/feature->.<-sub_feature->.<-action->.async-workflow\
Example: [Initiator & Executor](https://github.com/globalxtreme/rabbitmq/blob/v2.1/example/AsyncWorkflowExample.php)


## Configuration:

### 1. Jalankan command publisher
```shell
# Command ini untuk copy configurasi yang wajib dimiliki
php artisan vendor:publish --tag=gx-rabbitmq-config

# Command yang dibawah boleh dijalankan jika perlu karena tidak wajib
# Ini berfungsi untuk copy standar consumer command yang sudah disediakan untuk koneksi global & local dan juga asycn workflow
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
    
    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'async-workflow' => [
            'url' => env('REDIS_ASYNC_WORKFLOW_URL'),
            'host' => env('REDIS_ASYNC_WORKFLOW_HOST', '127.0.0.1'),
            'username' => env('REDIS_ASYNC_WORKFLOW_USERNAME'),
            'password' => env('REDIS_ASYNC_WORKFLOW_PASSWORD'),
            'port' => env('REDIS_ASYNC_WORKFLOW_PORT', '6379'),
            'database' => env('REDIS_ASYNC_WORKFLOW_DB', '0'),
        ],

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

# Ini untuk koneksi ke redis async workflow monitoring, jika gagal ataupun sukses memproses message akan mengirim event ke redis
# Ini wajib diseting
REDIS_ASYNC_WORKFLOW_HOST=127.0.0.1
REDIS_ASYNC_WORKFLOW_PORT=6379

```

### 4. Consumer & Executor (async workflow) generator
```shell
# Command ini untuk generate consumer class
php artisan make:rabbitmq-consumer WorkOrder\\WorkOrderCreateConsumer

# Command ini untuk generate executor class
php artisan make:async-workflow WorkOrder\\WorkOrderCreateExecutor
```

### 5. Cara penggunaan
```php

/** --- CONSUMER --- */

use GlobalXtreme\RabbitMQ\Constant\GXRabbitConnectionType;
use GlobalXtreme\RabbitMQ\Form\GXAsyncWorkflowForm;
use GlobalXtreme\RabbitMQ\Models\GXRabbitAsyncWorkflowStep;
use GlobalXtreme\RabbitMQ\Models\GXRabbitConnection;
use GlobalXtreme\RabbitMQ\Models\GXRabbitMessage;
use GlobalXtreme\RabbitMQ\Queue\Contract\GXRabbitMQConsumerContract;
use GlobalXtreme\RabbitMQ\Queue\GXAsyncWorkflowConsumer;
use GlobalXtreme\RabbitMQ\Queue\GXAsyncWorkflowPublish;
use GlobalXtreme\RabbitMQ\Queue\GXRabbitMQConsumer;
use GlobalXtreme\RabbitMQ\Queue\GXRabbitMQPublish;

class WorkOrderCreateConsumer implements GXRabbitMQConsumerContract
{
    /**
     * @param GXRabbitMessage $message
     * @param array $payload
     */
    public function __construct(protected GXRabbitMessage $message, protected array $payload)
    {
    }


    public function consume()
    {   
        // Logic yang kamu buat
        // Usahakan tidak menggunakan try catch dan menggunakan new throw jika terjadi error
        // New throw bisa menggunakan error() dan sejenisnya
    }
}

/** --- EXECUTOR/CONSUMER --- */

class WorkOrderCreateExecutor implements GXAsyncWorkflowConsumerContract, GXAsyncWorkflowForwardPayload
{
    /**
     * @param GXRabbitAsyncWorkflowStep $workflowStep
     * @param array $payload
     */
    public function __construct(protected GXRabbitAsyncWorkflowStep $workflowStep, protected array $payload)
    {
    }


    public function consume()
    {   
        // Logic yang kamu buat
        // Usahakan tidak menggunakan try catch dan menggunakan new throw jika terjadi error
        // New throw bisa menggunakan error() dan sejenisnya
        
        return $this->response($this->payload);
    }
    
    public function response($data = null)
    {
        return [
            // ...
        ];
    }

    // Ini response yang akan dikirim ke step/queue yang ingin di tuju (selain step selanjutnya)
    // Tambahkan hanya jika perlu saja
    // Secara default tidak perlu seting forward payload dan implement interface nya di executor
    public function forwardPayload()
    {
        return [
            'queue-name' => [
                // ...
            ],
            // ...
        ];
    }
}


/** --- RABBITMQ PUBSUB --- */

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
$consumer->rabbitmqConsume(); // local / global



/** --- ASA: ASYNC WORKFLOW --- */

/** --- INITIATOR --- */

$workflow = new GXAsyncWorkflowPublish(
    action: 'sales-management.prospect.service-location.convert',
    reference: 123,
    referenceType: 'prospect_service_locations',
    isStrict: true
);

// STEP 1
$workflow->onStep(new GXAsyncWorkflowForm(
    service: 'customers',
    queue: 'customer.service-location.convert.async-workflow',
    description: 'Create data service location baru ke customer service',
    payload: [
        'name' => 'First message',
        'subs' => ['testing 1', 'testing 2', 'testing 3', 'testing 4', 'testing 5'],
    ]
));

// STEP 2
$workflow->onStep(new GXAsyncWorkflowForm(
    'temporaries',
    'temporary.crm.service-location.save.async-workflow',
    'Mengirim data service location baru ke crm lewat temporary service',
));

// STEP 3
// ....

$workflow->push();


/** --- EXECUTOR/CONSUMER --- */

$consumer = new GXAsyncWorkflowConsumer();

$consumer->setQueues([
    'customer.service-location.convert.async-workflow' => StepOneExecutor::class,
    'temporary.crm.service-location.save.async-workflow' => StepTwoExecutor::class,
    'sales-management.prospect.service-location.update.async-workflow' => StepThreeExecutor::class,
]);

$consumer->consume();

```
