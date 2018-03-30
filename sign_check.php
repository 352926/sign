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

$accounts = require FCPATH . 'account.php';
$key = 5;
$account = $accounts[$key];

$sign = new Sign();

$res = $sign->heartbeat($account);
//$res = $sign->sign_in($account);

print_r($res);
die;
