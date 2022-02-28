<?php
namespace App\Http\Admin\Tms;
use App\Http\Controllers\FileController as File;
use App\Models\Tms\TmsDeliveryCity;
use App\Models\Tms\TmsOrder;
use Illuminate\Http\Request;
use App\Http\Controllers\CommonController;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use App\Tools\Import;
use App\Http\Controllers\StatusController as Status;
use App\Http\Controllers\DetailsController as Details;
use App\Http\Controllers\TmsController as Tms;
use App\Models\Group\SystemGroup;
use App\Models\Tms\TmsLine;
use App\Models\Tms\TmsLineList;


class LineController extends CommonController{

    /***    线路列表头部      /tms/line/lineList
     */
    public function  lineList(Request $request){
        $data['page_info']      =config('page.listrows');
        $data['button_info']    =$request->get('anniu');

        $abc='线路类型';
        $data['import_info']    =[
            'import_text'=>'下载'.$abc.'导入示例文件',
            'import_color'=>'#FC5854',
            'import_url'=>config('aliyun.oss.url').'execl/2020-07-02/TMS线路导入文件范本.xlsx',
        ];
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /***    线路分页      /tms/line/linePage
     */
    public function linePage(Request $request){
        /** 接收中间件参数**/
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $button_info    = $request->get('anniu');//接收中间件产生的参数

        $tms_line_status_type       =array_column(config('tms.tms_line_status_type'),'name','key');
        $tms_pick_type              =array_column(config('tms.tms_pick_type'),'name','key');
        $tms_send_type              =array_column(config('tms.tms_send_type'),'name','key');
        $tms_control_type           =array_column(config('tms.tms_control_type'),'name','key');
        $tms_line_type           =array_column(config('tms.tms_line_type'),'name','key');
        $tms_order_inco_type         =array_column(config('tms.tms_order_inco_type'),'icon','key');
        /**接收数据*/
        $num            = $request->input('num')??10;
        $page           = $request->input('page')??1;
        $use_flag       = $request->input('use_flag');
        $group_code     = $request->input('group_code');
        $start_city     = $request->input('start_city');
        $end_city       = $request->input('end_city');
        $min_price      = $request->input('min_price');
        $price          = $request->input('price');
        $trunking       = $request->input('trunking');
        $cold           = $request->input('cold');//温度
        $time           = $request->input('time');//发车日期
        $min_money    = $request->input('min_money');//最低干线费
        $depart_time  = $request->input('depart_time');//发车时间排序
        $listrows       = $num;
        $firstrow       = ($page-1)*$listrows;

        $search=[
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'all','name'=>'use_flag','value'=>$use_flag],
            ['type'=>'=','name'=>'group_code','value'=>$group_code],
            ['type'=>'like','name'=>'send_shi_name','value'=>$start_city],
            ['type'=>'like','name'=>'gather_shi_name','value'=>$end_city],
            ['type'=>'=','name'=>'control','value'=>$cold],
        ];

        $where=get_list_where($search);
        if($time){
            $week = date('w',strtotime($time));
            $where[] = ['time'.$week,'=','Y'];
        }

        $select=['self_id','shift_number','type','price','min_money','use_flag','group_name','send_address','gather_address','send_address_id','gather_address_id','trunking','control',
            'pick_price','send_price','pick_type','send_type','all_weight','all_volume','trunking','control','send_shi','send_qu','send_name','send_tel','gather_name','gather_tel',
            'send_sheng_name','send_shi_name','send_qu_name','gather_sheng_name','gather_shi_name','gather_qu_name','gather_sheng','gather_shi','gather_qu','send_sheng',
            'start_time','end_time','time0','time1','time2','time3','time4','time5','time6','depart_time','more_price'];

        switch ($group_info['group_id']){
            case 'all':
                $data['total']=TmsLine::where($where)->count(); //总的数据量
                $data['items']=TmsLine::where($where);
                if ($min_money){
                    $data['items'] = $data['items']->orderBy('send_price','asc');
                }
                if ($trunking){
                    $data['items'] = $data['items']->orderBy('trunking','asc');
                }
                if ($depart_time){
                    $data['items'] = $data['items']->orderBy('depart_time','asc');
                }
                $data['items'] = $data['items']
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time','desc')->orderBy('self_id','desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;

            case 'one':
                $where[]=['group_code','=',$group_info['group_code']];
                $data['total']=TmsLine::where($where)->count(); //总的数据量
                $data['items']=TmsLine::where($where);
                if ($min_money){
                    $data['items'] = $data['items']->orderBy('send_price','asc');
                }
                if ($trunking){
                    $data['items'] = $data['items']->orderBy('trunking','asc');
                }
                if ($depart_time){
                    $data['items'] = $data['items']->orderBy('depart_time','asc');
                }
                $data['items'] = $data['items']
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time','desc')->orderBy('self_id','desc')
                    ->where('depart_time','<=',date('H:i',time()+7200))
                    ->select($select)->get();
                $data['group_show']='N';
                break;

            case 'more':
                $data['total']=TmsLine::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                $data['items']=TmsLine::where($where)->whereIn('group_code',$group_info['group_code']);
                if ($min_money){
                    $data['items'] = $data['items']->orderBy('send_price','asc');
                }
                if ($trunking){
                    $data['items'] = $data['items']->orderBy('trunking','asc');
                }
                if ($depart_time){
                    $data['items'] = $data['items']->orderBy('depart_time','asc');
                }
                $data['items'] = $data['items']
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time','desc')->orderBy('self_id','desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;
        }
//dump($data['items']->toArray());
        //dump($tms_line_type);
        foreach ($data['items'] as $k=>$v) {
            $v->price=number_format($v->price/100,2);
            $v->pick_price=number_format($v->pick_price/100,2);
            $v->send_price=number_format($v->send_price/100,2);
            $v->min_money=number_format($v->min_money/100,2);
            $week=[];
            $v->type_inco=null;
            $v->week=null;
            $v->origin=null;
            $v->destination=null;
            if($v['time1']=='Y'){
                $week[]='周一';
            }
            if($v['time2']=='Y'){
                $week[]='周二';
            }
            if($v['time3']=='Y'){
                $week[]='周三';
            }
            if($v['time4']=='Y'){
                $week[]='周四';
            }
            if($v['time5']=='Y'){
                $week[]='周五';
            }
            if($v['time6']=='Y'){
                $week[]='周六';
            }
            if($v['time0']=='Y'){
                $week[]='周日';
            }

            if($week){
                $v->week=implode('*',$week);
            }

            if($v->start_time=='2018-11-30 00:00:00' && $v->end_time=='2099-12-31 00:00:00'){
                $v->time_show='长期有效';
            }else{
                $v->time_show=$v->start_time.'～'.$v->end_time;
            }

            $v->trunking_show=$v->trunking.'天';
            $v->pick_type_show=$tms_pick_type[$v->pick_type]??null;
            $v->send_type_show=$tms_send_type[$v->send_type]??null;
            $v->control_show=$tms_control_type[$v->control]??null;
            $v->status_show=$tms_line_status_type[$v->status]??null;
            $v->type_show=$tms_line_type[$v->type]??null;
            $v->button_info=$button_info;
            $v->type_inco=img_for($tms_order_inco_type[$v->type],'no_json') ?? null;
            $v->detime       = date('m-d H:i',strtotime($time.' '.$v->depart_time));
            $v->price_show = '元/公斤';
            $v->startime_show = '发车 '.$v->detime;
            $v->sendprice_show = '配送费'.$v->send_price.'元';
        }

//        DD($data['items']->toArray());

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /**
     * 线路列表 货主零担下单  /tms/line/line_list
     * */
    public function line_list(Request $request){
        /** 接收中间件参数**/
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $button_info    = $request->get('anniu');//接收中间件产生的参数

        $tms_line_status_type       =array_column(config('tms.tms_line_status_type'),'name','key');
        $tms_pick_type              =array_column(config('tms.tms_pick_type'),'name','key');
        $tms_send_type              =array_column(config('tms.tms_send_type'),'name','key');
        $tms_control_type           =array_column(config('tms.tms_control_type'),'name','key');
        $tms_line_type           =array_column(config('tms.tms_line_type'),'name','key');
        $tms_order_inco_type         =array_column(config('tms.tms_order_inco_type'),'icon','key');
        /**接收数据*/
        $num            = $request->input('num')??10;
        $page           = $request->input('page')??1;
        $now_time     = date('Y-m-d H:i:s',time());
        $user_info    = $request->get('user_info');
        $listrows	  = config('page.listrows')[0];//每次加载的数量
        $first		  = $request->input('page')??1;
        $firstrow	  = ($first-1)*$listrows;

        $startcity    = $request->input('start_city')??'';//发车城市
        $endcity      = $request->input('end_city')??'';//目的城市
        $time         = $request->input('time');//发车日期
        if (empty($time)){
            $time = date('Y-m-d',time());
        }
//        $time =  '2021-05-19';
        $week = date('w',strtotime($time));

        $select = ['self_id','shift_number','type','price','use_flag','group_name','pick_price','send_price','pick_type','send_type','trunking','control',
            'send_sheng_name','send_shi_name','send_qu_name','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','send_address',
            'send_sheng','send_shi','send_qu','gather_sheng','gather_shi','gather_qu','send_address_id','gather_address_id','send_name','send_tel','gather_name','gather_tel',
            'start_time','end_time','time0','time1','time2','time3','time4','time5','time6','depart_time','update_time','min_money'];
        $selectList = ['line_id','yuan_self_id'];

        $where = [
            ['use_flag','=','Y'],
            ['delete_flag','=','Y'],
        ];
        if ($startcity) {
            $where[] = ['send_shi_name','=',$startcity];
        }
        if ($endcity) {
            $where[] = ['gather_shi_name','=',$endcity];
        }
        $where[] = ['time'.$week,'=','Y'];
        $where[] = ['start_time','<',$time];
        $where[] = ['end_time','>',$time];

        $data['total']=TmsLine::where($where)->count(); //总的数据量
        $data['items'] = TmsLine::with(['tmsLineList' => function($query)use($selectList,$select) {
            $query->where('delete_flag','=','Y');
            $query->select($selectList);
            $query->orderBy('sort', 'asc');
            $query->with(['tmsLine' => function($query)use($select){
                $query->select($select);
            }]);
        }])->where($where)
            ->orderBy('update_time','asc')
            ->orderBy('min_money','desc')
            ->offset($firstrow)
            ->limit($listrows)
            ->select($select)
            ->get();

        $tms_pick_type    = array_column(config('tms.tms_pick_type'),'name','key');
        $tms_send_type    = array_column(config('tms.tms_send_type'),'name','key');
        $tms_control_type = array_column(config('tms.tms_control_type'),'name','key');
        $tms_line_type    = array_column(config('tms.tms_line_type'),'name','key');

        foreach ($data['items'] as $k=>$v) {
            $line_info = [];
            $week=[];
            $v->price      = number_format($v->price/100,2);
            $v->pick_price = number_format($v->pick_price/100);
            $v->send_price = number_format($v->send_price/100);
            $v->min_money  = number_format($v->min_money/100);
            $v->pick_type_show  = $tms_pick_type[$v->pick_type] ?? null;
            $v->send_type_show  = $tms_send_type[$v->send_type] ?? null;
            $v->control    =  $tms_control_type[$v->control] ?? null;
            $v->type       = $tms_line_type[$v->type] ?? null;
            $v->detime       = date('m-d H:i',strtotime($time.''.$v->depart_time));
            $v->detime_show       = date('Y-m-d H:i',strtotime($time.''.$v->depart_time));
            $v->trunking_show=$v->trunking.'天';
            if($v['time1']=='Y'){
                $week[]='周一';
            }
            if($v['time2']=='Y'){
                $week[]='周二';
            }
            if($v['time3']=='Y'){
                $week[]='周三';
            }
            if($v['time4']=='Y'){
                $week[]='周四';
            }
            if($v['time5']=='Y'){
                $week[]='周五';
            }
            if($v['time6']=='Y'){
                $week[]='周六';
            }
            if($v['time0']=='Y'){
                $week[]='周日';
            }

            if($week){
                $v->week=implode('*',$week);
            }
            $v->button_info=$button_info;
//            if(time() <= (strtotime($v->detime)-7200)){
//                $line_info[] = $v;
//            }
        }

//        dd($line_info);
        $msg['code'] = 200;
        $msg['msg']  = '数据拉取成功！';
        $msg['data'] = $data;
        return $msg;
    }


    /***    新建线路    /tms/line/createLine
     */
    public function createLine(Request $request){
        /** 接收数据*/
        $tms_control_type           =array_column(config('tms.tms_control_type'),'name','key');
		$data['tms_line_type']              =config('tms.tms_line_type');
        $data['tms_control_type']           =config('tms.tms_control_type');
        $data['tms_line_status_type']       =config('tms.tms_line_status_type');
        $data['tms_pick_type']              =config('tms.tms_pick_type');
        $data['tms_send_type']              =config('tms.tms_send_type');
        $self_id=$request->input('self_id');
//        $self_id='line_202101101119015092642561';
        $where=[
            ['delete_flag','=','Y'],
            ['self_id','=',$self_id],
        ];
        $select=['self_id','shift_number','type','price','use_flag','group_name','group_code','min_money','special','min_number','max_number','unit_price','start_price','max_price',
            'pick_price','send_price','pick_type','send_type','all_weight','all_volume','trunking','control','carriage_id',
            'send_sheng_name','send_shi_name','send_qu_name','gather_sheng_name','gather_shi_name','gather_qu_name','send_address','gather_address',
            'start_time','end_time','time0','time1','time2','time3','time4','time5','time6','depart_time','send_sheng','send_shi','send_qu',
            'gather_sheng','gather_shi','gather_qu','gather_name','gather_tel','send_name','send_tel','send_address_id','send_contacts_id','gather_address_id','gather_contacts_id','more_price'];
        $selectList=['line_id','yuan_self_id'];
        $selectList2=['self_id','use_flag','delete_flag','shift_number','send_shi_name','gather_shi_name'];
        $selectList3 = ['self_id','company_name'];
        $data['info']=TmsLine::with(['tmsLineList' => function($query)use($selectList,$selectList2) {
            $query->where('delete_flag','=','Y');
            $query->select($selectList);
            $query->with(['tmsLine' => function($query)use($selectList2){
                $query->select($selectList2);
            }]);
            $query->orderBy('sort', 'asc');
        }])
            ->with(['tmsGroup' => function($query)use($selectList3){
                $query->select($selectList3);
            }])->where($where)->select($select)->first();

//        dd($data['info']->toArray());
        if($data['info']){
            $data['info']->price            =$data['info']->price/100;
            $data['info']->pick_price       =$data['info']->pick_price/100;
            $data['info']->send_price       =$data['info']->send_price/100;
            $data['info']->min_money        =$data['info']->min_money/100;
            $data['info']->more_price        =$data['info']->more_price/100;
            $data['info']->unit_price        =$data['info']->unit_price/100;
            $data['info']->start_price        =$data['info']->start_price/100;
            $data['info']->max_price        =$data['info']->max_price/100;
            $data['info']->temperture       =$tms_control_type[$data['info']->control] ?? null;
            if ($data['info']->tmsGroup){
                $data['info']->company_name = $data['info']->tmsGroup->company_name;
            }
        }


        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        //dd($msg);
        return $msg;
    }


    /***    线路数据提交      /tms/line/addLine
     */
    public function addLine(Request $request,Tms $tms){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_line';

        $operationing->access_cause     ='创建/修改线路';
        $operationing->table           =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='add';

        $input              =$request->all();
		//dump($input);
        /** 接收数据*/
        $self_id                =$request->input('self_id');
        $type                   =$request->input('type');
        $shift_number           =$request->input('shift_number');
        $price                  =$request->input('price');
        $min_money              =$request->input('min_money');
        $group_code             =$request->input('group_code');
        $pick_price             =$request->input('pick_price');
        $send_price             =$request->input('send_price');
        $pick_type              =$request->input('pick_type');
        $send_type              =$request->input('send_type');
        $depart_time            =$request->input('depart_time');
        $all_weight             =$request->input('all_weight');
        $all_volume             =$request->input('all_volume');
        $trunking               =$request->input('trunking');
        $start_time             =$request->input('start_time');
        $end_time               =$request->input('end_time');
        $control                =$request->input('control');
        $time0                  =$request->input('time0');
        $time1                  =$request->input('time1');
        $time2                  =$request->input('time2');
        $time3                  =$request->input('time3');
        $time4                  =$request->input('time4');
        $time5                  =$request->input('time5');
        $time6                  =$request->input('time6');
        $combination            =$request->input('combination');
        $more_price             =$request->input('more_price');

        /** 地址接收数据**/
        $gather_address_id      =$request->input('gather_address_id');
//        $gather_contacts_id     =$request->input('gather_contacts_id');
        $send_address_id        =$request->input('send_address_id');
//        $send_contacts_id       =$request->input('send_contacts_id');
        $gather_qu              =$request->input('gather_qu');
        $gather_address         =$request->input('gather_address');
        $gather_longitude       =$request->input('gather_longitude');
        $gather_dimensionality  =$request->input('gather_dimensionality');
        $gather_contacts_name   =$request->input('gather_contacts_name');
        $gather_contacts_tel    =$request->input('gather_contacts_tel');
        $send_qu                =$request->input('send_qu');
        $send_address           =$request->input('send_address');
        $send_longitude         =$request->input('send_longitude');
        $send_dimensionality    =$request->input('send_dimensionality');
        $send_contacts_name     =$request->input('send_contacts_name');
        $send_contacts_tel      =$request->input('send_contacts_tel');
        $more_price             =$request->input('more_price');
        $special                =$request->input('special');
        $min_number             =$request->input('min_number');
        $max_number             =$request->input('max_number');
        $unit_price             =$request->input('unit_price');
        $start_price            =$request->input('start_price');
        $max_price              =$request->input('max_price');




        /*** 虚拟数据
        $input['self_id']           =$self_id=null;
        $input['type']             =$type='alone';           //alone   ,combination
        $input['shift_number']     =$shift_number='1237834e1545';
        $input['price']                =$price='1200';
        $input['min_money']              =$min_money='1100';
        $input['group_code']           =$group_code='group_202105221146560804358534';
        $input['pick_price']        =$pick_price='20';
        $input['send_price']         =$send_price='50';
        $input['pick_type']         =$pick_type='pick';
        $input['send_type']    =$send_type='send';
        $input['depart_time']        =$depart_time='12:00:00';
        $input['all_weight']        =$all_weight='154';

        $input['all_volume']    =$all_volume='30';
        $input['trunking']        =$trunking='3';
        $input['start_time']        =$start_time='2018-11-30 00:00:00';
        $input['end_time']        =$end_time='2099-12-31 00:00:00';
        $input['control']        =$control='freeze';
        $input['time0']        =$time0='Y';
        $input['time1']        =$time1='Y';
        $input['time2']        =$time2='Y';
        $input['time3']        =$time3='Y';
        $input['time4']        =$time4='Y';
        $input['time5']        =$time5='Y';
        $input['time6']        =$time6='Y';
        $input['gather_address_id']         =$gather_address_id='';
//        $input['gather_contacts_id']        =$gather_contacts_id='contacts_202012290959556624971375';
        $input['send_address_id']           =$send_address_id='';
//        $input['send_contacts_id']          =$send_contacts_id='contacts_202012291001340113861963';
        $input['combination']               =$combination=['line_202101061728561956916403','line_202101061729153626383498'];
        $input['gather_qu']               =$gather_qu='146';
        $input['gather_address']           =$gather_address='中山公园';
        $input['gather_particular']        =$gather_particular='1002333';
        $input['gather_longitude']         =$gather_longitude='123';
        $input['gather_dimensionality']    =$gather_dimensionality='456';

        $input['send_qu']    =$send_qu='2523';
        $input['send_address']    =$send_address='盖世物流园';
        $input['send_longitude']    =$send_longitude='456';
        $input['send_dimensionality']    =$send_dimensionality='456';
        $input['send_contacts_name']    =$send_contacts_name='456';
        $input['send_contacts_tel']    =$send_contacts_tel='456';
        $input['more_price']    =$more_price='456';
         **/

        $rules=[
            'type'=>'required',
            'price'=>'required',
            'shift_number'=>'required'

        ];
        $message=[
            'type.required'=>'类型必须填写',
            'price.required'=>'价格必须填写',
            'shift_number.required'=>'班次号必须填写',
        ];




        $validator=Validator::make($input,$rules,$message);

        if($validator->passes()) {
            /***开始做二次效验**/

            if(!$depart_time){
                $msg['code'] = 301;
                $msg['msg'] = '请选择发车时间';
                return $msg;
            }
            $where_group=[
                ['delete_flag','=','Y'],
                ['self_id','=',$group_code],
            ];

            $group_info    =SystemGroup::where($where_group)->select('group_code','group_name')->first();

            if(empty($group_info)){
                $msg['code'] = 301;
                $msg['msg'] = '公司不存在';
                return $msg;
            }

            if($self_id){
                $name_where=[
                    ['self_id','!=',$self_id],
                    ['shift_number','=',$shift_number],
                    ['delete_flag','=','Y'],
                ];
            }else{
                $name_where=[
                    ['shift_number','=',$shift_number],
                    ['delete_flag','=','Y'],
                ];
            }

            $shiftnumber = TmsLine::where($name_where)->count();
            if (empty($start_time)){
                $start_time = '2018-11-30 00:00:00';
            }
            if (empty($end_time)){
                $end_time = '2099-12-31 00:00:00';
            }

//            if ($time0 = $time1 = $time2 = $time3 = $time4 = $time5 = $time6 == 'N'){
//                $msg['code'] = 307;
//                $msg['msg'] = '请选择干线周期';
//                return $msg;
//            }
//            dump($shiftnumber);
            if ($shiftnumber>0){
                $msg['code'] = 308;
                $msg['msg'] = '班次号不能重复,请重新填写';
                return $msg;
            }
            switch ($type){
                case 'alone':

                    $gather_address=$tms->address_contact($gather_address_id,$gather_qu,$gather_address,$gather_contacts_name,$gather_contacts_tel,$group_info,$user_info,$now_time);
                    if(empty($gather_address)){
                        $msg['code'] = 303;
                        $msg['msg'] = '收货地址不存在';
                        return $msg;
                    }

                    $send_address = $tms->address_contact($send_address_id,$send_qu,$send_address,$send_contacts_name,$send_contacts_tel,$group_info,$user_info,$now_time);
                    if(empty($send_address)){
                        $msg['code'] = 304;
                        $msg['msg'] = '发货地址不存在';
                        return $msg;
                    }


//                    $gather_contacts = $tms->contacts($gather_contacts_id,$gather_contacts_name,$gather_contacts_tel,$group_info,$user_info,$now_time);
//                    if(empty($gather_contacts)){
//                        $msg['code'] = 305;
//                        $msg['msg'] = '收货联系人不存在';
//                        return $msg;
//                    }
//
//                    $send_contacts = $tms->contacts($send_contacts_id,$send_contacts_name,$send_contacts_tel,$group_info,$user_info,$now_time);
//                    if(empty($send_contacts)){
//                        $msg['code'] = 306;
//                        $msg['msg'] = '发货联系人不存在';
//                        return $msg;
//                    }

                    $data['type']                       =$type;
                    $data['shift_number']               =$shift_number;
                    $data['price']                      =$price*100;
                    $data['min_money']                  =$min_money*100;
                    $data['pick_price']                 =$pick_price*100;
                    $data['send_price']                 =$send_price*100;
                    $data['pick_type']                  =$pick_type;
                    $data['send_type']                  =$send_type;
                    $data['depart_time']                =$depart_time;
                    $data['all_weight']                 =$all_weight;
                    $data['all_volume']                 =$all_volume;
                    $data['trunking']                   =$trunking;
                    $data['start_time']                 =$start_time;
                    $data['end_time']                   =$end_time;
                    $data['control']                    =$control;
                    $data['time0']                      =$time0;
                    $data['time1']                      =$time1;
                    $data['time2']                      =$time2;
                    $data['time3']                      =$time3;
                    $data['time4']                      =$time4;
                    $data['time5']                      =$time5;
                    $data['time6']                      =$time6;
                    $data['gather_address_id']          = $gather_address->self_id;
//                    $data['gather_contacts_id']         = $gather_contacts->self_id;
                    $data['gather_name']                = $gather_address->contacts;
                    $data['gather_tel']                 = $gather_address->tel;
                    $data['gather_sheng']               = $gather_address->sheng;
                    $data['gather_sheng_name']          = $gather_address->sheng_name;
                    $data['gather_shi']                 = $gather_address->shi;
                    $data['gather_shi_name']            = $gather_address->shi_name;
                    $data['gather_qu']                  = $gather_address->qu;
                    $data['gather_qu_name']             = $gather_address->qu_name;
                    $data['gather_address']             = $gather_address->address;
                    $data['gather_address_longitude']   = $gather_address->longitude;
                    $data['gather_address_latitude']    = $gather_address->dimensionality;
                    $data['send_address_id']            = $send_address->self_id;
//                    $data['send_contacts_id']           = $send_contacts->self_id;
                    $data['send_name']                  = $send_address->contacts;
                    $data['send_tel']                   = $send_address->tel;
                    $data['send_sheng']                 = $send_address->sheng;
                    $data['send_sheng_name']            = $send_address->sheng_name;
                    $data['send_shi']                   = $send_address->shi;
                    $data['send_shi_name']              = $send_address->shi_name;
                    $data['send_qu']                    = $send_address->qu;
                    $data['send_qu_name']               = $send_address->qu_name;
                    $data['send_address']               = $send_address->address;
                    $data['send_address_longitude']     = $send_address->longitude;
                    $data['send_address_latitude']      = $send_address->dimensionality;
                    $data['more_price']                 = $more_price*100;
                    $data['special']                    = $special;
                    $data['min_number']                 = $min_number;
                    $data['max_number']                 = $max_number;
                    $data['unit_price']                 = $unit_price*100;
                    $data['start_price']                = $start_price*100;
                    $data['max_price']                  = $max_price*100;

                    $wheres['self_id'] = $self_id;
                    $old_info=TmsLine::where($wheres)->first();

                    if($old_info){
                        $data['update_time']=$now_time;
                        $id=TmsLine::where($wheres)->update($data);

                        $operationing->access_cause='修改线路';
                        $operationing->operation_type='update';

                    }else{
                        $self_id=generate_id('line_');
                        $data['self_id']=$self_id;
                        $data['create_user_id']=$user_info->admin_id;
                        $data['create_user_name']=$user_info->name;
                        $data['create_time']=$data['update_time']=$now_time;
                        $data['group_code']=$group_code;
                        $data['group_name']=$group_info->group_name;
                        $id=TmsLine::insert($data);
                        $delivery = TmsDeliveryCity::where('delete_flag','Y')->where('use_flag','Y')->get();
                        foreach ($delivery as $kkk  => $vvv){
                            if ($vvv->city == $data['gather_shi_name']){
                                $line = TmsLine::where('self_id',$self_id)->first();
                                $line_log = $line->attributesToArray();
                                unset($line_log['id']);
                                $line_log['self_id'] = generate_id('line_');
                                $line_log['shift_number'] = 'CT'.get_word($line->send_shi_name).get_word($line->gather_shi_name).get_word($line->send_qu_name).get_word($line->gather_qu_name).date('d',time());
                                $line_log['special'] = 1;
                                $line_log['min_number']  = $vvv->min_number;
                                $line_log['max_number']  = $vvv->max_number;
                                $line_log['unit_price']  = $vvv->unit_price;
                                $line_log['start_price'] = $vvv->start_price;
                                $line_log['max_price']   = $vvv->max_price;
                                $line_log['carriage_id'] = $vvv->carriage_group_code;//落地配承运公司
                                $line_log['carriage_group_code'] = $group_code;//平台线路关联公司
                                $line_log['group_code']  = '1234';
                                $line_log['group_name']  = '赤途平台';
                                TmsLine::insert($line_log);
                            }

                        }
                        $operationing->access_cause='新建线路';
                        $operationing->operation_type='create';

                    }

                    $operationing->table_id=$old_info?$self_id:$data['self_id'];
                    $operationing->old_info=$old_info;
                    $operationing->new_info=$data;


                    if($id){
                        $msg['code'] = 200;
                        $msg['msg'] = "添加成功";
                        return $msg;
                    }else{
                        $msg['code'] = 302;
                        $msg['msg'] = "添加失败";
                        return $msg;
                    }


                break;

                case 'combination':
                    $data['type']                       =$type;
                    $data['shift_number']               =$shift_number;
                    $data['price']                      =$price*100;
                    $data['min_money']                  =$min_money*100;
                    $data['pick_price']                 =$pick_price*100;
                    $data['send_price']                 =$send_price*100;
                    $data['pick_type']                  =$pick_type;
                    $data['send_type']                  =$send_type;
                    $data['depart_time']                =$depart_time;
                    $data['all_weight']                 =$all_weight;
                    $data['all_volume']                 =$all_volume;
                    $data['trunking']                   =$trunking;
                    $data['start_time']                 =$start_time;
                    $data['end_time']                   =$end_time;
                    $data['control']                    =$control;
                    $data['time0']                      =$time0;
                    $data['time1']                      =$time1;
                    $data['time2']                      =$time2;
                    $data['time3']                      =$time3;
                    $data['time4']                      =$time4;
                    $data['time5']                      =$time5;
                    $data['time6']                      =$time6;
                    $data['more_price']                 =$more_price;


                $list=[];
                foreach ($combination as $k => $v){
                    $where_line=[
                        ['delete_flag','=','Y'],
                        ['self_id','=',$v],
                    ];
                    $select=['self_id','send_address_id','send_contacts_id','send_name','send_tel','send_sheng','send_sheng_name','send_shi','send_shi_name','send_qu','send_qu_name',
                        'send_address','send_address_longitude','send_address_latitude','gather_address_id','gather_contacts_id','gather_name','gather_tel','gather_sheng','gather_sheng_name',
                        'gather_shi','gather_shi_name','gather_qu','gather_qu_name','gather_address','gather_address_longitude','gather_address_latitude','more_price'];
                    $line_info=TmsLine::where($where_line)->select($select)->first();
                    if($k == 0){
                        $data['send_address_id']            = $line_info->send_address_id;
//                        $data['send_contacts_id']           = $line_info->send_contacts_id;
                        $data['send_name']                  = $line_info->send_name;
                        $data['send_tel']                   = $line_info->send_tel;
                        $data['send_sheng']                 = $line_info->send_sheng;
                        $data['send_sheng_name']            = $line_info->send_sheng_name;
                        $data['send_shi']                   = $line_info->send_shi;
                        $data['send_shi_name']              = $line_info->send_shi_name;
                        $data['send_qu']                    = $line_info->send_qu;
                        $data['send_qu_name']               = $line_info->send_qu_name;
                        $data['send_address']               = $line_info->send_address;
                        $data['send_address_longitude']     = $line_info->send_address_longitude;
                        $data['send_address_latitude']      = $line_info->send_address_latitude;
                    }

                    if($k == count($combination)-1){
                        $data['gather_address_id']          = $line_info->gather_address_id;
//                        $data['gather_contacts_id']         = $line_info->gather_contacts_id;
                        $data['gather_name']                = $line_info->gather_name;
                        $data['gather_tel']                 = $line_info->gather_tel;
                        $data['gather_sheng']               = $line_info->gather_sheng;
                        $data['gather_sheng_name']          = $line_info->gather_sheng_name;
                        $data['gather_shi']                 = $line_info->gather_shi;
                        $data['gather_shi_name']            = $line_info->gather_shi_name;
                        $data['gather_qu']                  = $line_info->gather_qu;
                        $data['gather_qu_name']             = $line_info->gather_qu_name;
                        $data['gather_address']             = $line_info->gather_address;
                        $data['gather_address_longitude']   = $line_info->gather_address_longitude;
                        $data['gather_address_latitude']    = $line_info->gather_address_latitude;
                    }

                    $abc= $line_info->toArray();
                    $abc['sort']=$k+1;
                    $abc['yuan_self_id']=$abc['self_id'];
                    $list[]=$abc;
                }

                    $wheres['self_id'] = $self_id;
                    $old_info=TmsLine::where($wheres)->first();

                    if($old_info){

                        $where_line_delete=[
                            ['delete_flag','=','Y'],
                            ['line_id','=',$self_id],
                        ];

                        $line_delete['update_time']=$now_time;
                        $line_delete['delete_flag']='N';

                        TmsLineList::where($where_line_delete)->update($line_delete);

                        $data['update_time']=$now_time;
                        $id=TmsLine::where($wheres)->update($data);

                        foreach ($list as $k => $v){
                            $line_list[$k]['self_id']=generate_id('line_list_');
                            $line_list[$k]['line_id']=$self_id;
                            $line_list[$k]['create_user_id']=$user_info->admin_id;
                            $line_list[$k]['create_user_name']=$user_info->name;
                            $line_list[$k]['create_time']=$line_list[$k]['update_time']=$now_time;
                            $line_list[$k]['group_code']=$group_code;
                            $line_list[$k]['group_name']=$group_info->group_name;
                            $line_list[$k]['sort']=$v['sort'];
                            $line_list[$k]['yuan_self_id']=$v['self_id'];

                        }
                        TmsLineList::insert($line_list);

                        $operationing->access_cause='修改组合线路';
                        $operationing->operation_type='update';


                    }else{
                        $self_id=generate_id('line_');
                        $data['self_id']=$self_id;
                        $data['create_user_id']=$user_info->admin_id;
                        $data['create_user_name']=$user_info->name;
                        $data['create_time']=$data['update_time']=$now_time;
                        $data['group_code']=$group_code;
                        $data['group_name']=$group_info->group_name;

                        foreach ($list as $k => $v){
                            $line_list[$k]['self_id']=generate_id('line_list_');
                            $line_list[$k]['line_id']=$self_id;
                            $line_list[$k]['create_user_id']=$user_info->admin_id;
                            $line_list[$k]['create_user_name']=$user_info->name;
                            $line_list[$k]['create_time']=$line_list[$k]['update_time']=$now_time;
                            $line_list[$k]['group_code']=$group_code;
                            $line_list[$k]['group_name']=$group_info->group_name;
                            $line_list[$k]['sort']=$v['sort'];
                            $line_list[$k]['yuan_self_id']=$v['self_id'];
                        }

                        $id=TmsLine::insert($data);
//                        dd($line_list);
                        TmsLineList::insert($line_list);
                        $operationing->access_cause='新建组合线路';
                        $operationing->operation_type='create';

                    }

                    $operationing->table_id=$old_info?$self_id:$data['self_id'];
                    $operationing->old_info=$old_info;
                    $operationing->new_info=$data;


                    if($id){
                        $msg['code'] = 200;
                        $msg['msg'] = "添加成功";
                        return $msg;
                    }else{
                        $msg['code'] = 302;
                        $msg['msg'] = "添加失败";
                        return $msg;
                    }


                    break;
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



    /***    线路禁用/启用      /tms/line/lineUseFlag
     */
    public function lineUseFlag(Request $request){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='tms_line';
        $self_id=$request->input('self_id');
//        $self_id='line_202101061945156666954643';
        $operationing->access_cause='启用/禁用';
        $operationing->table=$table_name;
        $operationing->now_time=$now_time;
        $where['self_id']=$self_id;
        $select=['self_id','use_flag','delete_flag', 'update_time', 'group_code', 'group_name','type'];
        $selectList=['line_id','self_id','yuan_self_id'];
        $selectList2=['self_id','use_flag','delete_flag'];
        $line = TmsLine::with(['tmsLineList' => function($query)use($selectList,$selectList2){
            $query->where('delete_flag','=','Y');
            $query->select($selectList);
            $query->with(['tmsLine' => function($query)use($selectList2){
                $query->select($selectList2);
            }]);
        }])->where($where)->select($select)->first();

        $old_info = [
          'use_flag'=>$line->use_flag,
          'update_time'=>$now_time
        ];

        if($line){
            switch ($line->type){
                case 'combination':
                    $cando='Y';
                    if($line->use_flag == 'Y'){
                        $data['use_flag']='N';
                        $data['update_time']=$now_time;
                    }else{
                        /** 如果是启用这个线路，请注意，必须保证他关联的数据全部是能启用的姿态*/
                        foreach ($line->tmsLineList as $k => $v){
                            if($v->tmsLine->use_flag == 'N' || $v->tmsLine->delete_flag == 'N'){
                                $cando='N';
                            }
                        }

                        if($cando=='N'){
                            $msg['code']=301;
                            $msg['msg']="组合线路中包含不运行的线路";
                            return $msg;
                        }

                        $data['use_flag']='Y';
                        $data['update_time']=$now_time;

                    }
                    $id=TmsLine::where($where)->update($data);

                    break;
                case 'alone':
                    if($line->use_flag == 'N'){
                        $data['use_flag']='Y';
                        $data['update_time']=$now_time;
                        $id=TmsLine::where($where)->update($data);

                        $line_info = TmsLineList::where('yuan_self_id',$self_id)->pluck('line_id')->toArray();


                        $line = TmsLine::with(['tmsLineList' => function($query)use($selectList,$selectList2){
                            $query->where('delete_flag','=','Y');
                            $query->select($selectList);
                            $query->with(['tmsLine' => function($query)use($selectList2){
                                $query->select($selectList2);
                            }]);
                        }])->whereIn('self_id',$line_info)->select($select)->get();

                        $data['use_flag']='Y';
                        $data['update_time']=$now_time;
                        foreach ($line as $k => $v){
                            $cando='Y';
                            foreach ($v->tmsLineList as $kk => $vv){
//                                dump($vv->tmsLine->use_flag);
                                if($vv->tmsLine->use_flag == 'N' || $vv->tmsLine->delete_flag == 'N'){
                                    $cando='N';
                                }
                            }
                            if($cando=='Y'){
                                $where2222['self_id']=$v->self_id;
                                TmsLine::where($where2222)->update($data);
                            }
                        }
                    }else{
                        $line_info = TmsLineList::where('yuan_self_id',$self_id)->pluck('line_id')->toArray();

                        $where_line=[
                            ['delete_flag','=','Y'],
                            ['use_flag','=','Y'],
                        ];
                        $data['use_flag'] = 'N';
                        $data['update_time'] = $now_time;
                        $id=TmsLine::where($where)->update($data);
                        TmsLine::where($where_line)->whereIn('self_id',$line_info)->update($data);

                    }
                    break;
            }
            $operationing->old_info = (object)$old_info;
            $operationing->new_info = $data;
            $operationing->table_id = $self_id;
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
            $msg['code']=300;
            $msg['msg']="查询不到数据";
            return $msg;

        }
    }


    /***    线路删除     /tms/line/lineDelFlag
     */
    public function lineDelFlag(Request $request,Status $status){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='tms_line';
        $self_id=$request->input('self_id');
//        $self_id = ['line_202110101411233866568593'];
        $operationing->access_cause='删除';
        $operationing->table = $table_name;
        $operationing->table_id = json_encode($self_id,JSON_UNESCAPED_UNICODE);
        $operationing->now_time = $now_time;

//        $self_id='line_202108211358022844377973';
        $where['self_id']=$self_id;
        $where['delete_flag']='Y';
        $select=['self_id','use_flag','delete_flag', 'update_time', 'group_code', 'group_name','type'];
        $selectList=['line_id','self_id','yuan_self_id'];
        $selectList2=['self_id','use_flag','delete_flag'];
        $line = TmsLine::with(['tmsLineList' => function($query)use($selectList,$selectList2){
            $query->where('delete_flag','=','Y');
            $query->select($selectList);
            $query->with(['tmsLine' => function($query)use($selectList2){
                $query->select($selectList2);
            }]);
        }])->whereIn('self_id',$self_id)->select($select)->get();
        $old_info = [
            'use_flag'=>'Y',
            'update_time'=>$now_time
        ];

        $order_info = TmsOrder::whereIn('line_id',$self_id)->whereIn('order_status',[2,3,4,5])->select('self_id')->get()->toArray();
        if ($order_info){
            $msg['code'] = 301;
            $msg['msg']  = '该线路有未完成订单不能删除';
            return $msg;
        }
//        dd($line->toArray());

        if($line){
            foreach ($line as $key =>$value){
                switch ($value->type){
                    case 'combination':
                        $data['delete_flag']='N';
                        $data['update_time']=$now_time;
                        $id=TmsLine::whereIn('self_id',$value->self_id)->update($data);
                        break;
                    case 'alone':
                        $line_info = TmsLineList::where('yuan_self_id',$value->self_id)->pluck('line_id')->toArray();

                        $where_line=[
                            ['delete_flag','=','Y'],
                            ['use_flag','=','Y'],
                        ];
                        $data['delete_flag'] = 'N';
                        $data['update_time'] = $now_time;
                        $id=TmsLine::where('self_id',$value->self_id)->update($data);
                        TmsLine::where($where_line)->whereIn('self_id',$line_info)->update($data);
                        break;
                }
            }
            $operationing->old_info = (object)$old_info;
            $operationing->table_id = json_encode($self_id,JSON_UNESCAPED_UNICODE);
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
            $msg['code']=300;
            $msg['msg']="查询不到数据";
            return $msg;

        }
    }




    /***    拿去线路数据,用于配置多线路     /tms/line/getLine
     */
    public function  getLine(Request $request){
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $where=[
            ['delete_flag','=','Y'],
            ['use_flag','=','Y'],
            ['type','=','alone'],
        ];
        $select=['self_id','shift_number','group_name','send_shi_name','gather_shi_name'];

        switch ($group_info['group_id']){
            case 'all':
                $data['info']=TmsLine::where($where)->orderBy('create_time', 'desc')->select($select)->get();
                break;

            case 'one':
                $where[]=['group_code','=',$group_info['group_code']];
                $data['info']=TmsLine::where($where)->orderBy('create_time', 'desc')->select($select)->get();
                break;

            case 'more':
                $data['info']=TmsLine::where($where)->whereIn('group_code',$group_info['group_code'])->orderBy('create_time', 'desc')->select($select)->get();
                break;
        }

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;

        //dd($msg);
        return $msg;
    }

    public function get_line(Request $request){

        $group_code     = $request->input('group_code');//接收中间件产生的参数
//        $group_code = 'group_20210113105442098808812';
        $now_time           = date('Y-m-d H:i:s', time());
        $where=[
            ['delete_flag','=','Y'],
            ['use_flag','=','Y'],
            ['group_code','=',$group_code],
            ['start_time','<=',$now_time],
            ['end_time','>',$now_time]
        ];
        $select=['self_id','type','shift_number','group_name','send_shi_name','gather_shi_name','time0','time1','time2','time3','time4','time5','time6','send_type','pick_type',
            'send_sheng_name','send_qu_name','gather_sheng_name','gather_qu_name','pick_price','send_price','control','send_address','gather_address'];
        $data['info']=TmsLine::where($where)->orderBy('create_time', 'desc')->select($select)->get();

        $tms_pick_type              =array_column(config('tms.tms_pick_type'),'name','key');
        $tms_send_type              =array_column(config('tms.tms_send_type'),'name','key');
        foreach ($data['info'] as $key => $value){
            $week = [];
            $week1 = '';
            if($value['time1']=='Y'){
                $week[]='周一';
            }
            if($value['time2']=='Y'){
                $week[]='周二';
            }
            if($value['time3']=='Y'){
                $week[]='周三';
            }
            if($value['time4']=='Y'){
                $week[]='周四';
            }
            if($value['time5']=='Y'){
                $week[]='周五';
            }
            if($value['time6']=='Y'){
                $week[]='周六';
            }
            if($value['time0']=='Y'){
                $week[]='周日';
            }
            if($week){
                $week1=implode('*',$week);
            }

//            $data['info'][$key]['picktype'] =$tms_pick_type[$value['pick_type']];
//            $data['info'][$key]['sendtype'] =$tms_send_type[$value['send_type']];
            $data['info'][$key]['week'] =$week1;
        }
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;

    }

    /***    线路导入     /tms/line/import
     */
    public function import(Request $request,Tms $tms){
        $table_name         ='tms_line';
        $now_time           = date('Y-m-d H:i:s', time());

        $operationing       = $request->get('operationing');//接收中间件产生的参数
        $operationing->access_cause     ='导入创建线路';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='import';

        $user_info          = $request->get('user_info');//接收中间件产生的参数
//        dd($user_info);
        /** 接收数据*/
        $input              =$request->all();
        $group_code          =$request->input('group_code');
        $importurl          =$request->input('importurl');
        $file_id            =$request->input('file_id');

        /****虚拟数据
        $importurl = $input['importurl'] ="uploads/2021-01-04/线路导入模板.xlsx";
        $group_code = $input['group_code']='1234';
         ***/
        $rules = [
            'group_code' => 'required',
            'importurl' => 'required',
        ];
        $message = [
            'group_code.required' => '请选择公司',
            'importurl.required' => '请上传文件',
        ];
        $validator = Validator::make($input, $rules, $message);
        if ($validator->passes()) {

            /**发起二次效验，1效验文件是不是存在， 2效验文件中是不是有数据 3,本身数据是不是重复！！！* */
            if (!file_exists($importurl)) {
                $msg['code'] = 301;
                $msg['msg'] = '文件不存在';
                return $msg;
            }

            $res = Excel::toArray((new Import),$importurl);

            $info_check=[];
            if(array_key_exists('0', $res)){
                $info_check=$res[0];
                if(count($info_check) >1000){
                    $msg['code'] = 305;
                    $msg['msg'] = '最大导入999条数据';
                    return $msg;
                }
            }

//            dump($info_check);
            /**  定义一个数组，需要的数据和必须填写的项目
             键 是EXECL顶部文字，
             * 第一个位置是不是必填项目    Y为必填，N为不必须，
             * 第二个位置是不是允许重复，  Y为允许重复，N为不允许重复
             * 第三个位置为长度判断
             * 第四个位置为数据库的对应字段
             */
            $shuzu=[
                '班次号' =>['Y','N','64','shift_number'],
                '价格（元）' =>['Y','Y','30','price'],
                '最低干线费（元）' =>['N','Y','64','min_money'],
                '提货方式' =>['Y','Y','255','pick_type'],
                '提货费（元）' =>['N','Y','64','pick_price'],
                '配送方式' =>['Y','Y','255','send_type'],
                '配送费（元）' =>['N','Y','255','send_price'],
                '发车时间' =>['Y','Y','50','depart_time'],
                '总重量（kg）' =>['N','Y','50','all_weight'],
                '总体积(m³)' =>['N','Y','50','all_volume'],
                '干线周期' =>['Y','Y','200','week'],
                '时效(天)' =>['Y','Y','10','trunking'],
                '温控' =>['Y','Y','30','control'],
                '开始使用时间' =>['N','Y','20','start_time'],
                '结束使用时间' =>['N','Y','20','end_time'],
                '发货人姓名' =>['Y','Y','20','send_name'],
                '发货人电话' =>['Y','Y','50','send_tel'],
                '省（发货）' =>['Y','Y','50','send_sheng_name'],
                '市（发货）' =>['Y','Y','50','send_shi_name'],
                '区（发货）' =>['Y','Y','50','send_qu_name'],
                '详细地址（发货）' =>['Y','Y','255','send_address'],
                '收货人姓名' =>['Y','Y','20','gather_name'],
                '收货人电话' =>['Y','Y','50','gather_tel'],
                '省（收货）' =>['Y','Y','50','gather_sheng_name'],
                '市（收货）' =>['Y','Y','50','gather_shi_name'],
                '区（收货）' =>['Y','Y','50','gather_qu_name'],
                '详细地址（收货）' =>['Y','Y','255','gather_address'],
                ];
            $ret=arr_check($shuzu,$info_check);

//            dump($ret);
            if($ret['cando'] == 'N'){
                $msg['code'] = 304;
                $msg['msg'] = $ret['msg'];
                return $msg;
            }


            $where_check=[
                ['delete_flag','=','Y'],
                ['self_id','=',$group_code],
            ];

            $info= SystemGroup::where($where_check)->select('self_id','group_code','group_name')->first();
            if(empty($info)){
                $msg['code'] = 305;
                $msg['msg'] = '所属公司不存在';
                return $msg;
            }




            $info_wait=$ret['new_array'];
            /** 二次效验结束**/

            $datalist=[];       //初始化数组为空
            $cando='Y';         //错误数据的标记
            $strs='';           //错误提示的信息拼接  当有错误信息的时候，将$cando设定为N，就是不允许执行数据库操作
            $abcd=0;            //初始化为0     当有错误则加1，页面显示的错误条数不能超过$errorNum 防止页面显示不全1
            $errorNum=50;       //控制错误数据的条数
            $a=2;
            $tms_control_type           =array_column(config('tms.tms_control_type'),'name','key');
            $tms_pick_type              =array_column(config('tms.tms_pick_type'),'name','key');
            $tms_send_type              =array_column(config('tms.tms_send_type'),'name','key');
            $time_week=['日','一','二','三','四','五','六'];
//            dd($info_wait);
            /** 现在开始处理$car***/
            foreach($info_wait as $k => $v){
//                dump($v);
                //班次号不能重复
                $where['delete_flag'] = 'Y';
                $where['shift_number']=$v['shift_number'];
                $line_number = TmsLine::where($where)->value('shift_number');
                if ($line_number){
                    if($abcd<$errorNum){
                        $strs .= '数据中的第'.$a."行班次号已存在".'</br>';
                        $cando='N';
                        $abcd++;
                    }
                }
                //提货方式和配送方式不能为空，如果为是，必须填写提配价格
                $pick_type = array_search($v['pick_type'],$tms_pick_type) ?? null;
                if (empty($pick_type)){
                    if($abcd<$errorNum){
                        $strs .= '数据中的第'.$a."提货方式请填写上门提货/自送到点".'</br>';
                        $cando='N';
                        $abcd++;
                    }
                }
                $send_type = array_search($v['send_type'],$tms_send_type) ?? null;
                if (empty($send_type)){
                    if($abcd<$errorNum){
                        $strs .= '数据中的第'.$a."提货方式请填写送货上门/到点自提".'</br>';
                        $cando='N';
                        $abcd++;
                    }
                }
                foreach ($time_week as $kk => $vv){
                    $domain = strpos($v['week'], $vv);
                    if($domain !== false){
                        $week_time['time'.$kk]='Y';
                    }else{
                        $week_time['time'.$kk]='N';
                    }
                }
                $control = array_search($v['control'],$tms_control_type) ?? null;
                if (empty($control)) {
                    if($abcd<$errorNum){
                        $strs .= '数据中的第'.$a."请填写正确的温度类型".'</br>';
                        $cando='N';
                        $abcd++;
                    }
                }
                if ($v['start_time']){
                    $start_time = gmdate('Y-m-d H:i:s', ($v['start_time'] - 25569) * 3600 * 24);
                }else{
                    $start_time = '2018-11-30 00:00:00';
                }

                if ($v['end_time']){
                    $end_time = gmdate('Y-m-d 23:59:59', ($v['end_time'] - 25569) * 3600 * 24);
                }else{
                    $end_time = '2099-12-31 00:00:00';
                }
                $depart_time = gmdate('H:i:s', ($v['depart_time'] - 25569) * 3600 * 24);

                $send_result =  $tms->get_district($v['send_sheng_name'],$v['send_shi_name'],$v['send_qu_name'],$v['send_address'],$v['send_name'],$v['send_tel'],$info,'发货',$user_info,$now_time,$abcd,$a);

                if ($send_result->code == 306){
                    if($abcd<$errorNum){
                        $strs  .= $send_result->msg;
                        $cando='N';
                        $abcd++;
                    }
                }

                $gather_result = $tms->get_district($v['gather_sheng_name'],$v['gather_shi_name'],$v['gather_qu_name'],$v['gather_address'],$v['gather_name'],$v['gather_tel'],$info,'收货',$user_info,$now_time,$abcd,$a);
                if ($gather_result->code == 306){
                    if($abcd<$errorNum){
                        $strs  .= $gather_result->msg;
                        $cando='N';
                        $abcd++;
                    }
                }
//                $send_contacts = $tms->contacts('',$v['send_name'],$v['send_tel'],$info,$user_info,$now_time);
//                $gather_contacts = $tms->contacts('',$v['gather_name'],$v['gather_tel'],$info,$user_info,$now_time);

                $list=[];
                if($cando =='Y'){
                    $list['self_id']            =generate_id('line_');
                    $list['shift_number']       = $v['shift_number'];
                    $list['type']               = 'alone';
                    $list['price']              = $v['price']*100;
                    $list['min_money']          = $v['min_money']*100;
                    $list['pick_type']          = $pick_type;
                    $list['pick_price']         = $v['pick_price']*100;
                    $list['send_type']          = $send_type;
                    $list['send_price']         = $v['send_price']*100;
                    $list['depart_time']        = $depart_time;
                    $list['all_weight']         = $v['all_weight'];
                    $list['all_volume']         = $v['all_volume'];
                    $list['trunking']         = $v['trunking'];
                    $list['time0']           = $week_time['time0'];
                    $list['time1']           = $week_time['time1'];
                    $list['time2']           = $week_time['time2'];
                    $list['time3']           = $week_time['time3'];
                    $list['time4']           = $week_time['time4'];
                    $list['time5']           = $week_time['time5'];
                    $list['time6']           = $week_time['time6'];
                    $list['control']            = $control;
                    $list['start_time']         = $start_time;
                    $list['end_time']           = $end_time;
//                    $list['send_contacts_id']   = $send_contacts->self_id;
                    $list['send_name']          = $v['send_name'];
                    $list['send_tel']           = $v['send_tel'];
                    $list['send_address_id']    = $send_result->self_id;
                    $list['send_sheng']    = $send_result->sheng;
                    $list['send_shi']      = $send_result->shi;
                    $list['send_qu']       = $send_result->qu;
                    $list['send_sheng_name']    = $v['send_sheng_name'];
                    $list['send_shi_name']      = $v['send_shi_name'];
                    $list['send_qu_name']       = $v['send_qu_name'];
                    $list['send_address']       = $v['send_address'];
//                    $list['send_address_longitude']       = $send_result->longitude;
//                    $list['send_address_latitude']       = $send_result->dimensionality;
//                    $list['gather_contacts_id']        = $gather_contacts->self_id;
                    $list['gather_name']        = $v['gather_name'];
                    $list['gather_tel']         = $v['gather_tel'];
                    $list['gather_address_id']        = $gather_result->self_id;
                    $list['gather_sheng']  = $gather_result->sheng;
                    $list['gather_shi']    = $gather_result->shi;
                    $list['gather_qu']     = $gather_result->qu;
                    $list['gather_sheng_name']  = $v['gather_sheng_name'];
                    $list['gather_shi_name']    = $v['gather_shi_name'];
                    $list['gather_qu_name']     = $v['gather_qu_name'];
                    $list['gather_address']     = $v['gather_address'];
//                    $list['gather_address_longitude']     = $gather_result->longitude;
//                    $list['gather_address_latitude']     = $gather_result->dimensionality;
                    $list['group_code']         = $info->group_code;
                    $list['group_name']         = $info->group_name;
                    $list['create_user_id']     = $user_info->admin_id;
                    $list['create_user_name']   = $user_info->name;
                    $list['create_time']        =$list['update_time']=$now_time;
                    $list['file_id']            =$file_id;
                    $datalist[]=$list;

                    /***  生成落地配线路  **/
                    $delivery = TmsDeliveryCity::where('delete_flag','Y')->where('use_flag','Y')->get();
                    $line_log_info = [];
                    foreach ($delivery as $kkk  => $vvv){
                        if ($vvv->city == $list['gather_shi_name']){
//                            $line = TmsLine::where('self_id',$self_id)->first();

                            $line_log = $list;
                            unset($line_log['id']);
                            $line_log['self_id'] = generate_id('line_');
                            $line_log['shift_number'] = 'CT'.get_word($list['send_shi_name']).get_word($list['gather_shi_name']).get_word($list['send_qu_name']).get_word($list['gather_qu_name']).date('d',time());
                            $line_log['special'] = 1;
                            $line_log['min_number']  = $vvv->min_number;
                            $line_log['max_number']  = $vvv->max_number;
                            $line_log['unit_price']  = $vvv->unit_price;
                            $line_log['start_price'] = $vvv->start_price;
                            $line_log['max_price']   = $vvv->max_price;
                            $line_log['carriage_id'] = $vvv->carriage_group_code;//落地配承运公司
                            $line_log['carriage_group_code'] = $group_code;//平台线路关联公司
                            $line_log['group_code']  = '1234';
                            $line_log['group_name']  = '赤途平台';
                            $line_log_info[] = $line_log;
                        }

                    }
                }
                $a++;
            }

//            dump($operationing);

            if($cando == 'N'){
                $msg['code'] = 306;
                $msg['msg'] = $strs;
                return $msg;
            }
            $count=count($datalist);
            $id= TmsLine::insert($datalist);
            TmsLine::insert($line_log_info);

            $operationing->new_info=$datalist;

            if($id){
                $msg['code']=200;
                /** 告诉用户，你一共导入了多少条数据，其中比如插入了多少条，修改了多少条！！！*/
                $msg['msg']='操作成功，您一共导入'.$count.'条数据';

                return $msg;
            }else{
                $msg['code']=307;
                $msg['msg']='操作失败';
                return $msg;
            }
        }else{
            $erro = $validator->errors()->all();
            $msg['msg'] = null;
            foreach ($erro as $k => $v) {
                $kk=$k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            $msg['code'] = 300;
            return $msg;
        }


    }

    /***    线路详情     /tms/line/details
     */
    public function  details(Request $request,Details $details){
        $tms_control_type           =array_column(config('tms.tms_control_type'),'name','key');
        $tms_pick_type              =array_column(config('tms.tms_pick_type'),'name','key');
        $tms_send_type              =array_column(config('tms.tms_send_type'),'name','key');
        $self_id=$request->input('self_id');
        $table_name='tms_line';
        $select=['self_id','shift_number','type','price','min_money','pick_price','send_price','pick_type','send_type','depart_time','all_weight','all_volume','weight_price'
        ,'trunking','start_time','end_time','control','gather_name','gather_tel','send_name','send_tel','send_sheng_name','send_shi_name','send_qu_name','send_address','gather_shi_name',
            'gather_qu_name','gather_sheng_name','gather_address'];
//        $self_id='line_202101041058288542867962';
          $where['self_id'] = $self_id;
          $where['delete_flag'] = 'Y';
          $selectList = ['line_id','self_id','yuan_self_id'];
          $selectList2 = ['self_id','shift_number','type','price','min_money','pick_price','send_price','pick_type','send_type','depart_time','all_weight','all_volume','weight_price'
              ,'trunking','start_time','end_time','control','gather_name','gather_tel','send_name','send_tel','send_sheng_name','send_shi_name','send_qu_name','send_address','gather_shi_name',
              'gather_qu_name','gather_sheng_name','gather_address'];
          $info = TmsLine::with(['tmsLineList'=>function($query)use($selectList,$selectList2){
              $query->where('delete_flag','=','Y');
              $query->select($selectList);
              $query->with(['tmsLine'=>function($query)use($selectList2){
                  $query->select($selectList2);
              }]);
          }])->where($where)->select($select)->first();
//        dd($info);
        if($info){

            /** 如果需要对数据进行处理，请自行在下面对 $$info 进行处理工作*/
            $info->price=$info->price/100;
            $info->min_money=$info->min_money/100;
            $info->pick_price=$info->pick_price/100;
            $info->send_price=$info->send_price/100;

            $info->control = $tms_control_type[$info->control] ?? null;
            $info->pick_type = $tms_pick_type[$info->pick_type] ?? null;
            $info->send_type = $tms_send_type[$info->send_type] ?? null;
            if($info->start_time=='2018-11-30 00:00:00' && $info->end_time=='2099-12-31 00:00:00'){
                $info->time_show='长期有效';
            }else{
                $info->time_show=$info->start_time.'～'.$info->end_time;
            }
            $data['info']=$info;
            $log_flag='Y';
            $data['log_flag']=$log_flag;
            $log_num='10';
            $data['log_num']=$log_num;
            $data['log_data']=null;

            if($log_flag =='Y'){
                $data['log_data']=$details->change($self_id,$log_num);
            }


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

    /**
     * 线路导出
     */
    public  function excel(Request $request,File $file){
        $user_info  = $request->get('user_info');//接收中间件产生的参数
        $now_time   =date('Y-m-d H:i:s',time());
        $input      =$request->all();
        /** 接收数据*/
        $group_code     =$request->input('group_code');
//        $group_code  =$input['group_code']   ='1234';
//        dump($group_code);
        $rules=[
            'group_code'=>'required',
        ];
        $message=[
            'group_code.required'=>'必须选择公司',
        ];
        $validator=Validator::make($input,$rules,$message);
        $tms_pick_type              =array_column(config('tms.tms_pick_type'),'name','key');
        $tms_send_type              =array_column(config('tms.tms_send_type'),'name','key');
        $tms_control_type = array_column(config('tms.tms_control_type'),'name','key');
        if($validator->passes()){
            /** 下面开始执行导出逻辑**/
            $group_name     =SystemGroup::where('group_code','=',$group_code)->value('group_name');
            //查询条件
            $search=[
                ['type'=>'=','name'=>'group_code','value'=>$group_code],
                ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ];
            $where=get_list_where($search);

            $select=['self_id','shift_number','type','price','min_money','pick_price','send_price','pick_type','send_type','depart_time','all_weight','all_volume','weight_price'
                ,'trunking','start_time','end_time','control','send_name','send_tel','send_sheng_name','send_shi_name','send_qu_name','send_address','gather_shi_name',
                'gather_qu_name','gather_sheng_name','gather_address'];
            $info=TmsLine::where($where)->orderBy('create_time', 'desc')->select($select)->get();
//            dd($info->toArray());
            if($info){
                //设置表头
                $row = [[
                    "id"=>'ID',
                    "shift_number"=>'班次号',
                    "send_shi_name"=>'起始地',
                    "gather_shi_name"=>'目的地',
                    "type"=>'线路类型',
                    "price"=>'单公斤价格',
                    "pick_type"=>'提货服务',
                    "send_type"=>'配送服务',
                    "pick_price"=>'提货费',
                    "send_price"=>'配送费',
                    "control" =>'温度',
                    "trunking"=>'时效',
                    "send_address"=>'起始点地址',
                    "gather_address"=>'目的地地址',


                ]];

                /** 现在根据查询到的数据去做一个导出的数据**/
                $data_execl=[];

                foreach ($info as $k=>$v){
//                    dump($v['control'],$v->control);
//                    dd($v);
                    $list=[];

                    $list['id']=($k+1);
                    $list['shift_number']=$v->shift_number;
                    $list['send_shi_name']=$v->send_shi_name;
                    $list['gather_shi_name']=$v->gather_shi_name;
                    $control = '';
                    if (!empty($tms_control_type[$v['control']])) {
                        $control = $tms_control_type[$v['control']];
                    }
                     if($v->type == 'alone'){
                         $type = '单线路';
                     }else{
                         $type = '组合线路';
                     }
                    $pick_type = $tms_pick_type[$v['pick_type']];
                    $send_type = $tms_send_type[$v['send_type']];
                    $list['type']    =$type;
                    $list['price']    =$v->price/100;
                    $list['pick_type']    =$pick_type;
                    $list['send_type']    =$send_type;
                    $list['pick_price']    =$v->pick_price/100;
                    $list['send_price']    =$v->send_price/100;
                    $list['control']      =$control;
                    $list['trunking']      =$v->trunking;
                    $list['send_address']     =$v->send_address;
                    $list['gather_address']     =$v->gather_address;
                    $data_execl[]=$list;
                }
                /** 调用EXECL导出公用方法，将数据抛出来***/
                $browse_type=$request->path();
                $msg=$file->export($data_execl,$row,$group_code,$group_name,$browse_type,$user_info,$where,$now_time);

                //dd($msg);
                return $msg;

            }else{
                $msg['code']=301;
                $msg['msg']="没有数据可以导出";
                return $msg;
            }
        }else{
            $erro=$validator->errors()->all();
            $msg['msg']=null;
            foreach ($erro as $k=>$v) {
                $kk=$k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            $msg['code']=300;
            return $msg;
        }
    }


    /***
     *零担预估费用    /tms/line/count_price
     **/
    public function count_price(Request $request){
        //接收体积重量，是否提配，提货及配送地址,哪条线
        $input         = $request->all();
        $pick_type = $request->input('pick_type') ?? null;
        $send_type = $request->input('send_type') ?? null;
        $gather_address= $request->input('send_address') ?? null;
        $send_address = $request->input('gather_address') ?? null;
        $volume = $request->input('volume');
        $weight = $request->input('weight');
        $line_id = $request->input('line_id');

        /**虚拟数据
        //        $input['$gather_address']      = $gather_address      = json_encode([["area"=>"米东区","city"=>"乌鲁木齐市","info"=>"111","pro"=>"新疆"]],JSON_UNESCAPED_UNICODE);
        $input['gather_address']      = $gather_address      = '[{"area":"米东区","city":"乌鲁木齐市","info":"111","pro":"新疆"},{"area":"嘉定区","city":"上海市","info":"111","pro":"上海市"}]';
        //        $input['$send_address']       = $send_address       =  json_encode([["area"=>"城关区","city"=>"拉萨市","info"=>"222","pro"=>"西藏"]],JSON_UNESCAPED_UNICODE);
        $input['send_address']       = $send_address       =  '[{"area":"城关区","city":"拉萨市","info":"222","pro":"西藏"},{"area":"嘉定区","city":"上海市","info":"111","pro":"上海市"}]';
        $input['pick_type'] = $pick_type = 'Y';  // 'Y' 提货  'N' 自送
        $input['send_type'] = $send_type = 'Y';  // 'Y' 配送  'N' 自提
        $input['volume'] = $volume = 2;
        $input['weight'] = $weight = 1000;
        $input['line_id'] = $line_id = 'line_20210401181248762631785';
         * **/
        $rules = [
            'line_id'=>'required',
            'volume'=>'required',
            'weight'=>'required',
        ];
        $message = [
            'line_id.required'=>'请选择线路',
            'volume.required'=>'请填写货物体积',
            'weight.required'=>'请填写货物重量',
        ];
        $startstr=[];
        if ($gather_address){
            $pick_info=json_decode($gather_address,true);
            foreach ($pick_info as $k=> $v){
                $startstr[]=$v['area'].$v['city'].$v['info'].$v['pro'];
            }
            $startstr = count(array_unique($startstr));
        }
        $endstr = [];
        if ($send_address){
            $send_info=json_decode($send_address,true);
            foreach ($send_info as $k=> $v){
                $endstr[]=$v['area'].$v['city'].$v['info'].$v['pro'];
            }
            $endstr = count(array_unique($endstr));
        }
        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $line = TmsLine::where('self_id','=',$line_id)->select(['price','min_money','pick_price','send_price','all_weight','all_volume','more_price'])->first();
            /***判断根据重量计算还是根据体积计算 重泡比为1:2.5**/
            $weight_v = $volume*1000/$weight;
            if ($weight_v <2.5){
                $lineprice = $line->price * $weight/100;
            }else{
                $volume_price = $line->price * 1000/2.5/100;
                $lineprice = $volume * $volume_price;
            }
            /** 第二种方式计算干线价格
            $weight_count =  $volume*400;
            if($weight> $weight_count){
            $line_price = $weight*$line->price;
            }else{
            $line_price = $weight_count*$line->price;
            }
             **/
            if ($lineprice >$line->min_money/100){
                $lineprice = $lineprice;
            }else{
                $lineprice = $line->min_money/100;
            }
            $lineprice1 = $lineprice;

            $price_info['pick_price'] = 0; //提货费
            $price_info['send_price'] = 0; //配送费
            $more_price = 0; //多点提配费
            //提货费
            if($pick_type == 'Y'){
                $lineprice = $lineprice + $line->pick_price/100;
                $price_info['pick_price'] = $line->pick_price/100; //提货费
            }
            //配送费
            if ($send_type == 'Y'){
                $lineprice = $lineprice + $endstr*($line->send_price/100);
                $price_info['send_price'] = $endstr*($line->send_price/100); //配送费
            }
            if ($startstr - 1 >0){
                $more_price = ($startstr - 1)*$line->more_price;
                $lineprice = $lineprice+$more_price;
            }
            $lineprice = round($lineprice,2);
            $price_info['line_price'] = round($lineprice1,2); //干线费 price
            $price_info['more_price'] = $more_price;


            $msg['code']  = 200;
            $msg['msg']   = "数据拉取成功";
            $msg['data']  = $lineprice;
            $msg['price_info'] = $price_info;
            return $msg;
        }else{
            //前端用户验证没有通过
            $erro = $validator->errors()->all();
            $msg['code'] = 300;
            $msg['msg']  = null;
            foreach ($erro as $k => $v) {
                $kk = $k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            return $msg;
        }
    }

}
?>
