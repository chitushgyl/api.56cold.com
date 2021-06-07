<?php
namespace App\Http\Api\User;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User\UserCoupon;
use App\Models\User\UserCapital;
use App\Models\User\UserWallet;
use App\Models\User\UserCouponGive;
use App\Models\User\UserCouponGiveList;
use App\Models\Shop\ShopCoupon;
use App\Models\Shop\ShopCouponExchange;
use App\Http\Controllers\CouponController as Coupon;

class CouponController extends Controller{
    /**
     * 优惠券列表数据      /user/coupon_page
     */
    public function coupon_page(Request $request){
        $user_info=$request->get('user_info');
        /** 虚拟数据*/
        //$user_id='user_202004021006499587468765';
        $listrows       =config('page.listrows')[1];//每次加载的数量
        $first          =$request->input('page')??1;
        $firstrow       =($first-1)*$listrows;
        $now_time       =date('Y-m-d H:i:s',time());
        $status         =$request->input('status')??'unused';

        $user_coupon_where_do=[
            ['total_user_id','=',$user_info->total_user_id],
            ['coupon_status','=','unused'],
            ['time_end','<',$now_time],
        ];

        //将使用时间超过现在时间的券的状态变成过期的状态
        $coupon_data['coupon_status']   ='stale';
        $coupon_data['update_time']     =$now_time;
        UserCoupon::where($user_coupon_where_do)->update($coupon_data);


        $coupon_where=[
            ['total_user_id','=',$user_info->user_id],
            ['use_flag','=','Y'],
            ['delete_flag','=','Y'],
            ['coupon_status','=',$status],
        ];

        $select=['self_id as user_coupon_id',
            'coupon_title', 'range','range_condition','group_code',
            'time_start','time_end',
            'coupon_status', 'range_type','use_type','use_type_id','group_code','use_self_lifting_flag'];
            //dd($coupon_where);
            $coupon_info=UserCoupon::with(['erpShopGoods' => function($query) {
                $query->select('self_id','good_title');
            }])->with(['SystemGroup' => function($query) {
                $query->select('group_code','front_name','company_image_url');
            }])->where($coupon_where)
                ->offset($firstrow)->limit($listrows)
                ->select($select)
                ->orderBy('create_time','desc')
                ->get();
        //dd($coupon_info);
//            $coupon_info=manage_coupon($coupon_info,'user_coupon',$user_info->user_id);
           // dd($coupon_info->toArray());

          
		    $msg['code']=200;
            $msg['msg']='数据拉取成功！';
            $msg['data']=$coupon_info;

        return $msg;
    }


    /**
     * 优惠券积分可兑换列表      /user/exchange_coupon
     */
    public function exchange_coupon(Request $request,Coupon $coupon){
        //$user_id='user_202003311708144834304319';
            $user_info          =$request->get('user_info');
            $user_info->user_integral_show =number_format($user_info->integral/100, 2);          //用户积分;
			
            $listrows           =config('page.listrows')[1];//每次加载的数量
            $first              =$request->input('page')??1;
            $firstrow           =($first-1)*$listrows;
            $now_time           =date('Y-m-d H:i:s',time());
            $exchange_where=[
                ['get_way','=','integral'],
                ['delete_flag','=','Y'],
                ['get_start_time','<',$now_time],
                ['get_end_time','>',$now_time],
                ['use_flag','=','Y'],
                ['coupon_inventory','>','0'],
                ['time_start','<',$now_time],
                ['time_end','>',$now_time],
                ['coupon_status','=','process'],
            ];
            $user_track_where2=[
                ['delete_flag','=','Y'],
            ];

            $user_userCoupon=[
                ['total_user_id','=',$user_info->total_user_id],
            ];

        //dump($user_info->total_user_id);

        $select=['self_id','coupon_title','coupon_inventory','coupon_remark',
            'get_limit_number','get_redeem_code','get_start_time','get_end_time',
            'range_type','range_condition','range',
            'time_type','time_start_day','time_end_day','time_type','time_start','time_end','use_flag',
            'use_type','use_type_id','use_self_lifting_flag','group_code','group_name'];

        $select_erpShopGoodsSku=['self_id','good_name'];
        $select_systemGroup=['group_code','company_image_url','front_name'];

        $select_userCoupon=['self_id','shop_coupon_id'];
        $can_exchange=ShopCoupon::wherehas('systemGroup',function($query)use($user_track_where2){
            $query->where($user_track_where2);
        })->with(['erpShopGoodsSku' => function($query)use($select_erpShopGoodsSku) {
            $query->select($select_erpShopGoodsSku);
        }])->with(['systemGroup' => function($query)use($select_systemGroup) {
            $query->select($select_systemGroup);
        }])->with(['userCoupon' => function($query)use($user_userCoupon,$select_userCoupon) {
            $query->where($user_userCoupon);
            $query->select($select_userCoupon);
        }])->where($exchange_where)->orderBy('create_time','desc')->select($select)->get();


        foreach ($can_exchange as $k=>$v) {
            if($v->userCoupon){
                $yilin=$v->userCoupon->count();
            }else{
                $yilin=0;
            }

            $v->can_get_number         =$v->get_limit_number-$yilin;

            $info=$coupon->shop_coupon($v);
//            //领取路径
            $v->get_redeem_code         =$info['get_redeem_code'];
            $v->range_type_show         =$info['range_type_show'];
            $v->time_type_show          =$info['time_type_show'];
            $v->use_type_show           =$info['use_type_show'];
            $v->company_image_url       =img_for($v->systemGroup->company_image_url,'more');
        }


//        dd($can_exchange->toArray());


            $msg['code']=200;
			$msg['msg']='获取数据成功！';
            $msg['integral']=$user_info;
			$msg['data']=$can_exchange;


        //dd($msg);
        return $msg;
    }


