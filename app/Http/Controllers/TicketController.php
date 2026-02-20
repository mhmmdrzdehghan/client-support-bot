<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Message;
use App\Models\Tickets;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;


class TicketController extends Controller
{
    public function storeTicket($chatId ,$text)
    {
        try
        {
            DB::beginTransaction();

            $categoryId = Cache::get("UserCategoryId:{$chatId}");
            $priority = Cache::get("UserPriority:{$chatId}");

            $user = User::where('chat_id' , $chatId)->first();

            // ساخت تیکت و پیام
            $ticket = Tickets::create([
                'user_id' => $user->id,
                'category_id' => $categoryId,
                'priority' => $priority,
                'status' => 'open'
            ]);

            $message =  $this->StoreMessge($ticket , $user , $text , $chatId);

            $inlineKeyboard = [
                [
                    [
                        'text' => '🏠 Back to Main Menu',
                        'callback_data' => 'main_menu'
                    ]
                ]
            ];

            $replyMarkup = [
                'inline_keyboard' => $inlineKeyboard
            ];

            $this->sendMessage($chatId, " ✅ تیکت شما با موفقیت ثبت شد.\n تیکت شما در وضعیت باز قراردارد پس از برسی از طریق همین بات ادمین شمارا درجریان خواهد گذاشت", $replyMarkup);
            $this->AlaramTheAdmin($ticket , $message);

            

            DB::commit();


        }catch (\Exception $e){
            DB::rollBack();
            Log::info('TicketController function createTicket',[
                'error' =>  $e->getMessage(),
                'trace' =>  $e->getTraceAsString(),
                'line'  =>  $e->getLine(),
            ]);
        }    
    }

    private function StoreMessge($ticket , $user , $text , $chatId)
    {
        $message = Message::create([
            'ticket_id' => $ticket->id,
            'sender_id' => $user->id,
            'message' => $text
        ]);

        // پاک کردن state
        Cache::forget("UserCategoryId:{$chatId}");
        Cache::forget("UserPriority:{$chatId}");
        Cache::forget("UserState:{$chatId}");

        return $message;
    }



