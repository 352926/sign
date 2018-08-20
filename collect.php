<?php
/**
 * 抓取页面数据
 * User: dingding <352926@qq.com>
 * Date: 2018/8/20
 * Time: 15:21
 */

set_time_limit(0);
ini_set('memory_limit', '128M');
define('FCPATH', dirname(__FILE__) . '/');

require_once FCPATH . 'lib.php';
//此部分为调用自己服务器微信api，以及签名方法、秘钥等，不方便公开
//其它需要使用此签到系统的人可以注释 wx.php 以及lib.php 里的send方法
require_once FCPATH . 'wx.php';
$sign = new Sign();
$sign->collect();
