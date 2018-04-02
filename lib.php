<?php
/**
 * User: dingding <352926@qq.com>
 * Date: 2018/3/30
 * Time: 11:16
 */

class Sign {
    protected $lock_file = 'sign.lock';
    protected $log_file = 'log/sign.log';
    protected $sign_records = array();
    protected $sign_time = array();
    protected $accounts = array();
    protected $today = 0;
    protected $reload_config = 180;

    public function __construct() {
        $this->lock_file = FCPATH . $this->lock_file;
        $this->log_file = FCPATH . $this->log_file;
    }

    protected function get_random_time($day = null) {
        if (empty($day)) {
            if (empty($this->today)) {
                $today = date('Ymd');
            } else {
                $today = $this->today;
            }
        } else {
            $today = $day;
        }

        $time_h = mt_rand(7, 13);
        $time_h = $time_h < 10 ? "0{$time_h}" : $time_h;
        $time_i = mt_rand(0, 59);
        $time_i = $time_i < 10 ? "0{$time_i}" : $time_i;
        $time_s = mt_rand(0, 59);
        $time_s = $time_s < 10 ? "0{$time_s}" : $time_s;

        return strtotime("{$today}{$time_h}{$time_i}{$time_s}");
    }

    public function process() {
        if (!file_exists($this->lock_file)) {
            file_put_contents($this->lock_file, getmypid());
        } else {
            $pid = file_get_contents($this->lock_file);
            if (posix_getsid($pid) === false) {
                file_put_contents($this->lock_file, getmypid());
            } else {
                print "PID is still alive! can not run twice!\n";
                exit;
            }
        }

        $i = 0;

        do {
            $this->today = date('Ymd');
            $today = $this->today;

            if ($i % $this->reload_config === 0) {
                //每隔180秒重载一次配合
                $this->accounts = require 'account.php';
                $this->logger("载入最新账户配置...");

                if (!isset($this->sign_time[$today])) {
                    $this->sign_time[$today] = array();
                }
                foreach ($this->accounts as $account) {
                    if (isset($this->sign_time[$today][$account['id']])) {
                        continue;
                    }

                    $this->sign_time[$today][$account['id']] = $this->get_random_time($today);

                    $sign_time = date('Y-m-d H:i:s', $this->sign_time[$today][$account['id']]);
                    $this->logger("id:{$account['id']},user:{$account['user']},title:[{$account['title']}] 计划签到时间：{$sign_time}");
                }

            }

            //获取今天的签到记录
            if (!isset($this->sign_records[$today])) {
                $this->sign_records[$today] = array();
            }
            $sign_record = &$this->sign_records[$today];

            //释放内存中两天前的签到记录
            $two_days_ago = date('Ymd', time() - 86400 * 2);
            if (isset($this->sign_records[$two_days_ago])) {
                unset($this->sign_records[$two_days_ago]);
            }
            foreach ($this->accounts as $account) {
                if (isset($account['heartbeat']) && isset($account['heartbeat']['interval'])) {
                    if ($i % $account['heartbeat']['interval'] === 0) {
                        //心跳启动。。。
                        $result = $this->heartbeat($account);
                        if (!$result->success) {
                            //发送微信通知》》》
                            $wx_send_rs = '';
                            if (isset($account['open_id'])) {
                                $sign_x_time = date('H:i:s', $this->sign_time[$today][$account['id']]);
                                $wx_send_rs = send_template($account['open_id'], "计划签到时间：{$sign_x_time}！", '心跳包发送失败', "\n请关注今日签到状况");
                            }
                            $result_json = json_encode($result, JSON_UNESCAPED_UNICODE);
                            $this->logger("[id:{$account['id']},user:{$account['user']},{$account['title']}] 发送心跳包 失败,msg:{$result->message},result:{$result_json},wx_send_rs:{$wx_send_rs}");
                        } else {
                            $this->logger("[id:{$account['id']},user:{$account['user']},{$account['title']}] 发送心跳包 成功!");
                        }
                    }
                }

                if (!isset($sign_record[$account['id']])) {
                    $sign_record[$account['id']] = false;
                }

                if (!isset($this->sign_time[$today][$account['id']])) {
                    $this->sign_time[$today][$account['id']] = $this->get_random_time($today);
                    $sign_time = date('Y-m-d H:i:s', $this->sign_time[$today][$account['id']]);
                    $this->logger("id:{$account['id']},user:{$account['user']},title:[{$account['title']}] 新增计划签到时间：{$sign_time}");
                }

                if ($sign_record[$account['id']] !== true && time() > $this->sign_time[$today][$account['id']]) {
                    $result = $this->sign_in($account);
                    $result_json = json_encode($result, JSON_UNESCAPED_UNICODE);
                    if ($result->success) {
                        $sign_record[$account['id']] = true;
                        $this->logger("[id:{$account['id']},user:{$account['user']},{$account['title']}] 签到 成功!");
                    } else {
                        //失败不重试，直接设置成功。。。
                        $sign_record[$account['id']] = true;
                        //发送微信通知》》》
                        $wx_send_rs = '';
                        if (isset($account['open_id'])) {
                            $wx_send_rs = send_template($account['open_id'], $result->message, '签到失败，如已签到请忽略', "\n请尽快手工自行签到，技术人员尽快修复！");
                        }
                        $this->logger("[id:{$account['id']},user:{$account['user']},{$account['title']}] 签到 失败,msg:{$result->message},result:{$result_json},wx_send_rs:{$wx_send_rs}");
                    }
                }
            }

            $i++;
            sleep(1);
        } while (true);
    }

