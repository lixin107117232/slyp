<?php

namespace app\admin\controller\seckill;

use app\common\model\seckill\SeckillAttrKey;
use app\common\model\seckill\SeckillAttrVal;
use app\common\model\seckill\SeckillSku;
use app\common\controller\Backend;
use app\common\model\Config;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 秒杀商品管理
 *
 * @icon fa fa-circle-o
 */
class Goods extends Backend
{

    /**
     * Goods模型对象
     * @var \app\common\model\seckill\Goods
     */
    protected $model = null;

    protected $_site=[];
    public function _initialize()
    {
        parent::_initialize();
        $site = \think\Config::get("site");
        $this->_site=$site;
        $this->model = new \app\common\model\seckill\Goods;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("specsDataList", $this->model->getSpecsDataList());
        /* $one= $this->_site["one_seckill_start_time"];
        $two= $this->_site["two_seckill_start_time"];
        $timelist[]=["id"=>1,"name"=>$one];
        $timelist[]=["id"=>2,"name"=>$two];
        $this->view->assign("timelist",$timelist);*/
    }

    public function import()
    {
        parent::import();
    }



    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                    ->with(['allgoods'])
                    ->where($where)
                    //->order($sort, $order)
                    ->order("goods.weigh desc,goods.id desc")
                    ->paginate($limit);
            foreach ($list as $row) {
                $row->visible(['id','status','starttime','price','one_specs_data','two_specs_data','specs_data','stock',
                    'sales','weigh','createtime','two_stock','two_stock']);
                $row->visible(['allgoods']);
				$row->getRelation('allgoods')->visible(['name','cover_image']);
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }
    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    $one= $this->_site["one_seckill_start_time"];
                    $two= $this->_site["two_seckill_start_time"];
                    if(time()>$one && time()<$two) {
                        //第一场抢购期间-----修改第二场库存
                        $params["two_stock"]=$params["stock"];
                        //$params["two_sales"]=$params["sales"];
                        unset($params["stock"]);
                        //unset($params["sales"]);
                    }
                   /* if($params["starttime"]<$one)$params["starttime"].=" ".$one.":00";
                    if($params["starttime"]>$one&&$params["starttime"]<$two)$params["starttime"].=" ".$one.":00";*/
                    $params["createtime"]=time();
                    $result = $this->model->allowField(true)->insertGetId($params);
                    $id = $result;
                    if ($params["specs_data"] == 1) {
                        $specs_datas = json_decode($this->request->post('specs_datas'), true);
                        $spec_title = $_POST['spec_title'];
                        // $spec_item_title = $_POST['spec_item_title'];
                        if (empty($spec_title))
                            $this->error("请完善规格信息");
                        $data_key = [];
                        foreach ($spec_title as $k => $item) {
                            $integ = [
                                "attr_name" => $item,
                                'item_id' => $id,
                            ];
                            $spec_item_title = $_POST['spec_item_title_' . $k];
                            $spec_item_id = $_POST['spec_item_id_' . $k];
                            $interg_id = SeckillAttrKey::insertGetId($integ);
                            foreach ($spec_item_title as $item_k => $item_title) {
                                $attrval_id = SeckillAttrVal::insertGetId([
                                    "attr_key_id" => $interg_id,
                                    "item_id" => $id,
                                    "attr_value" => $item_title,
                                ]);
                                $data_key[$spec_item_id[$item_k]] = $attrval_id;
                            }

                        }
                        foreach ($specs_datas['option_ids'] as $ks => $v) {
                            $attr_symbol_path = [];
                            foreach (explode('_', $v) as $dk => $v1) {
                                $attr_symbol_path[] = $data_key[$v1];
                            }
                            $stock = intval($specs_datas['option_stock'][$ks]);
                            $sku_data[] = [
                                'item_id' => $id,
                                'attr_symbol_path' => implode(',', $attr_symbol_path),
                                'stock' => $stock ? $stock : 0,
                            ];
                            /*if((float)date('H:i',time())>(float)$one &&(float)date('H:i',time())<(float)$two)
                            {
                                //第一场抢购期间-----修改第二场库存
                                $sku_data[] = [
                                    'item_id' => $id,
                                    'attr_symbol_path' => implode(',', $attr_symbol_path),
                                    'two_stock' => $stock ? $stock : 0,
                                ];
                            }else{
                                $sku_data[] = [
                                    'item_id' => $id,
                                    'attr_symbol_path' => implode(',', $attr_symbol_path),
                                    'stock' => $stock ? $stock : 0,
                                ];
                            }*/
                        }
                        if (!empty($sku_data)){
                            SeckillSku::insertAll($sku_data);
                            $sum=SeckillSku::where(["item_id"=>$id])->sum("stock");
                            $stoce=["stock"=>$sum];
                            if((float)date('H:i',time())>(float)$one &&(float)date('H:i',time())<(float)$two)
                            {
                                //第一场抢购期间-----修改第二场库存
                                $sum=SeckillSku::where(["item_id"=>$id])->sum("two_stock");
                                $stoce=["two_stock"=>$sum];
                            }
                            $this->model->save($stoce,["id"=>$id]);
                        }
                    }
                    $result=true;
                    Db::commit();
                    $this->success();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $two= $this->_site["two_seckill_start_time"];
        $data=date("Y-m-d");
        if(date("H:i")>$two){
            $data=date("Y-m-d",strtotime("+1 day"));
        }
        $this->assign("time",$data);
        return $this->view->fetch();
    }
    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $one= $this->_site["one_seckill_start_time"];
        $two= $this->_site["two_seckill_start_time"];
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                  /*  if((float)date('H:i',time())>(float)$one &&(float)date('H:i',time())<(float)$two)
                    {
                        //第一场抢购期间-----修改第二场库存
                        $params["two_stock"]=$params["stock"];
                        //$params["two_sales"]=$params["sales"];
                        unset($params["stock"]);
                        //unset($params["sales"]);
                    }*/
                    $result = $row->allowField(true)->save($params);
                    if ($params["specs_data"] == 1) {
                        //查询该商品的fa_integral_attr_key
                        $specs_datas = json_decode($this->request->post('specs_datas'), true);
                        $str = implode(',',$specs_datas["option_id"]);
                        $spec_title = $_POST['spec_title'];
                        $spec_id = $_POST['spec_id'];
                        //删除无用的fa_integral_attr_key
                        $attr_key = SeckillAttrKey::whereNotIn('attr_key_id',$spec_id)
                            ->where(["item_id"=>$ids])->column('attr_key_id');
                        if (!empty($attr_key)){
                            SeckillAttrKey::whereIn('attr_key_id', $attr_key)->delete();
                            SeckillAttrVal::whereIn('attr_key_id', $attr_key)->delete();
                        }
                        $attr_key1 = SeckillAttrKey::where(["item_id"=>$ids])->column('attr_key_id');
                        //删除sku
                        $option_id = $specs_datas['option_id'];
                        SeckillSku::whereNotIn('sku_id', $option_id)->where("item_id",$ids)->delete();
                        if (empty($spec_title))
                            $this->error("请完善规格信息");
                        $data_key = [];

                        foreach ($spec_title as $k => $item) {
                            $integ = [
                                "attr_name" => $item,
                                'item_id' => $ids,
                            ];
                            $spec_item_title = $_POST['spec_item_title_' . $k];
                            $spec_item_id = $_POST['spec_item_id_' . $k];
                            SeckillAttrVal::whereNotIn('symbol',$spec_item_id)->where("attr_key_id",$k)->delete();
                            $data_val = SeckillAttrVal::where(['attr_key_id'=>$k])->column("symbol");
                            if (in_array($k, $attr_key1)) {
                                SeckillAttrKey::update(['attr_name' => $item], ['attr_key_id' => $k]);
                                foreach ($spec_item_id as $ks => $vs) {
                                    if (in_array($vs, $data_val)) {
                                        SeckillAttrVal::update(['attr_value' => $spec_item_title[$ks]], ['symbol' => $vs]);
                                    } else {
                                        if ($spec_item_title[$ks] && isset($spec_item_title[$ks])) {
                                            $attrval_id = SeckillAttrVal::insertGetId([
                                                "attr_key_id" => $k,
                                                "item_id" => $ids,
                                                "attr_value" => $spec_item_title[$ks],
                                            ]);
                                            $data_key[$spec_item_id[$ks]] = $attrval_id;
                                        }
                                    }
                                }
                            } else {
                                $interg_id = SeckillAttrKey::insertGetId($integ);
                                foreach ($spec_item_title as $item_k => $item_title) {
                                    $attrval_id = SeckillAttrVal::insertGetId([
                                        "attr_key_id" => $interg_id,
                                        "item_id" => $ids,
                                        "attr_value" => $item_title,
                                    ]);
                                    $data_key[$spec_item_id[$item_k]] = $attrval_id;
                                }
                            }
                        }
                        foreach ($specs_datas['option_ids'] as $ks => $v) {
                            $attr_symbol_path = [];
                            foreach (explode('_', $v) as $dk => $v1) {
                               $attr_symbol_path[] = is_numeric($v1)?$v1:$data_key[$v1];
                            }
                            $res = SeckillSku::where([
                                'attr_symbol_path'=>implode(',', $attr_symbol_path)
                            ])
                                ->where(["item_id"=>$ids])->find();
                            if (!empty($res)){

                                //$res->stock =intval($specs_datas['option_stock'][$ks]);
                                //$res->stock = intval($specs_datas['option_stock'][$ks]);
                                /*if((float)date('H:i',time())>(float)$one && (float)date('H:i',time())<(float)$two)
                                {
                                    //第一场抢购期间-----修改第二场库存
                                    $res->two_stock=intval($specs_datas['option_stock'][$ks]);
                                    //$res->two_sales=$params["sales"];
                                }else{
                                    $res->stock = intval($specs_datas['option_stock'][$ks]);
                                }*/
                                SeckillSku::update(['stock'=>intval($specs_datas['option_stock'][$ks])],
                                    ['sku_id'=>$res->sku_id]);
//                                $res->stock = intval($specs_datas['option_stock'][$ks]);
//                                $res->save();
                            }else{
                                $stock = intval($specs_datas['option_stock'][$ks]);
                                    $sku_data[] = [
                                        'item_id' => $ids,
                                        'attr_symbol_path' => implode(',', $attr_symbol_path),
                                        'stock' => $stock ? $stock : 0
                                    ];
                                /*if((float)date('H:i',time())>(float)$one && (float)date('H:i',time())<(float)$two)
                                {
                                    //第一场抢购期间-----修改第二场库存
                                    $sku_data[] = [
                                        'item_id' => $ids,
                                        'attr_symbol_path' => implode(',', $attr_symbol_path),
                                        'two_stock' => $stock ? $stock : 0
                                    ];
                                }else{
                                    $sku_data[] = [
                                        'item_id' => $ids,
                                        'attr_symbol_path' => implode(',', $attr_symbol_path),
                                        'stock' => $stock ? $stock : 0
                                    ];
                                }*/
                            }

                        }
                        if (!empty($sku_data))
                        {
                            SeckillSku::insertAll($sku_data);
                            /*if((float)date('H:i',time())>(float)$one &&(float)date('H:i',time())<(float)$two)
                            {
                                //第一场抢购期间-----修改第二场库存
                                $sum=SeckillSku::where(["item_id"=>$row["id"]])->sum("two_stock");
                                $stoce=["two_stock"=>$sum];
                            }*/
                        }
                        $sum=SeckillSku::where(["item_id"=>$row["id"]])->sum("stock");
                        $stoce=["stock"=>$sum];
                        $this->model->save($stoce,["id"=>$ids]);
                    }
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $data = SeckillAttrKey::with(['seckillattrval'])->where(['item_id' => $ids])->select();
        $need = [];
        foreach ($data as $item) {
            $need[] = $item->toArray();
        }
        $sku = SeckillSku::where(['item_id' => $ids])->select();
        $skus = [];
        foreach ($sku as $item) {
            /*if((float)date('H:i',time())>(float)$one && (float)date('H:i',time())<(float)$two)
            {
                $item->stock=$item->two_stock;
            }*/
            $skus[] = $item->toarray();
        }
        $this->view->assign("row", $row);
        $this->view->assign('itemAttr', $need);
        $this->view->assign('itemSku', json_encode($skus, 320));
        $two=$this->_site["two_seckill_start_time"];
        /*$date=date("Y-m-d");
        if(date("H:i")>$two){
            $date=date("Y-m-d",strtotime("+1 day"));
        }
        $one= $this->_site["one_seckill_start_time"];
        $two= $this->_site["two_seckill_start_time"];
        $timelist[]=["id"=>1,"name"=>$one];
        $timelist[]=["id"=>2,"name"=>$two];
        if((float)date("H:i",$row["starttime"])==(float)$one){
            $row["timeid"]=1;
        }
        if((float)date("H:i",$row["starttime"])==(float)$two){
            $row["timeid"]=2;
        }
        $row["starttime"]=date("Y-m-d",$row["starttime"]);
        $this->assign("time",$date);
        $this->assign("timelist",$timelist);*/
        return $this->view->fetch();
    }

    public function save_sku()
    {
        if (request()->isPost()) {
            $data = request()->post();
            $bool = SeckillSku::where(['item_id' => $data[0]['item_id']])->delete();
            $ids = "";
            foreach ($data as $item) {
                $sku = new SeckillSku();
                $sku->item_id = $item['item_id'];
                /* $sku->original_price=$item['original_price'];
                 $sku->price=$item['price'];*/
                $sku->stock = $item['stock'];
                $sku->stock = $item['sales'];
                $sku->attr_symbol_path = $item['symbol'];
                $id = $sku->save();
            }

        }

    }


    public function save_attr()
    {
        if (request()->isPost()) {
            $data = request()->post();
            $key = json_decode($data['key'], true);
            $value = json_decode($data['value'], true);
            $item_id = 1;
            $key_id = [];
            SeckillAttrKey::where(['item_id' => $item_id])->delete();
            foreach ($key as $k) {
                $attr_key = SeckillAttrKey::where(['attr_name' => $k, 'item_id' => $item_id])->find();
                if (!$attr_key) {
                    $attr_key = new SeckillAttrKey();
                    $attr_key->attr_name = $k;
                    $attr_key->item_id = $item_id;
                    $attr_key->save();
                }
                $key_id[] = $attr_key->attr_key_id;
            }
            $tm_v_in = [];
            $tm_v = [];
            SeckillAttrKey::where(['item_id' => $item_id])->delete();
            foreach ($value as $key => $v) {
                $attr_key_id = $key_id[$key];
                foreach ($v as $v1) {
                    $attr_value = SeckillAttrVal::where(['attr_value' => $v1, 'attr_key_id' => $attr_key_id])->find();
                    if (!$attr_value) {
                        $attr_value = new SeckillAttrVal();
                        $attr_value->attr_key_id = $attr_key_id;
                        $attr_value->attr_value = $v1;
                        $attr_value->item_id = $item_id;
                        $attr_value->save();
                    }
                    $tm_v[] = $attr_value->symbol;

                }
            }
            $this->success('请求成功', '', ['key' => $key_id, 'value' => $tm_v]);
        }

        $this->success('请求成功');
    }

    public function edit_save_attr()
    {
        if (request()->isPost()) {
            $data = request()->post();
            $key = json_decode($data['key'], true);
            $value = json_decode($data['value'], true);
            $item_id = $data["id"];
            $key_id = [];
            foreach ($key as $k) {
                $attr_key = SeckillAttrKey::where(['attr_name' => $k, 'item_id' => $item_id])->find();
                if (!$attr_key) {
                    $attr_key = new SeckillAttrKey();
                    $attr_key->attr_name = $k;
                    $attr_key->item_id = $item_id;
                    $attr_key->save();
                }
                $key_id[] = $attr_key->attr_key_id;
            }
            $tm_v_in = [];
            $tm_v = [];
            foreach ($value as $key => $v) {
                $attr_key_id = $key_id[$key];
                foreach ($v as $v1) {
                    $attr_value = SeckillAttrVal::where(['attr_value' => $v1, 'attr_key_id' => $attr_key_id])->find();
                    if (!$attr_value) {
                        $attr_value = new SeckillAttrVal();
                        $attr_value->attr_key_id = $attr_key_id;
                        $attr_value->attr_value = $v1;
                        $attr_value->item_id = $item_id;
                        $attr_value->save();
                    }
                    $tm_v[] = $attr_value->symbol;

                }
            }
            $this->success('请求成功', '', ['key' => $key_id, 'value' => $tm_v]);
        }

        $this->success('请求成功');
    }

    public function spec_tpl()
    {
        $key = $this->str_rand();
        $this->view->engine->layout(false);
        return $this->view->fetch('', compact('key'));
    }

    public function spec_item_tpl()
    {
        $key = input('key');
        $this_key = $this->str_rand();
        $this->view->engine->layout(false);
        return $this->view->fetch('', compact('key', 'this_key'));
    }

    public function str_rand($length = 16, $char = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        if (!is_int($length) || $length < 0) {
            return false;
        }
        $string = '';
        for ($i = $length; $i > 0; $i--) {
            $string .= $char[mt_rand(0, strlen($char) - 1)];
        }
        return $string;
    }

    /*public function gettime(){
        $one= $this->_site["one_seckill_start_time"];
        $two= $this->_site["two_seckill_start_time"];
        $list[]=["id"=>1,"name"=>$one];
        $list[]=["id"=>1,"name"=>$one];
        $this->success('请求成功', '', $list);
        $data = request()->post();
        if($data["date"]==date('Y-m-d')){
            //选择当天时间
            $one= $this->_site["one_seckill_start_time"];
            $two= $this->_site["two_seckill_start_time"];
            $list["one"]=$one;
            $list["two"]=$two;
            if((float)date('H:i')<(float)$one) $list["one"]=$one;;
            if((float)date('H:i')>(float)$one && (float)date('H:i')<(float)$two) $list["two"]='';
            $this->success('请求成功', '', $list);
        }
        $list["one"]=$one;
        $list["two"]=$two;
        $this->success('请求成功', '', $list);
    }*/

}
