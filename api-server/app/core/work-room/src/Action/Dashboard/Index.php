<?php

declare(strict_types=1);
/**
 * This file is part of MoChat.
 * @link     https://mo.chat
 * @document https://mochat.wiki
 * @contact  group@mo.chat
 * @license  https://github.com/mochat-cloud/mochat/blob/master/LICENSE
 */
namespace MoChat\App\WorkRoom\Action\Dashboard;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middlewares;
use Hyperf\HttpServer\Annotation\Middleware;
use MoChat\App\Common\Middleware\DashboardAuthMiddleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use MoChat\App\Rbac\Middleware\PermissionMiddleware;
use MoChat\App\WorkRoom\Logic\IndexLogic;
use MoChat\Framework\Action\AbstractAction;
use MoChat\Framework\Request\ValidateSceneTrait;

/**
 * 客户群管理-列表.
 *
 * Class Index.
 * @Controller
 */
class Index extends AbstractAction
{
    use ValidateSceneTrait;

    /**
     * @Inject
     * @var IndexLogic
     */
    protected $indexLogic;

    /**
     * @RequestMapping(path="/dashboard/workRoom/index", methods="get")
     * @Middlewares({
     *     @Middleware(DashboardAuthMiddleware::class),
     *     @Middleware(PermissionMiddleware::class)
     * })
     * @return array 返回数组
     */
    public function index(): array
    {
        ## 参数验证
        $this->validated($this->request->all());
        ## 获取当前登录用户
        $user = user();
        ## 接收参数
        $params = [
            'roomGroupId'     => $this->request->input('roomGroupId'),
            'workRoomName'    => $this->request->input('workRoomName'),
            'workRoomOwnerId' => $this->request->input('workRoomOwnerId'),
            'workRoomStatus'  => $this->request->input('workRoomStatus'),
            'startTime'       => $this->request->input('startTime'),
            'endTime'         => $this->request->input('endTime'),
            'page'            => $this->request->input('page', 1),
            'perPage'         => $this->request->input('perPage', 10),
        ];
        return $this->indexLogic->handle($user, $params);
    }

    /**
     * 验证规则.
     * @return array 响应数据
     */
    protected function rules(): array
    {
        return [];
    }

    /**
     * 验证错误提示.
     * @return array 响应数据
     */
    protected function messages(): array
    {
        return [];
    }
}
