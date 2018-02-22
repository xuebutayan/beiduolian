<?php
namespace Admin\Controller;
use Think\Page;
class BorrowController extends AdminController {
	function index(){
		$status=I('status');
        $member_id=I('member_id');
        $user_name = I('user_name');
        if($status!==''){
        	$where['b.status'] = $status;
        }
        if($member_id){
        	$where['b.member_id'] = $member_id;
        }
        if($user_name){
        	$where['m.user_name'] = ['like','%'.$user_name.'%'];
        }

        $field = "b.*,m.user_name,m.email,m.user_levels";
        $count = M('Borrow b')
        	->field($field)
        	->join('__MEMBER__ m on b.member_id=m.member_id','LEFT')
            ->where($where)
            ->order(" b.applydate desc ")->count();// 查询满足要求的总记录数
//echo M()->getLastSql();exit;
        $Page       = new Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        setPageParameter($Page, array('status'=>$status,'member_id'=>$member_id,'user_name'=>$user_name));

        $show       = $Page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = M('Borrow b')
        	->field($field)
        	->join('__MEMBER__ m on b.member_id=m.member_id','LEFT')
            ->where($where)
            ->order(" b.applydate desc ")
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        //币种
        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
        if($status=='') $this->display('index1');
        else $this->display();
	}
    function check(){
        $id = intval($_POST['id']);
        $re = M('Borrow')->save(['bid'=>$id,'status'=>1,'allowdate'=>time()]);
        if($re) $this->ajaxReturn(['status'=>1,'info'=>'审核成功！']);
        else $this->ajaxReturn(['status'=>0,'info'=>'审核失败！']);
    }
    function delete(){
        $id = intval($_POST['id']);
        $re = M('Borrow')->delete($id);
        if($re) $this->ajaxReturn(['status'=>1,'info'=>'删除成功！']);
        else $this->ajaxReturn(['status'=>0,'info'=>'删除失败！']);
    }
}