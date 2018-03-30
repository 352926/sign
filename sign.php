<?php
/**
 * 签到系统
 * User: dingding <352926@qq.com>
 * Date: 2018/3/29
 * Time: 14:38
 */

set_time_limit(0);
ini_set('memory_limit', '128M');
define('FCPATH', dirname(__FILE__) . '/');

require_once FCPATH . 'lib.php';
$sign = new Sign();
$sign->process();
