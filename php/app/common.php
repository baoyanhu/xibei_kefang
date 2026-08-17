<?php
// 这是系统自动生成的common文件
declare(strict_types=1);

if (!function_exists('save_log')) {
    function save_log($info = '', $level = 1, $title = '', $path = 'common', $extParam = [])
    {
        $typeString = '';
        if ($level == 1) {
            $typeString .= 'info';
        } elseif ($level == 2) {
            $typeString .= 'error';
        } elseif ($level == 3) {
            $typeString .= 'warning';
        } elseif ($level == 4) {
            $typeString .= 'notice';
        } elseif ($level == 5) {
            $typeString .= 'debug';
        } elseif ($level == 6) {
            $typeString .= 'sql';
        } elseif ($level == 7) {
            $typeString .= 'middleware';
        } else {
            $typeString .= $level;
        }

        $gradeString = 'P2';
        if (!empty($extParam) && is_array($extParam) && isset($extParam['grade'])) {
            if ($extParam['grade'] === 1) {
                $gradeString = 'P0';
            } elseif ($extParam['grade'] === 2) {
                $gradeString = 'P1';
            } elseif ($extParam['grade'] === 3) {
                $gradeString = 'P2';
            } else {
                $gradeString = 'P3';
            }
        }

        $message = [
            'title'          => $title,
            'level'          => $typeString,
            'grade_tag'      => $gradeString,
            'request_time'   => date('Y-m-d H:i:s'),
            'request_method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'CLI',
            'request_uri'    => '',
            'remote_ip'      => '',
            'request_ttl'    => 0,
            'input'          => '',
            'msg'            => $info,
            'version'        => config('log.log_version'),
            'token'          => \think\facade\Request::header('token'),
            'appId'          => \think\facade\Request::header('appId'),
        ];

        if ($message['request_method'] !== 'CLI') {
            $message['request_uri'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $message['remote_ip']   = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
            $message['request_ttl'] = isset($_SERVER['REQUEST_TIME_FLOAT']) ? number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 4) : 0;
        }

        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $message['input'] = urldecode($input);
        }

        if (!empty($extParam) && is_array($extParam)) {
            if (isset($extParam['msg'])) {
                $extParam['request_msg'] = $extParam['msg'];
                unset($extParam['msg']);
            }
            if (isset($extParam['code'])) {
                $extParam['request_code'] = $extParam['code'];
                unset($extParam['code']);
            }
            if (isset($extParam['request_code'])) {
                if ($extParam['request_code'] == 8001) {
                    $extParam['at'] = 'CP';
                } elseif ($extParam['request_code'] == 8002) {
                    $extParam['at'] = 'QD';
                } elseif ($extParam['request_code'] == 8003) {
                    $extParam['at'] = 'SJ';
                } elseif ($extParam['request_code'] == 8004) {
                    $extParam['at'] = 'YW';
                } elseif ($extParam['request_code'] == 8005) {
                    $extParam['at'] = 'NYW';
                } elseif ($extParam['request_code'] == 8006) {
                    $extParam['at'] = 'NAT';
                } else {
                    $extParam['at'] = 'YF';
                }
            } else {
                $extParam['at'] = 'YF';
            }
            $message = array_merge($message, $extParam);
        }

        if (strtolower((string)config('log.type')) == 'file') {
            $logPath = app()->getRuntimePath() . 'log' . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m') . DIRECTORY_SEPARATOR;
            if (!is_dir($logPath)) {
                mkdir($logPath, 0755, true);
            }
            $logName = date('d') . '.log';
            error_log(json_encode($message, JSON_UNESCAPED_UNICODE) . PHP_EOL . PHP_EOL, 3, $logPath . $logName);
        } else {
            app('lib\AliyunLogger')->putLogs($message, (string)config('log.logstore'));
        }
    }
}

if (!function_exists('exception')) {
    function exception(string $message, int $code = 250)
    {
        throw new \Exception($message, $code);
    }
}

if (!function_exists('get_operator')) {
    function get_operator(): string
    {
        return (string) (app('request')->operator ?? '');
    }
}
