<?php
namespace App\Http\Api\Tms;
use App\Http\Controllers\DetailsController as Details;
use App\Http\Controllers\StatusController as Status;
use App\Models\Group\SystemGroup;
use App\Models\Tms\TmsDiscuss;
use App\Models\Tms\TmsLineList;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TmsController as Tms;
use App\Models\Tms\TmsLine;
use Illuminate\Support\Facades\Validator;

class LineController extends Controller{
    /**
     * 首页模板接口      /api/line/linePage
     */
    public function linePage(Request $request){
        $now_time     = date('Y-m-d H:i:s',time());
        $user_info    = $request->get('user_info');
        $listrows	  = config('page.listrows')[0];//每次加载的数量
        $first		  = $request->input('page')??1;
        $firstrow	  = ($first-1)*$listrows;

        $startcity    = $request->input('startcity')??'';//发车城市
        $endcity      = $request->input('endcity')??'';//目的城市
        $time         = $request->input('time');//发车日期
        $cold         = $request->input('cold');//温度
        $min_money    = $request->input('min_money');//最低干线费
        $depart_time  = $request->input('depart_time');//发车时间排序
        $start_city   = $request->input('start_city');
        if (empty($time)){
            $time = date('Y-m-d',time());
        }
//        $time =  '2021-05-29';
        $week = date('w',strtotime($time));

        $select = ['self_id','shift_number','type','price','use_flag','group_name','pick_price','send_price','pick_type','send_type','trunking','control',
            'send_sheng_name','send_shi_name','send_qu_name','gather_sheng_name','gather_shi_name','gather_qu_name','special',
            'start_time','end_time','time0','time1','time2','time3','time4','time5','time6','depart_time','update_time','min_money'];
        $selectList = ['line_id','yuan_self_id'];

        $where = [
            ['use_flag','=','Y'],
            ['delete_flag','=','Y'],
        ];
        if ($startcity) {
            $where[] = ['send_shi_name','like','%'.$startcity.'%'];
        }
        if ($endcity) {
            $where[] = ['gather_shi_name','like','%'.$endcity.'%'];
        }
        if ($cold){
            $where[] = ['control','=',$cold];
        }
        $where[] = ['time'.$week,'=','Y'];
        $where[] = ['start_time','<',$time];
        $where[] = ['end_time','>',$time];


        $data['info'] = TmsLine::with(['tmsLineList' => function($query)use($selectList,$select) {
            $query->where('delete_flag','=','Y');
            $query->select($selectList);
            $query->orderBy('sort', 'asc');
            $query->with(['tmsLine' => function($query)use($select){
                $query->select($select);
            }]);
        }])->where($where)->orderBy('special','asc');

            if ($min_money){
                $data['info'] = $data['info']->orderBy('send_price','asc');
            }
            if ($depart_time){
                $data['info'] = $data['info']->orderBy('depart_time','asc');
            }

        $data['info']   =$data['info'] ->select($select)
            ->where('depart_time','<=',date('H:i',time()+7200))
            ->orderBy('create_time','asc')
            ->offset($firstrow)
            ->limit($listrows)
            ->get();

        $tms_pick_type    = array_column(config('tms.tms_pick_type'),'name','key');
        $tms_send_type    = array_column(config('tms.tms_send_type'),'name','key');
        $tms_control_type = array_column(config('tms.tms_control_type'),'name','key');
        $tms_line_type    = array_column(config('tms.tms_line_type'),'name','key');
        $line_info = [];
        foreach ($data['info'] as $k=>$v) {

            $v->price      = number_format($v->price/100,2);
            $v->pick_price = number_format($v->pick_price/100);
            $v->send_price = number_format($v->send_price/100);
            $v->min_money  = number_format($v->min_money/100);
            $v->pick_type  = $tms_pick_type[$v->pick_type] ?? null;
            $v->send_type  = $tms_send_type[$v->send_type] ?? null;
            $v->control    =  $tms_control_type[$v->control] ?? null;
            $v->type       = $tms_line_type[$v->type] ?? null;
            $v->detime       = date('m-d H:i',strtotime($time.' '.$v->depart_time));
            $v->detime_show       = date('Y-m-d H:i',strtotime($time.' '.$v->depart_time));
            $v->discount_show = '';
            if ($v->special == 1){
                $v->discount_show = img_for('2020-07/hui.png','no_json');
            }
            $v->trunking_show = $v->trunking.'天';
            $v->price_show = '元/公斤';
            $v->startime_show = '发车 '.$v->detime;
            $v->sendprice_show = '配送费'.$v->send_price.'元';
//            if(strtotime(date('Y-m-d H:i',time())) <= (strtotime($time.' '.$v->depart_time)-7200)){
//                $line_info[] = $v;
//            }
            $v->discuss_show      = 'N';
            $count = TmsDiscuss::where('line_id',$v->self_id)->where('type','line')->count();
            if ($count>0){
                $v->discuss_show  = 'Y';
            }
//            dump(strtotime(date('Y-m-d H:i:s',time())));
//            dump(strtotime($time.' '.$v->depart_time));

        }

//        dd($line_info);
//        $data['info'] = array_slice($line_info,$firstrow,$listrows);
        $msg['code'] = 200;
        $msg['msg']  = '数据拉取成功！';
        $msg['data'] = $data;
        return $msg;
    }

