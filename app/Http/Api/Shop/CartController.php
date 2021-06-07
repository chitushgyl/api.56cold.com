<?php
namespace App\Http\Api\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Tools\CartNumber as CartNumber;
use App\Models\User\UserCart;
use App\Models\Shop\ErpShopGoods;



class CartController extends Controller{
	/**
     * 加入购物车操作      /shop/cart
     * 前端传递必须参数：	类型type:cart,alone
     */
    public function cart(Request $request){
        $user_info 		= $request->get('user_info');//接收中间件产生的参数
		$group_info		=$request->get('group_info');
		$group_code		= $group_info->group_code??config('page.platform.group_code');

		$address_id		=$request->input('address_id');			//接受一个地址ID过来
		$all_coupon_id	=$request->input('all_coupon_id');		//接受一个全场优惠券ID过来
        $type			=$request->input('type')??'cart';
        $now_time		=date('Y-m-d H:i:s',time());
        $sku_id			=$request->input('sku_id');
        $number			=$request->input('number');
        /*** 虚拟数据*/
        $group_code		='1234';
        $address_id		='address_202101061314486494799341';
        $sku_id			='sku_202101081338184705578312';
        $number			='10';
        $type			='cart';
        $type			='alone';

        dump($user_info->toArray());

        dump($group_code);


            //开始初始化数据
        $pay['goods_total_money']               ='0';						//商品总金额
        $pay['discounts_single_total']          ='0';                 //单品券优惠总计
        $pay['discounts_all_total']             ='0';                    //全场券优惠总计
        $pay['discounts_activity_total']        ='0';               //活动优惠总计
        $pay['discounts_total_money']           ='0';                  //总计优惠
        $pay['serve_total_money']               ='0';                      //服务总费用
        $pay['kehu_yinfu']                      ='0';
        $pay['good_count']                      ='0';                         	//商品总数量

        //$input=Input::all();

            /**	以下为拿取商品信息的开始位置	*/
            switch ($type){
                case 'cart':
                    //拉取商品数据
                    if($group_code == config('page.platform.group_code')){
                        $where=[
                            ['total_user_id','=',$user_info->total_user_id],
                            ['use_flag','=','Y'],
                            ['delete_flag','=','Y'],
                        ];
                    }else{
                        $where=[
                            ['group_code','=',$group_code],
                            ['total_user_id','=',$user_info->total_user_id],
                            ['use_flag','=','Y'],
                            ['delete_flag','=','Y'],
                        ];

                    }
                    $select=['self_id as cart_id','good_id','sku_id','good_number','checked_state'];

                    $where_erp_shop_goods_sku=[
                        ['delete_flag','=','Y'],
                        ['good_status','=','Y'],
                        ['sell_start_time','<',$now_time],
                        ['sell_end_time','>',$now_time],
                    ];
                    $select_erp_shop_goods_sku=['self_id','sale_price','integral_scale'];
                    $where_erp_shop_goods=[
                        ['sell_start_time','<',$now_time],
                        ['sell_end_time','>',$now_time],
                        ['delete_flag','=','Y'],
                        ['good_status','=','Y'],
                    ];
                    $select_erp_shop_goods=['self_id','good_title','group_code','thum_image_url','label_info'];

                    $where_group=[
                        ['use_flag','=','Y'],
                        ['delete_flag','=','Y'],
                    ];

					$good_infos=UserCart::wherehas('erpShopGoodsSku',function($query)use($where_erp_shop_goods_sku){
                            $query->where($where_erp_shop_goods_sku);
                        })->wherehas('erpShopGoods',function($query)use($where_erp_shop_goods){
                        	$query->where($where_erp_shop_goods);
                    	})->wherehas('systemGroup',function($query)use($where_group){
                        	$query->where($where_group);
                    	})->with(['erpShopGoodsSku' => function($query)use($select_erp_shop_goods_sku) {
                            $query->select($select_erp_shop_goods_sku);
                        }])->with(['erpShopGoods' => function($query)use($select_erp_shop_goods) {
                            $query->select($select_erp_shop_goods);
                        }])
						->where($where)->select($select)->get();


					if($good_infos->count() == 0){
                        $msg['code']=301;
                        $msg['msg']='购物车没有商品';
                        return $msg;
                    }

                    $good_info=[];
                    foreach ($good_infos as $k => $v){
                        $abc['cart_id']                 =$v->cart_id;
                        $abc['good_title']              =$v->erpShopGoods->good_title;
                        $abc['thum_image_url']          =img_for($v->erpShopGoods->thum_image_url,'more');
                        $abc['sale_price_show']         =number_format($v->erpShopGoodsSku->sale_price/100,2);			//价格显示的地方
                        $abc['total_price']             =$v->erpShopGoodsSku->sale_price*$v->good_number;				                    //商品总金额
                        $abc['can_checked']             ='Y';				                    //商品总金额
                        //做一个优惠计算的标记，默认为N，还没有计算过，如果计算过了，给个Y，不再发起其他的计算
                        $abc['can_count_discount']      ='N';

                        if($v->checked_state=='Y'){
                            //开始初始化数据
                            $pay['goods_total_money'] += $v->erpShopGoodsSku->sale_price*$v->good_number;				//商品总金额
                            $abc['checked_state']='true';
                            $pay['good_count'] +=$v->good_number;

                        }else{
                            $abc['checked_state']='false';
                        }

                        $label_info= label_do($v->erpShopGoods->label_info , $now_time);
                        $abc['label_show_flag']         =$label_info['label_show_flag'];
                        $abc['label_show']              =$label_info['label_show'];
                        $good_info[]=$abc;
                    }


                    break;
                case 'alone':
                    $where=[
                        ['delete_flag','=','Y'],
                        ['good_status','=','Y'],
                        ['sell_start_time','<',$now_time],
                        ['sell_end_time','>',$now_time],
                    ];
                    $select_erp_shop_goods=['self_id','good_title','group_code','thum_image_url','label_info'];

                    $where_erp_shop_goods_sku=[
                        ['self_id','=',$sku_id],
                        ['sell_start_time','<',$now_time],
                        ['sell_end_time','>',$now_time],
                        ['delete_flag','=','Y'],
                        ['good_status','=','Y'],
                    ];
                    $select_erp_shop_goods_sku=['self_id','sale_price','integral_scale','good_id'];

                    $where_group=[
                        ['use_flag','=','Y'],
                        ['delete_flag','=','Y'],
                    ];

                    $good_infos=ErpShopGoods::wherehas('erpShopGoodsSku',function($query)use($where_erp_shop_goods_sku){
                        $query->where($where_erp_shop_goods_sku);
                    })->wherehas('systemGroup',function($query)use($where_group){
                        $query->where($where_group);
                    })->with(['erpShopGoodsSku' => function($query)use($select_erp_shop_goods_sku,$where_erp_shop_goods_sku) {
                        $query->where($where_erp_shop_goods_sku);
                        $query->select($select_erp_shop_goods_sku);
                    }])->where($where)->select($select_erp_shop_goods)->get();

                    if($good_infos->count() == 0){
                        $msg['code']=301;
                        $msg['msg']='查询不到没有商品';
                        return $msg;
                    }


                    dump($good_infos->toArray());

                    $good_info=[];
                    foreach ($good_infos as $k => $v){
                        $abc['cart_id']                 =1;
                        $abc['good_title']              =$v->good_title;
                        $abc['thum_image_url']          =img_for($v->thum_image_url,'more');
                        $abc['sale_price_show']         =number_format($v->erpShopGoodsSku[0]->sale_price/100,2);			//价格显示的地方
                        $abc['total_price']             =$v->erpShopGoodsSku[0]->sale_price*$number;				                    //商品总金额
                        $abc['can_checked']             ='Y';
                        //做一个优惠计算的标记，默认为N，还没有计算过，如果计算过了，给个Y，不再发起其他的计算
                        $abc['can_count_discount']      ='N';

                        if($v->checked_state=='Y'){
                            //开始初始化数据
                            $pay['goods_total_money'] += $v->erpShopGoodsSku[0]->sale_price*$number;				//商品总金额
                            $abc['checked_state']='true';
                            $pay['good_count'] +=$v->good_number;

                        }else{
                            $abc['checked_state']='false';
                        }

                        $label_info= label_do($v->label_info , $now_time);
                        $abc['label_show_flag']         =$label_info['label_show_flag'];
                        $abc['label_show']              =$label_info['label_show'];
                        $good_info[]=$abc;
                    }


                    break;
            }
            /**	以上为拿取商品信息的结束位置1	*/
//dd($good_infos);

DD($good_info);

        dd($good_infos->toArray());




            if($good_infos){


                //dd($good_infos);

                /**	开始做计算位置	*/
                $pay['kehu_yinfu']=$pay['goods_total_money'];

				//dump($pay);
                //抓取活动和优惠券
                $user_activity=null;            //假设活动是空

                $where_coupon=[
                    ['total_user_id','=',$user_info->total_user_id],
                    ['coupon_status','=','unused'],
                    ['time_start','<',$now_time],
                    ['time_end','>',$now_time],
                    ['delete_flag','=','Y'],
                    ['use_flag','=','Y'],
                ];
                $user_coupon=DB::table('user_coupon')->where($where_coupon)
                        ->select('self_id as user_coupon_id',
                            'coupon_title',
                            'range_type',
                            'range_condition',
                            'range',
                            'use_type',
                            'use_self_lifting_flag',
                            'use_type_id',
                            'group_code',
							'time_start',
							'time_end'
                            )
                ->get()->toArray();

                //dump($pay);

				//dump($user_coupo1n);

                $msg_info=calculate($group_code,$good_infos,$user_activity,$user_coupon,$pay,$all_coupon_id);
				//dd($msg_info);
                $pay_info=$msg_info['pay'];
                foreach ($pay_info as $k => $v){
                    $pay_info['goods_total_money_show']=number_format($pay_info['goods_total_money']/100,2);
                    $pay_info['serve_total_money_show']=number_format($pay_info['serve_total_money']/100,2);
                    $pay_info['discounts_total_money_show']=number_format($pay_info['discounts_total_money']/100,2);
                    $pay_info['kehu_yinfu_show']=number_format($pay_info['kehu_yinfu']/100,2);

                }
                /**	计算结束位置	*/
				
				/*做一个地址出来一下开始111  11*/
				$address_info=null;
				$address_id=$request->get('address_id');
				if($address_id){
					if($address_id=='001'){
						//自提信息
						$address_info['address_id']='001';
						$address_info['name']='自提';
						$address_info=(object)$address_info;
						
					}else{
						$where_address=[
							['self_id','=',$address_id],
							['delete_flag','=','Y'],
						];
						$address_info=DB::table('user_address')->where($where_address)
								->select('self_id as address_id',
									'name',
									'tel',
									'address'
									) ->first();
					
						
					}
				}else{
					$where_address_default=[
							['total_user_id','=',$user_info->total_user_id],
							['default_flag','=','Y'],
							['delete_flag','=','Y'],
						];
					
					$address_info=DB::table('user_address')->where($where_address_default)
							->select('self_id as address_id',
								'name',
								'tel',
								'address'
								) ->first();
					
				}
	
				
				
				/*做一个地址出来一结束  */
                $msg['code']=200;
                $msg['msg']='数据计算成功';
                $msg['address_info']=$address_info;
				$msg['good_infos']=$msg_info['good_infos'];
                $msg['pay_info']=$pay_info;
                $msg['user_all_coupon_can']=$msg_info['user_all_coupon_can'];
				$msg['user_all_coupon_canno']=$msg_info['user_all_coupon_canno'];
				$msg['change_price_show']='N';

			//dd($msg);
            }else{
                $msg['code']=301;
                $msg['msg']='没有商品！';
                return $msg;
			}


        //dd($msg);

        return $msg;
    }
	
	
    /**
     * 加入购物车操作      /cart/add_cart
     * 前端传递必须参数：
     *前端传递非必须参数：user_token(用户token)
     * 回调结果：200  数据拉取成功
     *
     *回调数据：  商品列表信息
     */
    public function add_cart(Request $request,CartNumber $cartNumber){
        $group_info		=$request->get('group_info');
        $user_info		=$request->get('user_info');
        $now_time       =date('Y-m-d H:i:s',time());

		$good_id		=$request->input('good_id');
		$sku_id			=$request->input('sku_id');
		$number			=$request->input('number');

		$group_code=$group_info->group_code??config('page.platform.group_code');
		//$user_id='user_202004021006499587468765';
		/** 虚拟数据
		$user_id='user_202001192116520483548783';
		$good_id='12212';
		$sku_id='12212';
        $number='2';
		*/

		$datt['total_user_id']	=$user_info->total_user_id;
		$datt['good_id']		=$good_id;
		$datt['sku_id']			=$sku_id;
		$datt['delete_flag']	="Y";
		$datt['use_flag']		="Y";
		$datt['group_code']		=$group_code;



		$cart_info=DB::table('user_cart')->where($datt)->first();

		if($cart_info){
			$where['self_id']=$cart_info->self_id;

			//如果购物车有这个商品
			$datt['good_number']=$cart_info->good_number + $number;
			$datt['update_time']=$now_time;

			DB::table('user_cart')->where($where)->update($datt);
			$msg['code']=200;

			$msg['cart_number']	= $cartNumber->cart_number($user_info,$group_code);
			$msg['msg']="加入购物车成功";
            return $msg;

		}else{
            //去抓取平台方的公司进来
            $group_where['group_code']=$group_code;
            $group_name=DB::table('system_group')->where($group_where)->value('group_name');
            $datt['group_name']=$group_name;

			$datt['self_id']=generate_id('cart_');
			$datt['good_number']=$number;
			$datt['create_time']=$datt['update_time']=$now_time;
			DB::table('user_cart')->insert($datt);

            $msg['cart_number']	= $cartNumber->cart_number($user_info,$group_code);


			$msg['code']=200;
			$msg['msg']="加入购物车成功";
            return $msg;

		}
			


		//dd($msg);


    }

