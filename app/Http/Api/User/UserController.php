<?php
namespace App\Http\Api\User;
use App\Models\Group\SystemAdmin;
use App\Models\Group\SystemAuthority;
use App\Models\Group\SystemGroup;
use App\Models\Group\SystemGroupAuthority;
use App\Models\Log\LogLogin;
use App\Models\Tms\AppVersionConfig;
use App\Models\Tms\TmsAttestation;
use App\Models\Tms\TmsCar;
use App\Models\Tms\TmsCarriage;
use App\Models\Tms\TmsOrder;
use App\Models\Tms\TmsOrderDispatch;
use App\Models\User\UserBank;
use App\Models\User\UserCapital;
use App\Models\User\UserTotal;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\SysFoot;
use App\Models\User\UserCoupon;
use App\Models\User\UserTrack;
use App\Models\Shop\ShopOrder;
use App\Models\User\UserIdentity;
use App\Tools\CartNumber;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\DetailsController as Details;
use App\Http\Controllers\AttestationController as Attestation;

class UserController extends Controller{
    /**
     * 用户底部导航      /user/foot
     */
    public function foot(Request $request,CartNumber $cartNumber){
        $user_info          =$request->get('user_info');
        $project_type       =$request->get('project_type');
        $group_info         =$request->get('group_info');
        $group_code         =$group_info->group_code??config('page.platform.group_code');
//        $project_type       ='dispatcher';
        /**初始化一下数据**/
        $info          =null;
        $select=['name','type','path','active_img','inactive_img','app_path'];
        $user_foot_where=[
            ['use_flag','=','Y'],
            ['delete_flag','=','Y'],
            ['project_type','=',$project_type],
        ];

        if($user_info){
            $foot_in=json_decode($user_info->foot_info);
        }else{
            $foot_in=null;
        }

        if($foot_in){
            $info = SysFoot::where($user_foot_where)->whereIn('id',$foot_in)->select($select)->orderBy('sort','asc')->get();
        }else{
            $info = SysFoot::where($user_foot_where)->select($select)->orderBy('sort','asc')->get();
        }
        foreach ($info as $k => $v){
            $v->number=0;
            if($v->type=='cart' && $user_info){
                $v->number=$cartNumber->cart_number($user_info,$group_code);
            }
            $v->active_img      =img_for($v->active_img,'no_json');
            $v->inactive_img    =img_for($v->inactive_img,'no_json');
        }

        $msg['code']=200;
        $msg['msg']='数据拉取成功！';
        $msg['data']=$info;
        //dd($msg['data']->toArray());
        return $msg;

    }
    /**
     * 用户个人中心      /user/owm
     */
    public function owm(Request $request){
		//print_r($request->all());exit;
		$user_info      =$request->get('user_info');
        $now_time       =date('Y-m-d H:i:s',time());
        $project_type       =$request->get('project_type');
//        $project_type = 'business';
//        dd($project_type);
        /** 如果用户的身份过来是user   ，而user_identity  是空的话，说明这个用户没有任何身份，则需要在身份那里添加一个默认的用户身份给他**/
        if ($user_info){
            if ($project_type == 'user'){
                if($project_type=='user' && count((array)$user_info->userIdentity)<=0){
                    $user_identity = UserIdentity::where([['total_user_id','=',$user_info->total_user_id],['type','=','user'],['delete_flag','=','Y']])->first();

                    $data['total_user_id']      =$user_info->total_user_id;
                    $data['type']               ='user';
                    $data['create_user_id']     = '1111111111';
                    $data['create_user_name']   = $user_info->total_user_id;
                    if ($user_identity){
                        UserIdentity::where([['total_user_id','=',$user_info->total_user_id],['type','=','user'],['delete_flag','=','Y']])->update($data);
                    }else{
                        $data['self_id']            = generate_id('identity_');
                        $data['create_time']        = $data['update_time'] = date('Y-m-d H:i:s',time());
                        UserIdentity::insert($data);
                    }

                }
            }elseif($project_type == 'carriage'){
                if($project_type=='carriage' && count((array)$user_info->userIdentity) <= 0){
                    $user_identity = UserIdentity::where([['total_user_id','=',$user_info->total_user_id],['type','=','carriage'],['delete_flag','=','Y']])->first();
//                    $data['self_id']            = generate_id('identity_');
                    $data['total_user_id']      =$user_info->total_user_id;
                    $data['type']               ='carriage';
                    $data['create_user_id']     = '2222222';
                    $data['create_user_name']   = $user_info->total_user_id;

                    if ($user_identity){
                        UserIdentity::where([['total_user_id','=',$user_info->total_user_id],['type','=','carriage'],['delete_flag','=','Y']])->update($data);
                    }else{
                        $data['self_id']            = generate_id('identity_');
                        $data['create_time']        = $data['update_time'] = date('Y-m-d H:i:s',time());
                        UserIdentity::insert($data);
                    }
                }
            }

        }


//        dump($project_type);

        $project_type2=$project_type.'_owm';
//        dd($user_info);
        if($user_info){
            switch ($user_info->userTotal->grade_id){
                case '3':
                    $user_info->grade_name='代理商';
                    break;
                case '4':
                    $user_info->grade_name='运营商';
                    break;
                case '5':
                    $user_info->grade_name='分公司';
                    break;
                default:
                    $user_info->grade_name=null;
                    break;
            }
        }


        $select=['id','node','name','type','path','inactive_img','list_flag','number_flag','app_path'];
        $user_foot_where=[
            ['use_flag','=','Y'],
            ['delete_flag','=','Y'],
            ['level','=','1'],
            ['project_type','=',$project_type2],
        ];
        $user_foot_where2=[
            ['use_flag','=','Y'],
            ['delete_flag','=','Y'],
        ];

        $info = SysFoot::with(['sysFoot' => function($query)use($select,$user_foot_where2) {
            $query->where($user_foot_where2);
            $query->select($select);
            $query->orderBy('sort','asc');
        }])->where($user_foot_where)->select($select)->orderBy('sort','asc')->get();
//        dd($info);
        foreach ($info as $k => $v){

            $v->inactive_img=img_for($v->inactive_img,'no_json');
            switch ($v->type){
                case 'message':

                    if($user_info){
                        if($user_info->userCapital->money>1000000){
                            $money['wallet']=number_format($user_info->userCapital->money/1000000, 2).'万';                 //用户余额
                        }else{
                            $money['wallet']=number_format($user_info->userCapital->money/100, 2);                          //用户余额
                        }

                        if($user_info->userCapital->integral>1000000){
                            $money['integral']=number_format($user_info->userCapital->integral/1000000, 2).'万';           //用户余额
                        }else{
                            $money['integral']=number_format($user_info->userCapital->integral/100, 2);                    //用户余额
                        }

                        //拉取用户的优惠券，先把过去的券的状态变一下
                        $user_coupon_where_do=[
                            ['total_user_id','=',$user_info->total_user_id],
                            ['coupon_status','=','unused'],
                            ['time_end','<',$now_time],
                        ];
                        //将使用时间超过现在时间的券的状态变成过期的状态
                        $coupon_data['coupon_status']='stale';
                        $coupon_data['update_time']=$now_time;
                        UserCoupon::where($user_coupon_where_do)->update($coupon_data);

                        $user_coupon_where=[
                            ['total_user_id','=',$user_info->total_user_id],
                            ['coupon_status','=','unused'],
                        ];
                        $money['coupon']=UserCoupon::where($user_coupon_where)->count();

                        $user_track_where=[
                            ['total_user_id','=',$user_info->total_user_id],
                            ['delete_flag','=','Y'],
                        ];
                        $user_track_where2=[
                            ['good_status','=','Y'],
                            ['delete_flag','=','Y'],
                            ['sell_start_time','<',$now_time],
                            ['sell_end_time','>',$now_time],
                        ];


                        $money['track']=UserTrack::wherehas('erpShopGoods',function($query)use($user_track_where2){
                            $query->where($user_track_where2);
                        })->where($user_track_where)->count();

                    }

                    foreach ($v->sysFoot as $kk => $vv){
                        $vv->inactive_img=img_for($vv->inactive_img,'no_json');
                        $vv->number=$money[$vv->type]??0;
                    }
                    break;

                case 'order':
                    /** 初始化一下*/
                    foreach ($v->sysFoot as $kkk => $vvv){
                        $vvv->inactive_img=img_for($vvv->inactive_img,'no_json');
                        $vvv->number=0;
                    }

                    if($user_info){
                        $user_order_where=[
                            ['total_user_id','=',$user_info->total_user_id],
                            ['read_flag','=','N'],
                        ];

                        $order_number=ShopOrder::where($user_order_where)->select('pay_status',DB::raw('count(*) as num'))->groupBy('pay_status')->get();
                        if($order_number){
                            foreach ($order_number as $kk => $vv){
                                $abcd='status'.$vv->pay_status;
                                foreach ($v->sysFoot as $kkk => $vvv){
                                    if($vvv->type == $abcd){
                                        $vvv->number=$vv->num;
                                    }
                                }
                            }
                        }
                    }

                    break;

                case 'user_order':
                     /** 初始化一下*/
                    foreach ($v->sysFoot as $kkk => $vvv){
                        $vvv->inactive_img=img_for($vvv->inactive_img,'no_json');
                        $vvv->number=0;
                    }

                    if($user_info){
                        if($user_info->type == 'user'){
                            $user_order_where=[
                                ['total_user_id','=',$user_info->total_user_id],
                                ['read_flag','=','N'],
                            ];
                            $order_number=TmsOrder::where($user_order_where)->select('order_status',DB::raw('count(*) as num'))->groupBy('order_status')->get();

                            if($order_number){
                                foreach ($order_number as $kk => $vv){
                                    $abcd='status';
                                    if ($vv->order_status == 1){
                                        $abcd='status1';
                                    }
                                    if ($vv->order_status == 2){
                                        $abcd='status2';
                                    }
                                    if ($vv->order_status == 3 || $vv->order_status == 4 || $vv->order_status == 5){
                                        $abcd='status5';
                                    }
                                    if ($vv->order_status == 7){
                                        $abcd='status7';
                                    }
                                    foreach ($v->sysFoot as $kkk => $vvv){
                                        if($vvv->type == $abcd){
                                            $vvv->number += $vv->num;
                                        }
                                    }
                                }
                            }
                        }

                        if ($vvv->number >99){
                            $vvv->number = '99+';
                        }
                    }
                    break;
//                case 'carriers_order':
//                    foreach ($v->sysFoot as $kkk => $vvv){
//                        $vvv->inactive_img=img_for($vvv->inactive_img,'no_json');
//                        $vvv->number=0;
//                    }
//                    if ($user_info){
//                        $user_order_where=[
//                            ['company_id','=',$user_info->company_id],
//                            ['use_flag','=','Y'],
//                            ['delete_flag','=','Y']
//                        ];
//                        $order_number=TmsCarriage::where($user_order_where)->select('order_status',DB::raw('count(*) as num'))->groupBy('order_status')->get();
//
//                        if($order_number){
//                            foreach ($order_number as $kk => $vv){
//                                $abcd='status';
//                                if ($vv->order_status == 1){
//                                    $abcd = 'status1';
//                                }
//                                if ($vv->order_status == 2 || $vv->order_status == 3){
//                                    $abcd = 'status2';
//                                }
////                                if ($vv->order_status == 4){
////                                    $abcd = 'status3';
////                                }
//                                foreach ($v->sysFoot as $kkk => $vvv){
//                                    if($vvv->type == $abcd){
//                                        $vvv->number+=$vv->num;
//                                    }
//                                }
//                            }
//                        }
//                    }
//
//                    break;
//                case 'customer_order':
//                    foreach ($v->sysFoot as $kkk => $vvv){
//                        $vvv->inactive_img=img_for($vvv->inactive_img,'no_json');
//                        $vvv->number=0;
//                    }
//                    $user_order_where=[
//                        ['company_id','=',$user_info->company_id],
//                        ['read_flag','=','N'],
//                    ];
//                    $order_number=TmsOrder::where($user_order_where)->select('order_status',DB::raw('count(*) as num'))->groupBy('order_status')->get();
////                    dump($order_number->toArray());
//                    if($order_number){
//                        foreach ($order_number as $kk => $vv){
//                            $abcd='status';
//                            if ($vv->order_status == 3){
//                                $abcd='status2';
//                            }
//                            if ($vv->order_status == 4 || $vv->order_status == 5){
//                                $abcd='status3';
//                            }
////                            if ($vv->order_status == 6){
////                                $abcd='status4';
////                            }
//                            if ($vv->order_status == 7){
//                                $abcd='status5';
//                            }
//                            foreach ($v->sysFoot as $kkk => $vvv){
//                                if($vvv->type == $abcd){
//                                    $vvv->number+=$vv->num;
//                                }
//                            }
//                        }
//                    }
//                    break;
                case 'TMS3PL_order':
                    foreach ($v->sysFoot as $kkk => $vvv){
                        $vvv->inactive_img=img_for($vvv->inactive_img,'no_json');
                        $vvv->number=0;
                    }
                    if ($user_info){
                        $user_order_where=[
                            ['group_code','=',$user_info->group_code],
                            ['read_flag','=','N'],
                        ];
                        $order_number=TmsOrder::where($user_order_where)->select('order_status',DB::raw('count(*) as num'))->groupBy('order_status')->get();

                        if($order_number){
                            foreach ($order_number as $kk => $vv){
                                $abcd='status';
                                if ($vv->order_status == 3){
                                    $abcd='status1';
                                }
                                if ($vv->order_status == 4 || $vv->order_status == 5){
                                    $abcd='status2';
                                }
//                            if ($vv->order_status == 6){
//                                $abcd='status3';
//                            }
                                if ($vv->order_status == 7){
                                    $abcd='status4';
                                }

                                foreach ($v->sysFoot as $kkk => $vvv){
                                    if($vvv->type == $abcd){
                                        $vvv->number +=$vv->num;
                                    }
                                }
                            }
                        }
                    }
                    if ($vvv->number >99){
                        $vvv->number = '99+';
                    }
                    break;
                case 'company_order':
                    foreach ($v->sysFoot as $kkk => $vvv){
                        $vvv->inactive_img=img_for($vvv->inactive_img,'no_json');
                        $vvv->number=0;
                    }
                    if ($user_info){
                        $user_order_where=[
                            ['group_code','=',$user_info->group_code],
                            ['read_flag','=','N'],
                        ];
                        $order_number=TmsOrder::where($user_order_where)->select('order_status',DB::raw('count(*) as num'))->groupBy('order_status')->get();
                        if($order_number){
                            foreach ($order_number as $kk => $vv){
                                $abcd='status';
                                if ($vv->order_status == 1){
                                $abcd='status1';
                                }
                                if ($vv->order_status == 2){
                                    $abcd='status2';
                                }
                                if ($vv->order_status == 3 || $vv->order_status == 4 || $vv->order_status == 5){
                                    $abcd='status5';
                                }
                                if ($vv->order_status == 7){
                                    $abcd='status7';
                                }

                                foreach ($v->sysFoot as $kkk => $vvv){
                                    if($vvv->type == $abcd){
                                        $vvv->number +=$vv->num;
                                    }
                                }
                            }
                        }
                    }
                    if ($vvv->number >99){
                        $vvv->number = '99+';
                    }
                    break;
                case 'TMS3PL_order_take':
                    foreach ($v->sysFoot as $kkk => $vvv){
                        $vvv->inactive_img=img_for($vvv->inactive_img,'no_json');
                        $vvv->number=0;
                    }
                    if ($user_info){
                        $user_order_where=[
                            ['receiver_id','=',$user_info->group_code],
                        ];
                        $order_number=TmsOrderDispatch::where($user_order_where)->select('order_status',DB::raw('count(*) as num'))->groupBy('order_status')->get();

                        if($order_number){
                            foreach ($order_number as $kk => $vv){
                                $abcd='status';
                                if ($vv->order_status == 2 || $vv->order_status == 3){
                                    $abcd='status1';
                                }
                                if ($vv->order_status == 4 || $vv->order_status == 5){
                                    $abcd='status2';
                                }
//                            if ($vv->order_status == 6){
//                                $abcd='status3';
//                            }
                                foreach ($v->sysFoot as $kkk => $vvv){
                                    if($vvv->type == $abcd){
                                        $vvv->number += $vv->num;
                                    }
                                }
                            }
                        }
                    }
                    if ($vvv->number >99){
                        $vvv->number = '99+';
                    }
                    break;

//                case 'driver_order':
//                    foreach ($v->sysFoot as $kkk => $vvv){
//                        $vvv->inactive_img=img_for($vvv->inactive_img,'no_json');
//                        $vvv->number=0;
//                    }
//                    break;
                case 'carriage_order':
                    foreach ($v->sysFoot as $kkk => $vvv){
                        $vvv->inactive_img=img_for($vvv->inactive_img,'no_json');
                        $vvv->number=0;
                    }
                    if ($user_info){
                        $user_order_where=[
                            ['receiver_id','=',$user_info->total_user_id],
                        ];
                        $order_number=TmsOrderDispatch::where($user_order_where)->select('order_status',DB::raw('count(*) as num'))->groupBy('order_status')->get();

                        if($order_number){
                            foreach ($order_number as $kk => $vv){
                                $abcd='status';
                                if ($vv->order_status == 2 || $vv->order_status == 3){
                                    $abcd='status1';
                                }
                                if ($vv->order_status == 4 || $vv->order_status == 5){
                                    $abcd='status2';
                                }
//                            if ($vv->order_status == 6){
//                                $abcd='status3';
//                            }
                                foreach ($v->sysFoot as $kkk => $vvv){
                                    if($vvv->type == $abcd){
                                        $vvv->number += $vv->num;
                                    }
                                }
                            }
                        }
                    }
                    if ($vvv->number >99){
                        $vvv->number = '99+';
                    }
                    break;
                case 'attestation':
                    if ($user_info){
                        if ($user_info->type == 'TMS3PL' ||$user_info->type == 'company' || $user_info->type == 'business' || $user_info->type == 'dispatcher'){
                            $v->title_show = $user_info->group_name;
                        }else{
                            $v->title_show = '企业认证，免费开放企业权限';
                        }
                    }else{
                        $v->title_show = '企业认证，免费开放企业权限';
                    }

                    break;
                case 'list':
                    $car_number = $money = $bank_number = 0;
                    if ($user_info){
                        if ($user_info->type == 'TMS3PL' || $user_info->type == 'company' || $user_info->type == 'business' || $user_info->type == 'dispatcher'){
                            $car_where = [
                                ['group_code','=',$user_info->group_code],
                                ['delete_flag','=','Y']
                            ];
                        }else{
                            $car_where = [
                                ['total_user_id','=',$user_info->total_user_id],
                                ['delete_flag','=','Y']
                            ];
                        }
                        $car_number = TmsCar::where($car_where)->count();


                        if ($user_info->type == 'TMS3PL' || $user_info->type == 'company' || $user_info->type == 'business' || $user_info->type == 'dispatcher'){
                            $bank_where = [
                                ['group_code','=',$user_info->group_code],
                                ['delete_flag','=','Y']
                            ];
                        }else{
                            $bank_where = [
                                ['total_user_id','=',$user_info->total_user_id],
                                ['delete_flag','=','Y']
                            ];
                        }
                        $bank_number = UserBank::where($bank_where)->count();
                    }

                    if ($user_info){
//                        ($user_info->type == 'TMS3PL' || $user_info->type == 'company')
                        if ($user_info->type == 'user' ||$user_info->type == 'carriage'){
//                            if($user_info->userCapital->money>1000000){
//                                $user_info->userCapital->money = $money =number_format($user_info->userCapital->money/1000000, 2).'万';                 //用户余额
//                            }else{
//                                $user_info->userCapital->money = $money =number_format($user_info->userCapital->money/100, 2);                          //用户余额
//                            }
                            $user_info->userCapital->money = $money = number_format($user_info->userCapital->money/100,2);
//                            dd($money);
                        }else{
                            $admin_where=[
                                ['group_code','=',$user_info->group_code],
                                ['delete_flag','=','Y'],
                            ];
                            $select_capital=['self_id','money'];
                            $money_info=UserCapital::where($admin_where)->select($select_capital)->first();
                            $user_info->userCapital->money = $money = number_format($money_info->money/100,2);
//                            if($money_info->money>=1000000){
//                                $user_info->userCapital->money = $money =number_format($money_info->money/1000000, 2).'万';                 //用户余额
//                            }else{
//                                $user_info->userCapital->money = $money =number_format($money_info->money/100, 2);                          //用户余额
//                            }
                        }
                    }
//                    dd($money);
                    foreach ($v->sysFoot as $kkk => $vvv){
                        if($vvv->type == 'car'){
                            $vvv->number = $car_number;
                        }elseif($vvv->type == 'money'){
//                            $vvv->number = number_format($money,2);
                            $vvv->number = $money;
                        }elseif($vvv->type == 'bank'){
                            $vvv->number = $bank_number;
                        }elseif($vvv->type == 'coupon'){
                            $vvv->number = 0;
                        }else{
                            $vvv->number = 0;
                        }

                    }
                    break;
//                case '':
//                    break;
                default:

                    foreach ($v->sysFoot as $kkk => $vvv){
                        $vvv->inactive_img=img_for($vvv->inactive_img,'no_json');
                        $vvv->number=0;
                    }

                    break;

            }
        }
//        dd($info->toArray());
        $msg['code']=200;
        $msg['msg']='数据拉取成功';
        $msg['data']=$user_info;
        $msg['info']=$info;
        return $msg;
    }

