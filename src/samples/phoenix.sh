#!/bin/sh
basepath=$(cd `dirname $0`; pwd)
filePath="$basepath/../index.php"
masterTitle="`cat $basepath/../conf/application_name`Service:Master"
useEnv="prod"
masterPid=`ps -ef | grep "$masterTitle" | grep -v "grep" | awk '{print $2}'`

start () {
	if [ -z "$masterPid" ]; then
            echo "Starting : begin"
            # ulimit -c unlimited
            /home/service/php7/bin/php $filePath -e $useEnv
            #/opt/php-7.0.28/bin/php $filePath -e $useEnv
            echo "Starting : finish"
	else
            echo "Starting : running, $masterPid"
	fi
}

stop () {
	if [ -z "$masterPid" ]; then
            echo "Stopping : no master"
	else
            echo "Stopping : begin"
            kill -TERM $masterPid
            echo "Stopping : finish"
	fi
}

reload () {
	echo "Reloading : begin"
	kill -USR1 $masterPid
        sleep 1
	kill -USR2 $masterPid
	echo "Reloading : finish"
}

monitor () {
	echo "Monitor : begin"
	if [ -z "$masterPid" ]; then
            start
	fi
	echo "Monitor : finish"
}

case "$1" in
  start)
	start
	;;
  stop)
	stop
	;;
  restart)
	stop
	start
	;;
  reload)
	reload
	;;
  monitor)
	;;
  *)
	echo $"Usage: $0 {start|stop|restart|reload|monitor}"
    ;;
esac