    private function AlaramTheAdmin($ticket, $message)
    {
        $userrole = UserRole::where('role_id', 1)->first();
        $admin = User::where('id', $userrole->user_id)->first();
        $chatIdAdmin = $admin->chat_id;

        $category = Category::find($ticket->category_id);
        $user = User::find($message->sender_id);

        $text = "📨 یک تیکت جدید از کاربر دریافت شد!

    👤 اطلاعات کاربر:
    - نام: {$user->name}
    - Telegram ID: {$user->chat_id}

    🎫 شناسه تیکت:
    #{$ticket->id}

    📂 دسته‌بندی:
    {$category->name}

    ⚡ اولویت:
    {$ticket->priorty}

    📝 متن پیام:
    {$message->message}

    ⏱ تاریخ ارسال:
    {$message->created_at}

    لطفاً برای پاسخ روی دکمه زیر کلیک کنید 👇";

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '✉️ ارسال جواب به یوزر',
                        'callback_data' => "admin_answer:{$ticket->id}"
                    ]
                ]
            ]
        ];

    

        $this->sendMessage($chatIdAdmin, $text, $replyMarkup);
    }

    public function asnwereAdmin($callback ,$ticketId)
    {
        $chatId = $callback['message']['chat']['id'];

        Cache::put("AdminState:{$chatId}", "answering_ticket_{$ticketId}", 3600);

        $this->sendMessage($chatId, "✍️ لطفاً متن پاسخ را ارسال کنید:", []);
    }

    public function asnwereUser($callback ,$ticketId)
    {
        $chatId = $callback['message']['chat']['id'];

        Cache::put("UserState:{$chatId}", "agananswer_{$ticketId}", 3600);

        $this->sendMessage($chatId, "✍️ لطفاً متن پاسخ را ارسال کنید:", []);
    }

    public function StoreTicketAdmin($ticketId , $data)
    {
        $ticket = Tickets::find(intval($ticketId));

        $ticket->update(['status'=>'answer']);
        $chatId = $data['message']['chat']['id'];
        $admin  = User::where('chat_id' , $chatId)->first(); 
        $text =  $data['message']['text'];

        $chat_id = User::find($ticket->user_id)->chat_id;

        $message = Message::create([
            'ticket_id' => $ticket->id,
            'sender_id' => $admin->id,
            'message' => $text
        ]);

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '✉️ ارسال جواب به ادمین',
                        'callback_data' => "user_answer:{$ticket->id}"
                    ]
                ]
            ]
        ];

        $this->sendMessage($chat_id,"📨 پاسخ پشتیبانی:\n\n" . $text,[]);
        $this->sendMessage($chat_id,"تیکت شما در وضعیت جواب داده شده قرار دارد",$replyMarkup);


        Cache::forget("AdminState:{$chatId}");

        $this->sendMessage($chatId, "✅ پاسخ برای کاربر ارسال شد.", []);

    }

    public function StoreAnswerAganUser($ticketId , $data)
    {
        $ticket = Tickets::find(intval($ticketId));

        $ticket->update(['status'=>'pending']);
        $chatId = $data['message']['chat']['id'];
        $user  = User::where('chat_id' , $chatId)->first(); 
        $text =  $data['message']['text'];


        $message = Message::create([
            'ticket_id' => $ticket->id,
            'sender_id' => $user->id,
            'message' => $text
        ]);


        $inlineKeyboard = [
            [
                [
                    'text' => '🏠 Back to Main Menu',
                    'callback_data' => 'main_menu'
                ]
            ]
        ];

        $replyMarkup = [
            'inline_keyboard' => $inlineKeyboard
        ];

        $this->sendMessage($chatId, " ✅  شما با موفقیت ثبت شد.\n تیکت شما در وضعیت باز قراردارد پس از برسی از طریق همین بات ادمین شمارا درجریان خواهد گذاشت", $replyMarkup);
        $this->AlaramTheAdmin($ticket , $message);

 
    }


    public function SelectCategory($data)
    {
        $chatId = $data['message']['chat']['id'];
        $categories = Category::all();

        Cache::put("UserState:{$chatId}", 'SelectCategory', 3600);

        $inlineKeyboard = [];
        foreach ($categories as $category) {
            $inlineKeyboard[] = [[
                'text' => $category->name,
                'callback_data' => "choose_category:{$category->id}"
            ]];
        }

        $replyMarkup = ['inline_keyboard' => $inlineKeyboard];

        $this->sendMessage($chatId, "📂 لطفا دسته‌بندی مورد نظر را انتخاب کنید:", $replyMarkup);   
    }

    public function SelectPriority($data)
    {
        $chatId = $data['message']['chat']['id'];
        $text = $data['data'];
        $categoryId = explode(':', $text)[1];

        Cache::put("UserCategoryId:{$chatId}", intval($categoryId), 3600);
        Cache::put("UserState:{$chatId}", 'SelectPriority', 3600);

        $priorities = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'];

        $inlineKeyboard = [];
        foreach ($priorities as $key => $label) {
            $inlineKeyboard[] = [[
                'text' => $label,
                'callback_data' => "choose_priority:$key"
            ]];
        }

        $replyMarkup = ['inline_keyboard' => $inlineKeyboard];
        $this->sendMessage($chatId, "⚡ لطفا اولویت تیکت را انتخاب کنید:", $replyMarkup);
    }

    public function writeText($data)
    {
        $chatId = $data['message']['chat']['id'];
        $text = $data['data'];
        $priority = explode(':', $text)[1];

        Cache::put("UserPriority:{$chatId}", $priority, 3600);
        Cache::put("UserState:{$chatId}", 'writeText', 3600);

        $this->sendMessage($chatId, "✉ لطفا متن خود را وارد کنید:", []);
    }


    public function ShowTickets($data)
    {
        $chatId = $data['message']['chat']['id'];
        $user  = User::where('chat_id', $chatId)->first();

        if (!$user) {
            $this->sendMessage($chatId, "❌ کاربر یافت نشد.", []);
            return;
        }

        $tickets = Tickets::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($tickets->isEmpty()) {
            $this->sendMessage($chatId, "📭 شما هیچ تیکتی ثبت نکرده‌اید.", []);
            return;
        }

        $text = "📂 لیست تیکت‌های شما:\n\n";
        $inlineKeyboard = [];

        foreach ($tickets as $value) {

            $category = Category::find($value->category_id);
            $status = $this->formatStatus($value->status);
            $priority = $this->formatpriorty($value->priorty);

            $text .= "🎫 #{$value->id}\n";
            $text .= "📂 {$category->name}\n";
            $text .= "⚡ {$priority}\n";
            $text .= "📌 {$status}\n";
            $text .= "📅 {$value->created_at}\n";
            $text .= "──────────────────\n";

            $inlineKeyboard[] = [
                [
                    'text' => "🎫 مشاهده تیکت #{$value->id}",
                    'callback_data' => "choose_ticket:{$value->id}"
                ]
            ];
        }

        $replyMarkup = [
            'inline_keyboard' => $inlineKeyboard
        ];

        $this->sendMessage($chatId, $text, $replyMarkup);
    }

    



    public function ShowTicketDetails($callback)
    {
        $data = $callback['data'];
        $chatId = $callback['message']['chat']['id'];

        $ticketId = explode(':', $data)[1];

        $user = User::where('chat_id', $chatId)->first();

        $ticket = Tickets::where('id', $ticketId)
            ->where('user_id', $user->id) // امنیت
            ->first();

        if (!$ticket) {
            $this->sendMessage($chatId, "❌ تیکت مورد نظر یافت نشد.", []);
            return;
        }

        $category = Category::find($ticket->category_id);

        $status = $ticket->status; // اگر accessor ساختی
        $priority = $ticket->priorty;

        $messages = Message::where('ticket_id', $ticket->id)
            ->orderBy('created_at', 'asc')
            ->get();

        $conversation = "";

        foreach ($messages as $msg) {

            $sender = $msg->sender_id == $user->id
                ? "👤 شما"
                : "👨‍💼 پشتیبانی";

            $conversation .= "{$sender}:\n{$msg->message}\n\n";
        }

        $text = "🎫 تیکت #{$ticket->id}

    📂 دسته‌بندی: {$category->name}
    ⚡ اولویت: {$priority}
    📌 وضعیت: {$status}
    📅 تاریخ ثبت: {$ticket->created_at}

    ──────────────────

    {$conversation}";

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '✍️ ارسال پیام جدید',
                        'callback_data' => "user_answer:{$ticket->id}"
                    ]
                ],
                [
                    [
                        'text' => '🔙 بازگشت به لیست',
                        'callback_data' => "main_menu"
                    ]
                ]
            ]
        ];

        $this->sendMessage($chatId, $text, $replyMarkup);
    }



    public function formatpriorty($priorty)
    {
        return match ($priorty) {
            'low' => '🟢 کم',
            'medium' => '🟡 متوسط',
            'high' => '🔴 زیاد',
            default => 'نامشخص'
        };
    }





    private function formatStatus($status)
    {
        return match ($status) {
            'open' => '🟡 در حال بررسی',
            'pending' => '🔵 منتظر پاسخ شما',
            'answer' => '🟢 پاسخ داده شده',
            default => 'نامشخص'
        };
    }

    public function sendMessage($chatId , $text , $markup)
    {
        $token =  env('BALE_BOT_TOKEN');
        $url = "https://tapi.bale.ai/{$token}/sendMessage";
        $respons =  Http::post($url  , ['chat_id'=>$chatId ,'text'=> $text ,'reply_markup'=> $markup]);

        Log::info($respons);
    }
}
