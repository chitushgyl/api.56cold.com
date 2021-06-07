<?php
namespace App\Http\Api\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Shop\ErpShopGoods;
use App\Models\Shop\ErpShopGoodsSku;
use App\Models\Shop\ShopCatalog;
use App\Models\User\UserTrack;
use App\Models\Shop\HomeMenuRelevance;
use App\Models\Shop\ShopCatalogGood;


class GoodController extends Controller{
    /**
     * 商品列表加载数据      /shop/good_page
     * 前端传递必须参数：page（第几页，从0开始）   type（类型：home_menu，kw，catalog）
     * 类型为home_menu 的 必须传  config_id（二级菜单ID）
     * 类型为kw 的 必须传  serach（搜索的具体内容）
     * 类型为catalog 的 必须传  catalog_id（分类ID）
     * 回调结果：200  数据拉取成功
     *
     *回调数据：  商品列表信息
     */
    public function good_page(Request $request){
        $user_info		=$request->get('user_info');
        $group_info		=$request->get('group_info');
        $group_code		=$group_info->group_code??config('page.platform.group_code');
        $listrows		=config('page.listrows')[1];//每次加载的数量
        $first			=$request->input('page')??1;
        $firstrow		=($first-1)*$listrows;
        $now_time		=date('Y-m-d H:i:s',time());
		
		$type			=$request->get('type');
        $config_id		=$request->get('config_id');
        $serach			=$request->get('serach');
        $catalog_id     =$request->get('catalog_id');
		/** 虚拟数据*/
        $group_code='group_202101081317220167531123';
        $type='catalog';
        $config_id='home_menu';
        $config_id='configdata201912231758455676339635';
        $catalog_id='1212121';
        $serach='哇';



        $good_infos =[];
        $select=['self_id','good_type','good_title','good_info','good_describe','thum_image_url',
						'price_show_flag','min_price','max_price','cart_flag','group_name','group_code',
            'label_flag','label_image_url','label_text','label_start_time','label_end_time'];

        $where_group=[
            ['delete_flag','=','Y'],
            ['use_flag','=','Y'],
        ];
        //$good_info=[];


        switch ($type){
            case 'home_menu':
                //二级菜单的商品
				if($config_id == 'home_menu' ){
                    $where=[
                        ['group_code','=',$group_code],
                        ['good_status','=','Y'],
                        ['delete_flag','=','Y'],
                        ['sell_start_time','<',$now_time],
                        ['sell_end_time','>',$now_time],
                    ];

                    $good_infos=ErpShopGoods::wherehas('systemGroup',function($query)use($where_group){
                        $query->where($where_group);
                    })->where($where)
                        ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                        ->select($select)
                        ->get()->toArray();

//                    dd($good_infos);

                }else{

					$where=[
						['delete_flag','=','Y'],
						['good_status','=','Y'],
						['sell_start_time','<',$now_time],
						['sell_end_time','>',$now_time],
					];


                    $where_homeMenuRelevance=[
                        ['config_data_id','=',$config_id],
                        ['delete_flag','=','Y'],
                        ['use_flag','=','Y'],
                    ];

                    $good_infos_qiao=HomeMenuRelevance::wherehas('erpShopGoods',function($query)use($where_group,$where,$select){
                        $query->where($where);
                        $query->wherehas('systemGroup',function($query)use($where_group){
                            $query->where($where_group);
                        });
                    })->with(['erpShopGoods' => function($query)use($select,$where) {
                        $query->where($where);
                        $query->select($select);
                    }])->where($where_homeMenuRelevance)
                        ->offset($firstrow)->limit($listrows)
                        ->select('relevance_id')->orderBy('sort', 'desc')
                        ->get();

                    $good_infos=[];
                    foreach ($good_infos_qiao as $k => $v){
                        $list['self_id']                  =$v->erpShopGoods->self_id;
                        $list['good_type']                =$v->erpShopGoods->good_type;
                        $list['good_title']               =$v->erpShopGoods->good_title;
                        $list['good_info']                =$v->erpShopGoods->good_info;
                        $list['good_describe']            =$v->erpShopGoods->good_describe;
                        $list['thum_image_url']           =$v->erpShopGoods->thum_image_url;
                        $list['price_show_flag']          =$v->erpShopGoods->price_show_flag;
                        $list['min_price']                =$v->erpShopGoods->min_price;
                        $list['max_price']              =$v->erpShopGoods->max_price;
                        $list['cart_flag']              =$v->erpShopGoods->cart_flag;
                        $list['group_name']             =$v->erpShopGoods->group_name;
                        $list['group_code']             =$v->erpShopGoods->group_code;
                        $list['label_flag']             =$v->erpShopGoods->label_flag;
                        $list['label_image_url']        =$v->erpShopGoods->label_image_url;
                        $list['label_text']             =$v->erpShopGoods->label_text;
                        $list['label_start_time']      =$v->erpShopGoods->label_start_time;
                        $list['label_end_time']         =$v->erpShopGoods->label_end_time;
                        $good_infos[]=$list;
                    }
				}


                break;

            case 'kw':
                //搜索结果的商品
				if($group_code== '1234'){
					//如果是1234，则拉出所有的数据
					$where=[
						['search_label','like','%'.$serach.'%'],
						['good_status','=','Y'],
						['delete_flag','=','Y'],
						['sell_start_time','<',$now_time],
						['sell_end_time','>',$now_time],
					];
				}else{
					//如果不是1234.则可能需要使用=或者是看他数据源是多少个，用IN查询了
					$where=[
                        ['group_code','=',$group_code],
                        ['search_label','like','%'.$serach.'%'],
                        ['good_status','=','Y'],
                        ['delete_flag','=','Y'],
                        ['sell_start_time','<',$now_time],
                        ['sell_end_time','>',$now_time],
					];
				}

                $good_infos=ErpShopGoods::wherehas('systemGroup',function($query)use($where_group){
                    $query->where($where_group);
                })->where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)
                    ->get()->toArray();

                dd($good_infos);
                break;

            case 'catalog':
                //分类中的商品的商品
                //先拿到这个东西的层级，然后再看看
                $where_catalog=[
                    ['self_id','=',$catalog_id],
                ];
                $level=ShopCatalog::where($where_catalog)->value('level');

                $where=[
                    ['delete_flag','=','Y'],
                    ['good_status','=','Y'],
                    ['sell_start_time','<',$now_time],
                    ['sell_end_time','>',$now_time],
                ];

                if($level=='1'){
                    $where_shopCatalogGood=[
                        ['delete_flag','=','Y'],
                        ['use_flag','=','Y'],
                        ['parent_catalog_id','=',$catalog_id],
                    ];
                }else{
                    $where_shopCatalogGood=[
                        ['delete_flag','=','Y'],
                        ['use_flag','=','Y'],
                        ['catalog_id','=',$catalog_id],
                    ];
                }

                $good_infos_qiao=ShopCatalogGood::wherehas('erpShopGoods',function($query)use($where_group,$where,$select){
                    $query->where($where);
                    $query->wherehas('systemGroup',function($query)use($where_group){
                        $query->where($where_group);
                    });
                })->with(['erpShopGoods' => function($query)use($select,$where) {
                    $query->where($where);
                    $query->select($select);
                }])->where($where_shopCatalogGood)
                    ->offset($firstrow)->limit($listrows)
                    ->select('good_id')->orderBy('sort', 'desc')
                    ->get();


                $good_infos=[];
                foreach ($good_infos_qiao as $k => $v){
                    $list['self_id']                  =$v->erpShopGoods->self_id;
                    $list['good_type']                =$v->erpShopGoods->good_type;
                    $list['good_title']               =$v->erpShopGoods->good_title;
                    $list['good_info']                =$v->erpShopGoods->good_info;
                    $list['good_describe']            =$v->erpShopGoods->good_describe;
                    $list['thum_image_url']           =$v->erpShopGoods->thum_image_url;
                    $list['price_show_flag']          =$v->erpShopGoods->price_show_flag;
                    $list['min_price']                =$v->erpShopGoods->min_price;
                    $list['max_price']              =$v->erpShopGoods->max_price;
                    $list['cart_flag']              =$v->erpShopGoods->cart_flag;
                    $list['group_name']             =$v->erpShopGoods->group_name;
                    $list['group_code']             =$v->erpShopGoods->group_code;
                    $list['label_flag']             =$v->erpShopGoods->label_flag;
                    $list['label_image_url']        =$v->erpShopGoods->label_image_url;
                    $list['label_text']             =$v->erpShopGoods->label_text;
                    $list['label_start_time']      =$v->erpShopGoods->label_start_time;
                    $list['label_end_time']         =$v->erpShopGoods->label_end_time;
                    $good_infos[]=$list;
                }

//                DD($good_infos);

                break;
				
			case 'classify':
				$where=[
					['classify_id',$catalog_id],
					['good_status','=','Y'],
					['delete_flag','=','Y'],
					['sell_start_time','<',$now_time],
					['sell_end_time','>',$now_time],
				];

                $good_infos=ErpShopGoods::wherehas('systemGroup',function($query)use($where_group){
                    $query->where($where_group);
                })->where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)
                    ->get()->toArray();

