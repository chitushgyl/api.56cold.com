<?php
namespace App\Http\Admin\Tms;


use App\Http\Controllers\CommonController;
use App\Models\Tms\AppParam;
use Illuminate\Http\Request;

class ParamController extends CommonController{

    public function  paramList(Request $request){
        $data['page_info']      =config('page.listrows');
        $data['button_info']    =$request->get('anniu');

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }


    public function paramPage(Request $request){
        /** 接收中间件参数**/
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $button_info    = $request->get('anniu');//接收中间件产生的参数
        $tms_car_possess_type    =array_column(config('tms.tms_car_possess_type'),'name','key');
        $tms_control_type    	 =array_column(config('tms.tms_control_type'),'name','key');
        /**接收数据*/
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;

        $search=[

        ];

        $where=get_list_where($search);

        $select=['self_id','XiaoMi','OPPO','vivo','HUAWEI','Apple','other','group_code','group_name','create_time','update_time'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=AppParam::where($where)->count(); //总的数据量
                $data['items']=AppParam::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;

            case 'one':
                $where[]=['group_code','=',$group_info['group_code']];
                $data['total']=AppParam::where($where)->count(); //总的数据量
                $data['items']=AppParam::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='N';
                break;

            case 'more':
                $data['total']=TmsCar::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                $data['items']=TmsCar::where($where)->whereIn('group_code',$group_info['group_code'])
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;
        }
        // dd($data['items']->toArray());

        foreach ($data['items'] as $k=>$v) {
            $v->button_info=$button_info;

        }

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;

    }

    public function paramAdd(Request $request){
        $group_info = $request->get('group_info');
        $user_info = $request->get('user_info');
        $now_time       =date('Y-m-d H:i:s',time());
        $input              =$request->all();

        /** 接收数据*/
        $self_id            =$request->input('self_id');
        $group_code         =$request->input('group_code');
        $XiaoMi          =$request->input('XiaoMi');
        $OPPO         =$request->input('OPPO');
        $vivo        =$request->input('vivo');
        $HUAWEI        =$request->input('HUAWEI');
        $Apple             =$request->input('Apple');
        $other             =$request->input('other');

        /*** 虚拟数据
        $input['self_id']        =$self_id='good_202007011336328472133661';
        $input['group_code']     =$group_code='1234';
        $input['XiaoMi']         =$XiaoMi=20;
        $input['OPPO']           =$OPPO=30;
        $input['vivo']           =$vivo=40;
        $input['HUAWEI']         =$HUAWEI=50;
        $input['Apple']          =$Apple=60;
        $input['other']          =$other=70;
         **/

        $rules=[

        ];
        $message=[

        ];

        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {

            $data['XiaoMi']         =$XiaoMi;
            $data['OPPO']           =$OPPO;
            $data['vivo']           =$vivo;
            $data['HUAWEI']         =$HUAWEI;
            $data['Apple']          =$Apple;
            $data['other']          =$other;

            $wheres['self_id'] = $self_id;
            $old_info=TmsCar::where($wheres)->first();

            if($old_info){
                //dd(1111);
                $data['update_time']=$now_time;
                $id=AppParam::where($wheres)->update($data);

            }else{

                $data['self_id']            =generate_id('param_');
                $data['group_code']         = $group_code;
//                $data['group_name']         = $group_name;
                $data['create_user_id']     =$user_info->admin_id;
                $data['create_user_name']   =$user_info->name;
                $data['create_time']        =$data['update_time']=$now_time;

                $id=AppParam::insert($data);

            }

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
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            return $msg;
        }

    }
}
