<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\admin\StyleService;
use think\App;
use think\Response;

/**
 * B 端样式配置控制器，对外提供基础样式的查询和保存入口。
 */
class Style extends BaseController
{
    /**
     * 通过容器注入样式配置业务服务。
     *
     * @param App          $app          ThinkPHP 应用实例。
     * @param StyleService $styleService 样式配置业务服务。
     */
    public function __construct(
        App $app,
        private StyleService $styleService
    ) {
        parent::__construct($app);
    }

    /**
     * 查询商户样式配置详情。
     *
     * @return Response 统一 JSON 响应。
     */
    public function detailOp(): Response
    {
        save_log('查询样式配置请求日志', 1, '查询样式配置', 'admin/style/detail', [
            'request_data' => $this->requestData,
        ]);

        try {
            $merchantId = (int) ($this->requestData['merchant_id'] ?? 0);
            // 详情必须指定商户，防止误读其他商户配置。
            if ($merchantId <= 0) {
                exception('缺少参数 merchant_id');
            }

            $result = $this->styleService->getDetail($merchantId);

            save_log('查询样式配置执行完成日志', 1, '查询样式配置', 'admin/style/detail', [
                'response_data' => $result,
            ]);
        } catch (\Exception $e) {
            $remark = '查询样式配置失败，原因：' . $e->getMessage();
            save_log($remark, 2, '查询样式配置', 'admin/style/detail', [
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
     * 新增或更新商户样式配置。
     *
     * @return Response 统一 JSON 响应。
     */
    public function saveOp(): Response
    {
        save_log('保存样式配置请求日志', 1, '保存样式配置', 'admin/style/save', [
            'request_data' => $this->requestData,
        ]);

        try {
            // 操作人优先取项目鉴权注入值，未接入时用验签 app_id 保证审计可追溯。
            $operator = get_operator();
            if ($operator === '') {
                $operator = (string) ($this->requestData['app_id'] ?? 'system');
            }

            $result = $this->styleService->save($this->requestData, $operator);

            save_log('保存样式配置执行完成日志', 1, '保存样式配置', 'admin/style/save', [
                'response_data' => $result,
            ]);
        } catch (\Exception $e) {
            $remark = '保存样式配置失败，原因：' . $e->getMessage();
            save_log($remark, 2, '保存样式配置', 'admin/style/save', [
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
