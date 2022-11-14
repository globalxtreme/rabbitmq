<?php

use Illuminate\Support\Facades\Route;

$version = config('base.conf.version');
$service = config('base.conf.service');

Route::prefix(config('base.conf.prefix.web') . "/$version/$service/message-brokers")
    ->middleware(['web', 'identifier.employee'])
    ->namespace("$this->namespace\\" . config('base.conf.namespace.web') . "\\$version")
    ->group(function () {

        Route::prefix('exchanges')
            ->group(function () {

                Route::get('', 'GXRabbitExchangeController@get');
                Route::post('', 'GXRabbitExchangeController@create');
                Route::put('{exchange}', 'GXRabbitExchangeController@update');
                Route::delete('{exchange}', 'GXRabbitExchangeController@update');

            });

        Route::prefix('queues')
            ->group(function () {

                Route::get('', 'GXRabbitQueueController@get');
                Route::post('', 'GXRabbitQueueController@create');
                Route::put('{queue}', 'GXRabbitQueueController@update');
                Route::delete('{queue}', 'GXRabbitQueueController@update');

            });

        Route::prefix('keys')
            ->group(function () {

                Route::get('', 'GXRabbitKeyController@get');
                Route::post('', 'GXRabbitKeyController@create');
                Route::put('{key}', 'GXRabbitKeyController@update');
                Route::delete('{key}', 'GXRabbitKeyController@update');

            });

        Route::prefix('messages')
            ->group(function () {

                Route::get('', 'GXRabbitKeyController@get');
                Route::get('failed', 'GXRabbitKeyController@getFailed');

            });

    });