    /**校验登录**/
   public function check_user(Request $request){
       $user_info      =$request->get('user_info');
       $msg['code']=200;
       $msg['msg']='数据拉取成功';
       $msg['data']=$user_info;

       return $msg;
   }

   /**
    * 获取角色身份 /user/get_identity
    * */
    public function get_identity(){
        $data['tms_user_type']           =config('tms.tms_user_type');
        foreach($data['tms_user_type'] as $key => $value){
//            dd($value);
            if ($value['key'] == 'user'){
                unset($data['tms_user_type'][$key]);
            }else if($value['key'] == 'company'){
                unset($data['tms_user_type'][$key]);
            }else if($value['key'] == 'carriage'){
                unset($data['tms_user_type'][$key]);
            }
//            dd($value);
        }
        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $data;
        return $msg;
    }

    /**
     * 修改账户密码
     */
    public function update_pwd(Request $request){
        $project_type       =$request->get('project_type');
        $now_time   = date('Y-m-d H:i:s',time());
        $user_info  = $request->get('user_info');//接收中间件产生的参数
        $input		= $request->all();
        /** 接收数据*/
        $password                      = $request->input('password');
        $com_pwd                       = $request->input('com_pwd');
        $new_pwd                       = $request->input('new_pwd');
//        dd($user_info);

        /** 虚拟一下数据来做下操作
        $input['password']          = $password       ='123456'; //原始密码
        $input['new_pwd']           = $new_pwd        ='666666'; // 新密码
        $input['com_pwd']           = $com_pwd        ='666666'; // 确认密码
         */
        $rules = [
            'password' => 'required',
            'com_pwd'  => 'required',
            'new_pwd'  => 'required',
        ];
        $message = [
            'password.required' => '原始密码不能为空',
            'new_pwd.required'  => '新密码不能为空',
            'com_pwd.required'  => '确认密码不能为空',
        ];

        if($com_pwd != $new_pwd){
            $msg['code'] = 303;
            $msg['msg']  = '两次密码输入不一致！';
            return $msg;
        }
        $validator = Validator::make($input,$rules,$message);

        if($validator->passes()){
            $where =[
                ['self_id','=',$user_info->total_user_id],
                ['delete_flag','=','Y']
            ];
            $userTotal = UserTotal::where($where)->select('self_id','password','tel','delete_flag')->first();
            if ($userTotal){
                if ($userTotal->password != get_md5($password)){
                    $msg['code'] = 302;
                    $msg['msg']  = '原始密码不正确！';
                    return $msg;
                }

                $update['password']    = get_md5($new_pwd);
                $update['update_time'] = $now_time;
                $id = UserTotal::where($where)->update($update);
                if ($id){
                    $msg['code'] = 200;
                    $msg['msg']  = '修改成功！';
                    return $msg;
                }else{
                    $msg['code'] = 304;
                    $msg['msg']  = '修改失败！';
                    return $msg;
                }
            }else{
                $msg['code'] = 301;
                $msg['msg']  = '账号异常请联系客服';
                return $msg;
            }

        }else{
            $erro=$validator->errors()->all();
            $msg['code']=300;
            $msg['msg']=$erro[0];
            return $msg;
        }
    }

