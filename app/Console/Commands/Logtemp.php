<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Reading;

class Logtemp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dtgraph:logtemp {sensor} {--C|celsius=} {--F|fahrenheit=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Log temperature reading provided on command-line';

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
        $serialnumber = $this->argument('sensor');

        $c = $this->option('celsius');
        $temperature = $this->option('fahrenheit');

        if (!$c && !$temperature) {
            echo "No option provided with temperature, please see help\n";
            exit(1);
        }

        //native is f
        if ($c) {
            $temperature = $c * 9/5 + 32;
        }

        echo "Temperature: ${temperature}F\n";

        echo "Inserting $serialnumber, $temperature\n";
        Reading::add($serialnumber, $temperature);
    }
}
