<?php
namespace App\Http\Api\User;
use App\Models\Group\SystemAdmin;
use App\Models\Group\SystemGroup;
use App\Models\Tms\TmsDriver;
use App\Models\Tms\TmsGroup;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Models\User\UserIdentity;
use App\Http\Controllers\Controller;
use App\Http\Controllers\StatusController as Status;
use App\Models\Log\LogLogin;

class BindingController extends Controller{
    /**
     * 用户账号进入数据库      /user/binding_page
     */
    public function binding_page(Request $request){
        $user_info=$request->get('user_info');
		//dd($user_info);
        $tms_user_type           =array_column(config('tms.tms_user_type'),'name','key');
        $app_id_type          =config('tms.app_id_type');
//		dump($app_id_type);
//        $user_id='user_202004021006499587468765';
//         dump($user_info->toArray());
            //先拿去所有的可选择的
			$admin_where=[
                ['total_user_id','=',$user_info->total_user_id],
                ['delete_flag','!=','N'],
                ['use_flag','!=','N'],
            ];
			//dd($admin_where);
        $select=['total_user_id','self_id','type','company_id','company_name','group_name','admin_login','default_flag','delete_flag','use_flag','atte_state'];
        $select_systemAdmin=['delete_flag','self_id','use_flag','login','group_code','authority_id'];
        $select_systemAuthority=['delete_flag','self_id','use_flag'];
        $select_systemGroup=['delete_flag','self_id','use_flag','group_code'];
        $select_userTotal=['self_id','login'];
        $select_attestation = ['self_id','state','identity_id'];
        //查询绑定的所有账号
			$info=UserIdentity::with(['systemAdmin' => function($query)use($select_systemAdmin,$select_systemAuthority,$select_systemGroup,$select_userTotal,$select_attestation){
                $query->select($select_systemAdmin);
                $query->with(['systemAuthority' => function($query)use($select_systemAuthority){
                    $query->select($select_systemAuthority);
                }]);
                $query->with(['systemGroup' => function($query)use($select_systemGroup){
                    $query->select($select_systemGroup);
                }]);
            }])->with(['tmsGroup' => function($query)use($select_systemAdmin,$select_systemAuthority,$select_systemGroup){
                $query->select($select_systemAuthority);
            }])
                ->with(['userTotal' => function($query)use($select_userTotal){
                $query->select($select_userTotal);
            }])
                ->with(['tmsAttestation' => function($query)use($select_attestation){
                    $query->select($select_attestation);
                }])
             ->where($admin_where)
			->select($select)
			->get();
//            dd($info->toArray());
			foreach ($info as $k => $v){
				$v->show='N';
				$v->button_show = 'N';
				$v->attestation_show = '';
				if($v->default_flag == 'N'){
						$v->show='Y';
					}

                switch ($v->type){
                    case 'TMS3PL':
                        if($v->systemAdmin->use_flag == 'N' || $v->systemAdmin->delete_flag == 'N'){
                            $v->show='N';//后台 ‘Y' 卡片，按钮 全显示 ’X‘，卡片显示，按钮消失 背景颜色置灰 ’N‘，按钮不显示
                        }
                        if($v->systemAdmin->systemAuthority->use_flag == 'N' || $v->systemAdmin->systemAuthority->delete_flag == 'N'){
                            $v->show='N';
                        }
                        if($v->systemAdmin->systemGroup->use_flag == 'N' || $v->systemAdmin->systemGroup->delete_flag == 'N'){
                            $v->show='N';
                        }
                        break;
                    case 'user':
                        $v->admin_login =  $v->userTotal->login;
                        break;
                    case 'carriage':
                        $v->admin_login =  $v->userTotal->login;
                        break;
                    case 'customer':
                        if($v->tmsGroup->use_flag == 'N' || $v->tmsGroup->delete_flag == 'N'){
                            $v->show = 'N';
                        }
                        break;
                    case 'carriers':
                        if($v->tmsGroup->use_flag == 'N' || $v->tmsGroup->delete_flag == 'N'){
                            $v->show = 'N';
                        }
                        break;
                    case 'driver':
                        if($v->tmsGroup->use_flag == 'N' || $v->tmsGroup->delete_flag == 'N'){
                            $v->show = 'N';
                        }
                        break;
                    case 'company':
                        if($v->systemAdmin->use_flag == 'N' || $v->systemAdmin->delete_flag == 'N'){
                            $v->show='N';//后台 ‘Y' 卡片，按钮 全显示 ’X‘，卡片显示，按钮消失 背景颜色置灰 ’N‘，按钮不显示
                        }
                        if($v->systemAdmin->systemAuthority->use_flag == 'N' || $v->systemAdmin->systemAuthority->delete_flag == 'N'){
                            $v->show='N';
                        }
                        if($v->systemAdmin->systemGroup->use_flag == 'N' || $v->systemAdmin->systemGroup->delete_flag == 'N'){
                            $v->show='N';
                        }
                        break;

                }
//                dd($tms_user_type);
                foreach ($app_id_type as $key => $value){
                    if ($v->type == $value['key']){
                        $v->img=img_for($value['id_status_image'],'no_json');
                        $v->color=$value['id_status_color'];
                    }
                }

                $v->company_type = $tms_user_type[$v->type];
                if($v->use_flag == 'W'){
                   $v->attestation_show = '审核中...';
                   $v->view_show = '点击查看';
                }
                if($v->use_flag == 'X'){
                   $v->attestation_show = '认证失败';
                   $v->view_show = '点击查看';
                }
                if ($v->atte_state == 'Y'){
                    $v->view_show = '点击查看';
                }
                if ($v->tmsAttestation){
                    $v->attestation_id = $v->tmsAttestation->self_id;
                }

            }
//			dd($info);
			$msg['code']=200;
			$msg['msg']='数据拉取成功！';
			$msg['data']=$info;

        return $msg;
    }

