<?php 

namespace App\Http\Service;

use App\Http\Controllers\RequestController;
use App\Http\Controllers\TicketController;
use App\Models\User;
use App\Models\UserRole;
use App\Http\Controllers\UserController;
use App\Models\Tickets;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;


class BaleService
{
    private $user;
    private $ticket;
    public function __construct(UserController $user , TicketController $ticket) {
        $this->user = $user;
        $this->ticket = $ticket;

    }

    public function Matching($data)
    {
        $chatId = $data['message']['chat']['id'];
        $text =  $data['message']['text'];
        if($text === '/start')
        {
            $this->user->Start($data);   
        }     


        $state = Cache::get("UserState:{$chatId}");

        if ($state === 'writeText') {
            // کاربر در مرحله نوشتن پیام تیکت است
            $this->ticket->storeTicket($chatId, $text);
        }

        
        if (str_starts_with($state, 'agananswer_')) {
            // کاربر در مرحله نوشتن پیام تیکت است
            $ticketId = explode('_', $state)[1];
            $this->ticket->StoreAnswerAganUser($ticketId, $data);
        }

        $stateAdmin = Cache::get("AdminState:{$chatId}");

            // Log::info($stateAdmin);

        if ($stateAdmin && str_starts_with($stateAdmin, 'answering_ticket_')) 
        {
            Log::info($stateAdmin);
            $ticketId = explode('_', $stateAdmin)[2];
            Log::info($ticketId);

            $this->ticket->StoreTicketAdmin($ticketId , $data);
           
        }


    }



    private function sendInvalidMessage($data)
    {
        $chatId = $data['message']['chat']['id'];

        $this->user->sendMessage($chatId,'❌ پیام ارسال‌شده نامعتبر است. لطفاً از منوی ربات استفاده کنید.' , []);
    }

    public function handleCallback($callback)
    {
        $data = $callback['data'];

        if($data === 'new_ticket')       
        {
            $this->ticket->SelectCategory($callback);
        } 
        elseif (str_starts_with($data, 'choose_category:')) 
        {
            $this->ticket->SelectPriority($callback);
        }
        elseif (str_starts_with($data, 'choose_priority:')) 
        {
            $this->ticket->writeText($callback);
        }   

        elseif (str_starts_with($data, 'admin_answer:')) 
        {

            $ticketId = explode(':', $data)[1];

            $this->ticket->asnwereAdmin($callback , intval($ticketId));

        }

        elseif (str_starts_with($data, 'user_answer:')) 
        {

            $ticketId = explode(':', $data)[1];

            $this->ticket->asnwereUser($callback , intval($ticketId));

        }

        elseif($data === 'main_menu') 
        {
            $this->user->Start($callback); 
        }

        elseif($data === 'track_ticket')
        {
            $this->ticket->ShowTickets($callback);
        }

        elseif(str_starts_with($data, 'choose_ticket:'))
        {
            $ticketId = explode(':', $data)[1];
            $this->ticket->ShowTicketDetails($callback , $ticketId);
            
        }
    }


}