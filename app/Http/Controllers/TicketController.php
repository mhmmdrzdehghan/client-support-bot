<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Message;
use App\Models\Tickets;
use App\Models\User;
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

            Message::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'message' => $text
            ]);

            // پاک کردن state
            Cache::forget("UserCategoryId:{$chatId}");
            Cache::forget("UserPriority:{$chatId}");
            Cache::forget("UserState:{$chatId}");

            $this->sendMessage($chatId, "✅ تیکت شما با موفقیت ثبت شد.", []);


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
