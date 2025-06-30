<?php

if (!function_exists("errRabbitMQMessageGet")) {
    function errRabbitMQMessageGet($internalMsg = "")
    {
        error(404, "Message not found!", $internalMsg);
    }
}

if (!function_exists("errRabbitMQMessageDeliveryGet")) {
    function errRabbitMQMessageDeliveryGet($internalMsg = "")
    {
        error(404, "Message delivery not found!", $internalMsg);
    }
}

if (!function_exists("errRabbitMQMessageDeliveryValidation")) {
    function errRabbitMQMessageDeliveryValidation($internalMsg = "")
    {
        error(400, "Message delivery form invalid!", $internalMsg);
    }
}
