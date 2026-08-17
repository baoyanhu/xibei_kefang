<?php
declare(strict_types=1);

namespace app;

use think\App;
use think\exception\ValidateException;
use think\Response;
use think\Validate;

/**
 * 控制器基础类
 */
abstract class BaseController
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

    /**
     * 当前请求参数
     * @var array
     */
    protected array $requestData = [];

    /**
     * C 端公共鉴权参数（ApiAuth 中间件获取的 RMS 权限：store_id 逗号分隔集合）
     * 业务方法直接 explode(',', $this->authStoreIds) 取门店数组；未挂中间件的端保持空串
     * @var string
     */
    protected string $authStoreIds = '';

    /**
     * 构造方法
     * @access public
     * @param  App  $app  应用对象
     */
    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $this->app->request;
        $this->requestData = $this->request->param();

        $this->authStoreIds = (string) ($this->request->authStoreIds ?? '');

        // C 端公共参数注入：所有方法默认携带 store_code = 权限门店集合（请求已显式传值时以请求为准）
        if ($this->authStoreIds !== '' && trim((string) ($this->requestData['store_code'] ?? '')) === '') {
            $this->requestData['store_code'] = $this->authStoreIds;
        }

        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {
    }

    /**
     * 统一 JSON 返回（§1.3 响应契约）
     *
     * @param int    $code    响应码（成功 0，业务失败 250）
     * @param string $message 提示信息
     * @param mixed  $data    响应数据
     * @return Response
     */
    protected function returnJson(int $code = 0, string $message = '成功', mixed $data = []): Response
    {
        return Response::create([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], 'json');
    }

    /**
     * 验证数据
     * @access protected
     * @param  array        $data     数据
     * @param  string|array $validate 验证器名或者验证规则数组
     * @param  array        $message  提示信息
     * @param  bool         $batch    是否批量验证
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate(array $data, string|array $validate, array $message = [], bool $batch = false)
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                [$validate, $scene] = explode('.', $validate);
            }
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v     = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        // 是否批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }
}
