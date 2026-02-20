<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function Start($data)
    {
        try
        {
            DB::beginTransaction();
            $first_name = $data['message']['from']['first_name'];
            $chatid = $data['message']['chat']['id'];
            $user = User::where('chat_id' , $chatid)->first();
            $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🎫 ثبت تیکت', 'callback_data' => 'new_ticket'],
                    ['text' => '📂 پیگیری تیکت', 'callback_data' => 'track_ticket']
                ],
                [
                    ['text' => '❓ سوالات متداول', 'callback_data' => 'faq'],
                    ['text' => '🤖 دستیار هوشمند', 'callback_data' => 'ai_assistant']
                ],
                [
                    ['text' => '📚 آموزش‌ها', 'callback_data' => 'tutorials'],
                    ['text' => '📢 اطلاعیه‌ها', 'callback_data' => 'announcements']
                ],
                [
                    ['text' => '👤 حساب کاربری', 'callback_data' => 'account']
                ]
            ]
        ];
            if($user)
            {
                $text = "{{$user->name}}لطفا از منو زیر استفاده کنید";
            }
            else
            {
                $user =  User::create(['name'=>$first_name , 'chat_id'=>$chatid]);
                $this->HandleRole($user);
                $text = " {{$user->name}} خوش آمدید لطفا از منو زیر استفاده کنید";                
            }


            $this->sendMessage($chatid  , $text ,$keyboard);
            DB::commit();


        }catch (\Exception $e){
            DB::rollBack();
            Log::info('UserController function start',[
                'error' =>  $e->getMessage(),
                'trace' =>  $e->getTraceAsString(),
                'line'  =>  $e->getLine(),
            ]);
        }
    }

    private function HandleRole($user)
    {
        UserRole::create(['user_id'=>$user->id , 'role_id'=>2]);
    }

    public function sendMessage($chatId , $text , $markup)
    {
        $token =  env('BALE_BOT_TOKEN');
        $url = "https://tapi.bale.ai/{$token}/sendMessage";
        $respons =  Http::post($url  , ['chat_id'=>$chatId ,'text'=> $text ,'reply_markup'=> $markup]);

        Log::info($respons);
    }
}
