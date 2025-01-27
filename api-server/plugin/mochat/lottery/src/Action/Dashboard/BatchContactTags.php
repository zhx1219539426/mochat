<?php

declare(strict_types=1);
/**
 * This file is part of MoChat.
 * @link     https://mo.chat
 * @document https://mochat.wiki
 * @contact  group@mo.chat
 * @license  https://github.com/mochat-cloud/mochat/blob/master/LICENSE
 */
namespace MoChat\Plugin\Lottery\Action\Dashboard;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middlewares;
use Hyperf\HttpServer\Annotation\Middleware;
use MoChat\App\Common\Middleware\DashboardAuthMiddleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use MoChat\App\Rbac\Middleware\PermissionMiddleware;
use MoChat\App\WorkContact\Contract\WorkContactEmployeeContract;
use MoChat\App\WorkEmployee\Contract\WorkEmployeeContract;
use MoChat\Framework\Action\AbstractAction;
use MoChat\Framework\Constants\ErrorCode;
use MoChat\Framework\Exception\CommonException;
use MoChat\Framework\Request\ValidateSceneTrait;
use MoChat\Plugin\AutoTag\Action\Dashboard\Traits\AutoContactTag;
use MoChat\Plugin\Lottery\Contract\LotteryContactContract;

/**
 * 抽奖活动 - 批量打标签.
 * @Controller
 */
class BatchContactTags extends AbstractAction
{
    use ValidateSceneTrait;
    use AutoContactTag;

    /**
     * @Inject
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @Inject
     * @var LotteryContactContract
     */
    protected $lotteryContactService;

    /**
     * @Inject
     * @var WorkContactEmployeeContract
     */
    protected $workContactEmployeeService;

    /**
     * @Inject
     * @var WorkEmployeeContract
     */
    protected $workEmployeeService;

    /**
     * @Middlewares({
     *     @Middleware(DashboardAuthMiddleware::class),
     *     @Middleware(PermissionMiddleware::class)
     * })
     * @RequestMapping(path="/dashboard/lottery/batchContactTags", methods="put")
     */
    public function handle(): array
    {
        ## 接受参数
        $params = $this->request->all();
        ## 获取当前登录用户
        $user = user();

        ## 判断用户绑定企业信息
        if (! isset($user['corpIds']) || count($user['corpIds']) !== 1) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '未选择登录企业，不可操作');
        }

        ## 数据操作
        Db::beginTransaction();
        try {
            $data = ['contactId' => 0, 'employeeId' => 0, 'tagArr' => array_column($params['tags'], 'tagid'), 'employeeWxUserId' => '', 'contactWxExternalUserid' => ''];
            foreach ($params['ids'] as $item) {
                ## 客户id
                $contactId = $this->lotteryContactService->getLotteryContactById((int) $item, ['contact_id']);
                if ($contactId['contactId'] > 0) {
                    $data['contactId'] = $contactId['contactId'];
                    ## 员工id
                    $contactEmployee    = $this->workContactEmployeeService->getWorkContactEmployeeByCorpIdContactId($contactId['contactId'], $user['corpIds'][0], ['employee_id']);
                    $data['employeeId'] = $contactEmployee['employeeId'];
                    ## 客户
                    $contact                         = $this->workContactService->getWorkContactById($contactId['contactId'], ['wx_external_userid']);
                    $data['contactWxExternalUserid'] = $contact['wxExternalUserid'];
                    ## 员工
                    $employee                 = $this->workEmployeeService->getWorkEmployeeById($contactEmployee['employeeId'], ['wx_user_id']);
                    $data['employeeWxUserId'] = $employee['wxUserId'];
                    $this->autoTag($data);
                    $this->lotteryContactService->updateLotteryContact([$item], ['contact_tags' => json_encode($params['tags'], JSON_THROW_ON_ERROR)]);
                }
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            $loggers = $this->container->get(StdoutLoggerInterface::class);
            $loggers->error(sprintf('%s [%s] %s', '批量打标签失败', date('Y-m-d H:i:s'), $e->getMessage()));
            $loggers->error($e->getTraceAsString());
            throw new CommonException(ErrorCode::SERVER_ERROR, $e->getMessage()); //'活动更新失败'
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
            'ids'  => 'required',
            'tags' => 'required',
        ];
    }

    /**
     * 验证错误提示.
     * @return array 响应数据
     */
    protected function messages(): array
    {
        return [
            'ids.required'  => '客户id 必填',
            'tags.required' => '标签 必填',
        ];
    }
}
