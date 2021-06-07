<?php
/**
 * Created by PhpStorm.
 * User: mac
 * Date: 2020/3/25
 * Time: 21:04
 */
namespace App\Http\Api\Wxinit;
use App\Http\Controllers\Controller;
use App\Http\Api\Login\LoginController;
use EasyWeChat\Factory;
use EasyWeChat\Kernel\Messages\Transfer;//多客服消息转发
use DB;

use Illuminate\Support\Facades\Log;
class WeChatController extends Controller
{
    /**             /wechat
     * 处理微信的请求消息1
     *
     * @return string
     */
    public function serve(LoginController $login){
        //获取配置文件的微信信息
        $con=config('page.platform');
        $config = [
            'app_id'=>$con['app_id'],
            'secret'=>$con['secret'],
            'token' => 'weixin'
        ];
        //dd($config);
        $app = Factory::officialAccount($config);
        $userService = $app->user;
        //利用php闭包函数把配置引入体内
        $app->server->push(function($message)use($userService,$login,$con){
            $datetime=date('Y-m-d H:i:s',time());
            list($date,$time)=explode(' ',$datetime);
            switch ($message['MsgType']){
                case 'event':
                    switch ($message['Event']) {
                        case 'subscribe':  //关注事件, 扫描带参数二维码事件(用户未关注时，进行关注后的事件推送)
                            $user = $userService->get($message['FromUserName']);
                            $appid=$con['app_id'];
                            $info['ip']         = null;
                            $info['app_id']     =$appid;
                            $info['promo_code'] =null;
                            $type               ='CT_H5';
							$reg_type           ='WEIXIN';
                            $login->addUser($user,$info,$type,$reg_type);
                            $this->SubscribeStatus($message['FromUserName'],$appid,1,json_encode($message));
                            return '欢迎关注';
                            break;
                        case 'unsubscribe':  //取消关注事件
                            $appid=$con['app_id'];
                            $this->SubscribeStatus($message['FromUserName'],$appid,0,json_encode($message));
                            break;
                        case 'SCAN':  //扫描带参数二维码事件(用户已关注时的事件推送)
                            return "欢迎关注";
                            break;
                        case 'LOCATION':  //上报地理位置事件
                            return "经度: " . $message['Longitude'] . "\n纬度: " . $message['Latitude'] . "\n精度: " . $message['Precision'];
                            break;
                        case 'CLICK':  //自定义菜单事件(点击菜单拉取消息时的事件推送)
                            //return "请您稍等，客服正在向您飞来2~~";
                            switch ($message['EventKey']){
                                case 'Customer_Service':
                                    //定义状态
                                    $datimes=time();
                                    $wherels['openid']=$message['FromUserName'];
                                    $timestamp=DB::table('school_official_account')->where($wherels)->orderBy('create_time','desc')->value('timestamp');
                                    $afile=$datimes-$timestamp;
                                    if($afile >= 600){
                                        $officialAccount['openid']=$message['FromUserName'];
                                        $officialAccount['timestamp']=$datimes;
                                        $officialAccount['create_time']=$datetime;
                                        DB::table('school_official_account')->insert($officialAccount);
                                        if((date('w',strtotime($date))==6) || (date('w',strtotime($date)) == 0)){
                                            return "尊敬的用户:您好,客服在线时间为工作日9:00~18:00。请输入要咨询的问题，我们将尽快给您回复。";
                                        }else{
                                            if($time>= '09:00:00' &&  $time<= '18:00:00'){
                                                return "请您稍等，客服正在向您飞来~~";
                                            }else{
                                                return "尊敬的用户:您好,客服在线时间为工作日9:00~18:00。请输入要咨询的问题，我们将尽快给您回复。";
                                            }
                                        }
                                    }
                                    break;
                                default:
                                    return "尊敬的用户:您好,客服在线时间为工作日9:00~18:00。请输入要咨询的问题，我们将尽快给您回复。";
                                    break;
                            }
                            //return "事件KEY值: " . $message['EventKey'];
                            break;
                        case 'VIEW':  //自定义菜单事件(点击菜单拉取消息时的事件推送)
                            return "跳转URL: " . $message['EventKey'];
                            break;
                        case 'ShakearoundUserShake':
                            return 'ChosenBeacon\n' . 'Uuid: ' . $message['ChosenBeacon']['Uuid'] . 'Major: ' . $message['ChosenBeacon']['Major'] . 'Minor: ' . $message['ChosenBeacon']['Minor'] . 'Distance: ' . $message['ChosenBeacon']['Distance'];
                            break;
                        default:
                            return $message['Event'];
                            break;
                    }
                     break;
                default:
                    $this->ChatContent($message['FromUserName'],$message['Content']);
                    if((date('w',strtotime($date))==6) || (date('w',strtotime($date)) == 0)){
                        $datimes=time();
                        $wherels['openid']=$message['FromUserName'];
                        $timestamp=DB::table('school_official_account')->where($wherels)->orderBy('create_time','desc')->value('timestamp');
                        $afile=$datimes-$timestamp;
                        if($afile >= 600){
                            $officialAccount['openid']=$message['FromUserName'];
                            $officialAccount['timestamp']=$datimes;
                            $officialAccount['create_time']=$datetime;
                            DB::table('school_official_account')->insert($officialAccount);
                            return $this->MessageType($message['MsgType']);
                        }
                    }else{
                        if($time>= '09:00:00' &&  $time<= '18:00:00'){
                            return new Transfer();//多客服转发消息
                        }else{
                            $datimes=time();
                            $wherels['openid']=$message['FromUserName'];
                            $timestamp=DB::table('school_official_account')->where($wherels)->orderBy('create_time','desc')->value('timestamp');
                            $afile=$datimes-$timestamp;
                            if($afile >= 600){
                                $officialAccount['openid']=$message['FromUserName'];
                                $officialAccount['timestamp']=$datimes;
                                $officialAccount['create_time']=$datetime;
                                DB::table('school_official_account')->insert($officialAccount);
                                return $this->MessageType($message['MsgType']);
                            }
                        }
                    }
                    break;
            }
        });

        return $app->server->serve();
    }