    /**
     * 购物车中修改数量     /cart/change_cart_number
     * 前端传递必须参数：
     *前端传递非必须参数：user_token(用户token)   address_id
     * 回调结果：200  数据拉取成功
     *
     *回调数据：
     */
    public function change_cart_number(Request $request,CartNumber $cartNumber){
        $group_info		=$request->get('group_info');
        $user_info		=$request->get('user_info');
        $group_code		=$group_info->group_code??config('page.platform.group_code');
        $now_time		=date('Y-m-d H:i:s',time());

		$cart_id		=$request->input('cart_id');
		$number			=$request->input('number');

		
		/** 虚拟数据*/
		//$user_id='user_202001192116520483548783';
		//$cart_id='cart_202003201807160565700665';
        //$number='2';

		//如果过来的是0，则将这个商品删除掉
		if($number>0){
			$datt['good_number']=$number;
			$datt['update_time']=$now_time;

		}else{
			$datt['good_number']=0;
			$datt['update_time']=$datt['delete_time']=$now_time;
			$datt['delete_flag']="N";
			$datt['delete_cause']="initiative";
		}

		$where['self_id']=$cart_id;
		DB::table('user_cart')->where($where)->update($datt);

		$msg['code']=200;
		$msg['msg']='购物车数量修改成功！';
		$msg['data']=$number;

        $msg['cart_number']	= $cartNumber->cart_number($user_info,$group_code);


		//dd($msg);
        return $msg;
    }

