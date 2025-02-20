<?php

declare(strict_types=1);
/**
 * This file is part of MoChat.
 * @link     https://mo.chat
 * @document https://mochat.wiki
 * @contact  group@mo.chat
 * @license  https://github.com/mochat-cloud/mochat/blob/master/LICENSE
 */
namespace MoChat\App\OfficialAccount\Action\Dashboard;

use EasyWeChat\Factory;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use MoChat\App\OfficialAccount\Contract\OfficialAccountContract;
use MoChat\App\Utils\File;
use MoChat\App\Utils\Url;
use MoChat\Framework\Action\AbstractAction;
use Psr\Container\ContainerInterface;

/**
 * 授权变更通知推送
 * @Controller
 */
class AuthRedirect extends AbstractAction
{
    /**
     * @Inject
     * @var OfficialAccountContract
     */
    protected $officialAccountService;

    /**
     * @Inject
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $config;

    /**
     * @RequestMapping(path="/dashboard/officialAccount/authRedirect/", methods="get,post")
     * @throws \JsonException|\League\Flysystem\FileExistsException
     */
    public function handle()
    {
        $params = $this->request->all();
        $this->config = config('framework.wechat_open_platform');

        ## 使用授权码获取授权信息
        $res = $this->queryAuth($params);
        ## 获取授权方的帐号基本信息
        if ($res['id'] > 0) {
            $this->authorizerInfo($res);
        }
        return $this->response->redirect(Url::getDashboardBaseUrl() . '/officialAccount/index');
    }

    /**
     * @param $params
     * @throws \JsonException
     */
    private function queryAuth($params): array
    {
        ## EasyWeChat
        $openPlatform = Factory::openPlatform($this->config);
        $openPlatform = rebind_app($openPlatform, $this->request);
        $result       = $openPlatform->handleAuthorize($params['auth_code']);
        if (! empty($result['authorization_info'])) {
            $res  = $result['authorization_info'];
            $data = [
                'appid'             => $this->config['app_id'],
                'authorized_status' => 1,
                'authorizer_appid'  => $res['authorizer_appid'],
                'encoding_aes_key'  => $this->config['aes_key'],
                'token'             => $this->config['token'],
                'secret'            => $this->config['secret'],
                'func_info'         => json_encode($res['func_info'], JSON_THROW_ON_ERROR),
                'corp_id'           => (int) $params['corp_id'],
            ];
            $authorizerAppid = $res['authorizer_appid'];
            ## 数据操作
            Db::beginTransaction();
            try {
                $info = $this->officialAccountService->getOfficialAccountByAppIdAuthorizerAppidCorpId($this->config['app_id'], $res['authorizer_appid'], (int) $params['corp_id'], ['id']);
                if (empty($info)) {
                    $data['created_at'] = date('Y-m-d H:i:s');
                    $id                 = $this->officialAccountService->createOfficialAccount($data);
                } else {
                    $id = $info['id'];
                    $this->officialAccountService->updateOfficialAccountById($info['id'], $data);
                }
                Db::commit();
            } catch (\Throwable $e) {
                Db::rollBack();
                $this->logger->error(sprintf('%s [%s] %s', '授权失败', date('Y-m-d H:i:s'), $e->getMessage()));
                $this->logger->error($e->getTraceAsString());
            }
        }
        return ['id' => $id, 'authorizer_appid' => $authorizerAppid];
    }

    /**
     * @param array $id
     * @throws \JsonException
     */
    private function authorizerInfo(array $authorizer): void
    {
        if ($authorizer['id'] === 0) {
            return;
        }
        ## EasyWeChat
        $openPlatform = Factory::openPlatform($this->config);
        $openPlatform = rebind_app($openPlatform, $this->request);
        $result       = $openPlatform->getAuthorizer($authorizer['authorizer_appid']);
        if (! empty($result['authorizer_info'])) {
            $res  = $result['authorizer_info'];
            $data = [
                'nickname'          => $res['nick_name'],
                'head_img'          => $res['head_img'],
                'avatar'            => File::uploadUrlImage($res['head_img'], 'contact/avatar/' . strval(microtime(true) * 10000) . '_' . uniqid() . '.jpg'),
                'service_type_info' => $res['service_type_info']['id'],
                'verify_type_info'  => $res['verify_type_info']['id'],
                'user_name'         => $res['user_name'],
                'principal_name	'   => $res['principal_name'],
                'alias'             => isset($res['alias']) ? $res['alias'] : '',
                'business_info'     => json_encode($res['business_info'], JSON_THROW_ON_ERROR),
                'qrcode_url'        => $res['qrcode_url'],
                'local_qrcode_url'  => File::uploadUrlImage($res['qrcode_url'], 'contact/avatar/' . strval(microtime(true) * 10000) . '_' . uniqid() . '.jpg'),
            ];
            ## 数据操作
            Db::beginTransaction();
            try {
                $this->officialAccountService->updateOfficialAccountById($authorizer['id'], $data);
                Db::commit();
            } catch (\Throwable $e) {
                Db::rollBack();
                $this->logger->error(sprintf('%s [%s] %s', '授权失败', date('Y-m-d H:i:s'), $e->getMessage()));
                $this->logger->error($e->getTraceAsString());
            }
        }
    }
}
