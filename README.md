# sign
自助批量签到系统

account.php 账户配置
```php
<?php
return array(
    array(
        'id' => 1,
        'title' => '脚本标题',
        'url' => 'http://www.xxx.com/ajax/checkin',
        'heartbeat' => array(
            'url' => 'http://www.xxx.com/ajax/common/xxx',
            'method' => 'GET',
            'interval' => 2900,//心态间隔，单位秒，防止cookie过期
            'params' => array(),
        ),
        'method' => 'GET',
        'format' => 'json',
        'raw' => false,
        'header' => array(
            'Pragma: no-cache',
            //'Accept-Encoding: gzip, deflate',//todo 带上此参数会gzip压缩，未来会做兼容，现在可暂时注释
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8,ht;q=0.7,ja;q=0.6,zh-TW;q=0.5,es;q=0.4,ko;q=0.3',
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.162 Safari/537.36',
            'Accept: application/json, text/javascript, */*; q=0.01',
            'Referer: http://www.xxx.com/my',
            'X-Requested-With: XMLHttpRequest',
            'Cookie: xxxx',
            'Connection: keep-alive',
            'Cache-Control: no-cache',
        ),
        'check_result' => array('message' => 'success', 'code' => 1),//当返回结果里 message的值为success,code的值为1的时候请求表示成功
        'check_callback' => 'xxx_callback',
        'params' => array(),//post 参数
    ),

);


```

用法：
```
mkdir log
/usr/bin/php /home/dd/sign/sign.php &> /dev/null &
tail -f log/sign.log-20180330
```

### 2019-06-19 changelog
增加调用签到接口后callback函数，可用于二次检查是否签到成功。

具体配置项目：check_callback => xxx_callback

xxx_callback 方法为内部成员方法，请确定 $this->xxx_callback() 可用

### 2019-07-10 changelog
为了提高稳定性，建议在crontab里加入 每天重启任务
```
#每天7点钟启动一次签到系统，防止异常退出的问题
0 7 * * * /bin/sh /home/dd/sign/start.sh &
```

start.sh 启动的主要目的是先结束进程，再启动

如果进程长时间运行不重启可能会因不可控导致进程死掉而不退出，因此建议每天重启一次。

