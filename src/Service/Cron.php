<?php
namespace Phoenix\Service;

class QueueServer
{

    public function usage()
    {
        return "Usage: php " . __FILE__ . " [list|run] <jobname>\n";
    }

    public function run()
    {
        global $argc, $argv;
        if ($argc < 2) {
            return $this->usage();
        }
        $command = $argv[1];
        switch ($command) {
            case 'list':
                $jobs = App\Cron::jobList();
                $keys = array_keys($jobs);
                echo "Job list:\n";
                $i = 0;
                foreach ($jobs as $k => $v) {
                    echo $i . "\t" . $k . "\n";
                    ++$i;
                }
                echo "\n";
                break;
            case 'run':
                if ($argc < 3) {
                    exit("Usage: php " . __FILE__ . " run <jobname>\n");
                }
                $jobname = strtolower(str_replace('_', '', $argv[2]));
                $jobs = App\Cron::jobList();
                if (!isset($jobs[$jobname])) {
                    exit("job {$jobname} not found\n");
                }
                $cls = $jobs[$jobname];
                if (!class_exists($cls)) {
                    exit("class {$cls} not found\n");
                }
                App\Cron::dispatch($cls);
                break;
            default:
                exit("Usage: php " . __FILE__ . "[list|run] <jobname>\n");
                break;
        }
    }

}