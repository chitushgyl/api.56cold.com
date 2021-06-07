<?php
/**
 * Created by PhpStorm.
 * User: mac
 * Date: 2020/7/24
 * Time: 11:09
 */
namespace App\Http\Api\Wxinit;
use App\Http\Controllers\Controller;
use EasyWeChat\Factory;
use DB;
class ChatController extends Controller{
    public function customerList(){
        //获取配置文件的微信信息
        $con=config('page.platform');
        $config = [
            'app_id'=>$con['app_id'],
            'secret'=>$con['secret']
        ];
        $app = Factory::officialAccount($config);

        $date=date('Y-m-d',time());
        $startTime=strtotime($date.' 09:00:00');
        $endTime=strtotime($date.' 18:00:00');
        $customer=$app->customer_service->messages($startTime, $endTime, $msgId = 1, $number = 10000);

        if($customer['number']>0){
            $recordlist=$customer['recordlist'];
            for($i=0;$i<$customer['number'];$i++){
                $where['time']=$recordlist[$i]['time'];
                $where['openid']=$recordlist[$i]['openid'];
                $where['type']='customer';
                $user_chat=DB::table('wx_chat')->where($where)->first();
                if(empty($user_chat)){
                    $chat['self_id']=generate_id('chat_');
                    $chat['content']=$recordlist[$i]['text'];
                    $chat['openid']=$recordlist[$i]['openid'];
                    $chat['worker']=$recordlist[$i]['worker'];
                    $chat['time']=$recordlist[$i]['time'];
                    $chat['create_time']=date('Y-m-d H:i:s',time());
                    $chat['type']='customer';
                    DB::table('wx_chat')->where($where)->insert($chat);
                }
            }
        }
        die('成功获取聊天记录');
    }
}
