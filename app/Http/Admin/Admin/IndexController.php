<?php
namespace App\Http\Admin\Admin;
use App\Models\Tms\TmsLine;
use App\Models\Tms\TmsOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\CommonController;
use App\Models\SystemMenuNew;
use App\Models\Group\SystemAdmin;

class IndexController extends CommonController{

    /***    首页接口，拿取菜单的地方      /admin/index
     *
     */
    public function index(Request $request){
        $user_info      = $request->get('user_info');//接收中间件产生的参数
        $group_info     = $request->get('group_info');
//        dump($group_info['group_code']);
        $where_menu=[
            ['delete_flag','=','Y'],
            ['level','=','1'],
        ];
		if($user_info->authority_id != '10'){
			$where_menu['admin_flag']='N';
		}
        $where_children=[
            ['delete_flag','=','Y'],
        ];
        if($user_info->authority_id != '10'){
            $where_children['admin_flag']='N';
        }
        $select=['id','level','name','an_name','img','node','sort','url'];

        if($user_info->menu_id== 'all'){
            $menu_info=SystemMenuNew::with(['children' => function($query)use($where_children,$select) {
                $query->where($where_children);
                $query->select($select);
                $query->orderBy('sort','asc');
            }])->where($where_menu)->select($select)->orderBy('sort','asc')->get();
            foreach ($menu_info as $key => $value){
                if ($value->an_name){
                    $value->name = $value->an_name;
                }
                foreach ($value->children as $k => $v){
                    if ($v->an_name){
                        $v->name = $v->an_name;
                    }
                }
            }
        }else{
            $menu_id=explode('*', $user_info->menu_id);
            $menu_info=SystemMenuNew::with(['children' => function($query)use($where_children,$select,$menu_id) {
                $query->where($where_children);
                $query->select($select);
                $query->orderBy('sort','asc');
                $query->whereIn('id',$menu_id);
            }])->where($where_menu)->select($select)->whereIn('id',$menu_id)->orderBy('sort','asc')->get();


        }

        /** 做一个 还有多少有效期的事情**/
        $now_time   =date('Y-m-d H:i:s',time());
        $startdate  =strtotime($now_time);
        $enddate    =strtotime($user_info->expire_time);
        $days       =intval(round(($enddate-$startdate)/3600/24)) ;                 //还有多少天数到期
        /*** 做统计数据 start**/
        $start_time = date('Y-m-d',strtotime('-10'.' days',strtotime($now_time)));
        $end_time = date('Y-m-d',time()).' 23:59:59';
        /** 订单数量***/
        $vehical_count_where = [
            ['order_type','=','vehicle'],
        ];
        $line_count_where = [
            ['order_type','!=','vehicle'],
        ];

        $order_count_where = [
            ['group_code','=',$group_info['group_code']]
        ];

        switch ($group_info['group_id']){
            case 'all':
                $vehical_order_count = TmsOrder::where($vehical_count_where)->count();
                $line_order_count = TmsOrder::where($line_count_where)->count();
                /*** 货品总重量总体积**/
                $vehical_weight = TmsOrder::where($vehical_count_where)->sum('good_weight');
                $line_weight = TmsOrder::where($line_count_where)->sum('good_weight');
                $count_weight = $vehical_weight + $line_weight;
                /***已完成订单数量**/
                $count_done_order = TmsOrder::where('order_status','=',6)->count();

                /**统计近十日的订单数量***/
                for($i=0;$i<=10;$i++){
                    $time = date('Y-m-d',strtotime('+'.$i.' days',strtotime($start_time)));
                    $arr['time'] = date('m/d',strtotime($time));
                    $finance_where = [
                        ['create_time','>',$time.' 00:00:00'],
                        ['create_time','<',$time.' 23:59:59']
                    ];
                    $arr['order_count'] = TmsOrder::where($finance_where)->count();
                    $finance_order[] = $arr;
                }

                //        统计线路排名
                $line_count = TmsLine::with(['tmsOrder' => function($query){
                    $query->select('line_id','self_id');
                    $query->count('line_id');
                }])
                    ->select('send_shi_name','gather_shi_name','self_id')->limit(7)->get();
                break;

            case 'one':
                $vehical_order_count = TmsOrder::where($vehical_count_where)->where($order_count_where)->count();
                $line_order_count = TmsOrder::where($line_count_where)->where($order_count_where)->count();
                //        货品总重量总体积
                $vehical_weight = TmsOrder::where($vehical_count_where)->where($order_count_where)->sum('good_weight');
                $line_weight = TmsOrder::where($line_count_where)->where($order_count_where)->sum('good_weight');
                $count_weight = $vehical_weight + $line_weight;
                //        已完成订单数量
                $count_done_order = TmsOrder::where($order_count_where)->where('order_status','=',6)->count();

                //        统计近十日的订单数量
                for($i=0;$i<=10;$i++){
                    $time = date('Y-m-d',strtotime('+'.$i.' days',strtotime($start_time)));
                    $arr['time'] = date('m/d',strtotime($time));
                    $finance_where = [
                        ['group_code','=',$user_info->group_code],
                        ['create_time','>',$time.' 00:00:00'],
                        ['create_time','<',$time.' 23:59:59']
                    ];
                    $arr['order_count'] = TmsOrder::where($finance_where)->count();
                    $finance_order[] = $arr;
                }

                //        统计线路排名
                $line_count = TmsLine::with(['tmsOrder' => function($query){
                    $query->select('line_id','self_id');
                    $query->count('line_id');
                }])
                    ->where($order_count_where)->select('send_shi_name','gather_shi_name','self_id')->limit(7)->get();
                break;

            case 'more':
                $vehical_order_count = TmsOrder::where($vehical_count_where)->whereIn('group_code',$group_info['group_code'])->count();
                $line_order_count = TmsOrder::where($line_count_where)->whereIn('group_code',$group_info['group_code'])->count();
                //        货品总重量总体积
                $vehical_weight = TmsOrder::where($vehical_count_where)->whereIn('group_code',$group_info['group_code'])->sum('good_weight');
                $line_weight = TmsOrder::where($line_count_where)->whereIn('group_code',$group_info['group_code'])->sum('good_weight');
                $count_weight = $vehical_weight + $line_weight;
                //        已完成订单数量
                $count_done_order = TmsOrder::whereIn('group_code',$group_info['group_code'])->where('order_status','=',6)->count();
                //        统计近十日的订单数量
                for($i=0;$i<=10;$i++){
                    $time = date('Y-m-d',strtotime('+'.$i.' days',strtotime($start_time)));
                    $arr['time'] = date('m/d',strtotime($time));
                    $finance_where = [
                        ['create_time','>',$time.' 00:00:00'],
                        ['create_time','<',$time.' 23:59:59']
                    ];
                    $arr['order_count'] = TmsOrder::where($finance_where)->whereIn('group_code',$group_info['group_code'])->count();
                    $finance_order[] = $arr;
                }

                //        统计线路排名
                $line_count = TmsLine::with(['tmsOrder' => function($query){
                    $query->select('line_id','self_id');
                    $query->count('line_id');
                }])
                    ->whereIn('group_code',$group_info['group_code'])->select('send_shi_name','gather_shi_name','self_id')->limit(7)->get();
                break;
        }

        $title_count['vehical_order_count'] = $vehical_order_count;
        $title_count['line_order_count'] = $line_order_count;
        $title_count['count_weight'] = round($count_weight,2);
        $title_count['count_done_order'] = $count_done_order;

        if ($line_count){
            foreach ($line_count as $key =>$value){
                $line_count[$key]['count'] = count($value->tmsOrder);
            }
            $line_count2 = $line_count->toArray();

            $line_count1 = array_column($line_count2,'count');
            array_multisort($line_count1,SORT_DESC,$line_count2);
            $line_count = $line_count2;
        }else{
              $line_count = [];
        }
        /*** 做统计数据 end**/


        $msg['code']=200;
        $msg['msg']="拉取数据成功";
        $msg['menu_info']=$menu_info;
        $msg['days']=$days;                         //后台使用到期时间
        $msg['user_info']=$user_info;
        $msg['title_show'] = $title_count;    //顶部统计
        $msg['finance_order'] = $finance_order; //图标统计
        $msg['line_count'] = $line_count; //线路排名
        return $msg;

    }

