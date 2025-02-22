<?php

declare(strict_types=1);
/**
 * This file is part of MoChat.
 * @link     https://mo.chat
 * @document https://mochat.wiki
 * @contact  group@mo.chat
 * @license  https://github.com/mochat-cloud/mochat/blob/master/LICENSE
 */

namespace MoChat\Plugin\Lottery\Action\Operation;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use MoChat\Framework\Action\AbstractAction;
use MoChat\Framework\Constants\ErrorCode;
use MoChat\Framework\Exception\CommonException;
use Psr\Http\Message\ResponseInterface as Psr7ResponseInterface;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Session\Middleware\SessionMiddleware;
use MoChat\Plugin\Lottery\Contract\LotteryContract;
use MoChat\App\OfficialAccount\Action\Operation\Traits\AuthTrait;
use MoChat\Framework\Request\ValidateSceneTrait;

/**
 * 公众号授权跳转.
 * @Controller
 */
class Auth extends AbstractAction
{
    use AuthTrait;
    use ValidateSceneTrait;

    /**
     * @Inject
     * @var LotteryContract
     */
    protected $lotteryService;

    /**
     * 为了自动兼容nginx转发规则，此处的路由定义与规范不同
     *
     * @Middleware(SessionMiddleware::class)
     * @RequestMapping(path="/operation/auth/lottery", methods="get,post")
     */
    public function handle(): Psr7ResponseInterface
    {
        $this->validated($this->request->all());
        return $this->execute();
    }

    /**
     * 验证规则.
     *
     * @return array 响应数据
     */
    protected function rules(): array
    {
        return [
            'target' => 'required',
        ];
    }

    /**
     * 验证错误提示.
     * @return array 响应数据
     */
    protected function messages(): array
    {
        return [
            'target.required' => 'target 必传',
        ];
    }

    protected function getModuleName()
    {
        return 'lottery';
    }

    protected function getType(): int
    {
        return 2;
    }

    protected function getCorpId(): int
    {
        $id = (int)$this->request->input('id');

        if ($id === 0) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '数据不存在');
        }

        $info = $this->lotteryService->getLotteryById($id, ['corp_id']);
        $corpId = empty($info) ? 0 : $info['corpId'];
        return $corpId;
    }
}