	/**
     * 用户账号进入数据库      /user/add_binding
     */
    public function add_binding(Request $request){
        $user_info          =$request->get('user_info');
        $project_type       =$request->get('project_type');
        $now_time           = date('Y-m-d H:i:s',time());
        $type               =$request->input('type');
        $login              =$request->input('login');
        $company_name       =$request->input('company_name');
        $pwd                =$request->input('pwd');
        $group_name                =$request->input('group_name');
        $driver_name                =$request->input('driver_name');
        $driver_tel                =$request->input('driver_tel');
//        dd($user_info->toArray());
        /** 虚假数据
        $input['type']=$type='TMS3PL';      //TMS3PL,company,customer
        $input['login']=$login='admin';
        $input['company_name']=$company_name='XXXr002有限公司';
        $input['pwd']=$pwd='forlove504616';
         **/
        switch ($type){
            case 'TMS3PL':
                $admin_where=[
                    ['login','=',$login],
                    ['pwd','=',get_md5($pwd)],
                    ['delete_flag','=','Y'],
                ];
                $info=SystemAdmin::where($admin_where)
                    ->select('group_code','group_name','authority_name','total_user_id')
                    ->first();

                if(empty($info)){
                    $msg['code']=301;
                    $msg['msg']='查询不到数据，请核实';
                    return $msg;
                }

                $group_info = SystemGroup::where('self_id','=',$info->group_code)->select('company_type','self_id')->first();

                if ($type != $group_info->company_type){
                    $msg['code']=304;
                    $msg['msg']='不可以绑定该类公司';
                    return $msg;
                }

                $identity_where = [
                    ['total_user_id','=',$user_info->total_user_id],
                    ['use_flag','=','Y'],
                    ['delete_flag','=','Y'],
                    ['type','=','TMS3PL'],
                    ['admin_login','=',$login],
                ];
                $identity =UserIdentity::where($identity_where)->count();
                if ($identity>0){
                    $msg['code']=302;
                    $msg['msg']='已绑定该账号，请勿重复绑定';
                    return $msg;
                }
                /**处理下是否默认*/
                $admin_identity=[
                    ['total_user_id','=',$user_info->total_user_id],
                    ['default_flag','=','Y'],
                    ['delete_flag','=','Y'],
                ];
                $count=UserIdentity::where($admin_identity)->count();

                if($count>0){
                    $data['default_flag']        ='N';
                }else{
                    $data['default_flag']        ='Y';
                }

                $data['self_id']            = generate_id('identity_');
                $data['total_user_id']      =$user_info->total_user_id;
                $data['type']               =$type;
                $data['create_user_id']     =$user_info->total_user_id;
                $data['create_user_name']   = $user_info->total_user_id;
                $data['group_code']         =$info->group_code;
                $data['group_name']         =$info->group_name;
                $data['admin_login']        =$login;
                $data['create_time']       = $data['update_time'] = $now_time;
                $id=UserIdentity::insert($data);

                if($id){
                    $msg['code']=200;
                    $msg['msg']='操作成功';
                    return $msg;
                }else{
                    $msg['code']=303;
                    $msg['msg']='操作失败';
                    return $msg;
                }

                break;
            case 'customer':

                $admin_where=[
                    ['company_name','=',$company_name],
                    ['group_name','=',$group_name],
                    ['pwd','=',get_md5($pwd)],
                    ['delete_flag','=','Y'],
                    ['type','=',$type],
                ];

                $info=TmsGroup::where($admin_where)
                    ->select('group_code','group_name','type','self_id','company_name')
                    ->first();

                if(empty($info)){
                    $msg['code']=301;
                    $msg['msg']='查询不到数据，请核实';
                    return $msg;

                }
                $identity_where = [
                    ['total_user_id','=',$user_info->total_user_id],
                    ['use_flag','=','Y'],
                    ['delete_flag','=','Y'],
                    ['type','=','customer'],
                    ['group_name','=',$group_name],
                    ['company_name','=',$company_name],
                ];
                $identity =UserIdentity::where($identity_where)->count();
                if ($identity>0){
                    $msg['code']=302;
                    $msg['msg']='已绑定该账号，请勿重复绑定';
                    return $msg;
                }
                $admin_identity=[
                    ['total_user_id','=',$user_info->total_user_id],
                    ['default_flag','=','Y'],
                    ['delete_flag','=','Y'],
                ];
                $count=UserIdentity::where($admin_identity)->count();

                if($count>0){
                    $data['default_flag']        ='N';
                }else{
                    $data['default_flag']        ='Y';
                }


                $data['self_id']            = generate_id('identity_');
                $data['total_user_id']      =$user_info->total_user_id;
                $data['type']               =$type;
                $data['create_user_id']     =$user_info->total_user_id;
                $data['create_user_name']   = $user_info->total_user_id;
                $data['group_code']         =$info->group_code;
                $data['group_name']         =$info->group_name;
                $data['company_id']         =$info->self_id;
                $data['company_name']       =$info->company_name;
                $data['create_time']       = $data['update_time'] = $now_time;


                $id=UserIdentity::insert($data);

                if($id){
                    $msg['code']=200;
                    $msg['msg']='操作成功';
                    return $msg;
                }else{
                    $msg['code']=303;
                    $msg['msg']='操作失败';
                    return $msg;
                }

                break;
            case 'carriers':
                $admin_where=[
                    ['company_name','=',$company_name],
                    ['pwd','=',get_md5($pwd)],
                    ['delete_flag','=','Y'],
                    ['type','=',$type],
                    ['group_name','=',$group_name],
                ];
				//dd($admin_where);
                $info=TmsGroup::where($admin_where)
                    ->select('group_code','group_name','type','self_id','company_name')
                    ->first();

                if(empty($info)){
                    $msg['code']=301;
                    $msg['msg']='查询不到数据，请核实';
                    return $msg;

                }
                $identity_where = [
                    ['total_user_id','=',$user_info->total_user_id],
                    ['use_flag','=','Y'],
                    ['delete_flag','=','Y'],
                    ['type','=','carriers'],
                    ['group_name','=',$group_name],
                    ['company_name','=',$company_name],
                ];
                $identity =UserIdentity::where($identity_where)->count();
                if ($identity>0){
                    $msg['code']=302;
                    $msg['msg']='已绑定该账号，请勿重复绑定';
                    return $msg;
                }
                $admin_identity=[
                    ['total_user_id','=',$user_info->total_user_id],
                    ['default_flag','=','Y'],
                    ['delete_flag','=','Y'],
                ];
                $count=UserIdentity::where($admin_identity)->count();

                if($count>0){
                    $data['default_flag']        ='N';
                }else{
                    $data['default_flag']        ='Y';
                }


                $data['self_id']            = generate_id('identity_');
                $data['total_user_id']      =$user_info->total_user_id;
                $data['type']               =$type;
                $data['create_user_id']     =$user_info->total_user_id;
                $data['create_user_name']   = $user_info->total_user_id;
                $data['group_code']         =$info->group_code;
                $data['group_name']         =$info->group_name;
                $data['company_id']         =$info->self_id;
                $data['company_name']       =$info->company_name;
                $data['create_time']       = $data['update_time'] = $now_time;


                $id=UserIdentity::insert($data);

                if($id){
                    $msg['code']=200;
                    $msg['msg']='操作成功';
                    return $msg;
                }else{
                    $msg['code']=303;
                    $msg['msg']='操作失败';
                    return $msg;
                }

                break;
            case 'driver':
                $admin_where=[
                    ['company_name','=',$driver_name],
                    ['tel','=',$driver_tel],
                    ['group_name','=',$group_name],
                    ['pwd','=',get_md5($pwd)],
                    ['delete_flag','=','Y'],
                ];

                $info=TmsGroup::where($admin_where)
                    ->select('group_code','group_name','type','self_id','company_name','tel')
                    ->first();

                if(empty($info)){
                    $msg['code']=301;
                    $msg['msg']='查询不到数据，请核实';
                    return $msg;

                }
                $identity_where = [
                    ['total_user_id','=',$user_info->total_user_id],
                    ['use_flag','=','Y'],
                    ['delete_flag','=','Y'],
                    ['type','=','3PLdriver'],
                    ['group_name','=',$group_name],
                    ['company_name','=',$company_name],
                ];
                $identity =UserIdentity::where($identity_where)->count();
                if ($identity>0){
                    $msg['code']=302;
                    $msg['msg']='已绑定该账号，请勿重复绑定';
                    return $msg;
                }
                $admin_identity=[
                    ['total_user_id','=',$user_info->total_user_id],
                    ['default_flag','=','Y'],
                    ['delete_flag','=','Y'],
                ];
                $count=UserIdentity::where($admin_identity)->count();

                if($count>0){
                    $data['default_flag']        ='N';
                }else{
                    $data['default_flag']        ='Y';
                }


                $data['self_id']            = generate_id('identity_');
                $data['total_user_id']      =$user_info->total_user_id;
                $data['type']               =$type;
                $data['create_user_id']     =$user_info->total_user_id;
                $data['create_user_name']   = $user_info->total_user_id;
                $data['group_code']         =$info->group_code;
                $data['group_name']         =$info->group_name;
                $data['company_id']         =$info->self_id;
                $data['company_name']       =$info->company_name;
                $data['phone']                =$info->tel;
                $data['create_time']       = $data['update_time'] = $now_time;


                $id=UserIdentity::insert($data);

                if($id){
                    $msg['code']=200;
                    $msg['msg']='操作成功';
                    return $msg;
                }else{
                    $msg['code']=303;
                    $msg['msg']='操作失败';
                    return $msg;
                }
                break;
            case 'carriage':
                $user_where = [
                    ['total_user_id','=',$user_info->total_user_id],
                    ['use_flag','=','Y'],
                    ['delete_flag','=','Y'],
                    ['type','=','user'],
                ];
                $user_update['delete_flag'] = 'N';
                $user_update['default_flag'] = 'N';
                UserIdentity::where($user_where)->update($user_update);

                $identity_where = [
                    ['total_user_id','=',$user_info->total_user_id],
                    ['use_flag','=','Y'],
                    ['delete_flag','=','Y'],
                    ['type','=','carriage'],
                ];
                $identity =UserIdentity::where($identity_where)->count();
                if ($identity>0){
                    $msg['code']=302;
                    $msg['msg']='已绑定该账号，请勿重复绑定';
                    return $msg;
                }
                $admin_identity=[
                    ['total_user_id','=',$user_info->total_user_id],
                    ['default_flag','=','Y'],
                    ['delete_flag','=','Y'],
                ];
                $count=UserIdentity::where($admin_identity)->count();

                if($count>0){
                    $data['default_flag']        ='N';
                }else{
                    $data['default_flag']        ='Y';
                }

                $data['self_id']            = generate_id('identity_');
                $data['total_user_id']      =$user_info->total_user_id;
                $data['type']               =$type;
                $data['create_user_id']     =$user_info->total_user_id;
                $data['create_user_name']   = $user_info->total_user_id;
                $data['create_time']        = $data['update_time'] = $now_time;

                $id=UserIdentity::insert($data);

                if($id){
                    $msg['code']=200;
                    $msg['msg']='操作成功';
                    return $msg;
                }else{
                    $msg['code']=303;
                    $msg['msg']='操作失败';
                    return $msg;
                }
                break;
            case 'company':
                $admin_where=[
                    ['login','=',$login],
                    ['pwd','=',get_md5($pwd)],
                    ['delete_flag','=','Y'],
                ];
                $info=SystemAdmin::where($admin_where)
                    ->select('group_code','group_name','authority_name','total_user_id')
                    ->first();

                if(empty($info)){
                    $msg['code']=301;
                    $msg['msg']='查询不到数据，请核实';
                    return $msg;
                }
                $group_info = SystemGroup::where('self_id','=',$info->group_code)->select('company_type','self_id')->first();

                if ($type != $group_info->company_type){
                    $msg['code']=304;
                    $msg['msg']='不可以绑定该类公司';
                    return $msg;
                }
                $identity_where = [
                    ['total_user_id','=',$user_info->total_user_id],
                    ['use_flag','=','Y'],
                    ['delete_flag','=','Y'],
                    ['type','=','company'],
                    ['admin_login','=',$login],
                ];
                $identity =UserIdentity::where($identity_where)->count();
                if ($identity>0){
                    $msg['code']=302;
                    $msg['msg']='已绑定该账号，请勿重复绑定';
                    return $msg;
                }
                /**处理下是否默认*/
                $admin_identity=[
                    ['total_user_id','=',$user_info->total_user_id],
                    ['default_flag','=','Y'],
                    ['delete_flag','=','Y'],
                ];
                $count=UserIdentity::where($admin_identity)->count();

                if($count>0){
                    $data['default_flag']        ='N';
                }else{
                    $data['default_flag']        ='Y';
                }

                $data['self_id']            = generate_id('identity_');
                $data['total_user_id']      =$user_info->total_user_id;
                $data['type']               =$type;
                $data['create_user_id']     =$user_info->total_user_id;
                $data['create_user_name']   = $user_info->total_user_id;
                $data['group_code']         =$info->group_code;
                $data['group_name']         =$info->group_name;
                $data['admin_login']        =$login;
                $data['create_time']       = $data['update_time'] = $now_time;
                $id=UserIdentity::insert($data);

                if($id){
                    $msg['code']=200;
                    $msg['msg']='操作成功';
                    return $msg;
                }else{
                    $msg['code']=303;
                    $msg['msg']='操作失败';
                    return $msg;
                }
                break;
            default:
                $msg['code']=300;
                $msg['msg']='你选择的类型不正确';
                return $msg;
                break;

        }
    }

