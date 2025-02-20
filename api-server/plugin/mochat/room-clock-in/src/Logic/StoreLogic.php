<?php

declare(strict_types=1);
/**
 * This file is part of MoChat.
 * @link     https://mo.chat
 * @document https://mochat.wiki
 * @contact  group@mo.chat
 * @license  https://github.com/mochat-cloud/mochat/blob/master/LICENSE
 */
namespace MoChat\Plugin\RoomClockIn\Logic;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use MoChat\App\Corp\Contract\CorpContract;
use MoChat\App\Utils\File;
use MoChat\Framework\Constants\ErrorCode;
use MoChat\Framework\Exception\CommonException;
use MoChat\Plugin\RoomClockIn\Contract\ClockInContract;

/**
 * 群打卡-增加.
 *
 * Class StoreLogic
 */
class StoreLogic
{
    /**
     * @Inject
     * @var ClockInContract
     */
    protected $clockInService;

    /**
     * @Inject
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @var CorpContract
     */
    protected $corpService;

    /**
     * StoreLogic constructor.
     */
    public function __construct(StdoutLoggerInterface $logger, CorpContract $corpService, ClockInContract $clockInService)
    {
        $this->logger         = $logger;
        $this->corpService    = $corpService;
        $this->clockInService = $clockInService;
    }

    /**
     * @param array $user 登录用户信息
     * @param array $params 请求参数
     * @throws \JsonException
     * @throws \League\Flysystem\FileExistsException
     * @return array 响应数组
     */
    public function handle(array $user, array $params): array
    {
        ## 处理参数
        $params = $this->handleParam($user, $params);
        ## 创建活动
        $id = $this->createClockIn($params);

        return [$id];
    }

    /**
     * 处理参数.
     * @param array $user 用户信息
     * @param array $params 接受参数
     * @throws \JsonException
     * @throws \League\Flysystem\FileExistsException
     * @return array 响应数组
     */
    private function handleParam(array $user, array $params): array
    {
        if (isset($params['corp_card']['logo'])) {
            $params['corp_card']['logo'] = File::uploadBase64Image($params['corp_card']['logo'], 'image/clockIn/' . strval(microtime(true) * 10000) . '_' . uniqid() . '.jpg');
        }

        return [
            'name'               => $params['name'],
            'description'        => $params['description'],
            'type'               => $params['type'],
            'tasks'              => json_encode($params['tasks'], JSON_THROW_ON_ERROR),
            'time_type'          => $params['time_type'],
            'start_time'         => isset($params['start_time']) ? $params['start_time'] : '',
            'end_time'           => isset($params['end_time']) ? $params['end_time'] : '',
            'employee_qrcode'    => File::uploadBase64Image($params['employee_qrcode'], 'image/clockIn/' . strval(microtime(true) * 10000) . '_' . uniqid() . '.jpg'),
            'corp_card_status'   => $params['corp_card_status'],
            'corp_card'          => isset($params['corp_card']) ? json_encode($params['corp_card'], JSON_THROW_ON_ERROR) : '{}',
            'contact_clock_tags' => isset($params['contact_clock_tags']) ? json_encode($params['contact_clock_tags'], JSON_THROW_ON_ERROR) : '{}',
            'point'              => isset($params['point']) ? $params['point'] : 0,
            'tenant_id'          => isset($params['tenant_id']) ? $params['tenant_id'] : 0,
            'corp_id'            => $user['corpIds'][0],
            'create_user_id'     => $user['id'],
            'created_at'         => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 创建活动.
     * @param array $params 参数
     * @return int 响应数值
     */
    private function createClockIn(array $params): int
    {
        ## 数据操作
        Db::beginTransaction();
        try {
            ## 创建活动
            $id = $this->clockInService->createClockIn($params);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            $this->logger->error(sprintf('%s [%s] %s', '活动创建失败', date('Y-m-d H:i:s'), $e->getMessage()));
            $this->logger->error($e->getTraceAsString());
            throw new CommonException(ErrorCode::SERVER_ERROR, $e->getMessage()); //'活动创建失败'
        }
        return $id;
    }
}
