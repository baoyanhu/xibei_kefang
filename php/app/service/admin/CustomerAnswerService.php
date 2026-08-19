<?php
declare(strict_types=1);

namespace app\service\admin;

use think\facade\Db;

/**
 * B 端顾客评价业务服务，读取答卷快照并补齐问卷名称、脱敏顾客与明细增强数据。
 */
class CustomerAnswerService
{
    /** 维度题题型（明细需补 config.dimensions 逐维展示）。 */
    private const TYPE_MULTI_DIM = 4;

    /**
     * 分页查询顾客答卷列表。
     *
     * @param array $data 已解密的业务请求参数。
     * @return array {count: int, list: array}。
     */
    public function getList(array $data): array
    {
        $input = $this->validateListInput($data);

        $query = Db::table('answers')
            ->alias('a')
            ->leftJoin('survey_instances si', 'si.id = a.instance_id')
            ->leftJoin('questionnaires q', 'q.id = si.questionnaire_id')
            ->field('a.id, a.submitted_at, a.order_no, si.questionnaire_id, q.name AS questionnaire_name, si.member_card_no, a.payload');

        if ($input['questionnaire_name'] !== '') {
            $query->whereLike('q.name', '%' . $input['questionnaire_name'] . '%');
        }
        if ($input['submit_time_start'] !== '') {
            $query->where('a.submitted_at', '>=', $input['submit_time_start'] . ' 00:00:00');
        }
        if ($input['submit_time_end'] !== '') {
            $query->where('a.submitted_at', '<=', $input['submit_time_end'] . ' 23:59:59');
        }

        $paginator = $query->order('a.submitted_at', 'desc')->order('a.id', 'desc')->paginate([
            'page'      => $input['page'],
            'list_rows' => $input['page_size'],
        ]);
        $rows = $paginator->toArray()['data'] ?? [];

        $list = [];
        foreach ($rows as $row) {
            $list[] = $this->assembleRow($row);
        }

        return [
            'count' => $paginator->total(),
            'list'  => $list,
        ];
    }

    /**
     * 查询单份答卷明细，维度题补题目配置，菜品题补菜名映射。
     *
     * @param array $data 已解密的业务请求参数。
     * @return array 答卷明细。
     */
    public function getDetail(array $data): array
    {
        $answerId = (int) ($data['answer_id'] ?? 0);
        if ($answerId <= 0) {
            exception('缺少参数 answer_id');
        }

        $row = Db::table('answers')
            ->alias('a')
            ->leftJoin('survey_instances si', 'si.id = a.instance_id')
            ->leftJoin('questionnaires q', 'q.id = si.questionnaire_id')
            ->field('a.id, a.instance_id, a.submitted_at, a.order_no, si.questionnaire_id, q.name AS questionnaire_name, si.member_card_no, a.payload')
            ->where('a.id', $answerId)
            ->find();
        if (!$row) {
            exception('答卷不存在');
        }

        $detail = $this->assembleRow($row);

        $this->attachDimensionConfigs($detail['questions']);
        $detail['dish_name_map'] = $this->fetchDishNameMap((int) $row['instance_id']);

        return $detail;
    }

    /**
     * 校验并规整列表入参。
     *
     * @param array $data 原始业务请求参数。
     * @return array 规整后的筛选参数。
     */
    private function validateListInput(array $data): array
    {
        $startTime = trim((string) ($data['submit_time_start'] ?? ''));
        $endTime = trim((string) ($data['submit_time_end'] ?? ''));
        if ($startTime !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startTime)) {
            exception('submit_time_start 格式应为 YYYY-MM-DD');
        }
        if ($endTime !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endTime)) {
            exception('submit_time_end 格式应为 YYYY-MM-DD');
        }

        return [
            'page'               => max(1, (int) ($data['page'] ?? 1)),
            'page_size'          => min(100, max(1, (int) ($data['page_size'] ?? 10))),
            'questionnaire_name' => trim((string) ($data['questionnaire_name'] ?? '')),
            'submit_time_start'  => $startTime,
            'submit_time_end'    => $endTime,
        ];
    }

    /**
     * 将查询行组装为对外结构，payload 快照零拼装透传。
     *
     * @param array $row 关联查询行。
     * @return array 对外答卷行。
     */
    private function assembleRow(array $row): array
    {
        $payload = is_array($row['payload']) ? $row['payload'] : (json_decode((string) $row['payload'], true) ?: []);

        return [
            'answer_id'          => (int) $row['id'],
            'submitted_at'       => $row['submitted_at'],
            'order_no'           => $row['order_no'] ?? '--',
            'questionnaire_id'   => (int) ($row['questionnaire_id'] ?? 0),
            'questionnaire_name' => ($row['questionnaire_name'] ?? '') !== '' ? $row['questionnaire_name'] : '--',
            'member_card_no'     => $this->maskCardNo((string) ($row['member_card_no'] ?? '')),
            'questions'          => is_array($payload['questions'] ?? null) ? $payload['questions'] : [],
        ];
    }

    /**
     * 给维度题补充题目配置 config（前端逐维渲染需要 dimensions）。
     *
     * @param array $questions 答卷题目数组（引用原地补充）。
     * @return void
     */
    private function attachDimensionConfigs(array &$questions): void
    {
        $dimIds = [];
        foreach ($questions as $question) {
            if ((int) ($question['question_type'] ?? 0) === self::TYPE_MULTI_DIM) {
                $dimIds[] = (int) ($question['question_id'] ?? 0);
            }
        }
        if ($dimIds === []) {
            return;
        }

        $configs = Db::table('questions')
            ->whereIn('id', $dimIds)
            ->column('config', 'id');
        foreach ($questions as &$question) {
            $qid = (int) ($question['question_id'] ?? 0);
            if ((int) ($question['question_type'] ?? 0) === self::TYPE_MULTI_DIM && isset($configs[$qid])) {
                $config = is_array($configs[$qid]) ? $configs[$qid] : (json_decode((string) $configs[$qid], true) ?: []);
                if ($config !== []) {
                    $question['config'] = $config;
                }
            }
        }
        unset($question);
    }

    /**
     * 取实例命中菜品快照的 goods_code 到菜名映射。
     *
     * @param int $instanceId 实例 ID。
     * @return array {goods_code: dish_name}。
     */
    private function fetchDishNameMap(int $instanceId): array
    {
        $rows = Db::table('survey_dishes')
            ->field('dish_id, dish_name')
            ->where('instance_id', $instanceId)
            ->order('sort_order', 'asc')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['dish_id']] = $row['dish_name'];
        }

        return $map;
    }

    /**
     * 会员卡号出参脱敏，保留前 3 后 4，与手机号脱敏观感一致。
     *
     * @param string $cardNo 库内明文卡号。
     * @return string 脱敏卡号。
     */
    private function maskCardNo(string $cardNo): string
    {
        $length = mb_strlen($cardNo);
        if ($cardNo === '') {
            return '--';
        }
        if ($length <= 7) {
            return str_repeat('*', $length);
        }

        return mb_substr($cardNo, 0, 3) . '****' . mb_substr($cardNo, -4);
    }
}
