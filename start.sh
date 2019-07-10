#先结束进程 防止进程死掉
ps aux | grep "dd/sign/sign.php" | grep -v grep | awk '{print $2}' | xargs kill -9
#再启动进程
/usr/bin/php /home/dd/sign/sign.php &>> /home/dd/sign/log/error.log &