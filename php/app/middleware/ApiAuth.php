<?php
declare(strict_types=1);

namespace app\middleware;

use app\service\api\AuthService;
use think\Request;
use think\Response;

/**
 * C 端公共鉴权中间件（挂 api 路由分组）
 * 任何 C 端方法执行前：按 mch_id + employee_code 获取 RMS 权限（Redis 缓存优先），
 * store_id 集合挂到 Request 中间传递数据（$request->authStoreIds），
 * BaseController 构造器统一读取为 $this->authStoreIds 并默认注入 requestData['store_code']
 *
 * store_code 权限规则：
 * - 业务未传 store_code → BaseController 默认注入权限门店集合（不在此校验）
 * - 业务显式传了 store_code → 此处校验必须在权限集合内（支持逗号分隔多选，逐个校验），否则 250 拦截
 *
 * 契约与 AdminSign 区别：C 端走业务码 250 + code/message/data 三字段（§1.3）
 */
class ApiAuth
{
    public function __construct(
        private AuthService $authService,
    ) {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        // ① 取参：C 端全 POST，mch_id / employee_code 必传
        $mchId        = trim((string) $request->param('mch_id', ''));
        $employeeCode = trim((string) $request->param('employee_code', ''));
        if ($mchId === '' || $employeeCode === '') {
            return $this->reject('缺少参数 mch_id / employee_code');
        }

        // ② 获取权限（Redis 缓存优先，未命中调 RMS；失败抛业务异常，此处统一拦截）
        try {
            $storeIds = $this->authService->getAuth($mchId, $employeeCode);
        } catch (\Exception $e) {
            return $this->reject($e->getMessage());
        }

        // ③ 显式传了 store_code 则校验权限（支持逗号分隔多选，逐个必须在权限集合内）
        $storeCode = trim((string) $request->param('store_code', ''));
        if ($storeCode !== '') {
            $allowed = explode(',', $storeIds);
            $wanted  = array_filter(explode(',', $storeCode), fn($id) => trim((string)$id) !== '');
            $illegal = array_diff($wanted, $allowed);
            if (!empty($illegal)) {
                return $this->reject('store_code 无权限：' . implode(',', array_values($illegal)));
            }
        }

        // ④ 权限挂载公共参数（Request 中间传递数据，BaseController 构造器读取并默认注入 store_code）
        $request->authStoreIds = $storeIds;

        // ⑤ 入口日志（鉴权结果，便于审计）
        save_log('C端鉴权中间件：权限获取成功', 1, 'C端公共鉴权', 'api/' . $request->pathinfo(), [
            'request_data'  => ['mch_id' => $mchId, 'employee_code' => $employeeCode, 'store_code' => $storeCode],
            'response_data' => ['store_count' => count(explode(',', $storeIds))],
        ]);

        return $next($request);
    }

    /**
     * 拦截响应（C 端契约：code 250 + code/message/data）
     */
    private function reject(string $msg): Response
    {
        return Response::create(['code' => 250, 'message' => $msg, 'data' => []], 'json');
    }
}
