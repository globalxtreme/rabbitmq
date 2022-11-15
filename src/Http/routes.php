<?php

use Illuminate\Support\Facades\Route;

$version = config('base.conf.version');
$service = config('base.conf.service');

Route::prefix(config('base.conf.prefix.web') . "/$version/$service/message-brokers")
    ->middleware(['web', 'identifier.employee'])
    ->namespace("GlobalXtreme\\RabbitMQ\\Http\\Controllers")
    ->group(function () {

        Route::prefix('exchanges')
            ->group(function () {

                Route::get('', 'GXRabbitExchangeController@get');
                Route::post('', 'GXRabbitExchangeController@create');
                Route::put('{exchange}', 'GXRabbitExchangeController@update');
                Route::delete('{exchange}', 'GXRabbitExchangeController@delete');

            });

        Route::prefix('queues')
            ->group(function () {

                Route::get('', 'GXRabbitQueueController@get');
                Route::post('', 'GXRabbitQueueController@create');
                Route::put('{queue}', 'GXRabbitQueueController@update');
                Route::delete('{queue}', 'GXRabbitQueueController@delete');

            });

        Route::prefix('keys')
            ->group(function () {

                Route::get('', 'GXRabbitKeyController@get');
                Route::post('', 'GXRabbitKeyController@create');
                Route::put('{key}', 'GXRabbitKeyController@update');
                Route::delete('{key}', 'GXRabbitKeyController@delete');

            });

        Route::prefix('messages')
            ->group(function () {

                Route::get('', 'GXRabbitMessageController@get');
                Route::get('failed', 'GXRabbitMessageController@getFailed');

            });

    });

