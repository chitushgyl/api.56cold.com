<?php
namespace App\Http\Api\User;
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
use App\Http\Controllers\AttestationController as Attestation;

class DriverController extends Controller{
    /**
     * 用户底部导航      /driver/foot
     */
    public function foot(Request $request,CartNumber $cartNumber){
        $user_info          =$request->get('user_info');
        $project_type       =$request->get('project_type');
        $group_info         =$request->get('group_info');
        $group_code         =$group_info->group_code??config('page.platform.group_code');

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
//        dd($info->toArray());
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
//        dump($project_type);
//        dd($user_info);
        /** 如果用户的身份过来是user   ，而user_identity  是空的话，说明这个用户没有任何身份，则需要在身份那里添加一个默认的用户身份给他**/
        if ($user_info){
            if($project_type=='carriage' && count((array)$user_info->userIdentity) <= 0){
                $data['self_id']            = generate_id('identity_');
                $data['total_user_id']      =$user_info->total_user_id;
                $data['type']               ='carriage';
                $data['create_user_id']     = '2222222';
                $data['create_user_name']   = $user_info->total_user_id;
                $data['create_time']       = $data['update_time'] = $now_time;
                UserIdentity::insert($data);
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
        $userIdentity = [
            ['type','!=','carriage'],
            ['total_user_id','=',$user_info->total_user_id]
        ];
        $identity_count = UserIdentity::where($userIdentity)->count();
        foreach ($info as $k => $v){
            if ($identity_count>0){
                $v->identity_show = 'Y';
            }else{
                $v->identity_show = 'N';
            }
            $v->inactive_img=img_for($v->inactive_img,'no_json');
//            dump($v->toArray());
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
                case 'money':
                    $v->money = 0 ;
                    if ($user_info){
                        if ($user_info->type == 'user'){
                            $user_info->userCapital->money = number_format($user_info->userCapital->money/100,2);
                        }elseif($user_info->type == 'TMS3PL'){
                            $admin_where=[
                                ['group_code','=',$user_info->group_code],
                                ['delete_flag','=','Y'],
                            ];
                            $select_capital=['self_id','money'];
                            $money_info=UserCapital::where($admin_where)->select($select_capital)->first();
                            $user_info->userCapital->money = number_format($money_info->money/100,2);
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


                    }
                    break;
                case 'carriers_order':
                    foreach ($v->sysFoot as $kkk => $vvv){
                        $vvv->inactive_img=img_for($vvv->inactive_img,'no_json');
                        $vvv->number=0;
                    }
                    if ($user_info){
                        $user_order_where=[
                            ['company_id','=',$user_info->company_id],
                            ['use_flag','=','Y'],
                            ['delete_flag','=','Y']
                        ];
                        $order_number=TmsCarriage::where($user_order_where)->select('order_status',DB::raw('count(*) as num'))->groupBy('order_status')->get();

                        if($order_number){
                            foreach ($order_number as $kk => $vv){
                                $abcd='status';
                                if ($vv->order_status == 1){
                                    $abcd = 'status1';
                                }
                                if ($vv->order_status == 2 || $vv->order_status == 3){
                                    $abcd = 'status2';
                                }
//                                if ($vv->order_status == 4){
//                                    $abcd = 'status3';
//                                }
                                foreach ($v->sysFoot as $kkk => $vvv){
                                    if($vvv->type == $abcd){
                                        $vvv->number+=$vv->num;
                                    }
                                }
                            }
                        }
                    }

                    break;
                case 'customer_order':
                    foreach ($v->sysFoot as $kkk => $vvv){
                        $vvv->inactive_img=img_for($vvv->inactive_img,'no_json');
                        $vvv->number=0;
                    }
                    $user_order_where=[
                        ['company_id','=',$user_info->company_id],
                        ['read_flag','=','N'],
                    ];
                    $order_number=TmsOrder::where($user_order_where)->select('order_status',DB::raw('count(*) as num'))->groupBy('order_status')->get();
//                    dump($order_number->toArray());
                    if($order_number){
                        foreach ($order_number as $kk => $vv){
                            $abcd='status';
                            if ($vv->order_status == 3){
                                $abcd='status2';
                            }
                            if ($vv->order_status == 4 || $vv->order_status == 5){
                                $abcd='status3';
                            }
//                            if ($vv->order_status == 6){
//                                $abcd='status4';
//                            }
                            if ($vv->order_status == 7){
                                $abcd='status5';
                            }
                            foreach ($v->sysFoot as $kkk => $vvv){
                                if($vvv->type == $abcd){
                                    $vvv->number+=$vv->num;
                                }
                            }
                        }
                    }
                    break;
                case 'TMS3PL_order':
                    foreach ($v->sysFoot as $kkk => $vvv){
                        $vvv->inactive_img=img_for($vvv->inactive_img,'no_json');
                        $vvv->number=0;
                    }

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

                    break;
                case 'TMS3PL_order_take':
                    foreach ($v->sysFoot as $kkk => $vvv){
                        $vvv->inactive_img=img_for($vvv->inactive_img,'no_json');
                        $vvv->number=0;
                    }

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
                    break;

                case 'driver_order':
                    foreach ($v->sysFoot as $kkk => $vvv){
                        $vvv->inactive_img=img_for($vvv->inactive_img,'no_json');
                        $vvv->number=0;
                    }
                    break;
                case 'carriage_order':
                    foreach ($v->sysFoot as $kkk => $vvv){
                        $vvv->inactive_img=img_for($vvv->inactive_img,'no_json');
                        $vvv->number=0;
                    }
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
                    break;
                case 'attestation':

                    if ($user_info->type == 'TMS3PL' ||$user_info->type == 'company'){
                        $v->title_show = $user_info->group_name;
                    }else{
                        $v->title_show = '企业认证，免费开放企业权限';
                    }
                    break;
                case 'list':

                    if ($user_info->type == 'TMS3PL'){
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


                    if ($user_info->type == 'TMS3PL'){
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

//dd($user_info);

                    if ($user_info){
                        if ($user_info->type == 'user' ||$user_info->type == 'carriage'){
                            $user_info->userCapital->money = $money = number_format($user_info->userCapital->money/100,2);
                        }elseif($user_info->type == 'TMS3PL' || $user_info->type == 'company'){
                            $admin_where=[
                                ['group_code','=',$user_info->group_code],
                                ['delete_flag','=','Y'],
                            ];
                            $select_capital=['self_id','money'];
                            $money_info=UserCapital::where($admin_where)->select($select_capital)->first();
                            $user_info->userCapital->money = $money = number_format($money_info->money/100,2);
                        }
                    }

                    foreach ($v->sysFoot as $kkk => $vvv){
                        if($vvv->type == 'car'){
                            $vvv->number = $car_number;
                        }elseif($vvv->type == 'money'){
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
                default:

                    foreach ($v->sysFoot as $kkk => $vvv){
                        $vvv->inactive_img=img_for($vvv->inactive_img,'no_json');
                        $vvv->number=0;
                    }

                    break;

            }
        }
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

        $where=[
            ['app_name','=','chitu'],
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
    public function attestation(Request $request ,Attestation $attestation){
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
//        dd($user_info);

        /** 虚拟一下数据来做下操作
        $input['name']              = $name         ='1234567'; //公司名称
        $input['user_name']         = $user_name    ='123456'; //真实姓名
        $input['image']             = $image        ='666666'; // 企业资质，营业执照
        $input['road']              = $road         ='666666'; // 道路运输许可证
        $input['address']           = $address      ='666666'; // 企业详细地址
        $input['sheng_name']        = $sheng_name   ='666666'; // 省
        $input['shi_name']          = $shi_name     ='666666'; // 市
        $input['qu_name']           = $qu_name      ='666666'; // 区
        $input['tel']               = $tel          ='666666'; // 企业联系电话
        $input['email']             = $email        ='666666'; // 公司邮箱
        $input['type']              = $type         ='TMS3PL'; //认证属性 1货主公司 2TMS3PL
        $input['identity_number']   = $identity_number = '1234567489';
         */
        $rules = [
            'name' => 'required',
            'road'  => 'required',
            'image'  => 'required',
            'identity_number'  => 'required',
            'user_name'  => 'required',
        ];
        $message = [
            'name.required'   => '公司名称不能为空',
            'user_name.required' => '真实姓名不能为空',
            'image.required'  => '请上传营业执照',
            'road.required'   => '请上传道路运输许可证',
            'identity_number.required'   => '身份证号不能为空',
        ];

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
            $attestation_where = [
                ['name','=',$name],
                ['state','=','WAIT'],
//                ['delete_flag','=','Y]
            ];
           $attestation_count = TmsAttestation::where($attestation_where)->count();
           if ($attestation_count>0){
               $msg['code'] = 304;
               $msg['msg']  = '认证审核中，请勿重复申请';
               return $msg;
           }
           $where = [
             ['total_user_id','=',$user_info->total_user_id],
             ['type','=',$user_info->type]
           ];

           $user_id['update_time'] = $now_time;
           $user_id['atte_state'] = 'WAIT';
           UserIdentity::where($where)->update($user_id);

           $data['self_id'] = generate_id('atte_');
           $data['name'] = $name;
           $data['image'] = $image;
           $data['road'] = $road;
           $data['address'] = $address;
           $data['sheng_name'] = $sheng_name;
           $data['shi_name'] = $shi_name;
           $data['qu_name'] = $qu_name;
           $data['tel'] = $tel;
           $data['email'] = $email;
           $data['login_account'] = $user_info->tel;
           $data['create_time'] = $now_time;
           $data['update_time'] = $now_time;
           $data['group_code']  = '1234';
           $data['group_name']  = '共享平台';
           $data['total_user_id'] = $user_info->total_user_id;
           $data['type']        = $type;

           $attestation_msg =  $attestation->attestationPass($data,$user_info);
           $id = TmsAttestation::insert($data);
           if($attestation_msg){
               return $attestation_msg;
           }
            if ($id){
                $msg['code'] = 200;
                $msg['msg']  = '申请认证成功,请等待平台审核！';
                return $msg;
            }else{
                $msg['code'] = 304;
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
}
?>
