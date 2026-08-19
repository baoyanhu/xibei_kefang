<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\admin\CustomerAnswerService;
use think\App;
use think\Response;

/**
 * B 端顾客评价控制器，对外提供答卷列表与明细查询入口。
 */
class CustomerAnswer extends BaseController
{
    /**
     * 通过容器注入顾客评价业务服务。
     *
     * @param App                     $app                     ThinkPHP 应用实例。
     * @param CustomerAnswerService   $customerAnswerService   顾客评价业务服务。
     */
    public function __construct(
        App $app,
        private CustomerAnswerService $customerAnswerService
    ) {
        parent::__construct($app);
    }

    /**
     * 分页查询顾客答卷列表。
     *
     * @return Response 统一 JSON 响应。
     */
    public function listOp(): Response
    {
        save_log('查询顾客评价列表请求日志', 1, '查询顾客评价列表', 'admin/answer/list', [
            'request_data' => $this->requestData,
        ]);

        try {
            $result = $this->customerAnswerService->getList($this->requestData);

            save_log('查询顾客评价列表执行完成日志', 1, '查询顾客评价列表', 'admin/answer/list', [
                'response_data' => ['count' => $result['count']],
            ]);
        } catch (\Exception $e) {
            $remark = '查询顾客评价列表失败，原因：' . $e->getMessage();
            save_log($remark, 2, '查询顾客评价列表', 'admin/answer/list', [
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

    /**
     * 查询单份答卷明细。
     *
     * @return Response 统一 JSON 响应。
     */
    public function detailOp(): Response
    {
        save_log('查询答题明细请求日志', 1, '查询答题明细', 'admin/answer/detail', [
            'request_data' => $this->requestData,
        ]);

        try {
            $result = $this->customerAnswerService->getDetail($this->requestData);

            save_log('查询答题明细执行完成日志', 1, '查询答题明细', 'admin/answer/detail', [
                'response_data' => ['answer_id' => $result['answer_id']],
            ]);
        } catch (\Exception $e) {
            $remark = '查询答题明细失败，原因：' . $e->getMessage();
            save_log($remark, 2, '查询答题明细', 'admin/answer/detail', [
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
