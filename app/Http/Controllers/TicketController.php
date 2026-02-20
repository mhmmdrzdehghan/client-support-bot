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

            $message = Message::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'message' => $text
            ]);

            // پاک کردن state
            Cache::forget("UserCategoryId:{$chatId}");
            Cache::forget("UserPriority:{$chatId}");
            Cache::forget("UserState:{$chatId}");

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

            $this->sendMessage($chatId, "✅ تیکت شما با موفقیت ثبت شد.", $replyMarkup);
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

    private function AlaramTheAdmin($ticket,$message)
    {
        $userrole = UserRole::where('role_id' , 1)->first();
        $admin = User::where('id' , $userrole->user_id)->first();
        $chatIdAdmin = $admin->chat_id;
        $category = Category::find($ticket->category_id);


        $user = User::find($message->sender_id);


        $text = "📨 یک تیکت جدید از کاربر دریافت شد!
                👤 اطلاعات کاربر:
                - نام: {$user->name}
                📂 دسته‌بندی تیکت:
                {$category->name}
                ⚡ اولویت:
                {$ticket->priorty}
                📝 متن پیام:
                {$message->message}
                📎 فایل‌های پیوست (در صورت وجود):
                [Attachment Links / File IDs]
                ⏱ تاریخ ارسال:
                {$message->created_at}
                 لطفاً پاسخ مناسب را آماده کرده و به کاربر ارسال کنید.";




        $this->sendMessage($chatIdAdmin, $text, []);




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

    public function sendMessage($chatId , $text , $markup)
    {
        $token =  env('BALE_BOT_TOKEN');
        $url = "https://tapi.bale.ai/{$token}/sendMessage";
        $respons =  Http::post($url  , ['chat_id'=>$chatId ,'text'=> $text ,'reply_markup'=> $markup]);

        Log::info($respons);
    }
}