    /**
     *获取APP版本信息  /user/get_version
     */
    public function get_version(Request $request){
        $user_info    = $request->get('user_info');//获取中间件中的参数
        $type = $request->input('type');
        $where=[
            ['app_name','=',$type],
            ['delete_flag','=','Y'],
        ];
        $select=['app_name','app_version','app_content','download_url','os','pubdate','force_upgrade','online','official','delete_flag','version_state'];

        $data['info']=AppVersionConfig::where($where)->select($select)->get();
//        dd($data['info']->toArray());
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;

        return $msg;
    }

    /**
     * 申请企业认证  /user/attestation
     * 1.提交申请 attestation
     * 2.添加认证资料-填写认证信息 admin
     * 3.创建后台公司 group 公司名称不能重复
     * 4.给公司默认权限 auth
     * 5.绑定身份   user_identity
     * 6.切换为公司身份
     * */
    public function attestation(Request $request){
        $project_type       =$request->get('project_type');
        $now_time   = date('Y-m-d H:i:s',time());
        $user_info  = $request->get('user_info');//接收中间件产生的参数
        $input		= $request->all();
        /** 接收数据*/
        $name                      = $request->input('name');
        $image                     = $request->input('image');
        $road                      = $request->input('road');
        $address                   = $request->input('address');
        $sheng_name                = $request->input('sheng_name');
        $shi_name                  = $request->input('shi_name');
        $qu_name                   = $request->input('qu_name');
        $tel                       = $request->input('tel');
        $email                     = $request->input('email');
        $type                      = $request->input('type');
        $user_name                 = $request->input('user_name');
        $identity_number           = $request->input('identity_number');
        $company_code              = $request->input('company_code');
        $self_id                   = $request->input('self_id');
//        dd($user_info);

        /** 虚拟一下数据来做下操作
        $input['self_id']           = $self_id      = 'atte_202104292028121443448755';      //验证公司ID
        $input['name']              = $name         ='德邦快递'; //公司名称
        $input['user_name']         = $user_name    ='覃'; // 企业法人姓名
        $input['image']             = $image        ='[{"url":"https://bloodcity.oss-cn-beijing.aliyuncs.com/images/2021-04-29/62acf06a864f597d79b969574f6e5cf4.png","width":"65","height":"77"}]'; // 企业资质，营业执照
        $input['road']              = $road         ='515615'; // 道路运输许可证
        $input['address']           = $address      =''; // 企业详细地址
        $input['sheng_name']        = $sheng_name   =''; // 省
        $input['shi_name']          = $shi_name     =''; // 市
        $input['qu_name']           = $qu_name      =''; // 区
        $input['tel']               = $tel          ='15612345648'; // 企业联系电话s
        $input['email']             = $email        =''; // 公司邮箱
        $input['type']              = $type         ='TMS3PL'; //认证属性 1货主公司 company 2 TMS3PL
        $input['identity_number']   = $identity_number = '320481199703275816'; //身份证号
        $input['company_code']      = $company_code = '555555555555555555'; // 组织机构代码
         */
        if ($type == 'TMS3PL'){
            $rules = [
                'name' => 'required',
                'road'  => 'required',
                'image'  => 'required',
                'identity_number'  => 'required',
                'user_name'  => 'required',
            ];
            $message = [
                'name.required'   => '公司名称不能为空',
                'user_name.required' => '公司法人姓名不能为空',
                'image.required'  => '请上传营业执照',
                'road.required'   => '请上传道路运输许可证',
                'identity_number.required'   => '身份证号不能为空',
            ];
        }else{
            $rules = [
                'name' => 'required',
                'image'  => 'required',
                'identity_number'  => 'required',
                'user_name'  => 'required',
            ];
            $message = [
                'name.required'   => '公司名称不能为空',
                'user_name.required' => '公司法人姓名不能为空',
                'image.required'  => '请上传营业执照',
                'identity_number.required'   => '身份证号不能为空',
            ];
        }


        if (!$user_info){
            $msg['code'] = 401;
            $msg['msg']  = '未登录，登录已过期！';
            return $msg;
        }
//        if ($user_info->type != 'user' || $user_info->type != 'driver'){
//            $msg['code'] = 304;
//            $msg['msg']  = '已认证成功，请勿重复申请！';
//            return $msg;
//        }
        $validator = Validator::make($input,$rules,$message);

        if($validator->passes()){
//           dd($user_info);
            $group_old_info = $authhority_old_info = $account_old_info=$capital_old_info= $identity_old_info=null;
            $attestation_where = [
                ['name','=',$name],
                ['state','=','WAIT'],
                ['delete_flag','=','Y'],
            ];
           $attestation_count = TmsAttestation::where($attestation_where)->count();
           if ($attestation_count>0){
               $msg['code'] = 304;
               $msg['msg']  = '认证审核中，请勿重复申请';
               return $msg;
           }
           $where = [
             ['self_id','=',$self_id],
           ];
           $old_info = TmsAttestation::where($where)->first();
           if ($old_info){
               $account_old_info = SystemAdmin::where([['login','=',$old_info->login_account],['delete_flag','=','Y']])->first();
               $group_old_info = SystemGroup::where([['self_id','=',$account_old_info->group_code]])->first();
               $authhority_old_info = SystemAuthority::where([['self_id','=',$account_old_info->authority_id]])->first();
               $capital_old_info = UserCapital::where([['group_code','=',$group_old_info->self_id]])->first();
               $identity_old_info = UserIdentity::where([['self_id','=',$old_info->identity_id]])->first();
           }

//           $user_id['update_time'] = $now_time;
//           $user_id['atte_state'] = 'W';
//           UserIdentity::where($where)->update($user_id);


            $attestation['name'] = $name;
            $attestation['image'] = img_for($image,'in');
            $attestation['road'] = img_for($road,'in');
            $attestation['address'] = $address;
            $attestation['sheng_name'] = $sheng_name;
            $attestation['shi_name'] = $shi_name;
            $attestation['qu_name'] = $qu_name;
            $attestation['tel'] = $tel;
            $attestation['email'] = $email;
            $attestation['login_account'] = $name;
            $attestation['group_code']  = '1234';
            $attestation['group_name']  = '共享平台';
            $attestation['total_user_id'] = $user_info->total_user_id;
            $attestation['type']        = $type;
            $attestation['user_name']   = $user_name;
            $attestation['identity_number']   = $identity_number;
            $attestation['company_code']   = $company_code;
            $attestation['state']   = 'WAIT';


            $now_time = date('Y-m-d H:i:s',time());
            $info = $attestation;
            /**保存公司数据**/
            if ($group_old_info){
                $group_where = [
                    ['group_name','=',$info['name']],
                    ['delete_flag','!=','N'],
                    ['self_id','!=',$group_old_info->self_id]
                ];
            }else{
                $group_where = [
                    ['group_name','=',$info['name']],
                    ['delete_flag','!=','N']
                ];
            }

            $count_group = SystemGroup::where($group_where)->count();
            if ($count_group>0){
                $msg['code'] = 303;
                $msg['msg'] = '认证公司名称不能重复！';
                return $msg;
            }
            $data['group_name']         =$info['name'];
            $data['tel']                =$info['tel'];
            $data['business_type']      ='TMS';
            $data['city']               =$info['shi_name'];
            $data['address']            =$info['address'];

            /** 查询出所有的预配置权限，然后把预配置权限给与这个公司**/
            $where_business=[
                ['use_flag','=','Y'],
                ['delete_flag','=','Y'],
                ['business_type','=','TMS'],
            ];
            $select=['self_id','authority_name','menu_id','leaf_id','type','cms_show'];
            $authority_info=SystemGroupAuthority::where($where_business)->orderBy('create_time', 'desc')->select($select)->get();
            $authority_list=[];
            if($authority_info){
                foreach ($authority_info as $k => $v){
                    if($v->type == 'system'){
                        $data['menu_id']       =$v->menu_id;
                        $data['leaf_id']       =$v->leaf_id;
                        $data['cms_show']      =$v->cms_show;
                    }else{
                        $authority_list[]=$v;
                    }
                }

            }

            //说明是新增1
            $data['group_id_show']      =$data['group_name'];
            $data['create_user_id']     ='1234';
            $data['create_user_name']   ='共享平台';
            $data['father_group_code']  ='1234';
            $data['binding_group_code'] ='1234';
            $data['use_flag']           = 'N';
            $data['company_type']       = $type;

            if($group_old_info){
                $data['update_time'] = $now_time;
                SystemGroup::where('self_id','=',$group_old_info->self_id)->update($data);
            }else{
                $group_code                 =generate_id('group_');
                $data['create_time']        =$data['update_time']=$now_time;
                $data['self_id']            =$data['group_code']=$data['group_id']=$group_code;
                SystemGroup::insert($data);
            }
            //添加公司资金表

            $capital_data['total_user_id']  = null;
            $capital_data['group_name']    =$data['group_name'];
            $capital_data['update_time']    =$now_time;
            if ($capital_old_info){
                UserCapital::where('self_id','=',$capital_old_info->self_id)->update($capital_data);
            }else{
                $capital_data['self_id']        = generate_id('capital_');
                $capital_data['group_code']    =$group_code;
                UserCapital::insert($capital_data);						//写入用户资金表
            }


            /**添加数据权限**/
            if ($info['type'] == 'TMS3PL'){
                //认证3pl公司
                $dat['menu_id']='145*698*141*142*227*511*228*232*233*143*285*512*159*177*255*309*242*602*258*257*256*161*178*532*555*259*260*261*262*294*584*629*640*586*587*588*589*211*626*596*597*601*315*600*676*689*687*679*678*681*680*307*612*608*609*628*639*610*611*613*618*614*615*619*646*616*617*150*212*647*224*272*191*705*704*636*645*643*644*703*306*631*648*252*632*649*633*634*650*651*652*653*241*299*199*202';
                $dat['leaf_id']='145*698*141*142*227*511*228*232*233*143*285*512*159*177*255*309*242*602*258*257*256*161*178*532*555*259*260*261*262*294*584*629*640*586*587*588*589*211*626*596*597*601*315*600*676*689*687*679*678*681*680*307*612*608*609*628*639*610*611*613*618*614*615*619*646*616*617*150*212*647*224*272*191*705*704*636*645*643*644*703*306*631*648*252*632*649*633*634*650*651*652*653*241*299*199*202';
                $dat['cms_show']='机构管理　首页　运营管理　操作记录　基础设置　在线订单　账户余额　财务管理';
            }else{
                //认证货主公司
                $dat['menu_id']= '145*698*141*142*511*227*228*232*233*143*512*285*159*177*255*309*242*602*258*257*256*161*178*555*532*259*260*261*262*294*676*679*687*689*681*678*680*307*612*639*628*609*608*610*611*150*212*706*647*224*707*272*703*306*648*631*252*632*649*633*634*241*299*199*202';
                $dat['leaf_id']= '145*698*141*142*511*227*228*232*233*143*512*285*159*177*255*309*242*602*258*257*256*161*178*555*532*259*260*261*262*294*676*679*687*689*681*678*680*307*612*639*628*609*608*610*611*150*212*706*647*224*707*272*703*306*648*631*252*632*649*633*634*241*299*199*202';
                $dat['cms_show']='机构管理　首页　运营管理　操作记录　基础设置　账户余额　财务管理';
            }


            $dat['group_id_show']=$info['name'];

            //查询一下这个里面是不是第一个权限，如果是第一个权限，则把他设置为lock_flag 为Y
            if (!$self_id){
                $wheere['group_code']=$group_code;
                $wheere['delete_flag']='Y';
                $idsss=SystemAuthority::where($wheere)->value('self_id');
                if($idsss){
                    $dat["lock_flag"]='N';
                }else{
                    $dat["lock_flag"]='Y';
                }
            }
            $dat["authority_name"]      =$info['name'];
            $dat['create_user_id']      ='admin_id201710272123271474838779197';
            $dat['create_user_name']    ='系统管理员';
            $dat["group_name"]          =$info['name'];
            if ($authhority_old_info){
                $dat['update_time'] = $now_time;
                SystemAuthority::where('self_id','=',$authhority_old_info->self_id)->update($dat);
            }else{
                $dat["self_id"]             =generate_id('authority_');
                $dat['group_id']            =$group_code;
                $dat["group_code"]          =$group_code;
                $dat["create_time"]         =$dat["update_time"]=$now_time;
                $authhority=SystemAuthority::insert($dat);
            }


            /**添加账号信息**/
            if ($account_old_info){
                $name_where=[
                    ['login','=',$info['login_account']],
                    ['delete_flag','!=','N'],
                    ['self_id','!=',$account_old_info->self_id]
                ];
            }else{
                $name_where=[
                    ['login','=',$info['login_account']],
                    ['delete_flag','!=','N'],
                ];
            }
            $name_count = SystemAdmin::where($name_where)->count();            //检查名字是不是重复
//            if($name_count > 0){
//                $msg['code'] = 301;
//                $msg['msg'] = '账号名称重复！';
//                return $msg;
//            }
            $account['login']              =$info['login_account'];
            $account['name']               =$info['name'];
            $account['tel']                =$info['tel'];
            $account['email']              =$info['email'];
            $account['group_name']         =$info['name'];
            $account['authority_name']     =$info['name'];
            $account['use_flag']           ='N';
            $account['pwd']=get_md5(123456);
            $account['create_user_id'] ='admin_id201710272123271474838779197';
            $account['create_user_name'] = '系统管理员';
            if ($account_old_info){
                $account['update_time'] = $now_time;
                SystemAdmin::where('self_id','=',$account_old_info->self_id)->update($account);
            }else{
                $account['self_id']=generate_id('admin_');
                $account['authority_id']       =$dat['self_id'];
                $account['group_code']         =$group_code;
                $account['create_time']=$account['update_time']=$now_time;
                $admin = SystemAdmin::insert($account);
            }


            /**绑定身份**/

            $identity['total_user_id']      =$user_info->total_user_id;
            if ($info['type'] == 'TMS3PL'){
                $identity['type']               ='TMS3PL';
            }else{
                $identity['type']               ='company';
            }
            $identity['create_user_id']     ='1234';
            $identity['create_user_name']   = '共享平台';
            $identity['group_name']         =$info['name'];
            $identity['admin_login']        =$info['name'];
            $identity['total_user_id']      =$info['total_user_id'];
            $identity['atte_state']         = 'W';
            $identity['use_flag']           = 'W';
            $identity['default_flag']           = 'N';

            if ($identity_old_info){
                $identity['update_time'] = $now_time;
                UserIdentity::where('self_id','=',$identity_old_info->self_id)->update($identity);
            }else{
                $identity['create_time']       = $identity['update_time'] = $now_time;
                $identity['self_id']            = generate_id('identity_');
                $identity['group_code']         =$group_code;
                $user_identity=UserIdentity::insert($identity);
            }


            if ($old_info){
                $attestation['update_time'] = $now_time;
                $id = TmsAttestation::where($where)->update($attestation);
            }else{
                $attestation['self_id'] = generate_id('atte_');
                $attestation['identity_id'] = $identity['self_id'];
                $attestation['create_time'] = $now_time;
                $attestation['update_time'] = $now_time;
                $id = TmsAttestation::insert($attestation);
            }

//           $attestation_msg =  $attestation->attestationPass($data,$user_info);

//           if($attestation_msg){
//               return $attestation_msg;
//           }
            if ($id){
                $msg['code'] = 200;
                $msg['msg']  = '申请认证成功,请等待平台审核！';
                return $msg;
            }else{
                $msg['code'] = 305;
                $msg['msg']  = '数据有误，请重新提交！';
                return $msg;
            }

        }else{
            $erro=$validator->errors()->all();
            $msg['code']=300;
            $msg['msg']=$erro[0];
            return $msg;
        }
    }

