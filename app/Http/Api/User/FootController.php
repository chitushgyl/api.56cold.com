<?php
namespace App\Http\Api\User;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class FootController extends Controller{

    /**
     * 用户个人中心底部导航选中      /user/create_foot
     */
    public function create_foot(Request $request){
        $img_url=config('aliyun.img.url');
        $user_info=$request->get('user_info');
        //$user_id='user_202003311706142717253101';
            //先拿去所有的可选择的
            $user_foot_where=[
                ['use_flag','=','Y'],
                ['delete_flag','=','Y'],
            ];

            $foot_info = DB::table('sys_foot')
                ->where($user_foot_where)
                ->select(
                    'id',
                    'name',
                    'active_img',
                    'default_flag',
                    'must_flag'
                )
                ->orderBy('sort','asc')
                ->get()->toArray();
            //拉取用户的信息
            $user_where=[
                ['self_id','=',$user_info->total_user_id],
                ['delete_flag','=','Y'],
            ];
            $user_foot_info = DB::table('user_reg')
                ->where($user_where)->value('foot_info');
            if($user_foot_info){
                $foot_in=json_decode($user_foot_info);
            }else{
                $foot_in=null;
            }
            if($foot_in){
                $foot_in=json_decode($user_foot_info);
            }
			//dump($foot_in);
            //dd($foot_info);

            foreach ($foot_info as $k => $v){
				//dd($v->id);
                if($foot_in){
                    if(!in_array($v->id,$foot_in)){
                        $v->default_flag='N';
                    }
                }

                if ($v->active_img) {
                    $v->active_img=$img_url.$v->active_img;
                }
            }

            $msg['code']=200;
            $msg['msg']='数据拉取成功！';
            $msg['data']=$foot_info;


        dd($foot_info);
        return $msg;
    }

    /**
     * 用户个人中心底部导航选中加入数据库      /user/add_foot
     */	
    public function add_foot(Request $request){
        $user_info=$request->get('user_info');
        //$user_id='user_202003311706142717253101';
        $foot_ids=$request->input('foot_ids');


        $foot_ids=explode('*', $foot_ids);
        $count=count($foot_ids);
        //dd($count);
        if($count>5){
            $msg['code']=302;
            $msg['msg']='底部导航设置不能超过5个！';

        }else{
            $where['self_id'] =$user_info->total_user_id;
            $data['update_time']=date('Y-m-d H:i:s',time());
            $data['foot_info']=$foot_ids;

            $id=DB::table('user_reg')->where($where)->update($data);
            if($id){
                $msg['code']=200;
                $msg['msg']='数据修改成功！';
            }else{
                $msg['code']=301;
                $msg['msg']='数据修改失败！';
            }
        }


       // dd($msg);
        return $msg;
    }
	
	
	
	

}
?>