    /**
     * 用户已选择的用于 赠送，出售，红包的优惠券分页数据     /user/handle_coupon_page
     * 前端传递必须参数：page，type【赠送give，转让transfer，红包hongbao'】
     *回调数据：  优惠券信息
     */
    public function handle_coupon_page(Request $request){
        $user_info=$request->get('user_info');
        $listrows=config('page.listrows')[1];//每次加载的数量
        $first=$request->input('page')??1;
        $firstrow=($first-1)*$listrows;
        $where_give=[
            ['total_user_id','=',$user_info->total_user_id],
            ['delete_flag','=','Y'],
        ];
        $select=['self_id','self_id as coupon_give_id','create_time','need_integral','type'];
        $selectList=['self_id','give_id','get_user_id','get_time','user_coupon_id'];
        $give_info=UserCouponGive::with(['userCouponGiveList' => function($query)use($selectList) {
            $query->select($selectList);
        }])->where($where_give)->offset($firstrow)->limit($listrows)->select($select)->get();

        foreach ($give_info as $k => $v){
            $v->coupon_number=$v->userCouponGiveList->count();
            $do='N';
            foreach ($v->userCouponGiveList as $kk => $vv){
                if($do=='N'){
                    if($vv->get_time){
                        $do='Y';
                    }
                }

                if($do == 'Y'){
                    if(empty($vv->get_time)){
                        $do='W';
                    }
                }

            }

            switch ($do){
                case 'N':
                    $v->get_flag_show='待领取';
                    break;
                case 'Y':
                    $v->get_flag_show='领取完毕';
                    break;
                default:
                    $v->get_flag_show='部分领取';
                    break;
            }

        }

        $msg['code']=200;
        $msg['msg']='获取数据成功！';
        $msg['data']=$give_info;

        return $msg;
    }
    /**
     * 用户用于赠送、出售、红包的优惠券详情     /user/send_coupon_detail
     *回调数据：  优惠券信息
     */
    public function send_coupon_detail(Request $request){
        $user_info          =$request->get('user_info');
        $coupon_give_id     =$request->input('coupon_give_id');
        //$user_id='user_202003311708144834304319';
//        $coupon_give_id     ='givecoupon_202101191747183825719177';

        $where_give=[
            ['self_id','=',$coupon_give_id],
            ['delete_flag','=','Y'],
        ];
        $select=['self_id','self_id as coupon_give_id','create_time','need_integral','type','total_user_id'];
        $selectList=['self_id','give_id','get_user_id','get_time','user_coupon_id'];
        $selectuserCoupon=['self_id','coupon_title',
            'range_condition','range',
            'time_start','time_end',
            'coupon_status','range_type',
            'use_type','use_type_id','group_code','use_self_lifting_flag',];
        $selectuserReg=['token_img','token_name','tel'];

        $give_info=UserCouponGive::with(['userCouponGiveList' => function($query)use($selectList,$selectuserCoupon,$selectuserReg) {
            $query->select($selectList);
            $query->with(['userCoupon' => function($query)use($selectuserCoupon) {
                $query->select($selectuserCoupon);
            }]);
            $query->with(['userReg' => function($query)use($selectuserReg) {
                $query->select($selectuserReg);
            }]);
        }])->where($where_give)->select($select)->first();

        if($give_info){
            $isOwn='N'; //是赠送方还是接收方【Y是赠送方，N是接收方】
            if($give_info->total_user_id == $user_info->total_user_id){
                $isOwn='Y';
            }

            if($give_info->type == 'transfer'){
                $give_info->need_integral=number_format($give_info->need_integral/100,2);
            }

            $msg['code']=200;
            $msg['msg']='获取数据成功';
            $msg['is_own']=$isOwn;
            $msg['give_info']=$give_info;
            return $msg;

        }else{
            $msg['code']=300;
            $msg['msg']='获取不到数据';
            return $msg;
        }



        //dd($msg);

    }

