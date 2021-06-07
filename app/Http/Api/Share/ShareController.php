<?php
namespace App\Http\Api\Share;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use EasyWeChat\Factory;
use App\Models\Group\SystemGroup;
use App\Models\Shop\ErpShopGoods;
use App\Models\User\UserCouponGive;
class ShareController extends Controller
{
    /**
     * 用户授权登陆微信      /share/share
     *
     * 回调结果：200  注册成功
     *          300  注册失败,数据库没有写入
     *          100  缺少必要的参数
     *
     *回调数据：  用户信息
     */


	public function share(Request $request)
    {
        $pathname       =$request->header('pathname');
        $referer        =$request->header('Signature-Url');

		$user_info      =$request->get('user_info');
        $group_code     =$request->get('group_code');
        $select=['front_name','group_code','group_name','company_image_url','floating_flag','father_group_code'];
        $systemGroupSelect=['group_code','wx_pay_info','front_name'];
        $systemGroupShareSelect=['group_code','share_img','share_title','share_content'];

        $where1['group_code']=$group_code;
        $group_info=SystemGroup::with(['systemGroup' => function($query)use($systemGroupSelect) {
            $query->select($systemGroupSelect);
        }])->with(['systemGroupShare' => function($query)use($systemGroupShareSelect) {
            $query->select($systemGroupShareSelect);
        }])->where($where1)
            ->select($select)
            ->first();

        //dump($group_info->toArray());
        //print_r($user_info);
        //dump($user_info->toArray());
        //$share_info=json_decode($group_info->share_info,true);
		
		if($group_info->systemGroupShare){
			$share['share_title']   ='['.$group_info->group_name.']'.$group_info->systemGroupShare->share_title;               //分享的标题
			$share['share_content'] =$group_info->systemGroupShare->share_content;
            $share['share_img']     = img_for($group_info->systemGroupShare->share_img,'one');

		}else{
			$share['share_title']='共享平台';//分享的标题
			$share['share_content']='共享平台';   //分享的内容
			$share['share_img']='https://bloodcity.oss-cn-beijing.aliyuncs.com/images/2020-07-17/3bd7e0290fc6255ec2bde678dd5e2ff6.png';//图片
		}

        //dd($share);exit;
		$jssdk = $this->jssdk($referer,$group_info);


	        if(in_array($pathname,config('page.remove_url'))){
				
				$pathnameUrl=str_replace($pathname,"/profile",$referer);
        	}else{
            	$pathnameUrl=$jssdk['url'];
        	}
			
		//如果这个链接中包含？ 则拼接的时候用& 符号
		//print_r($pathnameUrl);exit;
		//如果包含了promo_code 的值，要把值替换掉，如果没有则追加一个上去
		if(strpos($pathnameUrl,'promo_code') !== false){
			//这里是有，包含了他了，需要替换这个值的，那么我要拿到他的这个值才可以				应该是不替换把？
			$share['share_url']=$pathnameUrl;//分享的跳转地址，需要拼接数据
		}else{
			if(strpos($pathnameUrl,'?') !== false){
				$share['share_url']=$pathnameUrl.'&promo_code='.$user_info->userTotal->promo_code;//分享的跳转地址，需要拼接数据
			}else{
				$share['share_url']=$pathnameUrl.'?promo_code='.$user_info->userTotal->promo_code;//分享的跳转地址，需要拼接数据
			}
		}
		
		
		$do['type']=null;
		//准备做一些差异化，链接中如果带有商品的ID的，则图片使用
		if(strpos($referer,'good_id') !== false){
				//则拿取?后面是有的东西作为变量
				$abcc=trim(strrchr($referer, '?'),'?');
				// 分割数组
				$eriy=explode('&',$abcc);
				foreach($eriy as $k => $v){
					if(strpos($v,'good_id') !== false){
						//找到包含good_id 的属性，在进行分割
						$eriyssss=explode('=',$v);
					}
				}
				
				$do['type']='good';
				$do['good_id']=$eriyssss[1];
				
			}
		
		//这里是送券的逻辑
		if(strpos($referer,'coupon_give_id') !== false){
				//则拿取?后面是有的东西作为变量
				$abcc=trim(strrchr($referer, '?'),'?');
				// 分割数组
				$eriy=explode('&',$abcc);
				foreach($eriy as $k => $v){
					if(strpos($v,'coupon_give_id') !== false){
						//找到包含good_id 的属性，在进行分割
						$eriyssss=explode('=',$v);
					}
				}
				$do['type']='coupon_give';
				$do['coupon_give_id']=$eriyssss[1];
			}
			
			
		switch($do['type']){
			case 'good':
				//去抓取商品的数据
				$good_where=[
					['self_id','=',$do['good_id']],
					['delete_flag','=','Y'],
					['good_status','=','Y'],
				];

				$select=['good_title', 'good_describe','thum_image_url'];
                $good_info=ErpShopGoods::where($good_where)->select($select)->first();
                $share['share_title']='['.$group_info->group_name.']'.$good_info->good_title;//分享的标题
                $share['share_img']     = img_for($group_info->thum_image_url,'one');
                $share['share_content']=$user_info->token_name." 推荐：".$good_info->good_title;   //分享的内容
		
		
			break;
			
			
			case 'coupon_give':
				//去抓取商品的数据
				$coupon_where=[
					['self_id','=',$do['coupon_give_id']],
				];
                $select=['self_id','type'];
                $selectList=['self_id','give_id','get_user_id','get_time','user_coupon_id','sort','get_user_id'];

                $coupon_info=UserCouponGive::with(['userCouponGiveList' => function($query)use($selectList) {
                    $query->select($selectList);
                    $query->orderBy('sort','asc');
                }])->where($coupon_where)->select($select)->first();

                $count=$coupon_info->userCouponGiveList->count();
			
			switch($coupon_info->type){
				case 'give':
				//赠送
				
				$share['share_title']=$user_info->token_name.'为你点赞！';
				$share["share_content"]=$user_info->token_name.'赠送全城赞助券'.$count.'张'.'，点击后即记入你的账户！';
				$share['share_img']= 'https://bloodcity.oss-cn-beijing.aliyuncs.com/images/2020-07-17/3bd7e0290fc6255ec2bde678dd5e2ff6.png';//图片
				
				break;
				case 'transfer':
				//转让
				
				$share['share_title']=$user_info->token_name.'为你点赞！';
				$share["share_content"]=$user_info->token_name.'转让全城赞助券'.$count.'张'.'，点击查看详情！';
				$share['share_img']= 'https://bloodcity.oss-cn-beijing.aliyuncs.com/images/2020-07-17/3bd7e0290fc6255ec2bde678dd5e2ff6.png';//图片
				
		
		
				break;
				case 'hongbao':
				//红包
				
				$share['share_title']=$user_info->token_name.'发了个优惠券红包，快去抢吧！';
				$share["share_content"]=$user_info->token_name.'发了个全程赞助券红包，点击领取记入你的账户！';
				$share['share_img']= 'https://bloodcity.oss-cn-beijing.aliyuncs.com/images/2020-07-17/3bd7e0290fc6255ec2bde678dd5e2ff6.png';//图片
		
		
				
				break;
				
			}
			
			break;
			
		}
		
		
		
		//print_r($share);exit;
		

		$msg['code']=200;
		$msg['msg']='数据拉取成功！';
		$msg['share_config']=(object)$jssdk;
		$msg['share_data']=(object)$share;	
	        return $msg;
    }
	
	public function jssdk($url,$group_info)
    {

        $group_info_go=json_decode($group_info->systemGroup->wx_pay_info,true);

		$config = [
			'app_id' => $group_info_go['app_id'],
			'secret' => $group_info_go['secret'],
		];

		$app = Factory::officialAccount($config);
		$app->jssdk->setUrl($url);
		try {
           		 $jssdk = $app->jssdk->buildConfig(
			array(
			'updateAppMessageShareData', 
			'updateTimelineShareData', 
			'onMenuShareTimeline', 
			'onMenuShareAppMessage'
			), 
			$debug = true, 
			$beta = false, 
			$json = false
			);
 	       } catch (\Exception $e) {
        	    var_dump($e->getMessage());die;
        	}
		unset($jssdk['timestamp']);
                $jssdk['timeStamp']=strval(time());
		return $jssdk;
	}
	
}
