<?php
namespace App\Http\Api\Shop;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

// use Illuminate\Support\Facades\Schema;

class GroupController extends Controller{
    /**
     * 商户信息      /shop/group
     * 前端传递必须参数：group（第几页，从0开始）
     * 回调结果：200  数据拉取成功
     *
     *回调数据：  商户信息
     */
    public function group(Request $request){
        $group_info=$request->get('group_info');
        $group_code=$group_info->group_code??config('page.platform.group_code');
        $img_url=config('aliyun.oss.url');


        $where222['group_code']=$group_code;
        $where222['delete_flag']='Y';
        $group_info=DB::table('system_group')->where($where222)
            ->select(
                'group_code',
                'group_name',
                'name',
                'leader_phone',
                'tel',
                'company_image_url',
                //'address',
                'scroll_image_url',
                'info'
            )
            ->first();
        $group_info->company_image_url=$group_info->company_image_url?$img_url.$group_info->company_image_url:null;
        $group_info->scroll_image_url=json_decode($group_info->scroll_image_url,true);
		if($group_info->scroll_image_url){
			foreach($group_info->scroll_image_url as $k=>$v){
				$group_info->scroll_image_url[$k]['imageUrl']=$img_url.$group_info->scroll_image_url[$k]['imageUrl'];
			}			
		}
        


        //dd($group_info);
        $msg['code']=200;
        $msg['msg']='数据拉取成功！';
        $msg['data']=$group_info;

        return $msg;
    }


}
?>