    /**
     * 获取用户用于可以 赠送，出售，发红包 的优惠券列表数据    /user/get_coupon
     *回调数据：  优惠券信息
     */
    public function get_coupon(Request $request){
        $user_info=$request->get('user_info');

        $listrows=config('page.listrows')[1];//每次加载的数量
        $first=$request->input('page')??1;
        $firstrow=($first-1)*$listrows;
        $now_time=date('Y-m-d H:i:s',time());

        $where=[
            ['total_user_id','=',$user_info->total_user_id],
            ['delete_flag','=','Y'],
            ['coupon_status','=','unused'],
            ['time_end','>',$now_time],
            ['give_flag','=','Y'],
        ];
        $select=['self_id as user_coupon_id',
            'coupon_title','range_condition',
            'range','time_start','time_end','coupon_status','range_type','use_type', 'use_type_id','group_code','use_self_lifting_flag',];
        $user_track_where2=[
            ['delete_flag','=','Y'],
            ['use_flag','=','Y'],
        ];
        $select_erpShopGoodsSku=['self_id','good_name'];
        $select_systemGroup=['group_code','company_image_url','front_name'];

        $get_coupon=UserCoupon::wherehas('systemGroup',function($query)use($user_track_where2){
            $query->where($user_track_where2);
        })->with(['erpShopGoodsSku' => function($query)use($select_erpShopGoodsSku) {
            $query->select($select_erpShopGoodsSku);
        }])->with(['systemGroup' => function($query)use($select_systemGroup) {
            $query->select($select_systemGroup);
        }])->where($where)->select($select)->offset($firstrow)->limit($listrows)->orderBy('create_time','desc')->get();


        $msg['code']=200;
        $msg['msg']='数据获取成功！';
        $msg['data']=$get_coupon;


        //dd($msg);
        return $msg;
    }