    /**
     *用户端零担线路列表 /api/line/lineList
     */
    public function lineList(Request $request){
        $now_time     = date('Y-m-d H:i:s',time());
        $user_info    = $request->get('user_info');
        $listrows	  = config('page.listrows')[0];//每次加载的数量
        $first		  = $request->input('page')??1;
        $firstrow	  = ($first-1)*$listrows;

        $startcity    = $request->input('startcity')??'';//发车城市
        $endcity      = $request->input('endcity')??'';//目的城市
        $time         = $request->input('time');//发车日期
//        $time     = '2021-05-21';
        if (empty($time)){
            $time = date('Y-m-d',time());
        }
        $select = ['self_id','shift_number','type','price','use_flag','group_name',
            'pick_price','send_price','pick_type','send_type','trunking','control',
            'send_sheng_name','send_shi_name','send_qu_name','gather_sheng_name','gather_shi_name','gather_qu_name',
            'start_time','end_time','time0','time1','time2','time3','time4','time5','time6','depart_time','update_time','min_money'];
        $selectList = ['line_id','yuan_self_id'];
        if ($user_info){
            $group_code = $user_info->group_code;
        }else{
            $msg['code'] = 401;
            $msg['msg']  = '请登录！';
            return $msg;
        }

        $where = [
            ['use_flag','=','Y'],
            ['delete_flag','=','Y'],
            ['group_code','=',$group_code]
        ];
        if ($startcity) {
            $where[] = ['send_shi_name','=',$startcity];
        }
        if ($endcity) {
            $where[] = ['gather_shi_name','=',$endcity];
        }
        if ($time){
            $where[] = ['start_time','<',$time];
            $where[] = ['end_time','>',$time];
        }



        $data['info'] = TmsLine::with(['tmsLineList' => function($query)use($selectList,$select) {
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

        foreach ($data['info'] as $k=>$v) {

            $v->price = number_format($v->price / 100, 2);
            $v->pick_price = number_format($v->pick_price / 100, 2);
            $v->send_price = number_format($v->send_price / 100, 2);
            $v->min_money = number_format($v->min_money / 100);
            $v->pick_type = $tms_pick_type[$v->pick_type] ?? null;
            $v->send_type = $tms_send_type[$v->send_type] ?? null;
            $v->control = $tms_control_type[$v->control] ?? null;
            $v->type = $tms_line_type[$v->type] ?? null;
            $v->detime = date('m-d H:i', strtotime($time .' '. $v->depart_time));
            $v->detime_show       = date('Y-m-d H:i',strtotime($time.' '.$v->depart_time));
            $v->discuss_show      = 'N';
            $count = TmsDiscuss::where('line_id',$v->self_id)->where('type','line')->count();
            if ($count>0){
                $v->discuss_show  = 'Y';
            }

        }
        $msg['code'] = 200;
        $msg['msg']  = '数据拉取成功！';
        $msg['data'] = $data;
        return $msg;
    }

	/**
     * 线路详情     /api/line/details
     */
    public function details(Request $request,Details $details){
        $self_id    = $request->input('self_id');//线路self_id
        $table_name = 'tms_line';
        $select = ['self_id','shift_number','type','price','min_money','pick_price','send_price','pick_type','send_type','depart_time','all_weight','all_volume','weight_price','group_code'
            ,'trunking','start_time','end_time','control','send_name','send_tel','send_sheng_name','send_shi_name','send_qu_name','send_address','gather_shi_name','gather_name','gather_tel',
            'gather_qu_name','gather_sheng_name','gather_address','time0','time1','time2','time3','time4','time5','time6','gather_sheng','gather_shi','gather_qu','send_sheng','send_shi','send_qu',
            'gather_address_id','send_address_id'];
        // $self_id = 'line_202101111801545837767209';
        $where['self_id'] = $self_id;
        $where['delete_flag'] = 'Y';
        $selectList = ['line_id','self_id','yuan_self_id'];
        $selectList2 = ['self_id','use_flag','delete_flag'];
        $info = TmsLine::with(['tmsLineList'=>function($query)use($selectList,$selectList2){
            $query->where('delete_flag','=','Y');
            $query->select($selectList);
            $query->with(['tmsLine'=>function($query)use($selectList2){
                $query->select($selectList2);
            }]);
        }])->where($where)->select($select)->first();
        if($info) {
            $pick_type    = config('tms.tms_pick_type');
            $send_type    = config('tms.tms_send_type');
            $tms_pick_type    = array_column(config('tms.tms_pick_type'),'name','key');
            $tms_send_type    = array_column(config('tms.tms_send_type'),'name','key');
            $tms_control_type = array_column(config('tms.tms_control_type'),'name','key');
            $tms_line_type    = array_column(config('tms.tms_line_type'),'name','key');
            $tms_temperture_control             =array_column(config('tms.tms_temperture_control'),'value','key');
            /** 如果需要对数据进行处理，请自行在下面对 $$info 进行处理工作*/
            $info->price      = number_format($info->price/100,2);
            $info->min_money  = number_format($info->min_money/100);
            $info->pick_price = number_format($info->pick_price/100);
            $info->send_price = number_format($info->send_price/100);

            $info->picktype  = $tms_pick_type[$info->pick_type] ?? null;
            $info->sendtype  = $tms_send_type[$info->send_type] ?? null;
            $info->temperture    = $tms_control_type[$info->control] ?? null;
            $info->order_type       = $tms_line_type[$info->type] ?? null;
            $info->control_show = $tms_temperture_control[$info->control] ?? null;
            $info->shiftnumber_show = '班次号 '.$info->shift_number;
            $info->price_show = '单价'.$info->price.'元/公斤';
            $info->startime_show = '发车时间'.$info->depart_time;
            $info->lineprice_show = '干线最低收费'.$info->min_money.'元';
            $info->background_color = '#0088F4';
            $info->text_color = '#FFFFFF';
            $info->pick_type_show = $pick_type;
            $info->send_type_show = $send_type;
            $data = [];
            $data['info'] = $info;
            $msg['code']  = 200;
            $msg['msg']   = "数据拉取成功";
            $msg['data']  = $data;
            return $msg;
        } else {
            $msg['code']  = 300;
            $msg['msg']   = "没有查询到数据";
            return $msg;
        }

    }


    /***
     *零担预估费用    /api/line/count_price
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
        $number = $request->input('number');
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
        $input['line_id'] = $line_id = 'line_2021081217345645546727';
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
                $startstr[]=$v['pro'].$v['city'].$v['area'].$v['info'];
            }
            $startstr_count = count(array_unique($startstr));
        }
        $endstr = [];
        if ($send_address){
            $send_info=json_decode($send_address,true);
            foreach ($send_info as $k=> $v){
                $endstr[]=$v['pro'].$v['city'].$v['area'].$v['info'];
            }
            $endstr_count = count(array_unique($endstr));
        }
        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $line = TmsLine::where('self_id','=',$line_id)->select(['price','min_money','pick_price','send_price','all_weight','all_volume','more_price','special','min_number','max_number','unit_price','start_price','max_price'])->first();
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
                if ($line->special == 1){
//                    if ($number<=$line->min_number){
//                        $price_info['send_price'] = $line->start_price/100; //配送费
//                    }else if($line->max_number > $number && $number > $line->min_number){
//                        $price_info['send_price'] = $line->start_price/100 + $line->unit_price/100*($number-$line->min_number);
//                        if ($price_info['send_price'] > $line->max_price/100){
//                            $price_info['send_price'] = $line->max_price/100;
//                        }
//                    }else{
//                        $price_info['send_price'] = $line->max_price/100;
//                    }
                    $price_info['send_price'] = line_count_price($line,$number);
                    $lineprice = $lineprice + $price_info['send_price'];
                }else{
                    $lineprice = $lineprice + $endstr_count*($line->send_price/100);
                    $price_info['send_price'] = $endstr_count*($line->send_price/100); //配送费
                }
            }
            //计算落地配线路配送费

            if ($startstr_count - 1 >0){
                $more_price = ($startstr_count - 1)*$line->more_price/100;
                $lineprice = $lineprice+$more_price;
            }
            $lineprice = round($lineprice,2);
            $price_info['line_price'] = round($lineprice1,2); //干线费 price
            $price_info['more_price'] = $more_price;

            $price_info['all_price'] = $price_info['line_price'] + $price_info['pick_price'] + $price_info['send_price'] + $price_info['more_price'];
            $msg['code']  = 200;
            $msg['msg']   = "数据拉取成功";
            $msg['data']  = $price_info['all_price'];
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
