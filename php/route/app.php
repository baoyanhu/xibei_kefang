<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
use think\facade\Route;

// ==================== Admin 分组（B 端）====================
Route::group('admin', function () {
    // B 端门店列表示例（POST /admin/store/list）
    // ⚠️ B 端路由必须挂 AdminSign 验签中间件（§1.12 入站验签铁律）
    Route::post('store/list', \app\controller\admin\Store::class . '/listOp')
        ->middleware(\app\middleware\AdminSign::class);

    // B 端基础样式配置：详情与保存均执行统一入站验签。
    Route::post('style/detail', \app\controller\admin\Style::class . '/detailOp')
        ->middleware(\app\middleware\AdminSign::class);
    Route::post('style/save', \app\controller\admin\Style::class . '/saveOp')
        ->middleware(\app\middleware\AdminSign::class);

    // B 端基础问卷配置：详情与保存均执行统一入站验签。
    Route::post('questionnaire/detail', \app\controller\admin\Questionnaire::class . '/detailOp')
        ->middleware(\app\middleware\AdminSign::class);
    Route::post('questionnaire/save', \app\controller\admin\Questionnaire::class . '/saveOp')
        ->middleware(\app\middleware\AdminSign::class);

    // B 端基础菜品配置：详情与保存均执行统一入站验签。
    Route::post('dish/detail', \app\controller\admin\Dish::class . '/detailOp')
        ->middleware(\app\middleware\AdminSign::class);
    Route::post('dish/save', \app\controller\admin\Dish::class . '/saveOp')
        ->middleware(\app\middleware\AdminSign::class);
});

// ==================== API 分组（C 端）====================
// 全分组挂 ApiAuth 公共鉴权（§5.12）：按 mch_id+employee_code 获取 RMS 门店权限，
// store_id 集合经 BaseController 公共参数 $this->authStoreIds 提供给所有方法
Route::group('api', function () {
    // C 端门店列表示例（POST /api/store/list）
    // ⚠️ C 端路由禁挂 AdminSign（§1.12.0，C 端走自有鉴权 ApiAuth）
    Route::post('store/list', \app\controller\api\Store::class . '/listOp');
})->middleware(\app\middleware\ApiAuth::class);
