<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\admin\StoreService;
use think\App;

/**
 * 门店管理控制器（B 端）
 * 路由：POST /admin/store/list
 */
class Store extends BaseController
{
    /**
     * 构造器注入 StoreService（容器自动解析依赖）
     */
    public function __construct(
        App $app,
        private StoreService $storeService
    ) {
        parent::__construct($app);
    }

    /**
     * 门店列表（B 端）
     * 路由：POST /admin/store/list
     */
    public function listOp()
    {
        // ① 入口请求日志（try 之前，记请求参数）
        save_log('查询门店列表请求日志', 1, 'B端查询门店列表', 'admin/store/list', [
            'request_data' => $this->requestData,
        ]);

        try {
            // ② 业务处理：调用 Service（构造器注入）
            $result = $this->storeService->getList($this->requestData);

            // ④ 执行完成日志（成功后，记响应数据）
            save_log('查询门店列表执行完成日志', 1, 'B端查询门店列表', 'admin/store/list', [
                'response_data' => $result,
            ]);
        } catch (\Exception $e) {
            // ⑤ 异常日志 + 失败响应（3 字段：grade + request_data + response_data）
            $remark = 'B端查询门店列表失败，原因：' . $e->getMessage();
            save_log($remark, 2, 'B端查询门店列表', 'admin/store/list', [
                'grade'        => 1,
                'request_data' => $this->requestData,
                'response_data' => $remark
                    . ' 错误编码：' . $e->getCode()
                    . ' 错误行：' . $e->getLine()
                    . ' 错误文件：' . $e->getFile(),
            ]);
            return $this->returnJson(250, $e->getMessage());
        }

        // ⑥ 成功响应
        return $this->returnJson(0, '成功', $result);
    }
}
