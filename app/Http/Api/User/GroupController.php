<?php
namespace App\Http\Api\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User\UserTotal;

class GroupController extends Controller{
    /**
     * 我的团队  无限极    /user/my_group
     */
    public function my_group(Request $request){
        $user_info		=$request->get('user_info');
		$user_info->userCapital->performance=number_format($user_info->userCapital->performance/100, 2);                          //用户余额
		 $total_user_id  =$request->get('total_user_id')??$user_info->total_user_id;
		$user_info->grade_name=null;
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
        }

        $data['user_info']=$user_info;
        /*** 抓取他的下线的数据，只显示2级***/
        $where=[
            ['father_user_id1','=',$total_user_id],
			['delete_flag','=','Y'],
        ];
        $data['tier1']=UserTotal::with(['userReg' => function($query) {
            $query->select('total_user_id','token_name');
        }])->with(['userCapital' => function($query) {
            $query->select('total_user_id','performance','performance_share');
        }])->where($where)->select('self_id','tel','true_name')->get();





		foreach ($data['tier1'] as $k => $v){
			if(empty($v->tel)){
				$v->true_name=$v->userReg[0]->token_name;
			}

            $v->userCapital->performance=number_format($v->userCapital->performance/100, 2);

        }
        $data['tier1_count']=$data['tier1']->count();
		//dd($data);
		/**
        $where2=[
            ['father_user_id2','=',$user_info->total_user_id],
			['delete_flag','=','Y'],
        ];
        $data['tier2']=UserTotal::with(['userCapital' => function($query) {
            $query->select('total_user_id','performance','performance_share');
        }])->where($where2)->select('self_id','tel','true_name')->get();
		foreach ($data['tier2'] as $k => $v){
            $v->userCapital->performance=number_format($v->userCapital->performance/100, 2);
        }

        $data['tier2_count']=$data['tier2']->count();
		**/

		$msg['code']=200;
		$msg['msg']='数据拉取成功！';
		$msg['data']=$data;


        return $msg;
    }


    /**
     * 我的团队2级     /user/my_group2
     */

    public function my_group2(Request $request){
        $user_info		=$request->get('user_info');
        $user_info->userCapital->performance=number_format($user_info->userCapital->performance/100, 2);                          //用户余额
        $total_user_id  =$user_info->total_user_id;
        $user_info->grade_name=null;
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
        }

        $data['user_info']=$user_info;
        /*** 抓取他的下线的数据，只显示2级***/
        $where=[
            ['father_user_id1','=',$total_user_id],
            ['delete_flag','=','Y'],
        ];
        $data['tier']=UserTotal::with(['userTotal' => function($query) {
            $query->select('father_user_id1','self_id','tel','true_name');
            $query->with(['userReg' => function($query) {
                $query->select('total_user_id','token_name');
            }])->with(['userCapital' => function($query) {
                $query->select('total_user_id','performance','performance_share');
            }]);
        }])->with(['userReg' => function($query) {
            $query->select('total_user_id','token_name');
        }])->with(['userCapital' => function($query) {
            $query->select('total_user_id','performance','performance_share');
        }])->where($where)->select('self_id','tel','true_name','father_user_id1')->get();

        foreach ($data['tier'] as $k => $v){
            if(empty($v->tel)){
                $v->true_name=$v->userReg[0]->token_name;
            }
            $v->performance=number_format($v->userCapital->performance/100, 2);
            $v->performance_share=$v->userCapital->performance_share;

            foreach ($v->userTotal as $kk => $vv){
                if(empty($vv->tel)){
                    $vv->true_name=$vv->userReg[0]->token_name;
                }
                $vv->performance=number_format($vv->userCapital->performance/100, 2);
                $vv->performance_share=$vv->userCapital->performance_share;

            }

            if($v->userTotal){
                $v->count=$v->userTotal->count();
            }else{
                $v->count=0;
            }
        }


        if($data['tier']){
            $user_info->count=$data['tier']->count();
        }else{
            $user_info->count=0;
        }

        $msg['code']=200;
        $msg['msg']='数据拉取成功！';
        $msg['data']=$data;

        return $msg;
    }



}
?>
