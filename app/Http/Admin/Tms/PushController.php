<?php
namespace App\Http\Admin\Tms;

use App\Http\Controllers\CommonController;
use App\Models\Log\LogLogin;
use App\Models\Tms\TmsPush;
use App\Models\User\UserIdentity;
use App\Models\User\UserTotal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PushController extends CommonController{

    /***    业务公司列表      /tms/push/pushList
     */
    public function  pushList(Request $request){
        $data['page_info']      =config('page.listrows');
        $data['button_info']    =$request->get('anniu');
        $abc='推送消息';

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        //dd($msg);
        return $msg;
    }
    /**
     * 推送列表 /tms/push/pushPage
     * */
    public function pushPage(Request $request){
        /** 接收中间件参数**/
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $button_info    = $request->get('anniu');//接收中间件产生的参数

        /**接收数据*/
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $use_flag       =$request->input('use_flag');
        $group_code     =$request->input('group_code');
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;

        $search=[
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'all','name'=>'use_flag','value'=>$use_flag],
            ['type'=>'=','name'=>'group_code','value'=>$group_code],
        ];


        $where=get_list_where($search);

        $select=['self_id','push_content','use_flag','delete_flag','create_time','update_time','push_title'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=TmsPush::where($where)->count(); //总的数据量
                $data['items']=TmsPush::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;

            case 'one':
                $where[]=['group_code','=',$group_info['group_code']];
                $data['total']=TmsPush::where($where)->count(); //总的数据量
                $data['items']=TmsPush::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='N';
                break;

            case 'more':
                $data['total']=TmsPush::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                $data['items']=TmsPush::where($where)->whereIn('group_code',$group_info['group_code'])
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;
        }
        foreach ($data['items'] as $k=>$v) {
            $v->button_info=$button_info;
        }

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /**
     *  添加推送消息   /tms/push/addPush
     * */
     public function addPush(Request $request){
         $user_info = $request->get('user_info');//接收中间件产生的参数
         $operationing   = $request->get('operationing');//接收中间件产生的参数
         $now_time       =date('Y-m-d H:i:s',time());
         $table_name     ='tms_push';

         $operationing->access_cause     ='创建/修改车辆类型';
         $operationing->table            =$table_name;
         $operationing->operation_type   ='create';
         $operationing->now_time         =$now_time;
         $operationing->type             ='add';

         $input              =$request->all();
         //dd($input);
         /** 接收数据*/
         $self_id             =$request->input('self_id');
         $push_title          =$request->input('push_title');
         $push_content        =$request->input('push_content');
         /*** 虚拟数据
         $input['push_content']       =$push_content ='4米2厢车';
          **/
         $rules=[
             'push_title' => 'required',
             'push_content'=>'required',
         ];
         $message=[
             'push_title.required'=>'请填写推送标题',
             'push_content.required'=>'请填写推送内容',
         ];

         $validator=Validator::make($input,$rules,$message);
         if($validator->passes()) {

             $data['self_id']            =generate_id('push_');
             $data['push_title']         = $push_title;
             $data['push_content']       = $push_content;
             $data['create_time']   = $data['update_time'] = $now_time;
             $id=TmsPush::insert($data);
             $operationing->access_cause='新建推送消息';
             $operationing->operation_type='create';


             $operationing->table_id=$data['self_id'];
             $operationing->old_info=null;
             $operationing->new_info=$data;

             if($id){
                $msg['code'] = 200;
                $msg['msg'] = "操作成功";
                return $msg;
             }else{
                 $msg['code'] = 302;
                 $msg['msg'] = "操作失败";
                 return $msg;
             }
         }else{
             //前端用户验证没有通过
             $erro=$validator->errors()->all();
             $msg['code']=300;
             $msg['msg']=null;
             foreach ($erro as $k => $v){
                 $kk=$k+1;
                 $msg['msg'].=$kk.'：'.$v;
             }
             return $msg;
         }
     }
     /**
      * 启用禁用推送消息 /tms/push/pushUseFlag
      * */
    public function pushUseFlag(Request $request,Status $status){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='tms_push';
        $medol_name='TmsPush';
        $self_id=$request->input('self_id');
        $flag='useFlag';
//        $self_id='car_202012242220439016797353';

        $status_info=$status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);

        $operationing->access_cause='启用/禁用';
        $operationing->table=$table_name;
        $operationing->table_id=$self_id;
        $operationing->now_time=$now_time;
        $operationing->old_info=$status_info['old_info'];
        $operationing->new_info=$status_info['new_info'];
        $operationing->operation_type=$flag;

        $msg['code']=$status_info['code'];
        $msg['msg']=$status_info['msg'];
        $msg['data']=$status_info['new_info'];

        return $msg;
    }

    /**
     * 删除推送消息 /tms/push/pushDelFlag
     * */
    public function pushDelFlag(Request $request,Status $status){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='tms_push';
        $medol_name='TmsPush';
        $self_id=$request->input('self_id');
        $flag='delFlag';
//        $self_id='car_202012242220439016797353';

        $status_info=$status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);

        $operationing->access_cause='删除';
        $operationing->table=$table_name;
        $operationing->table_id=$self_id;
        $operationing->now_time=$now_time;
        $operationing->old_info=$status_info['old_info'];
        $operationing->new_info=$status_info['new_info'];
        $operationing->operation_type=$flag;

        $msg['code']=$status_info['code'];
        $msg['msg']=$status_info['msg'];
        $msg['data']=$status_info['new_info'];

        return $msg;
    }

     /**
      * 推送对象列表 /tms/push/pushObject
      * */
    public function pushObject(Request $request){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $tms_user_type    	 =array_column(config('tms.tms_user_type'),'name','key');
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $type       =$request->input('type');
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;

        $search=[
            ['type'=>'=','name'=>'type','value'=>$type],
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],

        ];

        $where=get_list_where($search);

        $select = ['type','total_user_id'];
        $select1 = ['self_id','tel'];

        $data['info'] = UserIdentity::with(['userTotal'=>function($query)use($where,$select1) {
            $query->select($select1);
        }])
            ->where($where)
            ->orderBy('create_time','desc')
            ->offset($firstrow)->limit($listrows)
            ->select($select)
            ->get();
        $data['total'] = UserIdentity::where($where)->count();
        foreach ($data['info'] as $key =>$value){
             $value->type_show = $tms_user_type[$value->type];
             $value->show_name = $value->userTotal->tel;
        }
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /**
     * 推送 /tms/push/toPush
     * */
    public function toPush(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_push';

        $operationing->access_cause     ='创建/修改车辆类型';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='add';

        $input              = $request->all();
        //dd($input);
        /** 接收数据*/
        $user_list        = $request->input('user_list');
        $push_id          = $request->input('push_id');
        /*** 虚拟数据
        $input['push_content']       =$push_content ='4米2厢车';
         **/
        $rules=[
            'user_list'=>'required',
            'push_id'=>'required',
        ];
        $message=[
            'user_list.required'=>'请选择推送对象',
            'push_id.required'=>'请填写推送内容',
        ];

        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $user_list = json_decode($user_list,true);
            $push_info = TmsPush::where('self_id',$push_id)->select(['push_title','push_content','self_id','is_push'])->first();
            $push_cid = [];
            foreach ($user_list as $key => $value){
                $login_info = LogLogin::where('user_id',$value)->select(['clientid'])->get();
                $push_cid[] = array_unique($login_info);
            }
            include_once base_path( '/vendor/push/GeTui.php');
            $geTui = new \GeTui();
            $result = $geTui->pushToList('推送信息',$push_info->title,$push_info->content,$push_cid);
            if ($result['code'] != 0){
                $msg['code']=301;
                $msg['msg']="推送失败";
                return $msg;
            }
            $msg['code']=200;
            $msg['msg']="推送成功";
            return $msg;
        }else{
            //前端用户验证没有通过
            $erro=$validator->errors()->all();
            $msg['code']=300;
            $msg['msg']=null;
            foreach ($erro as $k => $v){
                $kk=$k+1;
                $msg['msg'].=$kk.'：'.$v;
            }
            return $msg;
        }
    }
}
