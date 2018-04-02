<?php
/**
 * User: dingding <352926@qq.com>
 * Date: 2018/3/30
 * Time: 10:26
 */

set_time_limit(0);
ini_set('memory_limit', '128M');
define('FCPATH', dirname(__FILE__) . '/');

require FCPATH . 'lib.php';
require FCPATH . 'wx.php';

$accounts = require FCPATH . 'account.php';
$key = 5;
$account = $accounts[$key];

$sign = new Sign();


$to_user = 'o4nzC0ZlJHWSSQEADp5BJKMB0lhk';
$title = "返回结果对照不一致 key:{$key}";
$performance = '签到失败，如已签到请忽略';
$time = date('Y年m月d日H:i:s');
$sign_x_time = date('H:i:s');
$remark = "\n请关注今日签到状况，您的签到时间：{$sign_x_time}！";
$res = send_template($to_user, $title, $performance, $remark);
//$res = $sign->heartbeat($account);
//$res = $sign->sign_in($account);

print_r($res);
die;
