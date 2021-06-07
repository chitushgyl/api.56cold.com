<?php
namespace App\Http\Api\Shop;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Shop\ShopCatalog;
use App\Models\AttributeInfo;

class CatalogController extends Controller{
    /**
     * 分类详细信息      /shop/category
     *回调数据：  分类详细信息
     */
    public function category(Request $request){
        $user_info		=$request->get('user_info');
        $group_info		=$request->get('group_info');
        $group_code		=$group_info->group_code??config('page.platform.group_code');
        $type		    =$request->input('type')??'classify';
        $now_time		=date('Y-m-d H:i:s',time());
        $info=[];
        $type='catalog';
        $catalog_name	='商品分类';
        switch ($type){
            case 'classify':
                $catalog_where=[
                    ['use_flag','=','Y'],
                    ['delete_flag','=','Y'],
                    ['group_code','=',$group_code],
                    ['type','=',$type],
                    ['level','=',1],
                ];
                $select=['self_id','name as catalog_name','level'];

                $shopCatalogWhere=[
                    ['use_flag','=','Y'],
                    ['delete_flag','=','Y'],
                ];
                $shopCatalogSelect=['parent_id','self_id','name as catalog_name'];


                $info=AttributeInfo::with(['children' => function($query)use($shopCatalogWhere,$shopCatalogSelect) {
                    $query->where($shopCatalogWhere);
                    $query->select($shopCatalogSelect);
                }])->where($catalog_where)->orderBy('create_time','desc')
                    ->select($select)
                    ->get();

//                dd($info->toArray());

                break;

            case 'catalog':
                $catalog_where=[
                    ['group_code','=',$group_code],
                    ['use_flag','=','Y'],
                    ['delete_flag','=','Y'],
                    ['level','=','1'],
                    ['start_time','<',$now_time],
                    ['end_time','>',$now_time],

                ];

                    $select=['self_id','catalog_name','emphasis_flag','icon_url','level','all_flag','sort_flag'];
                    $shopCatalogWhere=[
                        ['use_flag','=','Y'],
                        ['delete_flag','=','Y'],
                        ['start_time','<',$now_time],
                        ['end_time','>',$now_time],
                    ];
                    $shopCatalogSelect=['parent_catalog_id','self_id','catalog_name','emphasis_flag','icon_url'];
                    $info=ShopCatalog::with(['shopCatalog' => function($query)use($shopCatalogWhere,$shopCatalogSelect) {
                        $query->where($shopCatalogWhere);
                        $query->select($shopCatalogSelect);
                    }])->where($catalog_where)->orderBy('sort','asc')
                        ->select($select)
                        ->get();
                break;

        }

//                dd($info->toArray());


        ///$catalog_id= 'catalog201912241722153683385567';

		//$catalog_id='catalog';
		//dd($catalog_id);
//		if($catalog_id == 'classify'){
//			$type			='classify';
//
//
//			//$group_code='group_2020052610285543774772091';
//
//            $msg['code']=200;
//            $msg['msg']='数据拉取成功';
//            $msg['data']=$catalog_info;
//            $msg['catalog_name']=$catalog_name;
//            $msg['type']=$type;
//
//		}else{
//			$type='catalog';
//
//
//               // dd($catalog_info->toArray());
//
//                foreach ($catalog_info as $k => $v){
//                    $v->icon_url=img_for($v->icon_url,'more');
//
//                    foreach ($v->shopCatalog as $kk => $vv){
//                        $vv->icon_url=img_for($vv->icon_url,'more');
//                    }
//
//                }
//
////                dd($catalog_info->toArray());



//		}
		

//        $pv['user_id']=$user_info?$user_info->total_user_id:null;
//		$pv['browse_path']=$request->path();
//		$pv['level']=2;
//		$pv['table_id']=$catalog_id;
//		$pv['ip']=$request->getClientIp();
//		$pv['place']='CT_H5';
		//pvuv($pv);
		
        //dd($pv);


//		dd($msg);
        $msg['code']=200;
        $msg['msg']='数据拉取成功';
        $msg['data']=$info;
        $msg['catalog_name']=$catalog_name;
        return $msg;
    }



}
?>