    /**
     * 赠送类优惠券进 user_coupon_give库    /user/add_user_coupon_give
     * 【post类型】
     * 前端传递必须参数：type【赠送give，转让transfer，红包hongbao'】，coupon_id【用*拼接的字符串】
     *回调数据：  优惠券信息
     */
    public function add_user_coupon_give(Request $request){
        $user_info          =$request->get('user_info');
        $now_time           =date('Y-m-d H:i:s',time());
        $input              =$request->all();
        /** 接收数据*/
        $type               =$request->input('type');
        $coupon_id          =$request->input('coupon_id');
        $need_integral      =$request->input('need_integral');

        /**   虚拟数据*/
        $input['type']          =$type='transfer';          //【赠送give，转让transfer，红包hongbao'】
        $input['coupon_id']     =$coupon_id=['usecoupon_202101191601557709610628','usecoupon_202101191601566669953895'];
        $input['need_integral'] =$need_integral='2121';

        $rules=[
            'type'=>'required',
            'coupon_id'=>'required',
        ];
        $message=[
            'type.required'=>'类型不能为空',
            'coupon_id.required'=>'优惠券集合不能为空',
        ];
        $validator=Validator::make($input,$rules,$message);

        if($validator->passes()){

            if($type == 'transfer'){
                if($need_integral<=0){
                    $msg['code']=302;
                    $msg['msg']='没有输入转让需要的积分';
                    return $msg;
                }
            }

            $where_lock=[
                ['delete_flag','=','Y'],
                ['use_flag','=','Y'],
                ['coupon_status','=','unused'],
                ['give_flag','=','Y'],
            ];

            $coupon_info=UserCoupon::where($where_lock)->whereIn('self_id',$coupon_id)->pluck('self_id')->toArray();
            if(count($coupon_id) !=count($coupon_info) ){
                $msg['code'] = 305;
                $msg['msg'] = '您选择的优惠券中有不能给与他人，请检查';
                return $msg;
            }

            $self_id                    =generate_id('givecoupon_');
            $data['self_id']            =$self_id;
            $data['total_user_id']      =$user_info->total_user_id;
            $data['type']               =$type;
            $data['create_time']        =$data['update_time']=$now_time;
            $data['need_integral']      =$need_integral*100;
            $id=UserCouponGive::insert($data);


            $data_info['coupon_status']     ='lock';
            $data_info['update_time']       =$now_time;
            UserCoupon::where($where_lock)->whereIn('self_id',$coupon_id)->update($data_info);

            $give_list=[];
            foreach ($coupon_info as $k=>$v){
                $list['self_id']        =generate_id('givelist_');
                $list['give_id']        =$self_id;
                $list['user_coupon_id'] =$v;
                $list['create_time']    =$list['update_time']=$now_time;
                $list['sort']           =$k+1;
                $give_list[]=$list;
            }

            UserCouponGiveList::insert($give_list);
            if($id){
                $msg['code']=200;
                $msg['msg']='新增可用优惠券成功';
                return $msg;

            }else{
                $msg['code']=303;
                $msg['msg']='新增可用优惠券失败';
                return $msg;
            }

        }else{
            //前端用户验证没有通过
            $erro=$validator->errors()->all();
            $msg['code']=300;
            $msg['msg']=null;
            foreach ($erro as $k => $v){
                $kk=$k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            return $msg;
        }


    }



    /**
     * 获得优惠券    /user/add_coupon
     * 前端传递必须参数：type:common首页领取,late迟到,web页面领取，integral积分兑换，exchange兑换码兑换，活动详情页activity,赠送表过来的give
     *回调数据：  优惠券信息
     */
    public function add_coupon(Request $request){
        $user_info      =$request->get('user_info');
        $now_time       =date('Y-m-d H:i:s',time());
        $input              =$request->all();
        /** 接收数据*/
        $type               =$request->input('type');
        $give_id            =$request->input('give_id');
        $exchange           =$request->input('exchange');
        $coupon_id          =$request->input('coupon_id');
        /*** 虚拟数据
        //第一种情况，赠送的！
        $input['type']              =$type='give';
        $input['give_id']           =$give_id='givecoupon_202101191747183825719177';

        //第二种情况，自己领取的
//        $input['type']              =$type='common';    //common首页领取,late迟到,web页面领取，integral积分兑换，exchange兑换码兑换，活动详情页activity,
//        //$input['coupon_ids']        =$coupon_ids=['coupon_20200928155425956582308','coupon_20200928163529774288295'];
//
//        //第三种情况，兑换码兑换
//        $input['type']              =$type='exchange';
//        $input['exchange']          =$exchange='UWK69vgg';
//
//        //第四种情况，积分兑换
//        $input['type']              =$type='integral';
//        $input['coupon_id']         =$coupon_id='coupon_20200928155425956582308';
         **/
        $rules=[
            'type'=>'required',
        ];
        $message=[
            'type.required'=>'类型不能为空',
        ];

        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()){

            $exchange_where=[
                ['delete_flag','=','Y'],
                ['get_start_time','<',$now_time],
                ['get_end_time','>',$now_time],
                ['use_flag','=','Y'],
                ['coupon_inventory','>','0'],
                ['time_start','<',$now_time],
                ['time_end','>',$now_time],
                ['coupon_status','=','process'],
            ];
            $user_track_where2=[
                ['delete_flag','=','Y'],
            ];

            $user_userCoupon=[
                ['total_user_id','=',$user_info->total_user_id],
            ];

            //dump($user_info->total_user_id);

            $select=['self_id','coupon_title','coupon_inventory','coupon_remark',
                'get_limit_number','get_redeem_code','get_start_time','get_end_time',
                'range_type','range_condition','range',
                'time_type','time_start_day','time_end_day','time_type','time_start','time_end','use_flag',
                'use_type','use_type_id','use_self_lifting_flag','group_code','group_name',
                'rear_give_flag','use_fallticket_flag'];

            $select_erpShopGoodsSku=['self_id','good_name'];
            $select_systemGroup=['group_code','company_image_url','front_name'];
            $select_userCoupon=['self_id','shop_coupon_id'];


            switch ($type){
                case 'give':
                    $where_give=[
                        ['self_id','=',$give_id],
                        ['delete_flag','=','Y'],
                    ];
                    $select=['self_id','self_id as coupon_give_id','create_time','need_integral','type','total_user_id'];
                    $selectList=['self_id','give_id','get_user_id','get_time','user_coupon_id','sort','get_user_id'];
                    $selectuserCoupon=['self_id','shop_coupon_id','coupon_title','coupon_remark','coupon_details',
                        'time_start','time_end',
                        'range_type','range_condition','range',
                        'coupon_status',
                        'use_type','use_type_id','group_code','group_name',
                        'use_self_lifting_flag','use_fallticket_flag','give_flag'];
                    $selectuserReg=['token_img','token_name','tel'];

                    $give_info=UserCouponGive::with(['userCouponGiveList' => function($query)use($selectList,$selectuserCoupon,$selectuserReg) {
                        $query->select($selectList);
                        $query->orderBy('sort','asc');
                        $query->with(['userCoupon' => function($query)use($selectuserCoupon) {
                            $query->select($selectuserCoupon);
                        }]);
                        $query->with(['userReg' => function($query)use($selectuserReg) {
                            $query->select($selectuserReg);
                        }]);
                    }])->where($where_give)->select($select)->first();

                    if(empty($give_info)){
                        $msg['code']=307;
                        $msg['msg']="没有查询到数据";
                        return $msg;
                    }


                    if($give_info->total_user_id == $user_info->total_user_id){
                        $msg['code']=306;
                        $msg['msg']="自己不能领取自己";
                        //dd($msg);
                        return $msg;
                    }


                    //赠送give，转让transfer，红包hongbao'
                    switch ($give_info->type){
                        case 'transfer':
                            //要扣除购买人的积分
                            if($user_info->integral < $give_info->need_integral){
                                $msg['code']=308;
                                $msg['msg']="积分不够支付";
                                return $msg;
                            }
                            /*** 定义一个变量，取多少条数据*/
                            $number='all';
                            break;

                        case 'give':
                            //拿就可以了
                            $number='all';
                            break;
                        case 'hongbao':
                            //一人只能拿一个，从上往下拿
                            $number='one';
                            break;
                    }

                    $can_exchange=[];           //定义一个一会要传递给控制器，来完成添加的数组

                    //$number='one';
                    if($number == 'all'){
                        foreach ($give_info->userCouponGiveList as $k => $v){
                            $list=$v;
                            $can_exchange[]=$list->toArray();
                        }
                    }else{
                        foreach ($give_info->userCouponGiveList as $k => $v){
                            if(empty($can_exchange)){
                                $list=$v;
                                $can_exchange[]=$list->toArray();
                            }
                        }
                    }

                    if(empty($can_exchange)){
                        $msg['code']=309;
                        $msg['msg']="已领取完毕";
                        return $msg;
                    }

                    $msg=$this->user_coupon_do($can_exchange,$give_info->type,$user_info,$now_time,$give_info->total_user_id,$give_info->need_integral);

                    break;

                case 'integral':
                    $exchange_where[]=['self_id','=',$coupon_id];
                    //dump($exchange_where);
                    $can_exchange=ShopCoupon::wherehas('systemGroup',function($query)use($user_track_where2){
                        $query->where($user_track_where2);
                    })->with(['erpShopGoodsSku' => function($query)use($select_erpShopGoodsSku) {
                        $query->select($select_erpShopGoodsSku);
                    }])->with(['systemGroup' => function($query)use($select_systemGroup) {
                        $query->select($select_systemGroup);
                    }])->with(['userCoupon' => function($query)use($user_userCoupon,$select_userCoupon) {
                        $query->where($user_userCoupon);
                        $query->select($select_userCoupon);
                    }])->where($exchange_where)->orderBy('create_time','desc')->select($select)->get();



                   // dump($can_exchange->toArray());
                    if($can_exchange->count()==0){
                        $msg['code']=303;
                        $msg['msg']='没有数据';
                        return $msg;
                    }

                    //判断用户的积分够不够！
                    if($user_info->integral < $can_exchange[0]->get_redeem_code  ){
                        $msg['code']=304;
                        $msg['msg']='积分不够';
                        return $msg;
                    }


                    $msg=$this->coupon_do($can_exchange,'integral',$user_info,$now_time,$exchange);
//
//
//
//                    dump($user_info->toArray());
//
//                    dd($coupon_id);
                    break;

                case 'exchange':
                    $user_track_where3=[
                        ['use_flag','=','Y'],
                        ['delete_flag','=','Y'],
                        ['exchange_code','=',$exchange],
                        ['exchange_flag','=','N'],
                    ];
                    $can_exchange=ShopCoupon::wherehas('systemGroup',function($query)use($user_track_where2){
                        $query->where($user_track_where2);
                    })->wherehas('shopCouponExchange',function($query)use($user_track_where3){
                        $query->where($user_track_where3);
                    })->with(['erpShopGoodsSku' => function($query)use($select_erpShopGoodsSku) {
                        $query->select($select_erpShopGoodsSku);
                    }])->with(['systemGroup' => function($query)use($select_systemGroup) {
                        $query->select($select_systemGroup);
                    }])->with(['userCoupon' => function($query)use($user_userCoupon,$select_userCoupon) {
                        $query->where($user_userCoupon);
                        $query->select($select_userCoupon);
                    }])->where($exchange_where)->orderBy('create_time','desc')->select($select)->get();

                    if($can_exchange->count()==0){
                        $msg['code']=303;
                        $msg['msg']='没有数据';
                        return $msg;
                    }
                    $msg=$this->coupon_do($can_exchange,'exchange',$user_info,$now_time,$exchange);
                    break;

                case 'activity':
                    dd(2112);
                    break;


                default:
                    //第一步，通过传递过来的数组去拉取数据去
                    $exchange_where[]=['get_way','=',$type];
                    $can_exchange=ShopCoupon::wherehas('systemGroup',function($query)use($user_track_where2){
                        $query->where($user_track_where2);
                    })->with(['erpShopGoodsSku' => function($query)use($select_erpShopGoodsSku) {
                        $query->select($select_erpShopGoodsSku);
                    }])->with(['systemGroup' => function($query)use($select_systemGroup) {
                        $query->select($select_systemGroup);
                    }])->with(['userCoupon' => function($query)use($user_userCoupon,$select_userCoupon) {
                        $query->where($user_userCoupon);
                        $query->select($select_userCoupon);
                    }])->where($exchange_where)->orderBy('create_time','desc')->select($select)->get();

                    if($can_exchange->count()==0){
                        $msg['code']=303;
                        $msg['msg']='没有数据';
                        return $msg;
                    }

                    //dump($can_exchange->toArray());

                    $msg=$this->coupon_do($can_exchange,'user_coupon',$user_info,$now_time);
                    break;
            }

            return $msg;
        }else{
            //前端用户验证没有通过
            $erro=$validator->errors()->all();
            $msg['code']=300;
            $msg['msg']=null;
            foreach ($erro as $k => $v){
                $kk=$k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            return $msg;
        }
    }

     /**
     * 从shop_coupon  领取的  优惠券写入数据库的操作
     */
    public function coupon_do($arr,$tpye,$user_info,$now_time,$exchange=null){
        /**  $arr   是即将要处理的数据
         *   $tpye  类型是user_coupon    ，还有兑换券的， 还是赠送那里得到的
         *
         */
        $data   =[];
        $id     =false;
        switch ($tpye){
            case 'user_coupon':
                foreach ($arr as $k => $v){
                    if($v->userCoupon){
                        $yilin=$v->userCoupon->count();
                    }else{
                        $yilin=0;
                    }
                    $can_get_number         =$v->get_limit_number-$yilin;
                    if($can_get_number>0){
                        $list['self_id']                =generate_id('usecoupon_'); //用户优惠劵ID
                        $list['total_user_id']          =$user_info->total_user_id; //用户ID
                        $list['shop_coupon_id']         =$v->self_id; //优惠劵ID
                        $list['coupon_title']           =$v->coupon_title ; //优惠券名称'
                        $list['coupon_remark']          =$v->coupon_remark ; //优惠券后台备注'
                        $list['range_type']             =$v->range_type; //优惠券类型:满减reduce，折扣discount，无门槛all
                        $list['coupon_details']         =$v->coupon_details; //优惠券使用详情描述
                        $list['range_condition']        =$v->range_condition; //使用条件:0 不限制  其他数字代表满x分
                        $list['range']                  =$v->range; //优惠幅度
                        $list['use_type']               =$v->use_type; //使用类型:全场券all，单品券good，品类券classify，门店券shop，活动券activity',
                        $list['coupon_status']          ='unused'; //使用状态:unused未使用 ,stale已过期

                        if($v->time_type=='dynamic'){           //动态时间,以领取的时间开始计算
                            if($v->time_start_day>0){
                                $list['time_start']     =date('Y-m-d H:i:s',strtotime('+'.$v->time_start_day.'day'));//优惠劵开始日期
                                $tempAll                =$v->time_start_day+$v->time_end_day;
                                $list['time_end']       =date('Y-m-d H:i:s',strtotime('+'.$tempAll.'day'));
                            }else{
                                $list['time_start']     =$now_time;//优惠劵开始日期
                                $list['time_end']       =date('Y-m-d H:i:s',strtotime('+'.$v->time_end_day.'day'));
                            }

                        }else{
                            $list['time_start']         =$v->time_start;
                            $list['time_end']           =$v->time_end;
                        }

                        $list['use_self_lifting_flag']  =$v->use_self_lifting_flag;//是否自提可用
                        $list['create_time']            =$list['update_time']=$now_time;
                        $list['group_code']             =$v->group_code;
                        $list['group_name']             =$v->group_name;
                        $list['use_type_id']            =$v->use_type_id;//活动，门店，单品卷，品类卷等的ID
                        $list['use_fallticket_flag']    =$v->use_fallticket_flag; //是否可以和全场券叠加使用(Y表示可以叠加使用，N表示不可以叠加使用)
                        $list['give_flag']              =$v->rear_give_flag;//活动，门店，单品卷，品类卷等的ID
                        $data[]=$list;
                    }

                }

                if($data){
                    $id=UserCoupon::insert($data);
                }
                break;
            case 'exchange':
                foreach ($arr as $k => $v){
                    $list['self_id']                =generate_id('usecoupon_'); //用户优惠劵ID
                    $list['total_user_id']          =$user_info->total_user_id; //用户ID
                    $list['shop_coupon_id']         =$v->self_id; //优惠劵ID
                    $list['coupon_title']           =$v->coupon_title ; //优惠券名称'
                    $list['coupon_remark']          =$v->coupon_remark ; //优惠券后台备注'
                    $list['range_type']             =$v->range_type; //优惠券类型:满减reduce，折扣discount，无门槛all
                    $list['coupon_details']         =$v->coupon_details; //优惠券使用详情描述
                    $list['range_condition']        =$v->range_condition; //使用条件:0 不限制  其他数字代表满x分
                    $list['range']                  =$v->range; //优惠幅度
                    $list['use_type']               =$v->use_type; //使用类型:全场券all，单品券good，品类券classify，门店券shop，活动券activity',
                    $list['coupon_status']          ='unused'; //使用状态:unused未使用 ,stale已过期

                    if($v->time_type=='dynamic'){           //动态时间,以领取的时间开始计算
                        if($v->time_start_day>0){
                            $list['time_start']     =date('Y-m-d H:i:s',strtotime('+'.$v->time_start_day.'day'));//优惠劵开始日期
                            $tempAll                =$v->time_start_day+$v->time_end_day;
                            $list['time_end']       =date('Y-m-d H:i:s',strtotime('+'.$tempAll.'day'));
                        }else{
                            $list['time_start']     =$now_time;//优惠劵开始日期
                            $list['time_end']       =date('Y-m-d H:i:s',strtotime('+'.$v->time_end_day.'day'));
                        }

                    }else{
                        $list['time_start']         =$v->time_start;
                        $list['time_end']           =$v->time_end;
                    }

                    $list['use_self_lifting_flag']  =$v->use_self_lifting_flag;//是否自提可用
                    $list['create_time']            =$list['update_time']=$now_time;
                    $list['group_code']             =$v->group_code;
                    $list['group_name']             =$v->group_name;
                    $list['use_type_id']            =$v->use_type_id;//活动，门店，单品卷，品类卷等的ID
                    $list['use_fallticket_flag']    =$v->use_fallticket_flag; //是否可以和全场券叠加使用(Y表示可以叠加使用，N表示不可以叠加使用)
                    $list['give_flag']              =$v->rear_give_flag;//活动，门店，单品卷，品类卷等的ID
                    $data[]=$list;

                }

                if($data){
                    $id=UserCoupon::insert($data);
                    //把兑换券的兑换状态修改成已兑换
                    $where=[
                        ['exchange_flag','=','N'],
                        ['exchange_code','=',$exchange],
                    ];
                    $shopCouponExchangeUp['exchange_flag']          ='Y';
                    $shopCouponExchangeUp['update_time']            =$now_time;
                    ShopCouponExchange::where($where)->update($shopCouponExchangeUp);
                }
                break;
            case 'integral':
                foreach ($arr as $k => $v){
                    if($v->userCoupon){
                        $yilin=$v->userCoupon->count();
                    }else{
                        $yilin=0;
                    }
                    $can_get_number         =$v->get_limit_number-$yilin;
                    if($can_get_number>0){
                        $list['self_id']                =generate_id('usecoupon_'); //用户优惠劵ID
                        $list['total_user_id']          =$user_info->total_user_id; //用户ID
                        $list['shop_coupon_id']         =$v->self_id; //优惠劵ID
                        $list['coupon_title']           =$v->coupon_title ; //优惠券名称'
                        $list['coupon_remark']          =$v->coupon_remark ; //优惠券后台备注'
                        $list['range_type']             =$v->range_type; //优惠券类型:满减reduce，折扣discount，无门槛all
                        $list['coupon_details']         =$v->coupon_details; //优惠券使用详情描述
                        $list['range_condition']        =$v->range_condition; //使用条件:0 不限制  其他数字代表满x分
                        $list['range']                  =$v->range; //优惠幅度
                        $list['use_type']               =$v->use_type; //使用类型:全场券all，单品券good，品类券classify，门店券shop，活动券activity',
                        $list['coupon_status']          ='unused'; //使用状态:unused未使用 ,stale已过期

                        if($v->time_type=='dynamic'){           //动态时间,以领取的时间开始计算
                            if($v->time_start_day>0){
                                $list['time_start']     =date('Y-m-d H:i:s',strtotime('+'.$v->time_start_day.'day'));//优惠劵开始日期
                                $tempAll                =$v->time_start_day+$v->time_end_day;
                                $list['time_end']       =date('Y-m-d H:i:s',strtotime('+'.$tempAll.'day'));
                            }else{
                                $list['time_start']     =$now_time;//优惠劵开始日期
                                $list['time_end']       =date('Y-m-d H:i:s',strtotime('+'.$v->time_end_day.'day'));
                            }

                        }else{
                            $list['time_start']         =$v->time_start;
                            $list['time_end']           =$v->time_end;
                        }

                        $list['use_self_lifting_flag']  =$v->use_self_lifting_flag;//是否自提可用
                        $list['create_time']            =$list['update_time']=$now_time;
                        $list['group_code']             =$v->group_code;
                        $list['group_name']             =$v->group_name;
                        $list['use_type_id']            =$v->use_type_id;//活动，门店，单品卷，品类卷等的ID
                        $list['use_fallticket_flag']    =$v->use_fallticket_flag; //是否可以和全场券叠加使用(Y表示可以叠加使用，N表示不可以叠加使用)
                        $list['give_flag']              =$v->rear_give_flag;//活动，门店，单品卷，品类卷等的ID
                        $data[]=$list;
                    }

                }

                if($data){
                    $id=UserCoupon::insert($data);
                    //把用户的积分去掉，并添加一个积分的流水
                    $where=[
                        ['total_user_id','=',$user_info->total_user_id],
                    ];

                    $capitalUp['integral']          =$user_info->integral-$arr[0]->get_redeem_code;
                    $capitalUp['update_time']       =$now_time;
                    UserCapital::where($where)->update($capitalUp);

                    $wallet_data['self_id']        =generate_id('wallet_');
                    $wallet_data['total_user_id']  =$user_info->total_user_id;
                    $wallet_data['capital_type']   ='integral';
                    $wallet_data['produce_type']   ='CONSUME';
                    $wallet_data['create_time']    =$now_time;
                    $wallet_data['update_time']    =$now_time;
                    $wallet_data['money']          =$arr[0]->get_redeem_code;
                    $wallet_data['order_sn']       =$user_info->admin_id;
                    $wallet_data['now_money']      =$user_info->integral-$arr[0]->get_redeem_code;
                    $wallet_data['now_money_md']   =get_md5($wallet_data['now_money']);
                    $wallet_data['wallet_status']  ='SU';
                    UserWallet::insert($wallet_data);

                }
                break;
        }


        if($id){
            $msg['code']=200;
            $msg['msg']='操作成功';
        }else{
            $msg['code']=305;
            $msg['msg']='没有数据';
        }
        return $msg;


    }

    /**
     * 从user_coupon  领取的  优惠券写入数据库的操作
     */
    public function user_coupon_do($arr,$tpye,$user_info,$now_time,$total_user_id,$exchange=null){
        //DUMP($arr);
        $uplist=[];
        $data=[];
        foreach ($arr as $k => $v){
            $list['self_id']                =generate_id('usecoupon_'); //用户优惠劵ID
            $list['total_user_id']          =$user_info->total_user_id; //用户ID
            $list['shop_coupon_id']         =$v['user_coupon']['shop_coupon_id']; //优惠劵ID
            $list['coupon_title']           =$v['user_coupon']['coupon_title'] ; //优惠券名称'
            $list['coupon_remark']          =$v['user_coupon']['coupon_remark'] ; //优惠券后台备注'
            $list['range_type']             =$v['user_coupon']['range_type']; //优惠券类型:满减reduce，折扣discount，无门槛all
            $list['coupon_details']         =$v['user_coupon']['coupon_details']; //优惠券使用详情描述
            $list['range_condition']        =$v['user_coupon']['range_condition']; //使用条件:0 不限制  其他数字代表满x分
            $list['range']                  =$v['user_coupon']['range']; //优惠幅度
            $list['use_type']               =$v['user_coupon']['use_type']; //使用类型:全场券all，单品券good，品类券classify，门店券shop，活动券activity',
            $list['coupon_status']          ='unused'; //使用状态:unused未使用 ,stale已过期
            $list['time_start']             =$v['user_coupon']['time_start'];
            $list['time_end']               =$v['user_coupon']['time_end'];
            $list['use_self_lifting_flag']  =$v['user_coupon']['use_self_lifting_flag'];//是否自提可用
            $list['create_time']            =$list['update_time']=$now_time;
            $list['group_code']             =$v['user_coupon']['group_code'];
            $list['group_name']             =$v['user_coupon']['group_name'];
            $list['use_type_id']            =$v['user_coupon']['use_type_id'];//活动，门店，单品卷，品类卷等的ID
            $list['use_fallticket_flag']    =$v['user_coupon']['use_fallticket_flag']; //是否可以和全场券叠加使用(Y表示可以叠加使用，N表示不可以叠加使用)
            $list['give_flag']              =$v['user_coupon']['give_flag'];//活动，门店，单品卷，品类卷等的ID
            $list['get_coupon_give_id']     =$v['give_id'];
            $list['get_cause_reason']       =$tpye;
            $data[]=$list;

            $uplist[]           =$v['self_id'];
        }

        $id=UserCoupon::insert($data);

        $up['get_user_id']      =$user_info->total_user_id;
        $up['get_time']         =$now_time;
        UserCouponGiveList::whereIn('self_id',$uplist)->update($up);

        /**如果这个东西是积分购买的，那么要给购买人-积分，发送人加积分**/
        if($tpye == 'transfer'){
            $where=[
                ['total_user_id','=',$user_info->total_user_id],
            ];

            $capitalUp['integral']          =$user_info->integral-$exchange;
            $capitalUp['update_time']       =$now_time;
            UserCapital::where($where)->update($capitalUp);

            $wallet_data['self_id']        =generate_id('wallet_');
            $wallet_data['total_user_id']  =$user_info->total_user_id;
            $wallet_data['capital_type']   ='integral';
            $wallet_data['produce_type']   ='CONSUME';
            $wallet_data['create_time']    =$now_time;
            $wallet_data['update_time']    =$now_time;
            $wallet_data['money']          =$exchange;
            $wallet_data['order_sn']       =$user_info->total_user_id;
            $wallet_data['now_money']      =$user_info->integral-$exchange;
            $wallet_data['now_money_md']   =get_md5($wallet_data['now_money']);
            $wallet_data['wallet_status']  ='SU';
            UserWallet::insert($wallet_data);

            //查询出原来的那个人的积分，把积分给他加上
            $where=[
                ['total_user_id','=',$total_user_id],
            ];

            $jifen=UserCapital::where($where)->value('integral');
            $capitalUp['integral']          =$jifen+$exchange;
            $capitalUp['update_time']       =$now_time;
            UserCapital::where($where)->update($capitalUp);

            $wallet_data['self_id']        =generate_id('wallet_');
            $wallet_data['total_user_id']  =$total_user_id;
            $wallet_data['capital_type']   ='integral';
            $wallet_data['produce_type']   ='IN';
            $wallet_data['create_time']    =$now_time;
            $wallet_data['update_time']    =$now_time;
            $wallet_data['money']          =$exchange;
            $wallet_data['order_sn']       =$user_info->total_user_id;
            $wallet_data['now_money']      =$jifen+$exchange;
            $wallet_data['now_money_md']   =get_md5($wallet_data['now_money']);
            $wallet_data['wallet_status']  ='SU';
            UserWallet::insert($wallet_data);

        }

        if($id){
            $msg['code']=200;
            $msg['msg']='操作成功';
        }else{
            $msg['code']=305;
            $msg['msg']='没有数据';
        }
        return $msg;
    }

}
?>