    /**
     * 购物车中是否选中的修改     /cart/change_cart_check
     * 前端传递必须参数：    type(单独alone，数组array)
     *前端传递非必须参数：user_token(用户token)   address_id
     * 回调结果：200  数据拉取成功
     *
     *回调数据：
     */
    public function change_cart_check(Request $request){
        $user_info		=$request->get('user_info');
        $group_info		=$request->get('group_info');
        $group_code		=$group_info->group_code??config('page.platform.group_code');
        //$user_id='user_202001192116520483548783';

		$cart_id		=$request->input('cart_id');
		$cart_ids		=$request->input('cart_ids');
		//dd($request->all());
		$checked		=$request->input('checked');
		$type			=$request->input('type')??'alone';
		/** 虚拟数据*/
		//$type='array';

		//$cart_id='cart_202004021754207751419381';
		//$checked='N';
		//$cart_ids=[
		   // 'cart_202004141352313041684378',
		   // 'cart_202004141352377562109221',
		//];

		$data['checked_state']=$checked;
		$data['update_time']=date('Y-m-d H:i:s',time());
		switch ($type){
			case 'alone':
					$cart_where['self_id']=$cart_id;
					$id=DB::table('user_cart')->where($cart_where)->update($data);
				break;

			case 'array':
			//print_r($data);
			//print_r($cart_ids);
				$id=DB::table('user_cart')->whereIn('self_id',$cart_ids)->update($data);
				break;

		}

		if($id){
			$msg['code']=200;
			$msg['msg']='购物车数选中状态修改成功！';
			$msg['data']=$checked;
			
		}else{
			$msg['code']=301;
			$msg['msg']='购物车数选中状态修改失败！';
		}


        //dd($msg);
        return $msg;

    }

}
?>
