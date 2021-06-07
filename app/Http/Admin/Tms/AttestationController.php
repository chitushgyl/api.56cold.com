<?php
namespace App\Http\Admin\Tms;

use App\Models\Group\SystemAdmin;
use App\Models\Log\LogLogin;
use App\Models\Tms\TmsAttestation;
use App\Models\User\UserIdentity;
use Illuminate\Http\Request;
use App\Http\Controllers\CommonController;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\DetailsController as Details;
use App\Models\Group\SystemGroup;


class AttestationController extends CommonController{

    /***    企业认证头部      /tms/attestation/attestationList
     */
    public function  attestationList(Request $request){
        $data['page_info']      =config('page.listrows');
        $data['button_info']    =$request->get('anniu');

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /***    企业认证列表分页      /tms/attestation/attestationPage
     */
    public function attestationPage(Request $request){
        /** 接收中间件参数**/
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $button_info    = $request->get('anniu');//接收中间件产生的参数
        $tms_car_possess_type    =array_column(config('tms.tms_car_possess_type'),'name','key');
        $tms_control_type    	 =array_column(config('tms.tms_control_type'),'name','key');
        $tms_company_type        =array_column(config('tms.tms_company_type'),'name','key');
        /**接收数据*/
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $state       =$request->input('state');
        $group_code     =$request->input('group_code');
        $group_name     = $request->input('group_name');
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;
//        $group_code = '1234';
        $search=[
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'=','name'=>'state','value'=>$state],
            ['type'=>'=','name'=>'group_code','value'=>$group_code],
            ['type'=>'like','name'=>'name','value'=>$group_name],


        ];

        $where=get_list_where($search);

        $select=['self_id','name','group_code','group_name','address','tel','image','road','state','email','reason','sheng_name','qu_name','shi_name','login_account','user_name','identity_number','company_code','type'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=TmsAttestation::where($where)->count(); //总的数据量
                $data['items']=TmsAttestation::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('update_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;

            case 'one':
                $where[]=['group_code','=',$group_info['group_code']];
                $data['total']=TmsAttestation::where($where)->count(); //总的数据量
                $data['items']=TmsAttestation::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('update_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='N';
                break;

            case 'more':
                $data['total']=TmsAttestation::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                $data['items']=TmsAttestation::where($where)->whereIn('group_code',$group_info['group_code'])
                    ->offset($firstrow)->limit($listrows)->orderBy('update_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;
        }
        // dd($data['items']->toArray());
        $button_info1=[];
        $button_info2=[];
        foreach ($button_info as $k => $v){
//            dump($v);
            if($v->id==672){
                $button_info2[]=$v;
            }
            if($v->id==673){
                $button_info2[]=$v;
            }
            if($v->id==674){
                $button_info1[]=$v;
                $button_info2[]=$v;
            }
        }
        foreach ($data['items'] as $k=>$v) {
            $v->type_show = $tms_company_type[$v->type];
            $v->button_info=$button_info;
            $v->address = $v->sheng_name.$v->shi_name.$v->qu_name;
            if ($v->state == 'WAIT'){
                $v->button_info=$button_info2;
            }elseif($v->state == 'SU'){
                $v->button_info=$button_info1;
            }else{
                $v->button_info=$button_info1;
            }
        }


        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        //dd($msg);
        return $msg;
    }

    /**
     * 企业认证审核通过/失败  /tms/attestation/attestationPass
     * */

    public function attestationPass(Request $request){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $user_info          = $request->get('user_info');//接收中间件产生的参数
        $table_name='tms_attestation';
        $input              =$request->all();
        $self_id=$request->input('self_id'); //数据ID
        $type = $request->input('type');//操作类别:pass 通过  fail失败
        $reason = $request->input('reason');
        $rules=[
            'self_id'=>'required',
        ];
        $message=[
            'self_id.required'=>'请选择要操作的数据',
        ];
//        $input['self_id'] = $self_id = 'atte_202104221343175828707184';
//        $input['type'] =  $type = 'pass';
        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $select = ['self_id','address','tel','state','name','email','sheng_name','shi_name','qu_name','total_user_id','login_account','identity_id','type'];
            $info = TmsAttestation::where('self_id',$self_id)->select($select)->first();
            $old_info = [
                'state'=>$info->state,
                'update_time'=>$now_time
            ];
            switch($type){
                case 'pass':
                    $new_info['update_time'] = $now_time;
                    $new_info['state'] = 'SU';
                    $id = TmsAttestation::where('self_id',$self_id)->update($new_info);

                    $update['use_flag'] = 'Y';
                    $update['update_time'] = $now_time;

                    $identity_update['use_flag']  = 'Y';
                    $identity_update['atte_state'] = 'Y';
                    $identity_update['update_time'] = $now_time;
                    $ids = SystemAdmin::where([['login','=',$info->login_account],['delete_flag','=','Y']])->update($update);
                    $idg = SystemGroup::where([['group_name','=',$info->name],['delete_flag','=','Y']])->update($update);
                    UserIdentity::where([['self_id','=',$info->identity_id]])->update($identity_update);

                    /***切换身份**/
                    $switch = 'N';//是否默认切换身份 Y是 N不
                    if($switch == 'Y'){
                        $identity_info = UserIdentity::where('self_id','=',$info->inentity_id)->first();
                        $admin = SystemAdmin::where('login','=',$info->login_account)->first();
                        $where = [
                            ['default_flag','=','Y'],
                            ['delete_flag','=','Y'],
                            ['total_user_id','=',$identity_info['total_user_id']]
                        ];
                        $user_update['default_flag'] = 'N';
                        $user_update['update_time'] = $now_time;
                        UserIdentity::where($where)->update($user_update);

                        $arr['default_flag'] = 'Y';
                        $arr['update_time'] = $now_time;
                        $id=UserIdentity::where('self_id','=',$info->identity_id)->update($arr);


                        /** 第三步，如果这个切换的属性是3PL的，帮他完成一次后台的登录**/
                        $user_token=null;
                        if($info['type'] =='TMS3PL' || $info['type'] == 'company'){
                            $user_token					 =md5($admin->self_id.$now_time);
                            $reg_place                   ='CT_H5';
                            $token_data['self_id']       = generate_id('login_');
                            $token_data["login"]         = $info->login_account;
                            $token_data["user_id"]       = $admin->self_id;
                            $token_data['type']          = 'after';
                            $token_data['login_status']  = 'SU';
                            $token_data["user_token"]    = $user_token;
                            $token_data["create_time"]   = $token_data["update_time"] = $now_time;
                            $id=LogLogin::insert($token_data);
                        }
                    }
                    break;
                case 'fail':
                    $new_info['update_time'] = $now_time;
                    $new_info['state'] = 'FS';
                    $new_info['reason'] = $reason;
                    $id = TmsAttestation::where('self_id',$self_id)->update($new_info);
                    break;

            }
            $operationing->access_cause='通过/失败';
            $operationing->table=$table_name;
            $operationing->table_id=$self_id;
            $operationing->now_time=$now_time;
            $operationing->old_info=$old_info;
            $operationing->new_info=$new_info;
            if ($id){
                $msg['code']=200;
                $msg['msg']='操作成功！';
                $msg['data']=$new_info;
                return $msg;
            }else{
                $msg['code']=303;
                $msg['msg']='操作失败！';
                return $msg;
            }

        }else{
            //前端用户验证没有通过
            $erro=$validator->errors()->all();
            $msg['code']=300;
            $msg['msg']=null;
            foreach ($erro as $k => $v){
                $kk=$k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            return $msg;
        }
    }

    /**
     * 企业认证审核失败 /tms/attestation/attestationFail
     * */
    public function attestationFail(Request $request){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='tms_attestation';
        $medol_name='tms_attestation';
        $self_id=$request->input('self_id');
        $reason = $request->input('reason');
        $select = ['self_id','address','tel','state','name','email','sheng_name','shi_name','qu_name','type'];
        $info = TmsAttestation::where('self_id',$self_id)->select($select)->first();
        $old_info = [
            'state'=>$info->state,
            'update_time'=>$now_time
        ];
        $new_info['update_time'] = $now_time;
        $new_info['state'] = 'FS';
        $new_info['reason'] = $reason;
        $id = TmsAttestation::where('self_id',$self_id)->update($new_info);
        $operationing->access_cause='修改';
        $operationing->table=$table_name;
        $operationing->table_id=$self_id;
        $operationing->now_time=$now_time;
        $operationing->old_info=$old_info;
        $operationing->new_info=$new_info;
        if ($id){
            $msg['code']=200;
            $msg['msg']='操作成功！';
            $msg['data']=$new_info;
        }else{
            $msg['code']=303;
            $msg['msg']='操作失败！';
        }
    }

    /***
      *企业认证详情  /tms/attestation/details
     **/
    public function details(Request $request,Details $details){
        $self_id=$request->input('self_id');
        $table_name='tms_attestation';
        $tms_company_type        =array_column(config('tms.tms_company_type'),'name','key');
        $select=['self_id','address','tel','state','name','type','email','sheng_name','shi_name','qu_name','total_user_id','login_account','user_name','identity_number','company_code','image','road'];
//         $self_id='atte_202104131347007299687246';
        $info=$details->details($self_id,$table_name,$select);

        if($info){
            /** 如果需要对数据进行处理，请自行在下面对 $info 进行处理工作*/
            $info->image = img_for($info->image,'more');
            $info->road = img_for($info->road,'more');
            $info->type_show = $tms_company_type[$info->type];
            $data['info']=$info;
            $log_flag='Y';
            $data['log_flag']=$log_flag;
            $log_num='10';
            $data['log_num']=$log_num;
            $data['log_data']=null;

            if($log_flag =='Y'){
                $data['log_data']=$details->change($self_id,$log_num);

            }
//             dd($data);

            $msg['code']=200;
            $msg['msg']="数据拉取成功";
            $msg['data']=$data;
            return $msg;
        }else{
            $msg['code']=300;
            $msg['msg']="没有查询到数据";
            return $msg;
        }
    }


//    public function attestationPass(Request $request){
//        $now_time=date('Y-m-d H:i:s',time());
//        $operationing = $request->get('operationing');//接收中间件产生的参数
//        $user_info          = $request->get('user_info');//接收中间件产生的参数
//        $table_name='tms_attestation';
//        $self_id=$request->input('self_id');
//        $rules=[
//            'self_id'=>'required',
//        ];
//        $message=[
//            'self_id.required'=>'请选择要操作的数据',
//        ];
//        $input['self_id'] = $self_id = 'atte_202104131347007299687246';
//        $validator=Validator::make($input,$rules,$message);
//        if($validator->passes()) {
//        $select = ['self_id','address','tel','state','name','email','sheng_name','shi_name','qu_name','total_user_id'];
//        $info = TmsAttestation::where('self_id',$self_id)->select($select)->first();
//        $old_info = [
//            'state'=>$info->state,
//            'update_time'=>$now_time
//        ];
//        $new_info['update_time'] = $now_time;
//        $new_info['state'] = 3;
//        TmsAttestation::where('self_id',$self_id)->update($new_info);
//        /**保存公司数据**/
//            $data['group_name']         =$info->name;
//            $data['tel']                =$info->tel;
//            $data['business_type']      ='TMS';
//            $data['city']               =$info->shi_name;
//            $data['address']            =$info->address;
//
//        /** 查询出所有的预配置权限，然后把预配置权限给与这个公司**/
//        $where_business=[
//            ['use_flag','=','Y'],
//            ['delete_flag','=','Y'],
//            ['business_type','=','TMS'],
//        ];
//        $select=['self_id','authority_name','menu_id','leaf_id','type','cms_show'];
//        $authority_info=SystemGroupAuthority::where($where_business)->orderBy('create_time', 'desc')->select($select)->get();
//        $authority_list=[];
//        if($authority_info){
//            foreach ($authority_info as $k => $v){
//                if($v->type == 'system'){
//                    $data['menu_id']       =$v->menu_id;
//                    $data['leaf_id']       =$v->leaf_id;
//                    $data['cms_show']      =$v->cms_show;
//                }else{
//                    $authority_list[]=$v;
//                }
//            }
//
//        }
//
//        //说明是新增1
//        $group_code                 =generate_id('group_');
//        $data['self_id']            =$data['group_code']=$data['group_id']=$group_code;
//        $data['create_time']        =$data['update_time']=$now_time;
//        $data['group_id_show']      =$data['group_name'];
//        $data['create_user_id']     =$user_info->admin_id;
//        $data['create_user_name']   =$user_info->name;
//        $data['father_group_code']  =$user_info->group_code;
//        $data['binding_group_code'] =$user_info->group_code;
//
//        //$data['user_number'] 		=$user_number;
//        /***生成一个二维码出来    上传到阿里云OSS上面去  执行createImage！！！！！！！**/
//
//         //添加公司资金表
//         $capital_data['self_id']        = generate_id('capital_');
//         $capital_data['total_user_id']  = null;
//         $capital_data['group_code']    =$group_code;
//         $capital_data['group_name']    =$data['group_name'];
//         $capital_data['update_time']    =$now_time;
//         UserCapital::insert($capital_data);						//写入用户资金表
//
//         $id=SystemGroup::insert($data);
//
//        /**添加数据权限**/
//        if ($info->type == 'TMS3PL'){
//            //认证3pl公司
//            $dat['menu_id']='157*620*624*621*622*623*141*142*227*511*228*273*467*232*233*143*285*512*159*177*255*309*242*256*257*258*602*161*178*259*532*555*260*261*262*477*484*590*294*584*586*587*629*640*588*589*211*596*597*626*637*315*601*600*307*612*608*609*628*639*610*611*613*618*614*615*619*646*616*617*150*212*224*647*272*191*636*645*643*644*306*648*252*631*632*649*633*634*650*651*652*653';
//            $dat['leaf_id']='157*620*624*621*622*623*141*142*227*511*228*273*467*232*233*143*285*512*159*177*255*309*242*256*257*258*602*161*178*259*532*555*260*261*262*477*484*590*294*584*586*587*629*640*588*589*211*596*597*626*637*315*601*600*307*612*608*609*628*639*610*611*613*618*614*615*619*646*616*617*150*212*224*647*272*191*636*645*643*644*306*648*252*631*632*649*633*634*650*651*652*653';
//            $dat['cms_show']='公司权限员工管理　TMS调度系统　系统设置　TMS基础设置　TMS线上订单';
//        }else{
//            //认证货主公司
//
//        }
//
//        $dat['group_id']=$group_code;
//        $dat['group_id_show']=$info->name;
//
//        //查询一下这个里面是不是第一个权限，如果是第一个权限，则把他设置为lock_flag 为Y
//        $wheere['group_code']=$group_code;
//        $wheere['delete_flag']='Y';
//        $idsss=SystemAuthority::where($wheere)->value('self_id');
//        if($idsss){
//            $dat["lock_flag"]='N';
//        }else{
//            $dat["lock_flag"]='Y';
//        }
//        $dat["self_id"]             =generate_id('authority_');
//        $dat["authority_name"]      =$info->name;
//        $dat['create_user_id']      =$user_info->admin_id;
//        $dat['create_user_name']    =$user_info->name;
//        $dat["create_time"]         =$dat["update_time"]=$now_time;
//        $dat["group_code"]          =$group_code;
//        $dat["group_name"]          =$info->name;
//
//        $authhority=SystemAuthority::insert($dat);
//
//        /**添加账号信息**/
//
//        if($self_id){
//            $name_where=[
//                ['login','=',$info->tel],
//                ['self_id','!=',$self_id],
//                ['delete_flag','=','Y'],
//            ];
//        }else{
//            $name_where=[
//                ['login','=',$info->tel],
//                ['delete_flag','=','Y'],
//                ];
//        }
//        $name_count = SystemAdmin::where($name_where)->count();            //检查名字是不是重复
//
//        if($name_count > 0){
//            $msg['code'] = 301;
//            $msg['msg'] = '账号名称重复！';
//            return $msg;
//        }
//        $account['login']              =$info->tel;
//        $account['name']               =$info->name;
//        $account['tel']                =$info->tel;
//        $account['email']              =$info->email;
//        $account['group_code']         =$group_code;
//        $account['group_name']         =$info->name;
//        $account['authority_id']       =$dat['self_id'];
//        $account['authority_name']     =$info->name;
//
//        $account['self_id']=generate_id('admin_');
//        $account['pwd']=get_md5(123456);
//        $account['create_time']=$account['update_time']=$now_time;
//        $account['create_user_id'] =$user_info->admin_id;
//        $account['create_user_name'] = $user_info->name;
//        $admin = SystemAdmin::insert($account);
//
//        /**绑定身份**/
//        $identity['self_id']            = generate_id('identity_');
//        $identity['total_user_id']      =$user_info->total_user_id;
//        $identity['type']               ='TMS3PL';
//        $identity['create_user_id']     =$user_info->group_code;
//        $identity['create_user_name']   = $user_info->group_name;
//        $identity['group_code']         =$group_code;
//        $identity['group_name']         =$info->name;
//        $identity['admin_login']        =$info->tel;
//        $identity['total_user_id']      =$info->total_user_id;
//        $identity['create_time']       = $identity['update_time'] = $now_time;
//        $user_identity=UserIdentity::insert($identity);
//
//        if($id){
//            $msg['code'] = 200;
//            $msg['msg'] = "操作成功";
//            return $msg;
//        }else{
//            $msg['code'] = 302;
//            $msg['msg'] = "操作失败";
//            return $msg;
//        }
//
//    }else{
//            //前端用户验证没有通过
//            $erro=$validator->errors()->all();
//            $msg['code']=300;
//            $msg['msg']=null;
//            foreach ($erro as $k => $v){
//                $kk=$k+1;
//                $msg['msg'].=$kk.'：'.$v.'</br>';
//            }
//            return $msg;
//        }
//    }


}
?>
