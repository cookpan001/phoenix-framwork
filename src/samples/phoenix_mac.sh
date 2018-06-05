#!/bin/sh

filePath="$PWD/../index.php"
useEnv="local"

start () {
	if [ -z "$masterPid" ]; then
            echo "Starting : begin"
            # ulimit -c unlimited
            /opt/php-7.0.28/bin/php $filePath -e $useEnv
            echo "Starting : finish"
	else
            echo "Starting : running, $masterPid"
	fi
}

stop () {
	ps aux | grep index | grep -v grep | awk '{print $2}' | sort -n | head -n 1 | xargs kill -TERM
}

reload () {
	ps aux | grep index | grep -v grep | awk '{print $2}' | sort -n | head -n 1 | xargs kill -USR1
        sleep 0.5
	ps aux | grep index | grep -v grep | awk '{print $2}' | sort -n | head -n 1 | xargs kill -USR2
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