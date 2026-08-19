<?php
declare(strict_types=1);

namespace app\service\admin;

use app\common\model\AuditLogModel;
use app\common\model\MerchantModel;
use app\common\model\NpsScoreImageModel;
use app\common\model\QuestionGroupModel;
use app\common\model\QuestionModel;
use app\common\model\QuestionnaireModel;
use app\common\model\QuestionOptionModel;
use think\facade\Db;

/**
 * B 端问卷列表业务服务，覆盖问卷的增删改查、复制与同商户启用互斥。
 */
class SurveyService
{
    /** 单份问卷允许的题目数量上限。 */
    protected const MAX_QUESTIONS = 50;

    /** 问卷名称长度上限（与 B 端原型一致）。 */
    protected const NAME_MAX_LENGTH = 40;

    /** 题目标题长度上限（与 B 端原型一致）。 */
    protected const TITLE_MAX_LENGTH = 40;

    /** 选项文本长度上限（与 B 端原型一致）。 */
    protected const OPTION_MAX_LENGTH = 20;

    /** NPS 分值图标最大字符数（500KB dataURL 或 URL）。 */
    protected const ICON_MAX_LENGTH = 512000;

    /** 默认题目组名称，B 端编辑器无分组概念，题目统一挂默认组。 */
    protected const DEFAULT_GROUP_NAME = '默认分组';

    /** 复制问卷的名称后缀。 */
    protected const COPY_SUFFIX = ' · 副本';