    /**
     * 删除绑定      /user/del_binding
     */
    public function del_binding(Request $request,Status $status){
        $now_time=date('Y-m-d H:i:s',time());
        $table_name='user_identity';
        $medol_name='UserIdentity';
        $self_id=$request->input('self_id');
        $flag='delFlag';
        //$self_id='car_202012242220439016797353';

        $status_info=$status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);

        $msg['code']=$status_info['code'];
        $msg['msg']=$status_info['msg'];
        $msg['data']=$status_info['new_info'];
        return $msg;
    }
/**
     * 切换身份      /user/switchover
     */
    public function switchover(Request $request){
        $now_time           =date('Y-m-d H:i:s',time());
        $self_id=$request->get('self_id');
       // $self_id='identity_202101271511561323937379';
        $select = ['self_id','type','group_code','admin_login','total_user_id'];
        $select_systemAdmin = ['self_id','login','authority_id','group_code'];
        /** 第一步，查询出该数据所有相关的元素**/
        $user_track_where=[
            ['self_id','=',$self_id],
            ['delete_flag','=','Y'],
            ['use_flag','=','Y'],
        ];

        $user_track_where2=[
            ['delete_flag','=','Y'],
            ['use_flag','=','Y'],
        ];

        $info=UserIdentity::with(['systemAdmin' => function($query)use($select_systemAdmin,$user_track_where2){
            $query->select($select_systemAdmin);
            $query->wherehas('systemAuthority',function($query)use($user_track_where2){
                $query->where($user_track_where2);
            });
            $query->wherehas('systemGroup',function($query)use($user_track_where2){
                $query->where($user_track_where2);
            });
        }])->where($user_track_where)->select($select)->first();

        if(empty($info)){
            $msg['code']=300;
            $msg['msg']='没有该数据';
            return $msg;
        }

        if($info->type =='TMS3PL' && empty($info->systemAdmin)){
            $msg['code']=301;
            $msg['msg']='该身份已被关闭';
            return $msg;
        }

        /*** 第二步，先将原来的默认身份修改成N，把现在的这个身份修改成Y**/
        $where = [
            ['default_flag','=','Y'],
            ['delete_flag','=','Y'],
            ['total_user_id','=',$info->total_user_id]
        ];
        $user_update['default_flag'] = 'N';
        $user_update['update_time'] = $now_time;
        UserIdentity::where($where)->update($user_update);

        $arr['default_flag'] = 'Y';
        $arr['update_time'] = $now_time;
        $id=UserIdentity::where('self_id','=',$self_id)->update($arr);

        /** 第三步，如果这个切换的属性是SPL的，帮他完成一次后台的登录**/
        $user_token=null;
        if($info->type =='TMS3PL' || $info->type == 'company'){
            $user_token					 =md5($info->systemAdmin->self_id.$now_time);
            $reg_place                   ='CT_H5';
            $token_data['self_id']       = generate_id('login_');
            $token_data["login"]         = $info->admin_login;
            $token_data["user_id"]         = $info->systemAdmin->self_id;
            $token_data['type']          = 'after';
            $token_data['login_status']  = 'SU';
            $token_data["user_token"]    = $user_token;
            $token_data["create_time"]   = $token_data["update_time"] = $now_time;
            $id=LogLogin::insert($token_data);
        }


        if($id){
            $msg['code']=200;
            $msg['msg']='切换成功';
            $msg['user_token']=$user_token;
            return $msg;
        }else{
            $msg['code']=302;
            $msg['msg']='切换失败';
            $msg['user_token']=$user_token;
            return $msg;
        }
    }

    /**
     * 切换身份有问题的代码      /user/switchover    老的
     */
    public function switchoverOLD(Request $request){
        $self_id=$request->get('self_id');
//        $self_id='identity_202101201323486454561218';
        $select = ['self_id','login'];
        $info=UserIdentity::with(['systemAdmin' => function($query)use($select){
            $query->select($select);
        }])->where('self_id','=',$self_id)->first();
        $user_token=null;
        if($info->type =='TMS3PL'){
            $now_time                    =date('Y-m-d H:i:s',time());
            $reg_place                   ='CT_H5';
            //帮他完成登录

            $token_data['self_id']       = generate_id('login_');
            $token_data["login"]         = $info->admin_login;
            $token_data["user_id"]         = $info->systemAdmin->self_id;
            $token_data['type']          = 'after';
            $token_data['login_status']  = 'SU';
            $user_token                  = $this->addToken($token_data['self_id'],$reg_place,$now_time);
            $token_data["user_token"]    = $user_token;
            $token_data["create_time"]   = $token_data["update_time"] = $now_time;
            $id=LogLogin::insert($token_data);
        }
        $where = [
            ['default_flag','=','Y'],
            ['delete_flag','=','Y'],
            ['total_user_id','=',$info->total_user_id]
        ];
        $user_where1['default_flag'] = 'N';
        $old_info = UserIdentity::where($where)->update($user_where1);
        $msg['code']=200;
        $msg['msg']='数据拉取成功！';
        $msg['user_token']=$user_token;
        //拉取用户的信息
            $user_where=[
                ['self_id','=',$self_id],
                ['delete_flag','=','Y'],
            ];
            $select = ['total_user_id','self_id','type','company_id','company_name','group_name','admin_login','default_flag'];
            $user_info = UserIdentity::where($user_where)
		               ->select($select)
		               ->first();
	    //dd($user_info);
	    if($user_info){
	        $arr['default_flag'] = 'Y';
	        UserIdentity::where('self_id','=',$self_id)->update($arr);
	        $msg['code']=200;
            	$msg['msg']='切换成功！';
            	$msg['data']=$user_info;

	    }else{
            	$msg['code']=300;
            	$msg['msg']='切换失败！';
            	$msg['data']=$user_info;
	    }
        return $msg;
    }

    /***完成登录***/
    public function addToken($user_id,$reg_place,$now_time){
        $data['self_id']        = generate_id('self_');
        $data['user_id']        = $user_id;
        $data['create_time']    = $now_time;
        $data['user_token']     = md5($user_id.$now_time);
        $data['type']           = $reg_place;
        LogLogin::insert($data);

        $user_token             = $data['user_token'];
        return $user_token;

    }

}
?>
