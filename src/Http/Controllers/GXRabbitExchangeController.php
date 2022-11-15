<?php

namespace GlobalXtreme\RabbitMQ\Http\Controllers;

use GlobalXtreme\RabbitMQ\Models\GXRabbitExchange;
use GlobalXtreme\Validation\Validator;
use Illuminate\Http\Request;

class GXRabbitExchangeController extends Controller
{
    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function get(Request $request)
    {
        $exchanges = GXRabbitExchange::filter($request)->getOrPaginate($request, true);
        return success($exchanges);
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

        $exchange = GXRabbitExchange::create($request->only(['name', 'type']) + (auth_created_by() ?: []));
        return success($exchange);
    }

    /**
     * @param GXRabbitExchange $exchange
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     * @throws \GlobalXtreme\Validation\Exception\ValidationException
     */
    public function update(GXRabbitExchange $exchange, Request $request)
    {
        Validator::make($request->all(), [
            'name' => 'required|string',
            'type' => 'required|string'
        ]);

        $exchange->update($request->only(['name', 'type']));
        return success($exchange->fresh());
    }

    /**
     * @param GXRabbitExchange $exchange
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function delete(GXRabbitExchange $exchange)
    {
        $exchange->delete();
        return success();
    }

}