    /**
     *   企业认证详情  /user/details
     **/
    public function details(Request $request,Details $details){
        $self_id    = $request->input('self_id');
        $table_name = 'tms_attestation';
        $tms_wallet_status = array_column(config('tms.tms_wallet_status'),'image','key');
        $select = ['self_id','name','identity_number','company_code','image','road','group_code','group_name','address','tel','image','road','state','email','reason','sheng_name','qu_name','shi_name','login_account','user_name'];
//         $self_id = 'atte_202104211117304721390355';
        $info = $details->details($self_id,$table_name,$select);

        if($info) {
            /** 如果需要对数据进行处理，请自行在下面对 $$info 进行处理工作*/

            $info->image = img_for($info->image,'more');
            $info->road = img_for($info->road,'more');
//            if($info->state == 'WAIT'){
//                $info->state_view = '认证审核中...';
//                $info->state_color = '';
//                $info->state_show = img_for($tms_wallet_status[$info->state],'no_json');
//            }elseif($info->state == 'SU'){
//                $info->state_view = '认证成功';
//                $info->state_color = '';
//                $info->state_show = img_for($tms_wallet_status[$info->state],'no_json');
//            }else{
//                $info->state_view = '认证失败';
//                $info->state_color = '';
//                $info->state_show = img_for($tms_wallet_status[$info->state],'no_json');
//            }
            if ($info->state == 'SU'){
                $info->href = 'admin.56cold.com';
                $info->pwd  = '123456';
            }
            $info->state_show = img_for($tms_wallet_status[$info->state],'no_json');
//            dd($info);
            $data['info'] = $info;
            $msg['code']  = 200;
            $msg['msg']   = "数据拉取成功";
            $msg['data']  = $data;
            return $msg;
        }else{
            $msg['code'] = 300;
            $msg['msg']  = "没有查询到数据";
            return $msg;
        }
    }
}
?>