    /**
     * 自动回复消息
     * @param $type
     * @return string
     */
    private function MessageType($type){
        switch ($type){
            case 'text':
                //自动回复的
                return "尊敬的用户:您好,客服在线时间为工作日9:00~18:00。请输入要咨询的问题，我们将尽快给您回复。";
                break;
            case 'image':
                return "尊敬的用户:您好,客服在线时间为工作日9:00~18:00。请输入要咨询的问题，我们将尽快给您回复。";
                break;
            case 'voice':
                return "尊敬的用户:您好,客服在线时间为工作日9:00~18:00。请输入要咨询的问题，我们将尽快给您回复。";
                break;
            case 'video':
                return "尊敬的用户:您好,客服在线时间为工作日9:00~18:00。请输入要咨询的问题，我们将尽快给您回复。";
                break;
            case 'shortvideo':
                return "尊敬的用户:您好,客服在线时间为工作日9:00~18:00。请输入要咨询的问题，我们将尽快给您回复。";
                break;
            case 'location':
                return "尊敬的用户:您好,客服在线时间为工作日9:00~18:00。请输入要咨询的问题，我们将尽快给您回复。";
                break;
            case 'link':
                return "尊敬的用户:您好,客服在线时间为工作日9:00~18:00。请输入要咨询的问题，我们将尽快给您回复。";
                break;
            default:
                return "尊敬的用户:您好,客服在线时间为工作日9:00~18:00。请输入要咨询的问题，我们将尽快给您回复。";
        }
    }

    /**
     * 记录用户发送的消息
     * @param $openid
     * @param $message
     */
    private function ChatContent($openid,$message){
        $chat['self_id']=generate_id('chat_');
        $chat['content']=$message;
        $chat['openid']=$openid;
        $chat['create_time']=date('Y-m-d H:i:s',time());
        $chat['type']='msg';
        DB::table('wx_chat')->insert($chat);
    }

    /**
     * 添加数据进入数据库1
     * @param $openid
     * @param $status
     * @param $message
     */
    private function SubscribeStatus($openid,$appid,$status,$message){
        $isSub['self_id']=generate_id('follow_');
        $isSub['subscribe']=$status;
        $isSub['text']=$message;
        $isSub['appid']=$appid;
        $isSub['wx_id']=$openid;
        $isSub['create_time']=date('Y-m-d H:i:s',time());
        DB::table('wx_follow')->insert($isSub);
    }
}