    /**
     * 分页查询商户问卷列表。
     *
     * @param array $data 已解密的业务请求参数。
     * @return array {count: int, list: array}。
     */
    public function getList(array $data): array
    {
        $merchantId = (int) ($data['merchant_id'] ?? 0);
        $this->assertMerchantExists($merchantId);

        $page = max(1, (int) ($data['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($data['page_size'] ?? 10)));

        $query = Db::table('questionnaires')
            ->alias('q')
            ->leftJoin('users u', 'u.id = q.updated_by')
            ->field('q.id, q.name, q.status, q.update_time, u.name AS operator')
            ->where('q.merchant_id', $merchantId)
            ->whereNull('q.delete_time');

        $name = trim((string) ($data['name'] ?? ''));
        if ($name !== '') {
            $query->whereLike('q.name', '%' . $name . '%');
        }
        $startTime = trim((string) ($data['update_time_start'] ?? ''));
        if ($startTime !== '') {
            $query->where('q.update_time', '>=', $startTime . ' 00:00:00');
        }
        $endTime = trim((string) ($data['update_time_end'] ?? ''));
        if ($endTime !== '') {
            $query->where('q.update_time', '<=', $endTime . ' 23:59:59');
        }
        $operator = trim((string) ($data['operator'] ?? ''));
        if ($operator !== '') {
            $query->whereLike('u.name', '%' . $operator . '%');
        }

        $paginator = $query->order('q.update_time', 'desc')->paginate([
            'page'      => $page,
            'list_rows' => $pageSize,
        ]);
        $list = $paginator->toArray()['data'] ?? [];

        $counts = $this->fetchQuestionCounts(array_map('intval', array_column($list, 'id')));
        foreach ($list as &$row) {
            $row['id'] = (int) $row['id'];
            $row['operator'] = ($row['operator'] ?? '') !== '' ? $row['operator'] : '--';
            $row['question_count'] = $counts[$row['id']] ?? 0;
        }
        unset($row);

        return [
            'count' => $paginator->total(),
            'list'  => $list,
        ];
    }

    /**
     * 查询问卷详情，详情弹窗与编辑页回显共用。
     *
     * @param array $data 已解密的业务请求参数。
     * @return array 问卷详情（含题目、选项、分值图标、跳题规则）。
     */
    public function getDetail(array $data): array
    {
        $merchantId = (int) ($data['merchant_id'] ?? 0);
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            exception('缺少参数 id');
        }
        $this->assertMerchantExists($merchantId);

        $survey = $this->assertSurveyExists($merchantId, $id, false);

        return $this->assembleDetail($survey);
    }

    /**
     * 新增或整卷编辑问卷，题目/选项/分值图标全量替换。
     *
     * @param array  $data     已解密的业务请求参数。
     * @param string $operator 验签调用方或当前操作人标识。
     * @return array 新增或更新后的问卷 ID。
     */
    public function save(array $data, string $operator): array
    {
        $input = $this->validateSaveInput($data);

        return Db::transaction(function () use ($input, $operator): array {
            $this->assertMerchantExists($input['merchant_id']);

            if ($input['id'] > 0) {
                $survey = $this->assertSurveyExists($input['merchant_id'], $input['id'], true);
                $before = $this->surveySummary($survey);
                $groupId = $this->ensureDefaultGroup((int) $survey->id);
            } else {
                $survey = new QuestionnaireModel();
                $survey->save([
                    'merchant_id'  => $input['merchant_id'],
                    'name'         => $input['name'],
                    // 编辑器不维护触发模式，建卷默认立即推送，后续由基础配置侧接管。
                    'trigger_mode' => QuestionnaireModel::TRIGGER_IMMEDIATE,
                    'status'       => QuestionnaireModel::STATUS_DRAFT,
                    'updated_by'   => $input['operator_id'],
                ]);
                $groupId = $this->ensureDefaultGroup((int) $survey->id);
                $before = null;
            }

            // 编辑器无分组概念，保留默认组外的多余题目组一并清理。
            QuestionGroupModel::where('questionnaire_id', $survey->id)
                ->where('id', '<>', $groupId)
                ->delete();
            $this->replaceQuestions((int) $survey->id, $groupId, $input['questions']);

            $survey->save([
                'name'       => $input['name'],
                'updated_by' => $input['operator_id'],
            ]);

            $this->writeAudit($operator, AuditLogModel::ACTION_SAVE_SURVEY, (int) $survey->id, [
                'merchant_id' => $input['merchant_id'],
                'before'      => $before,
                'after'       => [
                    'name'           => $input['name'],
                    'status'         => (string) $survey->status,
                    'question_count' => count($input['questions']),
                ],
            ]);

            return ['id' => (int) $survey->id];
        });
    }

    /**
     * 深拷贝问卷，副本为草稿态并改写跳题目标到新题目 ID。
     *
     * @param array  $data     已解密的业务请求参数。
     * @param string $operator 验签调用方或当前操作人标识。
     * @return array 新问卷 ID。
     */
    public function copy(array $data, string $operator): array
    {
        $merchantId = (int) ($data['merchant_id'] ?? 0);
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            exception('缺少参数 id');
        }
        $operatorId = $this->resolveOperatorId($data);

        return Db::transaction(function () use ($merchantId, $id, $operatorId, $operator): array {
            $this->assertMerchantExists($merchantId);
            $source = $this->assertSurveyExists($merchantId, $id, true);

            $new = new QuestionnaireModel();
            $new->save([
                'merchant_id'       => $source->merchant_id,
                'style_id'          => $source->style_id,
                'name'              => $source->name . self::COPY_SUFFIX,
                'trigger_mode'      => $source->trigger_mode,
                'delay_minutes'     => $source->delay_minutes,
                'validity_days'     => $source->validity_days,
                'fallback_enabled'  => $source->fallback_enabled,
                'dish_link_enabled' => $source->dish_link_enabled,
                'status'            => QuestionnaireModel::STATUS_DRAFT,
                'active_flag'       => 0,
                'updated_by'        => $operatorId,
            ]);

            $this->copyChildren($source, $new);

            $this->writeAudit($operator, AuditLogModel::ACTION_COPY_SURVEY, (int) $new->id, [
                'merchant_id' => $merchantId,
                'source_id'   => $id,
                'new_id'      => (int) $new->id,
                'name'        => (string) $new->name,
            ]);

            return ['id' => (int) $new->id];
        });
    }

    /**
     * 启用或禁用问卷，启用时同商户其他启用问卷自动禁用。
     *
     * @param array  $data     已解密的业务请求参数。
     * @param string $operator 验签调用方或当前操作人标识。
     * @return array 问卷 ID 与更新后的状态。
     */
    public function setStatus(array $data, string $operator): array
    {
        $merchantId = (int) ($data['merchant_id'] ?? 0);
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            exception('缺少参数 id');
        }
        $action = (string) ($data['action'] ?? '');
        if (!in_array($action, ['enable', 'disable'], true)) {
            exception('action 仅支持 enable / disable');
        }
        $operatorId = $this->resolveOperatorId($data);

        return Db::transaction(function () use ($merchantId, $id, $action, $operatorId, $operator): array {
            $this->assertMerchantExists($merchantId);
            $survey = $this->assertSurveyExists($merchantId, $id, true);
            $before = ['status' => (string) $survey->status, 'active_flag' => (int) $survey->active_flag];

            if ($action === 'enable') {
                // 同商户启用互斥：其他启用中的问卷先全部禁用再启用自身。
                QuestionnaireModel::where('merchant_id', $merchantId)
                    ->where('id', '<>', $id)
                    ->where('active_flag', 1)
                    ->update(['active_flag' => 0, 'status' => QuestionnaireModel::STATUS_DISABLED]);
                $survey->save([
                    'active_flag' => 1,
                    'status'      => QuestionnaireModel::STATUS_ENABLED,
                    'updated_by'  => $operatorId,
                ]);
            } else {
                if ((int) $survey->active_flag !== 1) {
                    exception('该问卷未启用，无需禁用');
                }
                $survey->save([
                    'active_flag' => 0,
                    'status'      => QuestionnaireModel::STATUS_DISABLED,
                    'updated_by'  => $operatorId,
                ]);
            }

            $this->writeAudit($operator, AuditLogModel::ACTION_SURVEY_STATUS, $id, [
                'merchant_id' => $merchantId,
                'action'      => $action,
                'before'      => $before,
                'after'       => ['status' => (string) $survey->status, 'active_flag' => (int) $survey->active_flag],
            ]);

            return ['id' => $id, 'status' => (string) $survey->status];
        });
    }

