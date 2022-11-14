<?php

namespace GlobalXtreme\RabbitMQ\Http\Controllers;

use GlobalXtreme\RabbitMQ\Models\GXRabbitKey;
use GlobalXtreme\Validation\Validator;
use Illuminate\Http\Request;

class GXRabbitKeyController extends Controller
{
    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function get(Request $request)
    {
        $keys = GXRabbitKey::filter($request)->getOrPaginate($request, true);
        return success($keys);
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
            'queueId' => 'required|integer',
            'name' => 'required|string'
        ]);

        $key = GXRabbitKey::create($request->only(['queueId', 'name']));
        return success($key);
    }

    /**
     * @param GXRabbitKey $key
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     * @throws \GlobalXtreme\Validation\Exception\ValidationException
     */
    public function update(GXRabbitKey $key, Request $request)
    {
        Validator::make($request->all(), [
            'queueId' => 'required|integer',
            'name' => 'required|string'
        ]);

        $key->update($request->only(['queueId', 'name']));
        return success($key->fresh());
    }

    /**
     * @param GXRabbitKey $key
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function delete(GXRabbitKey $key)
    {
        $key->delete();
        return success();
    }

}