				break;

        }


//        dump($good_infos->toArray());
        foreach ($good_infos as $k => $v){
            $good_infos[$k]['thum_image_url']       =img_for($v['thum_image_url'],'more');
            $good_infos[$k]['label_image_url']      =img_for($v['label_image_url'],'more');

            $good_infos[$k]['label_flag']      =label_do($v['label_flag'],$v['label_start_time'],$v['label_end_time'],$now_time);

            $good_infos[$k]['good_info']            =json_decode($v['good_info'],true);

            $good_infos[$k]['price_show']=price_show($v['price_show_flag'],$v['min_price'],$v['max_price']);

        }

        //dd($good_infos);  
        $msg['code']=200;
        $msg['msg']='数据拉取成功！';
        $msg['data']=$good_infos;

        return $msg;
    }

    /**
     * 商品详情     /shop/good_details
     * 前端传递必须参数：good_id
     *
     * 回调结果：200  数据拉取成功
     *
     *回调数据：  商品信息
     */
    public function good_details(Request $request){
        $user_info		=$request->get('user_info');
        $now_time		=date('Y-m-d H:i:s',time());
        //用户信息抓取
		$good_id		=$request->input('good_id');
		//$good_id		='good_202010100931229853334573';
        //$good_id		='good_202009271357482192469829';
        //$user_id='user_202001192116520483548783';
        $good_where=[
            ['self_id','=',$good_id],
            ['delete_flag','=','Y'],
            ['good_status','=','Y'],
        ];

        $select=['self_id','self_id as good_id','good_type','good_title','good_info',
            'good_describe','thum_image_url','scroll_image_url','details_image_url','price_show_flag','min_price','max_price','label_info','cart_flag','group_name'];


        $good_info=ErpShopGoods::where($good_where)->select($select)->first();

		//dd(json_decode(json_encode($good_info),true));
        if($good_info) {
            $good_info->price_show=price_show($good_info->price_show_flag,$good_info->min_price,$good_info->max_price);

            /**处理商品详细信息**/


			/**处理图片**/
            $good_info->thum_image_url=img_for($good_info->thum_image_url,'more');
            $good_info->scroll_image_url=img_for($good_info->scroll_image_url,'more');
            $good_info->details_image_url=img_for($good_info->details_image_url,'more');

            $good_info->label_image_url=img_for($good_info->label_image_url,'more');

            $good_info->label_flag=label_do($good_info->label_flag,$good_info->label_start_time,$good_info->label_end_time,$now_time);


            //dd($good_info->toArray());


            //看下是不是有收藏
            if($user_info){
                $track_where=[
                    ['data_id','=',$good_info->good_id],
					['total_user_id','=',$user_info->total_user_id],
                    ['delete_flag','=','Y'],
                ];
                $good_info->track_id=UserTrack::where($track_where)->value('self_id');
            }
		

            //dd($good_info->toArray());

            $pv['user_id']=$user_info?$user_info->total_user_id:null;
			$pv['browse_path']=$request->path();
			$pv['level']=null;
			$pv['table_id']=$good_id;
			$pv['ip']=$request->getClientIp();
			$pv['place']='CT_H5';
//			pvuv($pv);
			//$good_info=json_decode(json_encode($good_info),true);
//			$good_info->cart_flag = 'alone';
            $msg['code']=200;
            $msg['msg']='数据拉取成功！';
            $msg['data']=$good_info;
			
        }else{
            $msg['code']=300;
            $msg['msg']='商品不存在或已下架！';
            $msg['data']=$good_info;


        }
		
		
		
		//dd($msg);
        return $msg;
    }

     /**
     * 商品详情下方的收藏按钮1     /shop/good_track
     */
    public function good_track(Request $request){
        $user_info		=$request->get('user_info');

        $group_info		=$request->get('group_info');
        $group_code		=$group_info->group_code??config('page.platform.group_code');
        $group_name		=$group_info->group_name??config('page.platform.group_name');

        $now_time		=date('Y-m-d H:i:s',time());
        //添加及删除收藏
		$good_id		=$request->input('good_id');

//        $good_id		='good_202004261523566734855761';
        $track_good_where=[
            ['self_id','=',$good_id],
            ['delete_flag','=','Y'],
        ];
        $erp_shop_goods=ErpShopGoods::where($track_good_where)->select('group_code','group_name')->first();

        if($erp_shop_goods){
            $track_check=[
                ['total_user_id','=',$user_info->total_user_id],
                ['data_id','=',$good_id],
                ['delete_flag','=','Y'],
                ['class_type','=','good'],
            ];

            $track_id=UserTrack::where($track_check)->value('self_id');

            if($track_id){
                $data['delete_flag']='N';
                $data['delete_time']=$data['update_time']=$now_time;
                $id=UserTrack::where($track_check)->update($data);

                $abc['track_id']=$track_id;
                $msgs='取消商品收藏';
            }else{
                $data2['self_id']           =generate_id('track_');
                $data2['total_user_id']     =$user_info->total_user_id;
                $data2['class_type']        ='good';
                $data2['group_code']        =$erp_shop_goods->group_code;
                $data2['group_name']        =$erp_shop_goods->group_name;
                $data2['show_group_code']   =$group_code;
                $data2['show_group_name']   =$group_name;
                $data2['data_id']           =$good_id;
                $data2['create_time']       =$data2['update_time']=$now_time;
                $id=UserTrack::insert($data2);

                $abc['track_id']=$data2['self_id'];
                $msgs='商品收藏';
            }

            if($id){
                $msg['code']=200;
                $msg['data']=$abc;
                $msg['msg']=$msgs.'成功';
                return $msg;
            }else{
                $msg['code']=301;
                $msg['msg']=$msgs.'失败';
                return $msg;
            }

		}else{
            $msg['code']=301;
            $msg['msg']='没有查询到商品';
            return $msg;
		}




    }
    /**
     * 商品详细SKU信息     /shop/good_sku
     *回调数据：
     */
    public function good_sku(Request $request){
		$good_id	=$request->input('good_id');
//		$good_id	='good_202004261523566734855761';
		//dd($request->all());
		$sku_where=[
                ['good_id','=',$good_id],
                ['delete_flag','=','Y'],
            ];
        $select=['self_id as sku_id','good_name','sale_price','serve'];
		$sku_info=ErpShopGoodsSku::where($sku_where)
			->select($select)
			->orderBy('sale_price','asc')
			->get();
		//dd($sku_info);
		foreach ($sku_info as $k => $v){
			$v->sale_price=number_format($v->sale_price/100, 2);
		}
		
		$msg['code']=200;
		$msg['msg']='拉取数据成功！';
		$msg['data']=$sku_info;
//		dd($msg);
        return $msg;

    }



    /**
     * 商品详情     /shop/details
     */
    public function details(Request $request){
        $user_info		=$request->get('user_info');
        $now_time		=date('Y-m-d H:i:s',time());
        //用户信息抓取
        $good_id		='good_202010261916293512632330';
        $good_where=[
            ['self_id','=',$good_id],
        ];

        $select=['self_id','self_id as good_id','good_type','good_title','thum_image_url','scroll_image_url','details_image_url','cart_flag'];


        $good_info=ErpShopGoods::where($good_where)->select($select)->first();

        //dd(json_decode(json_encode($good_info),true));
        if($good_info) {
            /** 固定一个商品的ID，专门作为古钱币的商品层面，如果去抓取他现在的价格体系**/
            $sku_where=[
                ['good_id','=',$good_id],
            ];
            $sku=ErpShopGoodsSku::where($sku_where)->select('cost_price','sell_number')->orderBy('cost_price','asc')->get();
            //dump($sku->toArray());

            $cando='Y';

            foreach ($sku as $k => $v){
                if($cando == 'Y'){
                    $list_count_where=[
                        ['good_id','=',$good_id],
                        ['price','=',$v->cost_price],
                    ];
			//dd($list_count_where);
		    $pay_status=['2','3','4','7','9'];
		//->whereIn('coupon_status',$coupon_status)
                    //$count=DB::table('shop_order_list')->where($list_count_where)->whereIn('pay_status',$pay_status)->distinct('total_user_id')->count();
		    $count=DB::table('shop_order_list')->where($list_count_where)->whereIn('pay_status',$pay_status)->count();
		    //dump($count);
                    if($count < $v->sell_number){
                        $good_info->price_show=number_format($v->cost_price/100, 2);
                        $good_info->price=$v->cost_price;         //当前价格
                        $good_info->residue=$v->sell_number-$count.'人';         //剩余数量
                        $cando = 'N';
                    }

                }
            }


            /**处理图片**/
            $good_info->thum_image_url=img_for($good_info->thum_image_url,'more');
            $good_info->scroll_image_url=img_for($good_info->scroll_image_url,'more');
            $good_info->details_image_url=img_for($good_info->details_image_url,'more');
	        $good_info->cart_flag == 'alone';
            //dd($good_info->toArray());

            $msg['code']=200;
            $msg['msg']='数据拉取成功！';
            $msg['data']=$good_info;
            return $msg;

        }else{
            $msg['code']=300;
            $msg['msg']='商品不存在或已下架！';
            $msg['data']=$good_info;
            return $msg;

        }

    }


}
?>
