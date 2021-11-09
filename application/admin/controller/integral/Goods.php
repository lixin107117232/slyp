<?php

namespace app\admin\controller\integral;

use app\admin\model\IntegralAttrKey;
use app\admin\model\IntegralAttrVal;
use app\admin\model\IntegralSku;
use app\common\controller\Backend;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;
use app\common\model\GoodsLabel;

/**
 * 积分商城商品管理
 *
 * @icon fa fa-circle-o
 */
class Goods extends Backend
{

    /**
     * Goods模型对象
     * @var \app\common\model\integral\Goods
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\common\model\integral\Goods;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("exchangeDataList", $this->model->getExchangeDataList());
        $this->view->assign("specsDataList", $this->model->getSpecsDataList());
        $this->view->assign("isspecialList", $this->model->getspecialListList());
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
                ->with(['allgoods', 'integraltype'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $k =>$row) {
                $label =GoodsLabel::get(['id'=>$row->allgoods->bid]);
                $row->allgoods->bid = $label?$label->getAttr('name'):'';
                $row->visible(['id', 'goods_id', 'type_id', 'status', 'exchange_data', 'num', 'specs_data', 'stock', 'sales', 'weigh', 'createtime','real_price']);
                $row->visible(['allgoods'])->visible(['name', 'cover_image']);
                $row->visible(['integraltype']);
                $row->getRelation('integraltype')->visible(['name']);
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    public function selepage()
    {
        if ($this->request->request('keyField')) {
            $list = $this->model->field("id")->with(["allgoods" => function ($query) {
                $query->withField("name")->where("name", "like", "%" . $this->request->request("name") . "%");
            }])->select();
            $data = [];
            foreach ($list as $v) {
                $data[] = ["name" => $v["allgoods"]["name"], "id" => $v["id"]];
            }
            return json(['list' => $data, 'total' => count($data)]);
        }
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
//                    $spec_title = input('spec_title');
//                    $spec_title = input('spec_item_title');
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
                            $interg_id = IntegralAttrKey::insertGetId($integ);
                            foreach ($spec_item_title as $item_k => $item_title) {
                                $attrval_id = IntegralAttrVal::insertGetId([
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
                                'surplus_stock' => $stock ? $stock : 0,
                            ];
                        }
                        if (!empty($sku_data)){
                            IntegralSku::insertAll($sku_data);
                            $sum=IntegralSku::where(["item_id"=>$id])->sum("stock");
                            $this->model->save(["surplus_stock"=>$sum],["id"=>$id]);
                            $this->model->save(["stock"=>$sum],["id"=>$id]);
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
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
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

                    $result = $row->update($params,['id'=>$row->id]);
                    if ($params["specs_data"] == 1) {
                        //查询该商品的fa_integral_attr_key
                        $specs_datas = json_decode($this->request->post('specs_datas'), true);
                        $spec_title = $_POST['spec_title'];
                        $spec_id = $_POST['spec_id'];
                        //删除无用的fa_integral_attr_key
                        $attr_key = IntegralAttrKey::whereNotIn('attr_key_id',$spec_id)
                            ->where(["item_id"=>$ids])->column('attr_key_id');
                        if (!empty($attr_key)){
                            IntegralAttrKey::whereIn('attr_key_id', $attr_key)->delete();
                            IntegralAttrVal::whereIn('attr_key_id', $attr_key)->delete();
                        }
                        $attr_key1 = IntegralAttrKey::where(["item_id"=>$ids])->column('attr_key_id');
//                        //删除sku
//                        $option_id = $specs_datas['option_id'];
//                        if($option_id[0]){
//                             SeckillSku::whereNotIn('sku_id', $option_id)->where("item_id",$ids)->delete();::whereNotIn('sku_id', $option_id)->where("item_id",$ids)->delete();
//                        }
                        $option_id = $specs_datas['option_id'];
                        IntegralSku::whereNotIn('sku_id', $option_id)->where("item_id",$ids)->delete();
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

                            IntegralAttrVal::whereNotIn('symbol',$spec_item_id)->where("attr_key_id",$k)->delete();
                            $data_val = IntegralAttrVal::where(['attr_key_id'=>$k])->column("symbol");
                            if (in_array($k, $attr_key1)) {
                                IntegralAttrKey::update(['attr_name' => $item], ['attr_key_id' => $k]);
                                foreach ($spec_item_id as $ks => $vs) {
                                    if (in_array($vs, $data_val)) {
                                        IntegralAttrVal::update(['attr_value' => $spec_item_title[$ks]], ['symbol' => $vs]);
                                    } else {
                                        if ($spec_item_title[$ks] && isset($spec_item_title[$ks])) {
                                            $attrval_id = IntegralAttrVal::insertGetId([
                                                "attr_key_id" => $k,
                                                "item_id" => $ids,
                                                "attr_value" => $spec_item_title[$ks],
                                            ]);
                                            $data_key[$spec_item_id[$ks]] = $attrval_id;
                                        }
                                    }
                                }
                            } else {
                                $interg_id = IntegralAttrKey::insertGetId($integ);
                                foreach ($spec_item_title as $item_k => $item_title) {
                                    $attrval_id = IntegralAttrVal::insertGetId([
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
                            $res = IntegralSku::where(['attr_symbol_path'=>implode(',', $attr_symbol_path)])->find();
                            if (!empty($res)){
                                IntegralSku::update(['stock'=>intval($specs_datas['option_stock'][$ks])],
                                    ['sku_id'=>$res->sku_id]);
//                                $res->stock =intval($specs_datas['option_stock'][$ks]);
//                                $res->save();
                            }else{
                                $stock = intval($specs_datas['option_stock'][$ks]);
                                $sku_data[] = [
                                    'item_id' => $ids,
                                    'attr_symbol_path' => implode(',', $attr_symbol_path),
                                    'stock' => $stock ? $stock : 0
                                ];
                            }

                        }
                        if (!empty($sku_data)) IntegralSku::insertAll($sku_data);

                        $sum=IntegralSku::where(["item_id"=>$row["id"]])->sum("stock");
                        //$this->model->save(["surplus_stock"=>$sum],["id"=>$row["id"]]);
                        $this->model->save(["stock"=>$sum],["id"=>$row["id"]]);
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
                    $this->success('修改成功');
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $data = IntegralAttrKey::with(['Integralattrval'])->where(['item_id' => $ids])->select();
        $need = [];
        foreach ($data as $item) {
            $need[] = $item->toArray();
        }
        $sku = IntegralSku::where(['item_id' => $ids])->select();
        $skus = [];
        foreach ($sku as $item) {
            $skus[] = $item->toarray();
        }
        $type = new Type;
         $type->_initialize();
        $this->view->assign('itemAttr', $need);
        $this->view->assign('itemSku', json_encode($skus, 320));
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    public function save_sku()
    {
        if (request()->isPost()) {
            $data = request()->post();
            $bool = IntegralSku::where(['item_id' => $data[0]['item_id']])->delete();
            $ids = "";
            foreach ($data as $item) {
                $sku = new IntegralSku();
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
            IntegralAttrKey::where(['item_id' => $item_id])->delete();
            foreach ($key as $k) {
                $attr_key = IntegralAttrKey::where(['attr_name' => $k, 'item_id' => $item_id])->find();
                if (!$attr_key) {
                    $attr_key = new IntegralAttrKey();
                    $attr_key->attr_name = $k;
                    $attr_key->item_id = $item_id;
                    $attr_key->save();
                }
                $key_id[] = $attr_key->attr_key_id;
            }
            $tm_v_in = [];
            $tm_v = [];
            IntegralAttrKey::where(['item_id' => $item_id])->delete();
            foreach ($value as $key => $v) {
                $attr_key_id = $key_id[$key];
                foreach ($v as $v1) {
                    $attr_value = IntegralAttrVal::where(['attr_value' => $v1, 'attr_key_id' => $attr_key_id])->find();
                    if (!$attr_value) {
                        $attr_value = new IntegralAttrVal();
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
                $attr_key = IntegralAttrKey::where(['attr_name' => $k, 'item_id' => $item_id])->find();
                if (!$attr_key) {
                    $attr_key = new IntegralAttrKey();
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
                    $attr_value = IntegralAttrVal::where(['attr_value' => $v1, 'attr_key_id' => $attr_key_id])->find();
                    if (!$attr_value) {
                        $attr_value = new IntegralAttrVal();
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
}
