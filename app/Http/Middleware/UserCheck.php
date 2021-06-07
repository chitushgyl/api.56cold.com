<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/9/4
 * Time: 11:11
 */
namespace App\Http\Middleware;
//use Illuminate\Support\Facades\DB;
use Closure;
//use App\Http\Controllers\RedisController;
use App\Models\User\UserReg;
use App\Models\Group\SystemGroup;
use App\Models\SysFoot;
use Illuminate\Support\Facades\DB;

class UserCheck{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null){
        $token_info         = $request->get('token_info');//接收中间件产生的参数
        //$mini               =$request->header('mini');
        $project_type       =$request->header('projectType');

        //$project_type='shop';
        $user_info=null;


        if($token_info){
            //dump($token_info->user_id);
			$reg_select=['self_id','total_user_id','reg_type',
            'tel','authority','token_appid','token_id','token_img','token_name','foot_info','union_id','foot_info'];
			
			$user_where=[
				['self_id','=',$token_info->user_id],
			];

			$user_info=UserReg::with(['userCapital' => function($query) {
				$query->select('total_user_id','integral','money','share','performance','currency','performance_share');
			}])->with(['userTotal' => function($query) {
                $query->select('self_id','promo_code','grade_id','true_name');
            }])->with(['userIdentity' => function($query) {
                $query->where('use_flag','=','Y');
                $query->where('delete_flag','=','Y');
                $query->where('default_flag','=','Y');
                $query->select('self_id','type','total_user_id','company_id','company_name','group_code','group_name','phone');
            }])
            ->where($user_where)
            ->select($reg_select)
            ->first();

			if($user_info){
                $user_info->integral            =$user_info->userCapital->integral;
                $user_info->money               =$user_info->userCapital->money;
                $user_info->share               =$user_info->userCapital->share;
                $user_info->performance         =$user_info->userCapital->performance;
                $user_info->currency            =$user_info->userCapital->currency;
                $user_info->performance_share   =$user_info->userCapital->performance_share;

                $user_info->promo_code          =$user_info->userTotal->promo_code;
                $user_info->grade_id            =$user_info->userTotal->grade_id;
                $user_info->true_name           =$user_info->userTotal->true_name;

                if($user_info->userIdentity){
                    $user_info->type            =$user_info->userIdentity->type;
                    $user_info->company_id      =$user_info->userIdentity->company_id;
                    $user_info->company_name    =$user_info->userIdentity->company_name;
                    $user_info->group_code      =$user_info->userIdentity->group_code;
                    $user_info->group_name      =$user_info->userIdentity->group_name;
                    $user_info->phone           =$user_info->userIdentity->phone;
                }else{
                    $user_info->type            =null;
                    $user_info->company_id      =null;
                    $user_info->company_name    =null;
                    $user_info->group_code      =null;
                    $user_info->group_name      =null;
                }
            }
         }

//         if($project_type  != 'shop'){
//             $type=$user_info?$user_info->type:$project_type;
//             $project_type=$type??'user';
//         }
        $group_code     =$request->header('Group-Code');

        //dump($group_code);

        $hosturl        =$request->header('hosturl');

        //dump($hosturl);
        if(empty($group_code)){
            if($hosturl){
                $where2['front_name']=$hosturl;
                $group_code=SystemGroup::where($where2)->value('group_code');
            }
            $group_code=$group_code??config('page.platform.group_code');
        }
        $mid_params = ['user_info'=>$user_info,'project_type'=>$project_type,'group_code'=>$group_code];



//dd($user_info->toArray());

//
//            $group_info=null;
//            if($request->header('Group-Code')){

//
//            }
////dump($request->header('Group-Code'));
////dump($request->header('hosturl'));
////dump($group_info);
//            if(empty($group_info) && $request->header('hosturl')){
//                $where2['front_name']=$request->header('hosturl');
//                //$whrerr['a.front_name']='group_202005201344154541223633';
//                $group_info=SystemGroup::with(['systemGroup' => function($query)use($systemGroupSelect) {
//                    $query->select($systemGroupSelect);
//                }])->with(['systemGroupShare' => function($query)use($systemGroupShareSelect) {
//                    $query->select($systemGroupShareSelect);
//                }])->where($where2)
//                    ->select($select)
//                    ->first();
//            }
////dump($group_info);
//            if(!$group_info){
//                $where3['group_code']=config('page.platform.group_code');
//				//dump($where3);
//                $group_info=SystemGroup::with(['systemGroup' => function($query)use($systemGroupSelect) {
//                    $query->select($systemGroupSelect);
//                }])->with(['systemGroupShare' => function($query)use($systemGroupShareSelect) {
//                    $query->select($systemGroupShareSelect);
//                }])->where($where3)
//                    ->select($select)
//                    ->first();
//            }
//
//            /*** 这2个不知道是做什么用户的，好像是前端H5需要的功能回调，可能是用于分享的**/
//            $pathname       =$request->header('pathname');
//            $referer        =$request->header('Signature-Url');
//            $mid_params2    = ['group_info'=>$group_info,'referer'=>$referer,'pathname'=>$pathname];
        /** 处理个人中心订单操作按钮开始 **/
//            dd($project_type);
        $anniu=[];

        $anniu_where['type']='button';
        $anniu_where['delete_flag']='Y';
        $anniu_where['project_type'] = $project_type.'_button';
        $select=['id','name','app_path','project_type','level','path','button_color'];

        $anniu=SysFoot::where($anniu_where)->select($select)->get();

        $mid_params['buttonInfo'] = $anniu;
        /** 处理个人中心订单操作按钮结束 **/
            $request->attributes->add($mid_params);                 //(暂存数据)参数cha
//            $request->attributes->add($mid_params2);                 //(暂存数据)参数cha

        //dd($request->attributes);
            return $next($request);

    }
}
?>
