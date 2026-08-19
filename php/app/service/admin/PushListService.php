<?php
declare(strict_types=1);

namespace app\service\admin;

use app\common\model\SurveyInstanceModel;
use think\facade\Db;

/**
 * B 端评价推送列表业务服务，纯查询实例列表与命中菜品快照。
 */
class PushListService
{
    /**
     * 推送状态筛选到实例状态集合的映射。
     * 待推送=status1；已推送覆盖 2/3/4/5/6（已失效与推送失败同样完成过或终止了推送动作）。
     */
    private const PUSH_STATE_STATUS = [
        'pending' => [SurveyInstanceModel::STATUS_PENDING_PUSH],
        'pushed'  => [
            SurveyInstanceModel::STATUS_PUSHED,
            SurveyInstanceModel::STATUS_OPENED,
            SurveyInstanceModel::STATUS_SUBMITTED,
            SurveyInstanceModel::STATUS_EXPIRED,
            SurveyInstanceModel::STATUS_PUSH_FAILED,
        ],
    ];

    /** 答题状态筛选到实例状态的精确映射。 */
    private const ANSWER_STATE_STATUS = [
        'submitted' => [SurveyInstanceModel::STATUS_SUBMITTED],
        'opened'    => [SurveyInstanceModel::STATUS_OPENED],
        'unopened'  => [SurveyInstanceModel::STATUS_PUSHED],
    ];

    /**
     * 分页查询评价推送列表。
     *
     * @param array $data 已解密的业务请求参数。
     * @return array {count: int, list: array}。
     */
    public function getList(array $data): array
    {
        $input = $this->validateListInput($data);

        $query = Db::table('survey_instances')
            ->field('id, order_no, member_card_no, phone, status, pushed_at, opened_at, submitted_at, create_time')
            ->where(function ($query) use ($input) {
                if ($input['order_no'] !== '') {
                    $query->whereLike('order_no', '%' . $input['order_no'] . '%');
                }
                if ($input['member_card_no'] !== '') {
                    $query->whereLike('member_card_no', '%' . $input['member_card_no'] . '%');
                }
                if ($input['phone'] !== '') {
                    $query->whereLike('phone', '%' . $input['phone'] . '%');
                }
                if ($input['push_state'] !== '') {
                    $query->whereIn('status', self::PUSH_STATE_STATUS[$input['push_state']]);
                }
                if ($input['answer_state'] !== '') {
                    $query->whereIn('status', self::ANSWER_STATE_STATUS[$input['answer_state']]);
                }
                if ($input['create_time_start'] !== '') {
                    $query->where('create_time', '>=', $input['create_time_start'] . ' 00:00:00');
                }
                if ($input['create_time_end'] !== '') {
                    $query->where('create_time', '<=', $input['create_time_end'] . ' 23:59:59');
                }
            });

        $paginator = $query->order('create_time', 'desc')->order('id', 'desc')->paginate([
            'page'      => $input['page'],
            'list_rows' => $input['page_size'],
        ]);
        $rows = $paginator->toArray()['data'] ?? [];

        $dishes = $this->fetchDishes(array_map('intval', array_column($rows, 'id')));
        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id'              => (int) $row['id'],
                'order_no'        => $row['order_no'],
                'member_card_no'  => $this->maskCardNo((string) $row['member_card_no']),
                'phone'           => $row['phone'] ?? '--',
                'status'          => (int) $row['status'],
                'status_text'     => SurveyInstanceModel::$STATUS_TEXTS[(int) $row['status']] ?? '',
                'pushed_at'       => $row['pushed_at'],
                'opened_at'       => $row['opened_at'],
                'submitted_at'    => $row['submitted_at'],
                'create_time'     => $row['create_time'],
                'dishes'          => $dishes[(int) $row['id']] ?? [],
            ];
        }

        return [
            'count' => $paginator->total(),
            'list'  => $list,
        ];
    }

    /**
     * 校验并规整列表入参。
     *
     * @param array $data 原始业务请求参数。
     * @return array 规整后的筛选参数。
     */
    private function validateListInput(array $data): array
    {
        $pushState = trim((string) ($data['push_state'] ?? ''));
        $answerState = trim((string) ($data['answer_state'] ?? ''));

        if ($pushState !== '' && !isset(self::PUSH_STATE_STATUS[$pushState])) {
            exception('push_state 只能为 pushed 或 pending');
        }
        if ($answerState !== '' && !isset(self::ANSWER_STATE_STATUS[$answerState])) {
            exception('answer_state 只能为 submitted、opened 或 unopened');
        }

        $startTime = trim((string) ($data['create_time_start'] ?? ''));
        $endTime = trim((string) ($data['create_time_end'] ?? ''));
        if ($startTime !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startTime)) {
            exception('create_time_start 格式应为 YYYY-MM-DD');
        }
        if ($endTime !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endTime)) {
            exception('create_time_end 格式应为 YYYY-MM-DD');
        }

        return [
            'page'              => max(1, (int) ($data['page'] ?? 1)),
            'page_size'         => min(100, max(1, (int) ($data['page_size'] ?? 10))),
            'order_no'          => trim((string) ($data['order_no'] ?? '')),
            'member_card_no'    => trim((string) ($data['member_card_no'] ?? '')),
            'phone'             => trim((string) ($data['phone'] ?? '')),
            'push_state'        => $pushState,
            'answer_state'      => $answerState,
            'create_time_start' => $startTime,
            'create_time_end'   => $endTime,
        ];
    }

    /**
     * 批量取实例命中菜品名称，保持 sort_order 展示顺序。
     *
     * @param array $instanceIds 当前页实例 ID 集合。
     * @return array 以实例 ID 为键的菜品名称数组。
     */
    private function fetchDishes(array $instanceIds): array
    {
        if ($instanceIds === []) {
            return [];
        }

        $rows = Db::table('survey_dishes')
            ->field('instance_id, dish_name')
            ->whereIn('instance_id', $instanceIds)
            ->order('sort_order', 'asc')
            ->select()
            ->toArray();

        $dishes = [];
        foreach ($rows as $row) {
            $dishes[(int) $row['instance_id']][] = $row['dish_name'];
        }

        return $dishes;
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
        if ($length <= 7) {
            return $cardNo !== '' ? str_repeat('*', $length) : '--';
        }

        return mb_substr($cardNo, 0, 3) . '****' . mb_substr($cardNo, -4);
    }
}
