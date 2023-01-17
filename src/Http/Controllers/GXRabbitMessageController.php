<?php

namespace GlobalXtreme\RabbitMQ\Http\Controllers;

use GlobalXtreme\RabbitMQ\Consumer\RepairFailedMessage;
use GlobalXtreme\RabbitMQ\Models\GXRabbitMessage;
use GlobalXtreme\RabbitMQ\Models\GXRabbitMessageFailed;
use GlobalXtreme\Validation\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse|mixed|void
     * @throws \GlobalXtreme\Validation\Exception\ValidationException
     */
    public function repairFailed(Request $request)
    {
        Validator::make($request->all(), ['failedIds' => 'required']);

        try {

            DB::transaction(function () use ($request) {

                $failedIds = $request->failedIds;
                if (is_string($failedIds)) {
                    $failedIds = explode(',', $request->failedIds);
                }

                $failedMessages = GXRabbitMessageFailed::whereIn('id', $failedIds)->get();
                if (!$failedMessages || count($failedMessages) == 0) {
                    errMessageBrokerFailedNotFound();
                }

                foreach ($failedMessages as $failedMessage) {
                    $repairMessage = new RepairFailedMessage($failedMessage);
                    $repairMessage->handle();
                }

            });

            return success();

        } catch (\Exception $exception) {
            exception($exception);
        }
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse|mixed|void
     * @throws \GlobalXtreme\Validation\Exception\ValidationException
     */
    public function restFailed(Request $request)
    {
        Validator::make($request->all(), ['failedIds' => 'required']);

        try {

            DB::transaction(function () use ($request) {

                $failedIds = $request->failedIds;
                if (is_string($failedIds)) {
                    $failedIds = explode(',', $request->failedIds);
                }

                $failedMessages = GXRabbitMessageFailed::whereIn('id', $failedIds)->get();
                if (!$failedMessages || count($failedMessages) == 0) {
                    errMessageBrokerFailedNotFound();
                }

                foreach ($failedMessages as $failedMessage) {
                    $failedMessage->rested = true;
                    $failedMessage->save();
                }

            });

            return success();

        } catch (\Exception $exception) {
            exception($exception);
        }
    }

}
