<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\admin\PushListService;
use think\App;
use think\Response;

/**
 * B 端评价推送列表控制器，对外提供推送实例的查询入口。
 */
class PushList extends BaseController
{
    /**
     * 通过容器注入评价推送列表业务服务。
     *
     * @param App              $app              ThinkPHP 应用实例。
     * @param PushListService  $pushListService  评价推送列表业务服务。
     */
    public function __construct(
        App $app,
        private PushListService $pushListService
    ) {
        parent::__construct($app);
    }

    /**
     * 分页查询评价推送列表。
     *
     * @return Response 统一 JSON 响应。
     */
    public function listOp(): Response
    {
        save_log('查询评价推送列表请求日志', 1, '查询评价推送列表', 'admin/push/list', [
            'request_data' => $this->requestData,
        ]);

        try {
            $result = $this->pushListService->getList($this->requestData);

            save_log('查询评价推送列表执行完成日志', 1, '查询评价推送列表', 'admin/push/list', [
                'response_data' => ['count' => $result['count']],
            ]);
        } catch (\Exception $e) {
            $remark = '查询评价推送列表失败，原因：' . $e->getMessage();
            save_log($remark, 2, '查询评价推送列表', 'admin/push/list', [
                'grade'         => 1,
                'request_data'  => $this->requestData,
                'response_data' => $remark
                    . ' 错误编码：' . $e->getCode()
                    . ' 错误行：' . $e->getLine()
                    . ' 错误文件：' . $e->getFile(),
            ]);

            return $this->returnJson(250, $e->getMessage());
        }

        return $this->returnJson(0, '成功', $result);
    }
}