    protected function _curl_post($url, $post, $headers = array(), $raw = false) {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        if (!empty($post) && is_array($post)) {
            if ($raw) {
                //HTTP_RAW_POST_DATA
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            }
        }
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    protected function _curl_get($url, $headers = array()) {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public function heartbeat($account) {
        $result = $this->result();
        $heartbeat = $account['heartbeat'];
        $header = $account['header'];
        if ($heartbeat['method'] === 'GET') {
            $res_txt = $this->_curl_get($heartbeat['url'], $account['header']);
        } else {
            $raw = !empty($heartbeat['raw']) ? true : false;
            $res_txt = $this->_curl_post($heartbeat['url'], $heartbeat['params'], $header, $raw);
        }

        if ($account['format'] === 'json') {
            $res = json_decode($res_txt, true);
        } else {
            //todo xml
            $res = array();
        }

        if (isset($account['check_result'])) {
            $check_result = $account['check_result'];
            if (isset($heartbeat['check_result'])) {
                $check_result = $heartbeat['check_result'];
            }

            foreach ($check_result as $key => $value) {
                if (!isset($res[$key]) || $res[$key] != $value) {
                    $result->message = "heartbeat key:{$key} is not set OR value not equal";
                    $result->result = $res_txt;
                    return $result;
                }
            }
        }

        $result->success = true;
        $result->result = $res_txt;
        return $result;
    }

    /**
     * @return stdClass
     */
    protected function result() {
        $result = new stdClass();
        $result->success = false;
        $result->message = '';
        return $result;
    }

    public function sign_in($account) {
        $result = $this->result();
        if ($account['method'] === 'GET') {
            $res_txt = $this->_curl_get($account['url'], $account['header']);
        } else {
            $raw = !empty($account['raw']) ? true : false;
            $res_txt = $this->_curl_post($account['url'], $account['params'], $account['header'], $raw);
        }
        if ($account['format'] === 'json') {
            $res = json_decode($res_txt, true);
        } else {
            //todo xml
            $res = array();
        }

        foreach ($account['check_result'] as $key => $value) {
            if (!isset($res[$key]) || $res[$key] != $value) {
                $result->message = "返回结果对照不一致 key:{$key}";
                $result->result = $res_txt;
                return $result;
            }
        }
        $result->success = true;
        $result->result = $res_txt;

        return $result;
    }

    protected function logger($value) {
        $time = date('Y-m-d H:i:s');
        file_put_contents($this->log_file . '-' . date('Ymd'), "{$time}\t{$value}\n", FILE_APPEND);
    }
}