    /***    修改密码                /admin/changePwd
     */

    public function changePwd(Request $request){
		/** 接收数据*/
        $operationing   = $request->get('operationing');//接收中间件产生的参数

        $user_info      = $request->get('user_info');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='system_admin';

        $operationing->access_cause     ='修改自己密码';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='update';
        $operationing->now_time         =$now_time;


        /** 接收数据*/
        $old_pwd            =$request->input('old_pwd');
        $pwd                =$request->input('pwd');
        $pwd_confirmation   =$request->input('pwd_confirmation');

        $input=$request->all();

        /**  虚拟数据
        $input['old_pwd']           =$old_pwd           ='123456789';
        $input['pwd']               =$pwd               ='123456789';
        $input['pwd_confirmation']  =$pwd_confirmation  ='123456789';
        **/
       // dump($user_info);

        $rules=[
            'old_pwd'           =>'required',
            'pwd'               =>'required|confirmed',
            'pwd_confirmation'  =>'required',
        ];
        $message=[
            'old_pwd.required'          =>'原密码输入不能为空',
            'pwd.required'              =>'新密码输入不能为空',
            'pwd_confirmation.required' =>'重复密码输入不能为空',
            'pwd.confirmed'             =>'新密码和重复密码不一致',
        ];
        $validator=Validator::make($input,$rules,$message);
        //dump($input);


        if($validator->passes()){
            $where=[
                ['login','=',$user_info->login],
            ];
            $select=['login','self_id','group_code','group_name','pwd','update_time'];
            $old_info=SystemAdmin::where($where)->select($select)->first();

            $data['pwd']            =get_md5($pwd);
            $data['update_time']    =$now_time;

           // dump($old_info);


            $operationing->table_id     =$old_info->self_id;
            $operationing->old_info     =$old_info;
            $operationing->new_info     =$data;
            //dd($operationing);
            if($old_info->pwd == get_md5($old_pwd)){

                $id=SystemAdmin::where($where)->update($data);
                if($id){
                    $msg['code']=200;
                    $msg['msg']='修改成功';
                    return $msg;
                }else{
                    $msg['code']=303;
                    $msg['msg']='修改失败';
                    return $msg;
                }

            }else{
                //返回账号和密码不符合
                $msg['code']=301;
                $msg['msg']='您输入的老密码不正确，请您重新输入';
                return $msg;
            }

        }else{
            //前端用户验证没有通过，一般是新密码没有输入或者二次密码不对
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


}
?>
