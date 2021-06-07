<?php
namespace App\Http\Api\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User\UserTrack;

class TrackController extends Controller{
    /**
     * 用户收藏商品列表      /user/track_page
     * 前端传递必须参数：page
     *前端传递非必须参数：user_token(用户token)
     *
     * 回调结果：200  数据拉取成功
     *
     *回调数据：
     */
    public function track_page(Request $request){
        $user_info			=$request->get('user_info');
        $listrows			=config('page.listrows')[1];//每次加载的数量1
		$first				=$request->input('page')??1;
		$firstrow			=($first-1)*$listrows;
        $now_time			=date('Y-m-d H:i:s',time());
        $class_type			=$request->input('class_type')??'good';
		$track_where=[
			['total_user_id','=',$user_info->total_user_id],
			['class_type','=',$class_type],
			['delete_flag','=','Y'],
		];

		$select=['self_id as track_id','data_id','create_time'];
        $select_erpShopGoods=['self_id','good_title','good_info','good_type','group_code','thum_image_url','sell_start_time','sell_end_time','good_status'];

		$track_info=UserTrack::with(['erpShopGoods' => function($query)use($select_erpShopGoods) {
            $query->select($select_erpShopGoods);
        	}])->where($track_where)
			->select($select)
			->offset($firstrow)->limit($listrows)->orderBy('create_time','desc')
			->get();

		foreach ($track_info as $k => $v){
			$v->good_title=$v->erpShopGoods->good_title;
			$v->good_status='N';
			if($v->erpShopGoods->good_status == 'Y'){
				if($v->erpShopGoods->sell_start_time < $now_time && $now_time< $v->erpShopGoods->sell_end_time){
					$v->good_status='Y';
				}
			}
            $v->thum_image_url=img_for($v->erpShopGoods->thum_image_url,'one');
		}
	
		
		$msg['code']=200;
		$msg['msg']='数据拉取成功！';
		$msg['data']=$track_info;
		

		
        return $msg;
    }

    /**
     * 个人中心删除收藏      /user/track_delete
     * 前端传递必须参数：track_id
     *前端传递非必须参数：
     *
     * 回调结果：200  操作成功
     *
     *回调数据：
     */
    public function track_delete(Request $request){
        $user_info		=$request->get('user_info');
		$track_id		=$request->input('track_id');
        $now_time		=date('Y-m-d H:i:s',time());
		//$track_id='track_202003191605483668988124';
		$track_where=[
			['self_id','=',$track_id],
		];

		$flag=UserTrack::where($track_where)->value('delete_flag');
		if($flag=='Y'){
			$data['update_time']=$data['delete_time']=$now_time;
			$data['delete_flag']='N';

            UserTrack::where($track_where)->update($data);

			$msg['code']=200;
			$msg['msg']='操作成功！';
		}else{
			$msg['code']=500;
			$msg['msg']='操作失败！';

		}

        
        return $msg;
    }




}
?>
