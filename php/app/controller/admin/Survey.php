<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\admin\SurveyService;
use think\App;
use think\Response;

/**
 * B 端问卷列表控制器，对外提供问卷的查询、编辑、复制、启禁用与删除入口。
 */
class Survey extends BaseController
{
    /**
     * 通过容器注入问卷业务服务。
     *
     * @param App          $app          ThinkPHP 应用实例。
     * @param SurveyService $surveyService 问卷业务服务。
     */
    public function __construct(
        App $app,
        private SurveyService $surveyService
    ) {
        parent::__construct($app);
    }

    /**
     * 分页查询问卷列表。
     *
     * @return Response 统一 JSON 响应。
     */
    public function listOp(): Response
    {
        save_log('查询问卷列表请求日志', 1, '查询问卷列表', 'admin/survey/list', [
            'request_data' => $this->requestData,
        ]);

        try {
            $result = $this->surveyService->getList($this->requestData);

            save_log('查询问卷列表执行完成日志', 1, '查询问卷列表', 'admin/survey/list', [
                'response_data' => ['count' => $result['count']],
            ]);
        } catch (\Exception $e) {
            $remark = '查询问卷列表失败，原因：' . $e->getMessage();
            save_log($remark, 2, '查询问卷列表', 'admin/survey/list', [
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
     * 查询问卷详情。
     *
     * @return Response 统一 JSON 响应。
     */
    public function detailOp(): Response
    {
        save_log('查询问卷详情请求日志', 1, '查询问卷详情', 'admin/survey/detail', [
            'request_data' => $this->requestData,
        ]);

        try {
            $result = $this->surveyService->getDetail($this->requestData);

            save_log('查询问卷详情执行完成日志', 1, '查询问卷详情', 'admin/survey/detail', [
                'response_data' => $result,
            ]);
        } catch (\Exception $e) {
            $remark = '查询问卷详情失败，原因：' . $e->getMessage();
            save_log($remark, 2, '查询问卷详情', 'admin/survey/detail', [
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
     * 新增或整卷编辑问卷。
     *
     * @return Response 统一 JSON 响应。
     */
    public function saveOp(): Response
    {
        save_log('保存问卷请求日志', 1, '保存问卷', 'admin/survey/save', [
            'request_data' => $this->requestData,
        ]);

        try {
            $operator = $this->resolveOperator();
            $result = $this->surveyService->save($this->requestData, $operator);

            save_log('保存问卷执行完成日志', 1, '保存问卷', 'admin/survey/save', [
                'response_data' => $result,
            ]);
        } catch (\Exception $e) {
            $remark = '保存问卷失败，原因：' . $e->getMessage();
            save_log($remark, 2, '保存问卷', 'admin/survey/save', [
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
     * 复制问卷。
     *
     * @return Response 统一 JSON 响应。
     */
    public function copyOp(): Response
    {
        save_log('复制问卷请求日志', 1, '复制问卷', 'admin/survey/copy', [
            'request_data' => $this->requestData,
        ]);

        try {
            $operator = $this->resolveOperator();
            $result = $this->surveyService->copy($this->requestData, $operator);

            save_log('复制问卷执行完成日志', 1, '复制问卷', 'admin/survey/copy', [
                'response_data' => $result,
            ]);
        } catch (\Exception $e) {
            $remark = '复制问卷失败，原因：' . $e->getMessage();
            save_log($remark, 2, '复制问卷', 'admin/survey/copy', [
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
     * 启用或禁用问卷。
     *
     * @return Response 统一 JSON 响应。
     */
    public function statusOp(): Response
    {
        save_log('问卷启禁用请求日志', 1, '问卷启禁用', 'admin/survey/status', [
            'request_data' => $this->requestData,
        ]);

        try {
            $operator = $this->resolveOperator();
            $result = $this->surveyService->setStatus($this->requestData, $operator);

            save_log('问卷启禁用执行完成日志', 1, '问卷启禁用', 'admin/survey/status', [
                'response_data' => $result,
            ]);
        } catch (\Exception $e) {
            $remark = '问卷启禁用失败，原因：' . $e->getMessage();
            save_log($remark, 2, '问卷启禁用', 'admin/survey/status', [
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
     * 删除问卷（软删除）。
     *
     * @return Response 统一 JSON 响应。
     */
    public function deleteOp(): Response
    {
        save_log('删除问卷请求日志', 1, '删除问卷', 'admin/survey/delete', [
            'request_data' => $this->requestData,
        ]);

        try {
            $operator = $this->resolveOperator();
            $result = $this->surveyService->delete($this->requestData, $operator);

            save_log('删除问卷执行完成日志', 1, '删除问卷', 'admin/survey/delete', [
                'response_data' => $result,
            ]);
        } catch (\Exception $e) {
            $remark = '删除问卷失败，原因：' . $e->getMessage();
            save_log($remark, 2, '删除问卷', 'admin/survey/delete', [
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
     * 操作人优先取项目鉴权注入值，未接入时用验签 app_id 保证审计可追溯。
     *
     * @return string 操作人标识。
     */
    private function resolveOperator(): string
    {
        $operator = get_operator();
        if ($operator !== '') {
            return $operator;
        }

        return (string) ($this->requestData['app_id'] ?? 'system');
    }
}
