<?php
declare(strict_types=1);

namespace lib;

use Aliyun_Log_Client;
use Aliyun_Log_Models_LogItem;
use Aliyun_Log_Models_PutLogsRequest;

/**
 * 阿里云 SLS 日志服务封装
 */
class AliyunLogger
{
    // config 字段与 config/log.php 严格对齐（小写键名）
    protected array $config = [
        'end_point'     => '',  // 阿里云 SLS 域名
        'access_key_id' => '',  // AK ID
        'access_key'    => '',  // AK Secret
        'project'       => '',  // SLS Project
        'logstore'      => '',  // 默认 logstore
    ];

    protected ?Aliyun_Log_Client $client = null;

    public function __construct(array $config = [])
    {
        $logConfig = config('log') ?: [];
        $this->config = array_merge($this->config, $config ?: $logConfig);
        if (!empty($this->config['end_point']) && !empty($this->config['access_key_id']) && !empty($this->config['access_key'])) {
            try {
                $this->client = new Aliyun_Log_Client(
                    (string)$this->config['end_point'],
                    (string)$this->config['access_key_id'],
                    (string)$this->config['access_key']
                );
            } catch (\Throwable $e) {
                $this->fallbackFileLog([], 'Aliyun_Log_Client init error: ' . $e->getMessage());
            }
        }
    }

    /**
     * 上送日志到阿里云 SLS
     *
     * @param array|string $contents 日志内容
     * @param string $path logstore 名称
     */
    public function putLogs($contents, string $path = ''): void
    {
        try {
            $logstore = $path !== '' ? $path : (string)($this->config['logstore'] ?? 'inner_notp');
            $project  = (string)($this->config['project'] ?? '');

            if (!$this->client || empty($project) || empty($logstore)) {
                $this->fallbackFileLog($contents, 'Client not initialized or missing project/logstore');
                return;
            }

            $logItem = new Aliyun_Log_Models_LogItem();
            $logItem->setTime(time());

            $logContents = [];
            if (is_array($contents)) {
                foreach ($contents as $key => $value) {
                    if (is_array($value)) {
                        $logContents[(string)$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
                    } elseif (is_null($value)) {
                        $logContents[(string)$key] = '';
                    } else {
                        $logContents[(string)$key] = (string)$value;
                    }
                }
            } else {
                $logContents['msg'] = (string)$contents;
            }

            $logItem->setContents($logContents);

            $source = getenv('HOST_IP') ?: null;
            $req = new Aliyun_Log_Models_PutLogsRequest($project, $logstore, '', $source, [$logItem]);
            $this->client->putLogs($req);
        } catch (\Throwable $e) {
            $this->fallbackFileLog($contents, $e->getMessage());
        }
    }

    /**
     * 失败降级写入 runtime/log/log_error.log
     */
    protected function fallbackFileLog($contents, string $error = ''): void
    {
        $logDir = app()->getRuntimePath() . 'log' . DIRECTORY_SEPARATOR;
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . 'log_error.log';
        $data = [
            'time'  => date('Y-m-d H:i:s'),
            'error' => $error,
            'data'  => $contents,
        ];
        @error_log(json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL, 3, $logFile);
    }
}
