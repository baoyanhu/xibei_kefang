<?php
declare(strict_types=1);

namespace app\service\admin;

use app\common\model\RewardRecordModel;
use think\facade\Db;

/**
 * B 端评价发放记录业务服务，纯查询激励发放流水。
 */
class RewardRecordService
{
    /**
     * 分页查询评价发放记录，关联实例补订单号与卡号（实例被清理的孤儿记录仍展示）。
     *
     * @param array $data 已解密的业务请求参数。
     * @return array {count: int, list: array}。
     */
    public function getList(array $data): array
    {
        $input = $this->validateListInput($data);

        $query = Db::table('reward_records')
            ->alias('rr')
            ->leftJoin('survey_instances si', 'si.id = rr.instance_id')
            ->field('rr.id, rr.instance_id, rr.reward_type, rr.points, rr.coupon_template_id, rr.status, rr.grant_serial_no, rr.coupon_no, rr.granted_at, rr.failure_reason, rr.create_time, si.order_no, si.member_card_no')
            ->where(function ($query) use ($input) {
                if ($input['order_no'] !== '') {
                    $query->whereLike('si.order_no', '%' . $input['order_no'] . '%');
                }
                if ($input['member_card_no'] !== '') {
                    $query->whereLike('si.member_card_no', '%' . $input['member_card_no'] . '%');
                }
                if ($input['coupon_template_id'] !== '') {
                    $query->whereLike('rr.coupon_template_id', '%' . $input['coupon_template_id'] . '%');
                }
                if ($input['coupon_no'] !== '') {
                    $query->whereLike('rr.coupon_no', '%' . $input['coupon_no'] . '%');
                }
                if ($input['reward_type'] !== 0) {
                    $query->where('rr.reward_type', $input['reward_type']);
                }
                if ($input['status'] !== 0) {
                    $query->where('rr.status', $input['status']);
                }
            });

        $paginator = $query->order('rr.create_time', 'desc')->order('rr.id', 'desc')->paginate([
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
     * 校验并规整列表入参。
     *
     * @param array $data 原始业务请求参数。
     * @return array 规整后的筛选参数。
     */
    private function validateListInput(array $data): array
    {
        $rewardType = (int) trim((string) ($data['reward_type'] ?? ''));
        $status = (int) trim((string) ($data['status'] ?? ''));

        if ($rewardType !== 0 && !isset(RewardRecordModel::$REWARD_TYPE_TEXTS[$rewardType])) {
            exception('reward_type 只能为 1 或 2');
        }
        if ($status !== 0 && !isset(RewardRecordModel::$STATUS_TEXTS[$status])) {
            exception('status 只能为 1-5');
        }

        return [
            'page'               => max(1, (int) ($data['page'] ?? 1)),
            'page_size'          => min(100, max(1, (int) ($data['page_size'] ?? 10))),
            'order_no'           => trim((string) ($data['order_no'] ?? '')),
            'member_card_no'     => trim((string) ($data['member_card_no'] ?? '')),
            'coupon_template_id' => trim((string) ($data['coupon_template_id'] ?? '')),
            'coupon_no'          => trim((string) ($data['coupon_no'] ?? '')),
            'reward_type'        => $rewardType,
            'status'             => $status,
        ];
    }

    /**
     * 组装单行出参，枚举值附文本，卡号脱敏，实例缺失的关联列给 '--'。
     *
     * @param array $row 关联查询行。
     * @return array 列表行。
     */
    private function assembleRow(array $row): array
    {
        $cardNo = (string) ($row['member_card_no'] ?? '');

        return [
            'id'                 => (int) $row['id'],
            'instance_id'        => (int) $row['instance_id'],
            'order_no'           => ($row['order_no'] ?? '') !== '' ? $row['order_no'] : '--',
            'member_card_no'     => $cardNo !== '' ? $this->maskCardNo($cardNo) : '--',
            'reward_type'        => (int) $row['reward_type'],
            'reward_type_text'   => RewardRecordModel::$REWARD_TYPE_TEXTS[(int) $row['reward_type']] ?? '',
            'points'             => $row['points'] !== null ? (int) $row['points'] : null,
            'coupon_template_id' => $row['coupon_template_id'],
            'coupon_no'          => $row['coupon_no'],
            'status'             => (int) $row['status'],
            'status_text'        => RewardRecordModel::$STATUS_TEXTS[(int) $row['status']] ?? '',
            'grant_serial_no'    => $row['grant_serial_no'],
            'create_time'        => $row['create_time'],
            'granted_at'         => $row['granted_at'],
            'failure_reason'     => $row['failure_reason'],
        ];
    }

    /**
     * 会员卡号出参脱敏，保留前 3 后 4，与推送列表口径一致。
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
