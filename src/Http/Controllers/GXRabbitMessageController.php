<?php

namespace GlobalXtreme\RabbitMQ\Http\Controllers;

use GlobalXtreme\RabbitMQ\Models\GXRabbitMessage;
use GlobalXtreme\RabbitMQ\Models\GXRabbitMessageFailed;
use Illuminate\Http\Request;

class GXRabbitMessageController extends Controller
{
    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function get(Request $request)
    {
        $messages = GXRabbitMessage::filter($request)->orderBy('id', 'DESC')->getOrPaginate($request, true);
        return success($messages);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function getFailed(Request $request)
    {
        $faileds = GXRabbitMessageFailed::filter($request)->orderBy('id', 'DESC')->getOrPaginate($request, true);
        return success($faileds);
    }

}
