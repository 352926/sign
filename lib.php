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

    protected $diaoyuren_followed = array();
    protected $diaoyuren_liked = array();
    protected $diaoyuren_collect_url = 'http://www.diaoyu.com/jiqiao/list-0-0-%d.html';
    protected $diaoyuren_file_uids = 'uids.data';
    protected $diaoyuren_file_topics = 'topics.data';

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

        $time_h = mt_rand(9, 13);
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

        $uids = @file($this->diaoyuren_file_uids);
        $topics = @file($this->diaoyuren_file_topics);

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
                    $wx_send_rs = '';
                    if (isset($account['open_id'])) {
                        #$his_sign_time = date('H:i:s', strtotime($sign_time));
                        #$wx_send_rs = send_notice($account['open_id'], "{$account['title']} - {$account['user']}", "计划签到", "计划：{$his_sign_time}\n为了防止哪天没有签到，请每天都确保有推送通知哦！", "等待执行");
                        #$wx_send_rs = json_encode($wx_send_rs);
                    }
                    $this->logger("id:{$account['id']},user:{$account['user']},title:[{$account['title']}] 计划签到时间：{$sign_time},send weixin:{$wx_send_rs}");
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
                                $wx_send_rs = send_error($account['open_id'], "计划签到时间：{$sign_x_time}！", '心跳包发送失败', "\n请关注今日签到状况");
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
                    $wx_send_rs = '';
                    if (isset($account['open_id'])) {
                        $his_sign_time = date('H:i:s', strtotime($sign_time));
                        #$wx_send_rs = send_notice($account['open_id'], "{$account['title']} - {$account['user']}", "计划签到", "计划：{$his_sign_time}\n为了防止哪天没有签到，请每天都确保有推送通知哦！", "等待执行");
                        #$wx_send_rs = json_encode($wx_send_rs);
                    }
                    $this->logger("id:{$account['id']},user:{$account['user']},title:[{$account['title']}] 新增计划签到时间：{$sign_time}，send weixin:{$wx_send_rs}");
                }

                if ($sign_record[$account['id']] !== true && time() > $this->sign_time[$today][$account['id']]) {
                    $result = $this->sign_in($account);
                    $result_json = json_encode($result, JSON_UNESCAPED_UNICODE);
                    if ($result->success) {
                        $sign_record[$account['id']] = true;
                        $wx_send_rs = '';
                        if (isset($account['open_id'])) {
                            $day = (strtotime(date("Ymd", time() + 86400)) - strtotime('20181026')) / 86400;
                            $callback_msg = '';
                            if (!empty($result->callback_msg)) {
                                $callback_msg = PHP_EOL . $result->callback_msg;
                            }
                            $wx_send_rs = send_notice($account['open_id'], "{$account['title']} - {$account['user']}", "自动签到", "打卡成功{$callback_msg}", date('Y-m-d H:i:s') . "\n您已连续签到{$day}天了");
                        }
                        $this->logger("[id:{$account['id']},user:{$account['user']},{$account['title']}] 签到 成功!send weixin:{$wx_send_rs}");

                        //id为自己的账号自动做 关注、点赞 任务
                        if (false && $account['id'] == 1) {
                            $follow_success = 0;
                            foreach ($uids as $i => $uid) {
                                //关注3个人就够了
                                if ($follow_success >= 3) {
                                    break;
                                }
                                $rs = $this->diaoyuren_follow($uid, $account);
                                if ($rs === true) {
                                    $follow_success++;
                                    $this->logger("[id:{$account['id']},user:{$account['user']},{$account['title']}] 关注 {$uid} 成功!");
                                }
                                unset($uids[$i]);
                                usleep(mt_rand(100000, 1000000));
                            }

                            @file_put_contents($this->diaoyuren_file_uids, implode(PHP_EOL, $uids));

                            //点赞
                            $like_success = 0;
                            foreach ($topics as $i => $tid) {
                                //点赞超过10偏就退出
                                if ($like_success >= 10) {
                                    break;
                                }
                                $rs = $this->diaoyuren_like($tid, $account);
                                if ($rs === true) {
                                    $like_success++;
                                    $this->logger("[id:{$account['id']},user:{$account['user']},{$account['title']}] 点赞 {$tid} 成功!");
                                }
                                unset($topics[$i]);
                                usleep(mt_rand(100000, 1000000));
                            }
                            @file_put_contents($this->diaoyuren_file_topics, implode(PHP_EOL, $topics));
                        }
                    } else {
                        //失败不重试，直接设置成功。。。
                        $sign_record[$account['id']] = true;
                        //发送微信通知》》》
                        $wx_send_rs = '';
                        if (isset($account['open_id'])) {
                            $wx_send_rs = send_error($account['open_id'], $result->message, "签到失败，如已签到请忽略\n[可能已签过了]", "\n请尽快手工自行签到，技术人员尽快修复！");
                        }
                        $this->logger("[id:{$account['id']},user:{$account['user']},{$account['title']}] 签到 失败,msg:{$result->message},result:{$result_json},wx_send_rs:{$wx_send_rs}");
                    }
                }
            }

            $i++;
            sleep(1);
        } while (true);
    }

    public function collect() {
        $this->accounts = require 'account.php';
        @unlink($this->diaoyuren_file_uids);
        @unlink($this->diaoyuren_file_topics);

        foreach ($this->accounts as $account) {
            if ($account['id'] != 1) {
                continue;
            }

            for ($i = 2; $i <= 602; $i++) {
                $res_txt = $this->_curl_get(sprintf($this->diaoyuren_collect_url, $i), $account['header']);
                $uids = $this->diaoyuren_fetch_uids($res_txt);
                $tids = $this->diaoyuren_fetch_tids($res_txt);

                @file_put_contents($this->diaoyuren_file_uids, implode(PHP_EOL, $uids) . PHP_EOL, FILE_APPEND);
                @file_put_contents($this->diaoyuren_file_topics, implode(PHP_EOL, $tids) . PHP_EOL, FILE_APPEND);
                echo $i . " ok!\n";
                usleep(mt_rand(100000, 1000000));
            }
        }

        echo "Done!\n";
        die;
    }

    public function fetch_user_info($show = false) {
        $this->accounts = require 'account.php';
        @unlink($this->diaoyuren_file_uids);
        @unlink($this->diaoyuren_file_topics);

        $userinfo = array();
        foreach ($this->accounts as $account) {
            if ($account['title'] == '钓鱼人APP') {
                $rs = $this->_curl_get('http://www.diaoyu.com/my/home', $account['header']);
                @preg_match_all("/<div class=\"user-info-honor\"><span>等级：<i>([^<]*)<\/i><\/span><span>头衔：([^<]*)<\/span><\/div>/si", $rs, $matches);
                $level = @$matches[1][0];
                $level_title = @$matches[2][0];

                @preg_match_all("/<p class=\"user-info-name\">([^<]*)<\/p>/si", $rs, $matches);
                //print_r($matches);
                $user = @$matches[1][0];
                @preg_match_all("/<ul class=\"user-info-award\">(.*?)<\/ul>/si", $rs, $matches);
                //print_r($matches[1][0]);
                //print_r($matches[1][0]);
                @preg_match_all("/<p>(\d*)<\/p>/si", $matches[1][0], $all);
                $point = @$all[1][0];
                $gold = @$all[1][1];
                if ($show) {
                    echo "用户：{$user}\n等级：{$level}，头衔：{$level_title}，金币：{$gold}，积分：{$point}\n===============\n";
                }
                $userinfo[$account['id']] = array('name' => $user, 'level' => $level, 'level_title' => $level_title, 'gold' => $gold, 'point' => $point);
            }

        }
        return $userinfo;
    }

    protected function diaoyuren_fetch_uids($content) {
        // 正则匹配规则： http://www.diaoyu.com/user/7519924
        $uids = array();
        $rs = @preg_match_all("/http:\/\/www\.diaoyu\.com\/user\/(\d+)/si", $content, $matches);

        if ($rs === false || empty($matches[1])) {
            return $uids;
        }

        $uids = array_unique($matches[1]);

        return $uids;
    }

    protected function diaoyuren_fetch_tids($content) {
        // 正则匹配规则： http://bbs.diaoyu.com/showtopic-2722009-1-1.html
        $tids = array();
        $rs = @preg_match_all("/http:\/\/bbs\.diaoyu\.com\/showtopic\-(\d+)\-/si", $content, $matches);

        if ($rs === false || empty($matches[1])) {
            return $tids;
        }

        $tids = array_unique($matches[1]);

        return $tids;
    }

    protected function diaoyuren_like($tid, $account) {
        $url = "http://www.diaoyu.com/ajax/thread/like";

        $raw = !empty($account['raw']) ? true : false;
        $post = array(
            'tid' => $tid
        );
        $res_txt = _curl_post($url, $post, $account['header'], $raw);
        $res = json_decode($res_txt, true);

        if ($res['code'] == '1') {
            //恭喜,点赞成功
            return true;
        } elseif ($res['code'] == '-1') {
            //你已经赞过它
            return 0;
        }
        return false;
    }

    protected function diaoyuren_follow($uid, $account) {
        $url = "http://www.diaoyu.com/ajax/user/follow";

        $raw = !empty($account['raw']) ? true : false;
        $post = array(
            'follow_uid' => $uid
        );
        $res_txt = _curl_post($url, $post, $account['header'], $raw);
        $res = json_decode($res_txt, true);

        if ($res['code'] == '1') {
            //关注成功
            return true;
        } elseif ($res['code'] == '-1') {
            //您已经关注过TA
            return 0;
        }
        return false;
    }

    public function diaoyuren_result_check($account, $content) {
        $res = json_decode($content);
        $result = new stdClass();
        $result->success = false;
        $result->message = '';

        if (empty($res->data->html)) {
            send_notice($account['open_id'], "{$account['title']} - {$account['user']}", "数据校验", "调用钓鱼人签到接口返回的数据\$res->data->html不存在或为空！", "需要去日志里查下原因哦");
            $this->logger("id:{$account['id']},user:{$account['user']},title:[{$account['title']}] 调用钓鱼人签到接口返回的数据\$res->data->html不存在或为空！content:{$content}");
            $result->message = '钓鱼人签到接口返回的数据$res->data->html不存在或为空！';
            return $result;
        }

        $html = $res->data->html;

        //方法一：使用strpos字符串查找“特征标识”来框小范围
        $id1_start_tag = '<ul class="calendar-date" data-id="1"';
        $id1_end_tag = '</ul>';
        $ul_id1_start_pos = strpos($html, $id1_start_tag);
        $ul_id1_start_pos = strpos($html, '>', $ul_id1_start_pos) + 1;
        $ul_id1_end_pos = strpos($html, $id1_end_tag, $ul_id1_start_pos);

        $current_month = trim(substr($html, $ul_id1_start_pos, $ul_id1_end_pos - $ul_id1_start_pos));

        //方法二：使用正则来匹配
        // (?:<li>)([\w\<\s\"\'\>\d\/\-\=]+?)\s*(?:<\/li>)
        // (?:<li>)([\s\S]+?)\s*(?:<\/li>)

        if (empty($current_month)) {
            $result->message = '钓鱼人签到接口返回的数据匹配出的当月日历数据为空！';
            return $result;
        }

        $date_m = date('Ymd');

        $sign_tag = "<span class=\"signed\" data-id='{$date_m}'>";
        if (strpos($current_month, $sign_tag) === false) {
            send_notice($account['open_id'], "{$account['title']} - {$account['user']}", "数据校验失败", "签到接口返回数据中没有匹配到成功的特征“{$sign_tag}”！", "需要去日志里查下原因哦");
            $this->logger("id:{$account['id']},user:{$account['user']},title:[{$account['title']}] 签到接口返回数据中没有匹配到成功的特征“{$sign_tag}”！content:{$content}");
            $result->message = '签到接口返回数据中没有匹配到成功的特征“{$sign_tag}”！';
            return $result;
        }

        //到这一步基本可以确定今天的签到成功!

        //接下来验证本月的漏签情况
        $pattern = '/<li>([\n\w\s<=\'\-\">\/]+?)<\/li>/si';
        //$pattern = '/<li>([\s\S]+?)<\/li>/si';
        // preg_match_all($pattern, $current_month, $matches);
        $matches = $this->get_match_all($pattern, $current_month);

        if (empty($matches) || empty($matches[1])) {
            send_notice($account['open_id'], "{$account['title']} - {$account['user']}", "历史签名数据校验失败", "签到接口返回数据正则匹配返回失败！", "需要去日志里查下原因哦");
            $this->logger("id:{$account['id']},user:{$account['user']},title:[{$account['title']}] 签到接口返回数据正则匹配返回失败！content:{$content}");
            $result->message = '签到接口返回数据正则匹配返回失败！';
            return $result;
        }

        $weeks = $matches[1];

        $sign_days = array();
        $fetch_days = array();

        foreach ($weeks as $k => $week) {
            $days = explode(PHP_EOL, trim($week));
            foreach ($days as $i => $day) {
                $day = trim($day);

                //$rs[0]:完整span内容,$rs[1]:class的值,$rs[2]:data-id的值,$rs[3]:对应的日期
                $rs = $this->get_match('/<span(?:\s+class=\"([\w\-]+)\"(?:\s+data\-id=(?:\'|\")([\d]+)(?:\'|\"))*)*>(\d+)<\/span>/si', $day);
                if (empty($rs) || !isset($rs[3])) {
                    continue;
                }

                //上个月
                $pre_month = date('Ym', strtotime(date('Ym01')) - 86400);
                $this_month = date('Ym');
                $next_month = date('Ym', strtotime(date('Ym30')) + 86400 * 5);

                $d = $rs[3] < 10 ? '0' . $rs[3] : $rs[3];

                #如果k为0，且日期大于24号，则认定此日期为上个月的
                if ($k === 0 && $d > 24) {
                    $date_ymd = $pre_month . $d;
                } elseif ($k === count($weeks) - 1 && $d <= 7) {
                    $date_ymd = $next_month . $d;
                } else {
                    $date_ymd = $this_month . $d;
                }

                if (date('Ymd') < $date_ymd) {
                    //echo '> ' . $k . ' ' . $rs[3] . ' ' . $date_ymd . PHP_EOL;
                    //未来的日期退出
                    continue;
                }

                if ($rs[1] === 'text-gray') {
                    //未签到，应该是上个月
                    $sign_status = 0;//未签到
                } elseif ($rs[1] === 'signed') {
                    $sign_status = 1;//已签到
                } elseif (empty($rs[1])) {
                    //未签到
                    $sign_status = 0;//未签到
                } else {
                    send_notice($account['open_id'], "{$account['title']} - {$account['user']}", "历史签名数据校验异常", "签到接口返回数据有未知的class出现！Ymd:{$date_ymd},class:{$rs[1]}", "需要去日志里查下原因哦");
                    $this->logger("id:{$account['id']},user:{$account['user']},title:[{$account['title']}] 签到接口返回数据有未知的class出现！Ymd:{$date_ymd},class:{$rs[1]},content:{$content}");
                    continue;
                }

                $sign_days[$date_ymd] = $sign_status;
                $fetch_days[] = array(
                    'date' => $date_ymd,
                    'sign' => $sign_status
                );
            }
        }

        //print_r($sign_days);
        //print_r($fetch_days);

        $un_sign = array();
        $check_history_day = 5;
        for ($i = 1; $i <= $check_history_day; $i++) {
            $tmp_ymd = date('Ymd', strtotime('-' . $i . ' days'));
            if ($sign_days[$tmp_ymd] === 0) {
                $un_sign[] = date('m月d日', strtotime($tmp_ymd));
            }
        }


        if ($un_sign) {
            $result->message = implode('、', $un_sign) . ' 签到失败';
            send_notice($account['open_id'], "{$account['title']} - {$account['user']}", "历史签到校验", implode('、', $un_sign) . ' 漏签', "以往{$check_history_day}天内有漏签！请自行前往检查！");
            $this->logger("id:{$account['id']},user:{$account['user']},title:[{$account['title']}] 以往{$check_history_day}天内有漏签的！未签到日期：" . json_encode($un_sign, JSON_UNESCAPED_UNICODE) . "content:{$content}");
        } else {
            $result->success = true;
            $result->message = '没有发现漏签';
        }

        return $result;
    }

    function get_match_all($pattern, $subject) {
        $rs = preg_match_all($pattern, $subject, $matches);

        return $rs ? $matches : $rs;
    }

    function get_match($pattern, $subject) {
        $rs = preg_match($pattern, $subject, $matches);

        return $rs ? $matches : $rs;
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
                    $result->message = "heartbeat [key:{$key},value:{$value}] is not set OR value not equal";
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
                $result->message = "返回结果对照不一致 {$key}:{$value}";
                $result->result = $res_txt;
                return $result;
            }
        }

        if (!empty($account['check_callback']) && method_exists($this, $account['check_callback'])) {
            $callback_result = @$this->{$account['check_callback']}($account, $res_txt);

            $result->callback_msg = $callback_result->message;
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
