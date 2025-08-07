<?php

use GlobalXtreme\RabbitMQ\Form\GXAsyncWorkflowForm;
use GlobalXtreme\RabbitMQ\Models\GXRabbitAsyncWorkflow;
use GlobalXtreme\RabbitMQ\Models\GXRabbitAsyncWorkflowStep;
use GlobalXtreme\RabbitMQ\Queue\Contract\GXAsyncWorkflowConsumerContract;
use GlobalXtreme\RabbitMQ\Queue\Contract\GXAsyncWorkflowForwardPayload;
use GlobalXtreme\RabbitMQ\Queue\GXAsyncWorkflowConsumer;
use GlobalXtreme\RabbitMQ\Queue\GXAsyncWorkflowPublish;
use Illuminate\Support\Facades\Log;

class AsyncWorkflowExample
{
    public function publish()
    {
        // CASE: Convert prospect ke customer (service location)
        // STEP 0: Logic pada sales

        $workflow = new GXAsyncWorkflowPublish(
            'sales-management.prospect.service-location.convert',
            123, // ID prospect_service_locations
            'prospect_service_locations', // Table prospect_service_locations
            true // Untuk pengecekan apakah 1 reference hanya boleh memiliki 1 async workflow yang berstatus pending & processing
        );

        // STEP 1
        $workflow->onStep(new GXAsyncWorkflowForm(
            'customers',
            'customer.service-location.convert.async-workflow',
            'Create data service location baru ke customer service',
            [
                'name' => 'First message',
                'subs' => ['testing 1', 'testing 2', 'testing 3', 'testing 4', 'testing 5'],
            ] // Payload pada step 1 wajib diisi
        ));

        // STEP 2
        $workflow->onStep(new GXAsyncWorkflowForm(
            'temporaries',
            'temporary.crm.service-location.save.async-workflow',
            'Mengirim data service location baru ke crm lewat temporary service',
        ));

        // STEP 3
        $workflow->onStep(new GXAsyncWorkflowForm(
            'sales-managements',
            'sales-management.prospect.service-location.update.async-workflow',
            'Sales update kembali data prospect service location berdasarkan response dari (STEP 1)',
        ));

        $workflow->push();

    }

    public function consume()
    {
        // Contoh: Semua services (queue) akan diletakan di 1 consumer (CONTOH)

        $consumer = new GXAsyncWorkflowConsumer();

        $consumer->setQueues([
            'customer.service-location.convert.async-workflow' => StepOneExecutor::class,
            'temporary.crm.service-location.save.async-workflow' => StepTwoExecutor::class,
            'sales-management.prospect.service-location.update.async-workflow' => StepThreeExecutor::class,
        ]);

        $consumer->consume();
    }

}

class StepOneExecutor implements GXAsyncWorkflowConsumerContract, GXAsyncWorkflowForwardPayload
{
    private $serviceLocation;

    /**
     * @param GXRabbitAsyncWorkflow $workflow
     * @param GXRabbitAsyncWorkflowStep $workflowStep
     * @param array $payload
     */
    public function __construct(protected GXRabbitAsyncWorkflow     $workflow,
                                protected GXRabbitAsyncWorkflowStep $workflowStep,
                                protected array                     $payload)
    {
    }


    /**
     * @return array|null
     */
    public function consume()
    {
        // Isi dengan logic atau memanggil logic
        Log::info("Step One");
        Log::info($this->payload);
        Log::info("=========");

        // Isi dengan data sesuai kebutuhan (model/array)
        $this->serviceLocation = null;

        return $this->response();
    }

    public function response($data = null)
    {
        return [
            'id' => $this->serviceLocation->id,
            'locationId' => $this->serviceLocation->locationId,
            'circuitId' => $this->serviceLocation->circuitId,
            // ...
        ];
    }

    public function forwardPayload()
    {
        // Ini response yang akan dikirim ke step/queue yang ingin di tuju (selain step selanjutnya)
        return [
            'sales-management.prospect.service-location.update.async-workflow' => [
                'id' => $this->serviceLocation->id,
                'locationId' => $this->serviceLocation->locationId,
                'circuitId' => $this->serviceLocation->circuitId,
                // ...
            ],
            // ...
        ];
    }

}

class StepTwoExecutor implements GXAsyncWorkflowConsumerContract
{
    /**
     * @param GXRabbitAsyncWorkflow $workflow
     * @param GXRabbitAsyncWorkflowStep $workflowStep
     * @param array $payload
     */
    public function __construct(protected GXRabbitAsyncWorkflow     $workflow,
                                protected GXRabbitAsyncWorkflowStep $workflowStep,
                                protected array                     $payload)
    {
    }


    /**
     * @return array|null
     */
    public function consume()
    {
        // Isi dengan logic atau memanggil logic
        Log::info("Step Two");
        Log::info($this->payload);
        Log::info("=========");

        return $this->response();
    }

    public function response($data = null)
    {
        // Kalau step slanjutnya tidak butuh response dari step ini, cukup kirim array kosong / null
        return [];
    }

}

class StepThreeExecutor implements GXAsyncWorkflowConsumerContract
{
    /**
     * @param GXRabbitAsyncWorkflow $workflow
     * @param GXRabbitAsyncWorkflowStep $workflowStep
     * @param array $payload
     */
    public function __construct(protected GXRabbitAsyncWorkflow     $workflow,
                                protected GXRabbitAsyncWorkflowStep $workflowStep,
                                protected array                     $payload)
    {
    }


    /**
     * @return array|null
     */
    public function consume()
    {
        // Isi dengan logic atau memanggil logic
        Log::info("Step Two");
        Log::info($this->payload); // Payload akan digabung dari response di step 1 + step sebelumnya (2)
        Log::info("=========");

        return $this->response();
    }

    public function response($data = null)
    {
        // Karena step terakhir, jadi tidak perlu kirim response lagi
        return [];
    }

}
