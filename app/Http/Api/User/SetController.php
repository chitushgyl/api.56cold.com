<?php
namespace App\Http\Api\User;
use App\Models\User\UserTotal;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class SetController extends Controller{
    /**
     * 修改交易密码     /user/setPassword
     *前端传递非必须参数：user_token(用户token)  pay_pwd(交易密码)
     *
     * 回调结果：200  数据拉取成功
     *
     *回调数据：
     */
    public function setPassword(Request $request){
        $user_info=$request->get('user_info');
        //$user_id='user_202003311706142717253101';
        $pwd_type=$request->input('pwd_type');
        $password=$request->input('password');
	//dd($pay_pwd);
		//dump($pay_pwd);
        /***虚拟数据
        $pwd_type = 'login';// 密码类型：login登录密码  pay支付密码
        $password = '123456';
         * **/
		$where=[
			['tel','=',$user_info->tel],
			['self_id','=',$user_info->total_user_id],
		];

		$info=UserTotal::where($where)->first();
			if($info){

				//去添加数据去
                if ($pwd_type == 'login'){
                    $data['password']= get_md5($password);
                }else{
                    $data['pay_pwd']= get_md5($password);
                }

				$data['update_time']=date('Y-m-d H:i:s',time());
				$id=UserTotal::where($where)->update($data);

				if($id){
					$msg['code']=200;
					$msg['msg']='修改成功！';
				}else{
					$msg['code']=302;
					$msg['msg']='修改失败！';
				}

			}else{
				$msg['code']=303;
				$msg['msg']='账号不存';
			}


       //dd($msg);
        return $msg;
    }

}
?>
