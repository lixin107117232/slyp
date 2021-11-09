<?php

namespace app\admin\controller\hospital;

use app\admin\model\HospitalAttrKey;
use app\admin\model\HospitalAttrVal;
use app\admin\model\HospitalSku;
use app\common\controller\Backend;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 医美商城商品管理
 *
 * @icon fa fa-circle-o
 */
class Goods extends Backend
{
    
    /**
     * Goods模型对象
     * @var \app\admin\model\Goods
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Goods;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("exchangeDataList", $this->model->getExchangeDataList());
        $this->view->assign("specsDataList", $this->model->getSpecsDataList());
    }

    public function import()
    {
        parent::import();
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    public function selepage(){
        if ($this->request->request('keyField')) {
            $list=$this->model->field("id")->with(["goodsdata"=>function($query){
                $query->withField("name")->where("name","like","%".$this->request->request("name")."%");
            }])->select();
            $data=[];
            foreach ($list as $v){
                $data[]=["name"=>$v["goodsdata"]["name"],"id"=>$v["id"]];
            }
            return json(['list' => $data, 'total' => count($data)]);
        }
    }

    public function save_sku(){
        if(request()->isPost()){
            $data=request()->post();
            // 将以前的旧数据删除
            $bool=HospitalSku::where(['item_id'=>0])->delete();
            $price=999999999;
            $stock=999999999;
            foreach ($data as $item) {
                // 新增当前数据
                $upd_data = [
                    'item_id' => 0,
                    'stock' => $item['stock'],
                    'sales' => $item['sales'],
                    'attr_symbol_path' => $item['symbol'],
                ];
                HospitalSku::create($upd_data);
                if($price > $item['sales']){
                    $price = $item['sales'];
                    $stock = $item['stock'];
                }
            }
            $data = ['sales' => $price,'stock' => $stock];
        }
        return $data;
    }

    public function save_attr()
    {
        if(request()->isPost()){
            $data=request()->post();
            $key=json_decode($data['key'],true);
            $value=json_decode($data['value'],true);
            $item_id=0;
            $key_id=[];
//            HospitalAttrKey::where(['item_id'=>$item_id])->delete();
            foreach ($key as $k) {
                $attr_key=HospitalAttrKey::where(['attr_name'=>$k,'item_id'=>$item_id])->find();
                if(!$attr_key){
                    $attr_key=new HospitalAttrKey();
                    $attr_key->attr_name=$k;
                    $attr_key->item_id=$item_id;
                    $attr_key->save();
                }
                $key_id[]=$attr_key->attr_key_id;
            }
            $tm_v_in=[];
            $tm_v=[];
            HospitalAttrVal::where(['item_id'=>0])->delete();
            foreach ($value as $key=>$v){
                $attr_key_id=$key_id[$key];
                foreach ($v as $v1){
                    $attr_value=HospitalAttrVal::where(['attr_value'=>$v1,'attr_key_id'=>$attr_key_id])->find();
                    if(!$attr_value){
                        $attr_value=new HospitalAttrVal();
                        $attr_value->attr_key_id=$attr_key_id;
                        $attr_value->attr_value=$v1;
                        $attr_value->item_id=$item_id;
                        $attr_value->save();
                    }
                    $tm_v[]=$attr_value->symbol;

                }
            }
            $this->success('请求成功','',['key'=>$key_id,'value'=>$tm_v]);
        }

        $this->success('请求成功');
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
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    if($params["specs_data"]==1) {
                        $datas = $this->request->post("obj");
                        $data = json_decode($datas, true);
                        if($data){
                            $params['createtime'] = time();
                            // 获取新增数据的ID
                            $id = $this->model->allowField(true)->insertGetId($params);

                            foreach ($data as $item) {
                                $sku = new HospitalSku();
                                $sku->item_id = $id;
                                $sku->attr_symbol_path = $item['symbol'];
                                $sku->stock = $item['stock'];
                                $sku->sales = $item['sales'];
                                $sku->save();
                                HospitalAttrVal::update(["item_id" => $id], ["symbol" => $item['symbol']]);
                                HospitalAttrKey::where('item_id',0) -> update(["item_id" => $id]);
                                $ids = HospitalAttrVal::where(["item_id" => $id])->find();
                                HospitalAttrVal::update(["item_id" => $ids], ["attr_key_id" => $ids]);

                            }
                        }else{
                            Db::rollback();
                            $this->error('您还有未完成的操作，请继续操作');
                        }

                    }else{
                        $result = $this->model->allowField(true)->save($params);
                    }
                    Db::commit();
                    $this->success("添加成功");
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
            }else{
                $this->error(__('Parameter %s can not be empty', ''));
            }
        }
        return $this->view->fetch();
    }

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->with(['goodsdata','typedata','shopdata'])
                ->where($where)
                ->order($sort, $order)
                ->order("createtime", "desc")
                ->paginate($limit);
            foreach ($list as $k => $v) {
                $v->visible(['id','goods_id','type_id','status','num','specs_data','stock','sales','weigh','createtime','updatetime','deletetime']);
                $v->visible(['goodsdata']);
                $v->getRelation('goodsdata')->visible(['name']);
                $v->visible(['typedata']);
                $v->getRelation('typedata')->visible(['name']);
                $v->visible(['shopdata']);
                $v->getRelation('shopdata')->visible(['name']);
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
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
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    $result = $this->model->where('id',$ids)->update($params);
                    // 选择了规格，修改规格表中的内容
                    if($params["specs_data"]==1) {
                        HospitalSku::where(['item_id'=>$ids])->delete();

                        $data = json_decode($this->request->post("obj"), true);
                        if($data){
                            foreach ($data as $item) {
                                $sku = new HospitalSku();
                                $sku->item_id = $ids;
                                $sku->attr_symbol_path = $item['symbol'];
                                $sku->stock = $item['stock'];
                                $sku->sales = $item['sales'];
                                $sku->save();
                            }
                        }else{
                            Db::rollback();
                            $this->error('商品规格信息保存成功，请继续操作');
                        }

                    }else{
                        // 未选择规格，将sku表删除
                        HospitalSku::where(['item_id'=>$ids])->delete();
                        // 将key表删除
                        HospitalAttrKey::where(['item_id'=>$ids])->delete();
                        // 将val表中的内容删除
                        HospitalAttrVal::where(['item_id'=>$ids])->delete();

                    }
                    Db::commit();
                    $this->success("修改成功");
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
            }else{
                $this->error(__('Parameter %s can not be empty', ''));
            }
        }

        $data=HospitalAttrKey::with(['Hospitalattrval'])->where(['item_id'=>$ids])->select();

        $need=[];
        foreach ($data as $item) {
            $need[]=$item->toArray();
        }
        $sku=HospitalSku::where(['item_id'=>$ids])->select();
        $skus=[];
        foreach ($sku as $item) {
            $skus[]=$item->toarray();
        }
        $this->view->assign('itemAttr',$need);
        $this->view->assign('itemSku',json_encode($skus,320));
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    public function edit_save_attr()
    {
        if(request()->isPost()){
            $data=request()->post();
            $key=json_decode($data['key'],true);
            $value=json_decode($data['value'],true);
            $item_id=$data["id"];
            $key_id=[];
            // 把之前的删除，然后新增
            HospitalAttrKey::where(['item_id'=>$item_id])->delete();
            foreach ($key as $k) {
                $attr_key=new HospitalAttrKey();
                $attr_key->attr_name=$k;
                $attr_key->item_id=$item_id;
                $attr_key->save();

                $key_id[]=$attr_key->attr_key_id;
            }
            $tm_v_in=[];
            $tm_v=[];
            // 把之前的删除，然后新增
            HospitalAttrVal::where(['item_id'=>$item_id])->delete();
            foreach ($value as $key=>$v){
                $attr_key_id=$key_id[$key];
                foreach ($v as $v1){
                    $attr_value=new HospitalAttrVal();
                    $attr_value->attr_key_id=$attr_key_id;
                    $attr_value->attr_value=$v1;
                    $attr_value->item_id=$item_id;
                    $attr_value->save();

                    $tm_v[]=$attr_value->symbol;

                }
            }
            $this->success('请求成功','',['key'=>$key_id,'value'=>$tm_v,'id'=>$item_id]);
        }

        $this->success('请求成功');
    }

}
