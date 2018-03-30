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

