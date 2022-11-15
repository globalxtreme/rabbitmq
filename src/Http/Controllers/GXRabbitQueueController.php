<?php

namespace GlobalXtreme\RabbitMQ\Http\Controllers;

use GlobalXtreme\RabbitMQ\Models\GXRabbitQueue;
use GlobalXtreme\Validation\Validator;
use Illuminate\Http\Request;

class GXRabbitQueueController extends Controller
{
    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function get(Request $request)
    {
        $queues = GXRabbitQueue::filter($request)->getOrPaginate($request, true);
        return success($queues);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     * @throws \GlobalXtreme\Validation\Exception\ValidationException
     */
    public function create(Request $request)
    {
        Validator::make($request->all(), [
            'name' => 'required|string',
            'type' => 'required|string'
        ]);

        $queue = GXRabbitQueue::create($request->only(['name', 'type']) + (auth_created_by() ?: []));
        return success($queue);
    }

    /**
     * @param GXRabbitQueue $queue
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     * @throws \GlobalXtreme\Validation\Exception\ValidationException
     */
    public function update(GXRabbitQueue $queue, Request $request)
    {
        Validator::make($request->all(), [
            'name' => 'required|string',
            'type' => 'required|string'
        ]);

        $queue->update($request->only(['name', 'type']));
        return success($queue->fresh());
    }

    /**
     * @param GXRabbitQueue $queue
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function delete(GXRabbitQueue $queue)
    {
        $queue->delete();
        return success();
    }

}
