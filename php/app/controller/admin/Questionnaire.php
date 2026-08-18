<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\admin\QuestionnaireService;
use think\App;
use think\Response;

/**
 * B 端问卷配置控制器，对外提供问卷配置的查询和保存入口。
 */
class Questionnaire extends BaseController
{
    /**
     * 通过容器注入问卷配置业务服务。
     *
     * @param App                 $app                 ThinkPHP 应用实例。
     * @param QuestionnaireService $questionnaireService 问卷配置业务服务。
     */
    public function __construct(
        App $app,
        private QuestionnaireService $questionnaireService
    ) {
        parent::__construct($app);
    }

    /**
     * 查询商户问卷配置详情。
     *
     * @return Response 统一 JSON 响应。
     */
    public function detailOp(): Response
    {
        save_log('查询问卷配置请求日志', 1, '查询问卷配置', 'admin/questionnaire/detail', [
            'request_data' => $this->requestData,
        ]);

        try {
            $merchantId = (int) ($this->requestData['merchant_id'] ?? 0);
            // 详情必须指定商户，防止误读其他商户配置。
            if ($merchantId <= 0) {
                exception('缺少参数 merchant_id');
            }

            $result = $this->questionnaireService->getDetail($merchantId);

            save_log('查询问卷配置执行完成日志', 1, '查询问卷配置', 'admin/questionnaire/detail', [
                'response_data' => $result,
            ]);
        } catch (\Exception $e) {
            $remark = '查询问卷配置失败，原因：' . $e->getMessage();
            save_log($remark, 2, '查询问卷配置', 'admin/questionnaire/detail', [
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
     * 新增或更新商户问卷配置。
     *
     * @return Response 统一 JSON 响应。
     */
    public function saveOp(): Response
    {
        save_log('保存问卷配置请求日志', 1, '保存问卷配置', 'admin/questionnaire/save', [
            'request_data' => $this->requestData,
        ]);

        try {
            // 操作人优先取项目鉴权注入值，未接入时用验签 app_id 保证审计可追溯。
            $operator = get_operator();
            if ($operator === '') {
                $operator = (string) ($this->requestData['app_id'] ?? 'system');
            }

            $result = $this->questionnaireService->save($this->requestData, $operator);

            save_log('保存问卷配置执行完成日志', 1, '保存问卷配置', 'admin/questionnaire/save', [
                'response_data' => $result,
            ]);
        } catch (\Exception $e) {
            $remark = '保存问卷配置失败，原因：' . $e->getMessage();
            save_log($remark, 2, '保存问卷配置', 'admin/questionnaire/save', [
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
