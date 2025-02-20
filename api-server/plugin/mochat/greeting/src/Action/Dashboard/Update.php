<?php

declare(strict_types=1);
/**
 * This file is part of MoChat.
 * @link     https://mo.chat
 * @document https://mochat.wiki
 * @contact  group@mo.chat
 * @license  https://github.com/mochat-cloud/mochat/blob/master/LICENSE
 */
namespace MoChat\Plugin\Greeting\Action\Dashboard;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middlewares;
use Hyperf\HttpServer\Annotation\Middleware;
use MoChat\App\Common\Middleware\DashboardAuthMiddleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use MoChat\App\Common\Constants\BusinessLog\Event;
use MoChat\App\Common\Contract\BusinessLogContract;
use MoChat\App\Rbac\Middleware\PermissionMiddleware;
use MoChat\Framework\Action\AbstractAction;
use MoChat\Framework\Constants\ErrorCode;
use MoChat\Framework\Exception\CommonException;
use MoChat\Framework\Request\ValidateSceneTrait;
use MoChat\Plugin\Greeting\Contract\GreetingContract;

/**
 * 欢迎语-更新提交.
 *
 * Class Update.
 * @Controller
 */
class Update extends AbstractAction
{
    use ValidateSceneTrait;

    /**
     * @Inject
     * @var GreetingContract
     */
    private $greetingService;

    /**
     * @Inject
     * @var BusinessLogContract
     */
    private $businessLogService;

    /**
     * @Inject
     * @var StdoutLoggerInterface
     */
    private $logger;

    /**
     * @RequestMapping(path="/dashboard/greeting/update", methods="put")
     * @Middlewares({
     *     @Middleware(DashboardAuthMiddleware::class),
     *     @Middleware(PermissionMiddleware::class)
     * })
     * @return array 返回数组
     */
    public function handle(): array
    {
        ## 获取登录用户信息
        $user = user();
        ## 判断用户绑定企业信息
        if (! isset($user['corpIds']) || count($user['corpIds']) != 1) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '未选择登录企业，不可操作');
        }
        ## 参数验证
        $this->validated($this->request->all());
        ## 接收参数
        $greetingId = $this->request->input('greetingId');
        $params     = [
            'range_type' => $this->request->input('rangeType'),
            'employees'  => $this->request->input('employees', ''),
            'type'       => $this->request->input('type'),
            'words'      => $this->request->input('words', ''),
            'medium_id'  => $this->request->input('mediumId', 0),
            'corp_id'    => $user['corpIds'][0],
        ];
        ## 欢迎语类型
        $typeArr        = explode(',', $params['type']);
        $params['type'] = count($typeArr) == 1 ? '-' . $typeArr[0] . '-' : '-' . implode('-', $typeArr) . '-';
        ## 适用成员
        $params['employees'] = empty($params['employees']) ? json_encode([]) : json_encode(explode(',', $params['employees']));
        ## 数据操作
        Db::beginTransaction();
        try {
            ## 创建数据
            $this->greetingService->updateGreetingById((int) $greetingId, $params);
            ## 记录业务日志
            $businessLog = [
                'business_id'  => $greetingId,
                'params'       => json_encode($params),
                'event'        => Event::GREETING_UPDATE,
                'operation_id' => $user['workEmployeeId'],
                'created_at'   => date('Y-m-d H:i:s'),
            ];
            $this->businessLogService->createBusinessLog($businessLog);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            $this->logger->error(sprintf('%s [%s] %s', '欢迎语更新失败', date('Y-m-d H:i:s'), $e->getMessage()));
            $this->logger->error($e->getTraceAsString());
            throw new CommonException(ErrorCode::SERVER_ERROR, '欢迎语更新失败');
        }
        return [];
    }

    /**
     * 验证规则.
     *
     * @return array 响应数据
     */
    protected function rules(): array
    {
        return [
            'greetingId' => 'required | integer | min:0 | bail',
            'rangeType'  => 'required | integer | in:1,2, | bail',
            'type'       => 'required | string | min:1 | bail',
            'mediumId'   => 'integer | min:0 | bail',
        ];
    }

    /**
     * 验证错误提示.
     * @return array 响应数据
     */
    protected function messages(): array
    {
        return [
            'greetingId.required' => '欢迎语ID 必填',
            'greetingId.integer'  => '欢迎语ID 必需为整数',
            'greetingId.min  '    => '欢迎语ID 不可小于1',
            'rangeType.required'  => '适用成员类型 必填',
            'rangeType.integer'   => '适用成员类型 必需为整数',
            'rangeType.in'        => '适用成员类型 值必须在列表内：[1,2]',
            'type.required'       => '欢迎语类型 必填',
            'type.string'         => '欢迎语类型 必需为字符串',
            'type.min'            => '欢迎语类型 字符串长度不可小于1',
            'mediumId.integer'    => '素材库ID 必需为整数',
            'mediumId.min'        => '素材库ID 不可小于1',
        ];
    }
}
