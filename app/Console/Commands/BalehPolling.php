<?php

namespace App\Console\Commands;

use App\Http\Service\BaleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BalehPolling extends Command
{
    public $Baleservice;
    public function __construct(BaleService $service) 
    {
        parent::__construct();
        $this->Baleservice = $service;
    }
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'baleh:poll';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        
        while(true)
        {
            $botName = 'bale_bot';
            $token = env('BALE_BOT_TOKEN');

            $lastUpdateId = DB::table('bot_offsets')
            ->where('bot_name', $botName)
            ->value('last_update_id');


            $this->info("starting Bale Long Polling");

        

            $response = Http::get("https://tapi.bale.ai/{$token}/getUpdates" , [
                'offset' => $lastUpdateId + 1,

            ]);

            if (!$response->ok()) {
            $this->error('Bale API error');
            return;
            }

            $updates = $response->json('result');

            foreach ($updates as $update) 
            {


                $updateId = $update['update_id'];

                if (isset($update['message'])) {
                    Log::info('this message is normal');
                    $this->Baleservice->Matching($update);
                }

                elseif (isset($update['callback_query'])) {
                    Log::info('this message is callback');
                    $this->Baleservice->handleCallback($update['callback_query']);
                }

                DB::table('bot_offsets')
                    ->where('bot_name', $botName)
                    ->update([
                        'last_update_id' => $updateId,
                        'updated_at' => now(),
                    ]);
            }
    
        }   
    }
}