    /**
     * 软删除问卷，启用中的问卷必须先禁用。
     *
     * @param array  $data     已解密的业务请求参数。
     * @param string $operator 验签调用方或当前操作人标识。
     * @return array 被删除的问卷 ID。
     */
    public function delete(array $data, string $operator): array
    {
        $merchantId = (int) ($data['merchant_id'] ?? 0);
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            exception('缺少参数 id');
        }

        return Db::transaction(function () use ($merchantId, $id, $operator): array {
            $this->assertMerchantExists($merchantId);
            $survey = $this->assertSurveyExists($merchantId, $id, true);

            if ((int) $survey->active_flag === 1) {
                exception('启用中的问卷不能删除，请先禁用');
            }

            $name = (string) $survey->name;
            $survey->delete();

            $this->writeAudit($operator, AuditLogModel::ACTION_DELETE_SURVEY, $id, [
                'merchant_id' => $merchantId,
                'name'        => $name,
            ]);

            return ['id' => $id];
        });
    }

    /**
     * 校验并规整保存入参。
     *
     * @param array $data 原始业务请求参数。
     * @return array 规整后的入库参数。
     */
    private function validateSaveInput(array $data): array
    {
        $merchantId = (int) ($data['merchant_id'] ?? 0);
        if ($merchantId <= 0) {
            exception('缺少参数 merchant_id');
        }

        $id = (int) ($data['id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            exception('问卷名称不能为空');
        }
        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            exception('问卷名称不能超过 ' . self::NAME_MAX_LENGTH . ' 字');
        }

        $rawQuestions = $data['questions'] ?? null;
        if (!is_array($rawQuestions) || $rawQuestions === []) {
            exception('至少需要一道题目');
        }
        if (count($rawQuestions) > self::MAX_QUESTIONS) {
            exception('题目数量不能超过 ' . self::MAX_QUESTIONS . ' 道');
        }

        $total = count($rawQuestions);
        $questions = [];
        $index = 0;
        foreach ($rawQuestions as $rawQuestion) {
            $index++;
            $questions[] = $this->normalizeQuestion(is_array($rawQuestion) ? $rawQuestion : [], $index, $total);
        }

        return [
            'merchant_id' => $merchantId,
            'id'          => $id,
            'name'        => $name,
            'operator_id' => $this->resolveOperatorId($data),
            'questions'   => $questions,
        ];
    }

    /**
     * 规整单道题目入参，按题型校验配置、选项与跳题规则。
     *
     * @param array $raw    题目原始参数。
     * @param int   $index  题目序号（从 1 起）。
     * @param int   $total  题目总数。
     * @return array 规整后的题目。
     */
    private function normalizeQuestion(array $raw, int $index, int $total): array
    {
        $type = (int) ($raw['type'] ?? 0);
        if (!isset(QuestionModel::$TYPE_TEXTS[$type])) {
            exception('第 ' . $index . ' 题题型非法');
        }

        $title = trim((string) ($raw['title'] ?? ''));
        if ($title === '') {
            exception('第 ' . $index . ' 题标题不能为空');
        }
        if (mb_strlen($title) > self::TITLE_MAX_LENGTH) {
            exception('第 ' . $index . ' 题标题不能超过 ' . self::TITLE_MAX_LENGTH . ' 字');
        }

        $required = (int) ($raw['required'] ?? 1) === 0 ? 0 : 1;
        $config = $this->normalizeConfig($type, is_array($raw['config'] ?? null) ? $raw['config'] : [], $index);

        $options = [];
        if ($type === QuestionModel::TYPE_SINGLE || $type === QuestionModel::TYPE_MULTI) {
            $options = $this->normalizeOptions(is_array($raw['options'] ?? null) ? $raw['options'] : [], $index);
            // options_extras 与选项一一对应，仅选择题落库（与存量 config 契约一致）。
            $config['options_extras'] = array_map(static fn (array $option): ?array => $option['extra'], $options);
        }

        $npsIcons = [];
        if ($type === QuestionModel::TYPE_NPS) {
            $npsIcons = $this->normalizeNpsIcons($raw['nps_icons'] ?? [], $config['upper_bound'], $index);
        }

        if ($type === QuestionModel::TYPE_TEXT) {
            $config['required'] = $required === 1;
        }

        $maxPosition = 0;
        if ($type === QuestionModel::TYPE_SINGLE || $type === QuestionModel::TYPE_MULTI) {
            $maxPosition = count($options);
        } elseif ($type === QuestionModel::TYPE_NPS) {
            $maxPosition = $config['upper_bound'];
        } elseif ($type === QuestionModel::TYPE_DIMENSION) {
            $maxPosition = count($config['star_slots']);
        }
        $jumps = $this->normalizeJumps($raw['option_jumps'] ?? [], $maxPosition, $index, $total);

        return [
            'type'       => $type,
            'title'      => $title,
            'required'   => $required,
            'config'     => $config,
            'options'    => $options,
            'nps_icons'  => $npsIcons,
            'jumps'      => $jumps,
        ];
    }

    /**
     * 按题型规整 config 配置。
     *
     * @param int   $type  题型。
     * @param array $raw   原始配置。
     * @param int   $index 题目序号。
     * @return array 规整后的配置。
     */
    private function normalizeConfig(int $type, array $raw, int $index): array
    {
        if ($type === QuestionModel::TYPE_SINGLE || $type === QuestionModel::TYPE_MULTI) {
            // 选项型配置只有 options_extras，由选项入参推导后写入。
            return [];
        }

        if ($type === QuestionModel::TYPE_NPS) {
            $upper = (int) ($raw['upper_bound'] ?? 5);
            if ($upper < 2 || $upper > 10) {
                exception('第 ' . $index . ' 题 NPS 最高分需在 2-10 之间');
            }
            $low = (int) ($raw['nps_low_threshold'] ?? 3);
            if ($low < 1 || $low >= $upper) {
                exception('第 ' . $index . ' 题低分阈值需在 1 到最高分之间');
            }

            return [
                'upper_bound'       => $upper,
                'nps_low_threshold' => $low,
                'nps_enable_text'   => !empty($raw['nps_enable_text']),
                'nps_require_text'  => !empty($raw['nps_require_text']),
                'nps_text_limit'    => $this->clampInt($raw['nps_text_limit'] ?? 50, 1, 500),
                'nps_enable_image'  => !empty($raw['nps_enable_image']),
                'nps_require_image' => !empty($raw['nps_require_image']),
                'nps_image_limit'   => $this->clampInt($raw['nps_image_limit'] ?? 1, 1, 9),
            ];
        }

        if ($type === QuestionModel::TYPE_DIMENSION) {
            return [
                'dimensions' => $this->normalizeDimensions($raw['dimensions'] ?? [], $index),
                'star_slots' => $this->normalizeStarSlots($raw['star_slots'] ?? null),
            ];
        }

        if ($type === QuestionModel::TYPE_IMAGE) {
            return ['max_images' => $this->clampInt($raw['max_images'] ?? 1, 1, 9)];
        }

        if ($type === QuestionModel::TYPE_TEXT) {
            $textMode = (string) ($raw['text_mode'] ?? 'short');
            if (!in_array($textMode, ['short', 'long'], true)) {
                exception('第 ' . $index . ' 题文本类型仅支持 short / long');
            }

            return [
                'text_mode'     => $textMode,
                'max_chars'     => $this->clampInt($raw['max_chars'] ?? 200, 1, 500),
                'enable_image'  => !empty($raw['enable_image']),
                'image_limit'   => $this->clampInt($raw['image_limit'] ?? 3, 1, 9),
                'require_image' => !empty($raw['require_image']),
            ];
        }

        // 菜品题：评分 / 维度两种配置方式。
        $mode = (string) ($raw['dish_config_mode'] ?? 'rating');
        if (!in_array($mode, ['rating', 'dimension'], true)) {
            exception('第 ' . $index . ' 题菜品配置方式仅支持 rating / dimension');
        }
        $config = [
            'dish_config_mode' => $mode,
            'dish_skus'        => $this->normalizeDishSkus($raw['dish_skus'] ?? [], $index),
        ];
        if ($mode === 'rating') {
            $config += [
                'dish_score_max'      => $this->clampInt($raw['dish_score_max'] ?? 5, 2, 10),
                'dish_low_threshold'  => $this->clampInt($raw['dish_low_threshold'] ?? 3, 1, 9),
                'dish_enable_text'    => !empty($raw['dish_enable_text']),
                'dish_require_text'   => !empty($raw['dish_require_text']),
                'dish_text_limit'     => $this->clampInt($raw['dish_text_limit'] ?? 200, 1, 500),
                'dish_enable_image'   => !empty($raw['dish_enable_image']),
                'dish_require_image'  => !empty($raw['dish_require_image']),
                'dish_image_limit'    => $this->clampInt($raw['dish_image_limit'] ?? 3, 1, 9),
            ];
        } else {
            $config += [
                'dimensions'            => $this->normalizeDimensions($raw['dimensions'] ?? [], $index),
                'star_slots'            => $this->normalizeStarSlots($raw['star_slots'] ?? null),
                'dish_dim_low_threshold' => $this->clampInt($raw['dish_dim_low_threshold'] ?? 3, 1, 9),
                'dish_enable_text'      => !empty($raw['dish_enable_text']),
                'dish_enable_image'     => !empty($raw['dish_enable_image']),
            ];
        }

        return $config;
    }

    /**
     * 规整单选/多选选项。
     *
     * @param array $raw   选项原始参数。
     * @param int   $index 题目序号。
     * @return array 规整后的选项列表。
     */
    private function normalizeOptions(array $raw, int $index): array
    {
        if (count($raw) < 2) {
            exception('第 ' . $index . ' 题至少需要两个选项');
        }

        $options = [];
        $labels = [];
        foreach (array_values($raw) as $i => $row) {
            $position = $i + 1;
            $label = is_array($row) ? trim((string) ($row['label'] ?? '')) : trim((string) $row);
            if ($label === '') {
                exception('第 ' . $index . ' 题选项 ' . $position . ' 内容不能为空');
            }
            if (mb_strlen($label) > self::OPTION_MAX_LENGTH) {
                exception('第 ' . $index . ' 题选项不能超过 ' . self::OPTION_MAX_LENGTH . ' 字');
            }
            if (isset($labels[$label])) {
                exception('第 ' . $index . ' 题选项内容重复：' . $label);
            }
            $labels[$label] = true;

            $extra = null;
            if (is_array($row) && is_array($row['extra'] ?? null)) {
                $extra = [
                    'text'  => !empty($row['extra']['text']),
                    'image' => !empty($row['extra']['image']),
                ];
                if (!$extra['text'] && !$extra['image']) {
                    $extra = null;
                }
            }

            $options[] = ['label' => $label, 'value' => $label, 'extra' => $extra];
        }

        return $options;
    }

    /**
     * 规整 NPS 分值图标。
     *
     * @param mixed $raw         图标原始参数。
     * @param int   $upperBound  分值上限。
     * @param int   $index       题目序号。
     * @return array 规整后的图标列表。
     */
    private function normalizeNpsIcons(mixed $raw, int $upperBound, int $index): array
    {
        if (!is_array($raw) || $raw === []) {
            return [];
        }

        $icons = [];
        $seen = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $score = (int) ($row['score'] ?? 0);
            $imageUrl = trim((string) ($row['image_url'] ?? ''));
            if ($score < 1 || $score > $upperBound) {
                exception('第 ' . $index . ' 题分值图标分值 ' . $score . ' 超出范围');
            }
            if ($imageUrl === '') {
                continue;
            }
            if (strlen($imageUrl) > self::ICON_MAX_LENGTH) {
                exception('第 ' . $index . ' 题 ' . $score . ' 分图标不能超过 500KB');
            }
            if (isset($seen[$score])) {
                exception('第 ' . $index . ' 题 ' . $score . ' 分图标重复');
            }
            $seen[$score] = true;
            $icons[] = ['score' => $score, 'image_url' => $imageUrl];
        }

        return $icons;
    }

    /**
     * 规整跳题规则入参，目标统一转为 1 起的题目序号。
     *
     * @param mixed $raw        跳题原始参数（对象键为 1 起位置，或顺序数组）。
     * @param int   $maxPosition 位置上限（选项数 / 分值上限 / 星数）。
     * @param int   $index      当前题目序号。
     * @param int   $total      题目总数。
     * @return array [位置 => 'thanks'|题目序号]。
     */
    private function normalizeJumps(mixed $raw, int $maxPosition, int $index, int $total): array
    {
        if (!is_array($raw) || $raw === []) {
            return [];
        }

        $jumps = [];
        // 入参两种形态:对象键为 1 起位置({"4":3}),顺序数组按下标 +1([3] 即位置 1)。
        $isList = array_is_list($raw);
        foreach ($raw as $key => $target) {
            $position = $isList ? (int) $key + 1 : (int) $key;
            if ($position < 1 || ($maxPosition > 0 && $position > $maxPosition)) {
                exception('第 ' . $index . ' 题跳题位置 ' . $position . ' 超出范围');
            }
            if ($target === null || $target === 'none') {
                continue;
            }
            if ($target === 'thanks') {
                $jumps[$position] = 'thanks';
                continue;
            }
            $targetIndex = (int) $target;
            if ($targetIndex <= $index) {
                exception('第 ' . $index . ' 题跳题目标必须是其后的题目');
            }
            if ($targetIndex > $total) {
                exception('第 ' . $index . ' 题跳题目标超出题目总数');
            }
            $jumps[$position] = $targetIndex;
        }
        ksort($jumps);

        return $jumps;
    }

    /**
     * 规整维度名称列表。
     *
     * @param mixed $raw   维度原始参数。
     * @param int   $index 题目序号。
     * @return array 维度列表。
     */
    private function normalizeDimensions(mixed $raw, int $index): array
    {
        if (!is_array($raw) || $raw === []) {
            exception('第 ' . $index . ' 题至少需要一个评分维度');
        }

        $dimensions = [];
        foreach (array_values($raw) as $i => $row) {
            $name = is_array($row) ? trim((string) ($row['name'] ?? '')) : trim((string) $row);
            if ($name === '') {
                exception('第 ' . $index . ' 题维度 ' . ($i + 1) . ' 名称不能为空');
            }
            if (mb_strlen($name) > self::OPTION_MAX_LENGTH) {
                exception('第 ' . $index . ' 题维度名称不能超过 ' . self::OPTION_MAX_LENGTH . ' 字');
            }
            $dimensions[] = ['name' => $name];
        }

        return $dimensions;
    }

    /**
     * 规整星标配置，默认 5 星。
     *
     * @param mixed $raw 星标原始参数。
     * @return array 星标列表。
     */
    private function normalizeStarSlots(mixed $raw): array
    {
        if (!is_array($raw) || $raw === []) {
            return array_fill(0, 5, '★');
        }

        return array_values(array_map(static fn ($slot): string => (string) $slot, $raw));
    }

    /**
     * 规整菜品 SKU 列表。
     *
     * @param mixed $raw   SKU 原始参数。
     * @param int   $index 题目序号。
     * @return array SKU 列表。
     */
    private function normalizeDishSkus(mixed $raw, int $index): array
    {
        if (!is_array($raw) || $raw === []) {
            exception('第 ' . $index . ' 题至少需要选择一道菜品');
        }

        $skus = [];
        foreach (array_values($raw) as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $goodsCode = trim((string) ($row['goods_code'] ?? ''));
            $goodsName = trim((string) ($row['goods_name'] ?? ''));
            if ($goodsCode === '' || $goodsName === '') {
                exception('第 ' . $index . ' 题菜品 ' . ($i + 1) . ' 缺少商品编码或名称');
            }
            $skus[] = ['goods_code' => $goodsCode, 'goods_name' => $goodsName];
        }
        if ($skus === []) {
            exception('第 ' . $index . ' 题至少需要选择一道菜品');
        }

        return $skus;
    }

    /**
     * 全量替换问卷题目：旧题目/选项/分值图标物理删除后按入参重建。
     *
     * @param int   $surveyId  问卷 ID。
     * @param int   $groupId   默认题目组 ID。
     * @param array $questions 规整后的题目列表。
     * @return void
     */
    private function replaceQuestions(int $surveyId, int $groupId, array $questions): void
    {
        $oldGroupIds = QuestionGroupModel::where('questionnaire_id', $surveyId)->column('id');
        if ($oldGroupIds !== []) {
            $oldQuestionIds = QuestionModel::whereIn('group_id', $oldGroupIds)->column('id');
            if ($oldQuestionIds !== []) {
                QuestionOptionModel::whereIn('question_id', $oldQuestionIds)->delete();
            }
            QuestionModel::whereIn('group_id', $oldGroupIds)->delete();
        }
        NpsScoreImageModel::where('questionnaire_id', $surveyId)->delete();

        // 先插入拿新题目 ID，再按新 ID 回填跳题目标。
        $idMap = [];
        foreach ($questions as $i => $question) {
            $model = new QuestionModel();
            $model->save([
                'group_id'     => $groupId,
                'type'         => $question['type'],
                'title'        => $question['title'],
                'sort_order'   => $i + 1,
                'required'     => $question['required'],
                'config'       => $question['config'],
                'option_jumps' => [],
            ]);
            $idMap[$i + 1] = (int) $model->id;

            foreach ($question['options'] as $j => $option) {
                $optionModel = new QuestionOptionModel();
                $optionModel->save([
                    'question_id' => $model->id,
                    'label'       => $option['label'],
                    'value'       => $option['value'],
                    'sort_order'  => $j + 1,
                ]);
            }
            foreach ($question['nps_icons'] as $icon) {
                $iconModel = new NpsScoreImageModel();
                $iconModel->save([
                    'questionnaire_id' => $surveyId,
                    'question_id'      => $model->id,
                    'score'            => $icon['score'],
                    'image_url'        => $icon['image_url'],
                ]);
            }
        }

        foreach ($questions as $i => $question) {
            if ($question['jumps'] === []) {
                continue;
            }
            $stored = [];
            foreach ($question['jumps'] as $position => $target) {
                $stored[(string) $position] = $target === 'thanks' ? 'thanks' : (string) $idMap[$target];
            }
            // 静态 update 不走模型类型转换，JSON 字段需手动编码。
            QuestionModel::where('id', $idMap[$i + 1])->update([
                'option_jumps' => json_encode($stored, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    /**
     * 深拷贝问卷子表数据并改写跳题目标。
     *
     * @param QuestionnaireModel $source 源问卷。
     * @param QuestionnaireModel $new    新问卷。
     * @return void
     */
    private function copyChildren(QuestionnaireModel $source, QuestionnaireModel $new): void
    {
        $groups = QuestionGroupModel::where('questionnaire_id', $source->id)
            ->order('sort_order', 'asc')
            ->select();

        $qidMap = [];
        foreach ($groups as $group) {
            $newGroup = new QuestionGroupModel();
            $newGroup->save([
                'questionnaire_id' => $new->id,
                'name'             => $group->name,
                'sort_order'       => $group->sort_order,
                'display_limit'    => $group->display_limit,
            ]);

            $oldQuestions = QuestionModel::where('group_id', $group->id)
                ->order('sort_order', 'asc')
                ->select();
            foreach ($oldQuestions as $question) {
                $newQuestion = new QuestionModel();
                $newQuestion->save([
                    'group_id'     => $newGroup->id,
                    'type'         => $question->type,
                    'title'        => $question->title,
                    'sort_order'   => $question->sort_order,
                    'required'     => $question->required,
                    'config'       => $question->config,
                    'option_jumps' => [],
                ]);
                $qidMap[(string) $question->id] = (string) $newQuestion->id;

                $oldOptions = QuestionOptionModel::where('question_id', $question->id)
                    ->order('sort_order', 'asc')
                    ->select();
                foreach ($oldOptions as $option) {
                    $newOption = new QuestionOptionModel();
                    $newOption->save([
                        'question_id' => $newQuestion->id,
                        'label'       => $option->label,
                        'value'       => $option->value,
                        'sort_order'  => $option->sort_order,
                    ]);
                }
            }
        }

        // 分值图标按新问卷 / 新题目 ID 重建。
        $oldIcons = NpsScoreImageModel::where('questionnaire_id', $source->id)->select();
        foreach ($oldIcons as $icon) {
            $mappedQuestionId = $qidMap[(string) $icon->question_id] ?? null;
            if ($mappedQuestionId === null) {
                continue;
            }
            $newIcon = new NpsScoreImageModel();
            $newIcon->save([
                'questionnaire_id' => $new->id,
                'question_id'      => (int) $mappedQuestionId,
                'score'            => $icon->score,
                'image_url'        => $icon->image_url,
            ]);
        }

        // 跳题目标按新旧题目 ID 映射改写，none / thanks 原样保留。
        foreach ($qidMap as $oldQid => $newQid) {
            $jumps = QuestionModel::where('id', (int) $oldQid)->value('option_jumps');
            $jumps = is_array($jumps) ? $jumps : (json_decode((string) $jumps, true) ?: []);
            if ($jumps === []) {
                continue;
            }
            $rewritten = [];
            foreach ($jumps as $position => $target) {
                $targetKey = (string) $target;
                $rewritten[(string) $position] = $qidMap[$targetKey] ?? $target;
            }
            QuestionModel::where('id', (int) $newQid)->update([
                'option_jumps' => json_encode($rewritten, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    /**
     * 确保问卷存在默认题目组，返回组 ID。
     *
     * @param int $surveyId 问卷 ID。
     * @return int 默认题目组 ID。
     */
    private function ensureDefaultGroup(int $surveyId): int
    {
        $group = QuestionGroupModel::where('questionnaire_id', $surveyId)
            ->order('sort_order', 'asc')
            ->find();
        if ($group) {
            return (int) $group->id;
        }

        $group = new QuestionGroupModel();
        $group->save([
            'questionnaire_id' => $surveyId,
            'name'             => self::DEFAULT_GROUP_NAME,
            'sort_order'       => 1,
            'display_limit'    => 5,
        ]);

        return (int) $group->id;
    }

    /**
     * 组装问卷详情返回结构，跳题目标转为题目序号。
     *
     * @param QuestionnaireModel $survey 问卷模型。
     * @return array 详情结构。
     */
    private function assembleDetail(QuestionnaireModel $survey): array
    {
        $operator = '--';
        if ($survey->updated_by) {
            $operatorName = Db::table('users')->where('id', (int) $survey->updated_by)->value('name');
            $operator = ($operatorName !== null && $operatorName !== '') ? (string) $operatorName : '--';
        }

        $questions = $this->fetchQuestions((int) $survey->id);
        $indexByQid = [];
        foreach ($questions as $i => $question) {
            $indexByQid[(string) $question['id']] = $i + 1;
        }
        foreach ($questions as &$question) {
            $question['option_jumps'] = $this->jumpsToOutput($question['option_jumps'], $indexByQid);
        }
        unset($question);

        return [
            'id'              => (int) $survey->id,
            'merchant_id'     => (int) $survey->merchant_id,
            'name'            => (string) $survey->name,
            'status'          => (string) $survey->status,
            'active_flag'     => (int) $survey->active_flag,
            'question_count'  => count($questions),
            'update_time'     => (string) $survey->update_time,
            'operator'        => $operator,
            'questions'       => $questions,
        ];
    }

    /**
     * 拉取问卷题目及选项、分值图标。
     *
     * @param int $surveyId 问卷 ID。
     * @return array 题目列表。
     */
    private function fetchQuestions(int $surveyId): array
    {
        $groupIds = QuestionGroupModel::where('questionnaire_id', $surveyId)
            ->order('sort_order', 'asc')
            ->column('id');
        if ($groupIds === []) {
            return [];
        }

        $questions = QuestionModel::whereIn('group_id', $groupIds)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
        if ($questions === []) {
            return [];
        }

        $questionIds = array_map('intval', array_column($questions, 'id'));
        $optionsByQuestion = [];
        foreach (QuestionOptionModel::whereIn('question_id', $questionIds)->order('sort_order', 'asc')->select() as $option) {
            $optionsByQuestion[(int) $option->question_id][] = [
                'id'         => (int) $option->id,
                'label'      => (string) $option->label,
                'value'      => (string) $option->value,
                'sort_order' => (int) $option->sort_order,
            ];
        }
        $iconsByQuestion = [];
        foreach (NpsScoreImageModel::whereIn('question_id', $questionIds)->select() as $icon) {
            $iconsByQuestion[(int) $icon->question_id][] = [
                'score'     => (int) $icon->score,
                'image_url' => (string) $icon->image_url,
            ];
        }

        foreach ($questions as &$question) {
            $question['id'] = (int) $question['id'];
            $question['group_id'] = (int) $question['group_id'];
            $question['type'] = (int) $question['type'];
            $question['sort_order'] = (int) $question['sort_order'];
            $question['required'] = (int) $question['required'];
            $question['options'] = $optionsByQuestion[$question['id']] ?? [];
            $question['nps_icons'] = $iconsByQuestion[$question['id']] ?? [];
            unset($question['create_time'], $question['update_time']);
        }
        unset($question);

        return $questions;
    }

    /**
     * 存储态跳题规则转为输出态（题目 ID → 题目序号）。
     *
     * @param mixed $jumps      存储态跳题规则。
     * @param array $indexByQid 题目 ID 到序号的映射。
     * @return array 输出态跳题规则。
     */
    private function jumpsToOutput(mixed $jumps, array $indexByQid): array
    {
        $jumps = is_array($jumps) ? $jumps : (json_decode((string) $jumps, true) ?: []);
        if ($jumps === []) {
            return [];
        }

        $result = [];
        foreach ($jumps as $position => $target) {
            if ($target === null) {
                continue;
            }
            if ($target === 'none' || $target === 'thanks') {
                $result[(string) $position] = $target;
                continue;
            }
            // 悬空目标按继续下一题处理，避免 C 端读到无效跳转。
            $result[(string) $position] = $indexByQid[(string) $target] ?? 'none';
        }
        ksort($result);

        return $result;
    }

    /**
     * 查询一批问卷的题目数。
     *
     * @param array $surveyIds 问卷 ID 列表。
     * @return array [问卷 ID => 题目数]。
     */
    private function fetchQuestionCounts(array $surveyIds): array
    {
        if ($surveyIds === []) {
            return [];
        }

        $rows = Db::table('questions')
            ->alias('q')
            ->join('question_groups g', 'g.id = q.group_id')
            ->whereIn('g.questionnaire_id', $surveyIds)
            ->group('g.questionnaire_id')
            ->column('COUNT(*) AS cnt', 'g.questionnaire_id');

        $counts = [];
        foreach ($rows as $surveyId => $cnt) {
            $counts[(int) $surveyId] = (int) $cnt;
        }

        return $counts;
    }

    /**
     * 问卷关键字段摘要，用于保存审计的 before。
     *
     * @param QuestionnaireModel $survey 问卷模型。
     * @return array 摘要。
     */
    private function surveySummary(QuestionnaireModel $survey): array
    {
        return [
            'name'           => (string) $survey->name,
            'status'         => (string) $survey->status,
            'question_count' => $this->fetchQuestionCounts([(int) $survey->id])[(int) $survey->id] ?? 0,
        ];
    }

    /**
     * 解析可选操作人 ID，未传或无效返回 null。
     *
     * @param array $data 原始业务请求参数。
     * @return int|null 操作人用户 ID。
     */
    private function resolveOperatorId(array $data): ?int
    {
        $operatorId = (int) ($data['operator_id'] ?? 0);
        if ($operatorId <= 0) {
            return null;
        }
        if (!Db::table('users')->where('id', $operatorId)->find()) {
            exception('操作人不存在');
        }

        return $operatorId;
    }

    /**
     * 校验商户入参并确认商户存在。
     *
     * @param int $merchantId 商户 ID。
     * @return void
     */
    private function assertMerchantExists(int $merchantId): void
    {
        if ($merchantId <= 0) {
            exception('缺少参数 merchant_id');
        }
        if (!MerchantModel::where('id', $merchantId)->find()) {
            exception('商户不存在');
        }
    }

    /**
     * 校验问卷归属与存在性，返回问卷模型。
     *
     * @param int  $merchantId 商户 ID。
     * @param int  $id         问卷 ID。
     * @param bool $lock       是否行锁（写场景防并发）。
     * @return QuestionnaireModel 问卷模型。
     */
    private function assertSurveyExists(int $merchantId, int $id, bool $lock): QuestionnaireModel
    {
        $query = QuestionnaireModel::where('merchant_id', $merchantId)->where('id', $id);
        if ($lock) {
            $query = $query->lock(true);
        }
        $survey = $query->find();
        if (!$survey) {
            exception('问卷不存在');
        }

        return $survey;
    }

    /**
     * 数值限幅规整。
     *
     * @param mixed $value 原始值。
     * @param int   $min   下限。
     * @param int   $max   上限。
     * @return int 规整值。
     */
    private function clampInt(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }

    /**
     * 写入问卷审计日志。
     *
     * @param string $operator 操作人标识。
     * @param string $action   审计动作。
     * @param int    $surveyId 问卷 ID。
     * @param array  $payload  审计内容。
     * @return void
     */
    private function writeAudit(string $operator, string $action, int $surveyId, array $payload): void
    {
        $audit = new AuditLogModel();
        $audit->save([
            'operator'    => trim($operator) !== '' ? trim($operator) : 'system',
            'action'      => $action,
            'target_type' => AuditLogModel::TARGET_SURVEY,
            'target_id'   => $surveyId,
            'payload'     => $payload,
        ]);
    }
}
