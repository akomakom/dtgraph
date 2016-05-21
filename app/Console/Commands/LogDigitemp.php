<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Reading;

class LogDigitemp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dtgraph:logdigitemp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs digitemp as current user and writes all readings to db';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $command = config('dtgraph.logger.read_temps_command');
        echo "Executing read_temps_command: '$command'";
        $reply = shell_exec($command);
        $reply_array = explode("\n", $reply);
        if (!is_array($reply_array) || count($reply_array) == 0) {
            echo "No valid output from command received: '$reply'";
        }

        foreach ($reply_array as $line) {
            if (trim($line) == '') {
                continue; //don't need blank lines                                                                                          
            }
            $sensor = explode(' ', $line);
            $serialnumber = $sensor[0];
            $temperature = $sensor[1];
            if ($serialnumber != '' and is_numeric($temperature)) {
                echo "Inserting $serialnumber, $temperature\n";
                if ($temperature < config('dtgraph.logger.valid_temp_min') || config('dtgraph.logger.valid_temp_max')) {
                    echo "Invalid temperature, outside configured bounds";
                } else {
                    Reading::add($serialnumber, $temperature);
                }
            } else {
                echo "Invalid line from '$command':\n$line";
            }
        }
    }
}
