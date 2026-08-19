<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\admin\RewardRecordService;
use think\App;
use think\Response;

/**
 * B 端评价发放记录控制器，对外提供激励发放流水的查询入口。
 */
class RewardRecord extends BaseController
{
    /**
     * 通过容器注入评价发放记录业务服务。
     *
     * @param App                 $app                 ThinkPHP 应用实例。
     * @param RewardRecordService $rewardRecordService 评价发放记录业务服务。
     */
    public function __construct(
        App $app,
        private RewardRecordService $rewardRecordService
    ) {
        parent::__construct($app);
    }

    /**
     * 分页查询评价发放记录。
     *
     * @return Response 统一 JSON 响应。
     */
    public function listOp(): Response
    {
        save_log('查询评价发放记录请求日志', 1, '查询评价发放记录', 'admin/reward/list', [
            'request_data' => $this->requestData,
        ]);

        try {
            $result = $this->rewardRecordService->getList($this->requestData);

            save_log('查询评价发放记录执行完成日志', 1, '查询评价发放记录', 'admin/reward/list', [
                'response_data' => ['count' => $result['count']],
            ]);
        } catch (\Exception $e) {
            $remark = '查询评价发放记录失败，原因：' . $e->getMessage();
            save_log($remark, 2, '查询评价发放记录', 'admin/reward/list', [
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
