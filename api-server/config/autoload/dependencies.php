<?php

declare(strict_types=1);
/**
 * This file is part of MoChat.
 * @link     https://mo.chat
 * @document https://mochat.wiki
 * @contact  group@mo.chat
 * @license  https://github.com/mochat-cloud/mochat/blob/master/LICENSE
 */
use Hyperf\Contract\StdoutLoggerInterface;
use MoChat\Framework\Log\StdoutLoggerFactory;

$dependencies = [];

$dependencies = array_merge(register_service_map(BASE_PATH . '/app/core'), $dependencies);

$appEnv = env('APP_ENV', 'production');
if ($appEnv !== 'dev') {
    $dependencies[StdoutLoggerInterface::class] = StdoutLoggerFactory::class;
}

return $dependencies + [
        Hyperf\HttpServer\CoreMiddleware::class => MoChat\App\Common\Middleware\CoreMiddleware::class,
        EasyWeChat\Work\ExternalContact\MessageClient::class => MoChat\App\Utils\EasyWeChat\Work\ExternalContact\MessageClient::class,
];
