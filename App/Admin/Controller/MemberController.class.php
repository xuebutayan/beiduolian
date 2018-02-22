<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 16-3-8
 * Time: 下午12:28
 */

namespace Admin\Controller;
use Think\Page;
use Think\Upload;
class MemberController extends AdminController {
    public function _initialize(){
        parent::_initialize();
    }
    /**
     * 会员列表
     */
    public function index(){
        $email = I('email');
        $user_name = I('user_name');
        $member_id=I('member_id');
        $user_code = I('user_code');
        $new_where = '';
        if(!empty($user_name)){
            $where['user_name'] = array('like','%'.$user_name.'%');
            $new_where .= " m.user_name like '%$user_name%' ";
        }
        if(!empty($email)){
            $where['email'] = array('like','%'.$email.'%');
            $new_where .= " m.email like '%$email%' ";
        }
        if (!empty($member_id)){
            $where['member_id']=$member_id;
            $new_where .= " m.member_id=$member_id ";
        }
        if (!empty($user_code)){
            $where['user_code']=$user_code;
            $new_where .= " m.user_code='{$user_code}' ";
        }
        if(I('mt4')){
            $where['mt4'] = ['neq',''];
            $new_where .= " m.mt4 <> '' ";
        }
        if(I('j2t5000')){
            $where['huge_mt4'] = ['egt',5000];
            $new_where .= " m.huge_mt4 >= 5000 ";
        }

        $count      =  M('Member')->where($where)->count();// 查询满足要求的总记录数
        $page_size = I('get.export')?$count:20;
        $Page       = new Page($count,$page_size);// 实例化分页类 传入总记录数和每页显示的记录数(25)

        //给分页传参数
        setPageParameter($Page, array('email'=>$email,'member_id'=>$member_id,'user_name'=>$user_name));

        $show       = $Page->show();// 分页显示输出
        $new_where =  $new_where?$new_where:1;
        $list =
        /*M('Member')->alias('m')
            ->field("m.*,c.num as cnum,sum(w.money) as wmoney")
            ->join("__CURRENCY_USER__ c on c.member_id=m.member_id and c.currency_id=30",'LEFT')
            ->join("__WITHDRAW__ w on w.uid=m.member_id and w.status=2 group by w.uid")*/
            M()->query("select m.*,w.wmoney,b.bmoney,(a.money) as ben,t.tmoney,(IFNULL(t.tmoney,0)+IFNULL(w.wmoney,0)) as tjz,((IFNULL(a.money,0)-IFNULL(b.bmoney,0)-IFNULL(w.wmoney,0)-IFNULL(t.tmoney,0))*1.5/6.9) as jtjz from yang_member m left join yang_agent a on m.user_levels=a.levels left join (select uid,status,sum(money) as wmoney from yang_withdraw where status=2 group by uid) w on w.uid=m.member_id left join (select member_id,sum(money) as bmoney from yang_borrow where status>0 group by member_id) b on b.member_id=m.member_id left join (select member_id,sum(money*6.9) as tmoney from yang_alps_log where platform='glt' and type=1 group by member_id) t on t.member_id=m.member_id where ".$new_where." order by m.member_id desc limit ".$Page->firstRow.','.$Page->listRows);
            /*->where($new_where)
            ->order(" m.member_id desc ")
            ->limit($Page->firstRow.','.$Page->listRows)->select();*/

        //代理级别
        $agent_type = [
            0 => ['无代理', 0],
            1 => ['基础代理', 1000],
            2 => ['中级代理', 5000],
            3 => ['高级代理', 10000],
            4 => ['旗舰代理', 30000],
            5 => ['战略代理', 50000]
        ];
        if($_GET['export'] ==1){
            $levels = [0=>'无代理',1=>'基础代理',2=>'中级代理',3=>'高级代理',4=>'旗舰代理',5=>'战略代理'];
            $data = [];
            foreach ($list as $k=>$v) {
                $tmp = [];
                $tmp['id'] = $v['member_id'];
                $tmp['user_name'] = $v['user_name'];
                $tmp['user_code'] = $v['user_code'];
                $tmp['user_levels'] = $agent_type[$v['user_levels']][0];
                $tmp['rmb'] = $v['rmb'];
                $tmp['forzen_rmb'] = $v['forzen_rmb'];
                $tmp['tjz'] = $v['tjz'];
                $tmp['tdj'] = $v['tmoney'];
                $tmp['jtjz'] = $v['jtjz'];
                $tmp['touzi'] = $agent_type[$v['user_levels']][1]-$v['bmoney'];
                $tmp['tixian'] = $v['wmoney'];
                $tmp['daikuan'] = $v['bmoney'];
                $data[] = $tmp;
            }
            $title = ['用户ID','用户名','代理编号','用户级别','账户余额','冻结金额','提现加通兑金','通兑金总额','应减通兑金','投资金额','提现总额','贷款总额'];
            exportexcel($data,$title,'会员列表');exit;
        }
        $this->assign('agent',$agent_type);
        $this->assign('list',$list);// 赋值数据集
        $this->assign('page',$show);// 赋值分页输出
        $this->display(); // 输出模板
    }
    //会员通兑金记录
    function tdj(){
        $member_id=I('member_id');
        $user_code = I('user_code');
        $start_time = I('start_time',0,'strtotime');
        $end_time = I('end_time',0,'strtotime');
        $where['platform'] = 'glt';
        $new_where = "a.platform='glt'";
        if (!empty($member_id)){
            $where['member_id']=$member_id;
            $new_where .= " and a.member_id=$member_id ";
        }
        if (!empty($user_code)){
            $member_id = M('member')->where(['user_code'=>$user_code])->getField('member_id');
            if($member_id){
                $where['member_id']=$member_id;
                $new_where .= " and a.member_id=$member_id ";
            }
        }
        if(!empty($start_time)){
            $where['addtime']=['egt',$start_time];
            $new_where .= " and a.addtime >= $start_time ";
        }
        if(!empty($end_time)){
            $where['addtime']=['elt',$end_time];
            $new_where .= " and a.addtime <= $end_time ";
        }
        $count      =  M('alps_log')->where($where)->count();// 查询满足要求的总记录数

        $Page       = new Page($count,20);// 实例化分页类 传入总记录数和每页显示的记录数(25)

        //给分页传参数
        setPageParameter($Page, array('member_id'=>$member_id,'user_name'=>$user_name));
        $show       = $Page->show();

        $list = M('alps_log a')->field("m.user_code,m.user_name,a.*")->join("__MEMBER__ m on m.member_id=a.member_id")->where($new_where)->order("a.id desc")
            ->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('list',$list);// 赋值数据集
        $this->assign('page',$show);// 赋值分页输出
        $this->display();
    }
    /**
     * 添加会员
     */
    public function addMember(){
        if(IS_POST){
            $M_member = D('Member');
            $_POST['ip'] = get_client_ip(0,1);
            $_POST['reg_time'] = time();
            if($r = $M_member->create()){
                if($r['pwd']==$r['pwdtrade']){
                    $this->error('支付密码不能和密码一样');
                    return;
                }
                if($M_member->add($r)){
                    $this->success('添加成功',U('Member/index'));
                    return;
                }else{
                    $this->error('服务器繁忙,请稍后重试');
                    return;
                }
            }else{
                $this->error($M_member->getError());
                return;
            }
        }else{
            $this->display();
        }
    }
    /**
     * 添加个人信息
     */
    public function saveModify(){
        $member_id = I('get.member_id','','intval');
        $M_member = D('Member');
        if(IS_POST){
            $_POST['status'] = 1;//0=有效但未填写个人信息1=有效并且填写完个人信息2=禁用
            if (!$data=$M_member->create()){ // 创建数据对象
                // 如果创建失败 表示验证没有通过 输出错误提示信息
                $this->error($M_member->getError());
                return;
            }else {
                $where['member_id'] = $_POST['member_id'];
                $r = $M_member->where($where)->save();
                if($r){
                    $this->success('添加成功',U('Member/index'));
                    return;
                }else{
                    $this->error('服务器繁忙,请稍后重试');
                    return;
                }
            }
        }else{
            $where['member_id'] = $member_id;
            $list = $M_member->where($where)->find();
            $this->assign('list',$list);
            $this->display();
        }
    }
    /**
     * 显示自己推荐列表
     */
    public function show_my_invit(){
        $member_id = $_GET['member_id'];
        if(empty($member_id)){
            $this->error('参数错误');
            return;
        }
        $M_member = M('Member');
        $count      = $M_member->where(array('pid'=>$member_id))->count();// 查询满足要求的总记录数
        $Page       = new Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        $show       = $Page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $my_invit = $M_member
            ->where(array('pid'=>$member_id))
            ->order(" reg_time desc ")
            ->limit($Page->firstRow.','.$Page->listRows)->select();
        if($my_invit){
            $this->assign('my_invit',$my_invit);
            $this->assign('page',$show);// 赋值分页输出
            $this->display(); // 输出模板
        }else{
            $this->error('抱歉,您还没有推荐其他人');
            return;
        }
    }
    /**
     * 修改会员
     */
    public function saveMember(){
        $member_id = I('get.member_id','','intval');
        $M_member = M('Member');
        if(IS_POST){
            $member_id = I('post.member_id','','intval');
            $where['member_id'] = $member_id;
            $list = $M_member->where($where)->find();
            //检查金额、冻结金额、积分有没有变化
            if($_POST['rmb']!=$list['rmb'] || $_POST['forzen_rmb']!=$list['forzen_rmb']||$_POST['integrals']!=$list['integrals'] || $_POST['waihui']!=$list['waihui']){
                $data['admin'] = $this->admin['username'];
                $data['member_id'] = $list['member_id'];
                $data['user_name'] = $list['user_name'];
                $data['rmb'] = $_POST['rmb']-$list['rmb'];
                $data['forzen_rmb'] = $_POST['forzen_rmb']-$list['forzen_rmb'];
                $data['integrals'] = $_POST['integrals']-$list['integrals'];
                $data['waihui'] = $_POST['waihui']-$list['waihui'];
                $data['addtime'] = time();

                M('member_log')->add($data);
            }
            //头像上传
            $upload = new Upload();// 实例化上传类
            $upload->maxSize   =     3145728 ;// 设置附件上传大小
            $upload->exts      =     array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
            $upload->rootPath  =     './Uploads/'; // 设置附件上传根目录
            $upload->savePath  =     'Member/Head/'; // 设置附件上传（子）目录
            // 上传文件
            if(!$_FILES['head']['error']){
                $info   =   $upload->upload();
                $file_path = ltrim($upload->rootPath.$info['head']["savepath"].$info['head']["savename"],'.');
            }
            $_POST['head'] = empty($file_path)?I('post.headold'):$file_path;
            //头像上传end
            if($_POST['pwd'] == $_POST['pwdtrade'] && $_POST['pwd']!=null ){
                $this->error('交易密码不能和密码一致');
                return;
            }
            if($_POST['nick']!=$list['nick']){
                $where = null;
                $where['member_id']  = array('NEQ',$member_id);
                $where['nick'] = $_POST['nick'];
                if($M_member->field('nick')->where($where)->select()){
                    $this->error('昵称重复');
                    return;
                }
            }
            if($_POST['phone']!=$list['phone']){
                $where = null;
                $where['member_id']  = array('NEQ',$member_id);
                $where['phone'] = $_POST['phone'];
                if($M_member->field('phone')->where($where)->select()){
                    $this->error('手机号重复');
                    return;
                }
            }
            $new_pwd = I('post.pwd','');
            $new_tradepwd = I('post.pwdtrade','');
            $_POST['pwd'] =  $_POST['pwd']?I('post.pwd','','md5'):$list['pwd'];
            $_POST['pwdtrade'] = $_POST['pwdtrade']?I('post.pwdtrade','','md5'):$list['pwdtrade'];


            if($list['user_id'] && ($new_pwd || $new_tradepwd)){//代理中心存在用户
                //远程请求
                $url = C('daili_url').'/api/test_007';
                $time = time();
                $key = 'GDSL28GSJGJ2G5YH6JSGS03S';
                $md5key = md5($time.md5($key));
                $post = ['uid'=>$member_id,'pwd1'=>$new_pwd,'pwd2'=>$new_tradepwd,'times'=>$time,'md5key'=>$md5key];
                $data2 = curlPost($url,$post);
                $r2 = json_decode($data2,true);
                if(0){//$r2['status']<1
                    $this->error('代理中心修改失败');
                    return;
                }
            }

            $r = $M_member->save($_POST);
            if($r!==false){
                $this->success('修改成功',U('Member/index'));
                return;
            }else{
                $this->error('修改失败');
                return;
            }
        }else{
            if($member_id){
                $where['member_id'] = $member_id;
                $list = $M_member->where($where)->find();
                $this->assign('list',$list);
                $this->display();
            }else{
                $this->error('参数错误');
                return;
            }
        }
    }
    //管理员操作日志
    function memberLog(){
        $member_log = M('member_log');
        $count = $member_log->where("id <> 0 ")->order('addtime desc')->count();
        $Page       = new Page($count,25);
        setPageParameter($Page);
        $show       = $Page->show();
        $list = $member_log->where("id <> 0 ")->order('addtime desc')->select();
        $this->assign('list',$list);
        $this->assign('page',$show);
        $this->display();
    }
    /**
     * 删除会员
     */
    public function delMember(){
        $member_id = I('get.member_id','','intval');
        $M_member = M('Member');
        //判断还有没有余额
        $where['member_id']= $member_id;
        $member = $M_member->where($where)->find();
        $member_currency = M('Currency_user')->where($where)->find();
        if($member['rmb']>0||$member['forzen_rmb']>0||$member_currency['num']>0||$member_currency['forzen_num']>0){
            $this->error('因账户有剩余余额,禁止删除');
            return;
        }
        $r[] = $M_member->delete($member_id);
        $r[] = M('Currency_user')->where($where)->delete();
        $r[] = M('Finance')->where($where)->delete();
        $r[] = M('Orders')->where($where)->delete();
        $r[] = M('Trade')->where($where)->delete();
        $r[] = M('Withdraw')->where('uid='.$member_id)->delete();
        $r[] = M('Pay')->where($where)->delete();
        if($r){
            $this->success('删除成功',U('Member/index'));
            return;
        }else{
            $this->error('删除失败');
            return;
        }
    }
    /**
     * ajax判断邮箱
     * @param $email
     */
    public function ajaxCheckEmail($email){
        $email = urldecode($email);
        $data = array();
        if(!checkEmail($email)){
            $data['status'] = 0;
            $data['msg'] = "邮箱格式错误";
        }else{
            $M_member = M('Member');
            $where['email']  = $email;
            $r = $M_member->where($where)->find();
            if($r){
                $data['status'] = 0;
                $data['msg'] = "邮箱已存在";
            }else{
                $data['status'] = 1;
                $data['msg'] = "";
            }
        }
        $this->ajaxReturn($data);
    }

    /**
     * ajax验证昵称是否存在
     */
    public function ajaxCheckNick($nick){
        $nick = urldecode($nick);
        $data =array();
        $M_member = M('Member');
        $where['nick']  = $nick;
        $r = $M_member->where($where)->find();
        if($r){
            $data['msg'] = "昵称已被占用";
            $data['status'] = 0;
        }else{
            $data['msg'] = "";
            $data['status'] = 1;
        }
        $this->ajaxReturn($data);
    }
    /**
     * ajax手机验证
     */
    function ajaxCheckPhone($phone) {
        $phone = urldecode($phone);
        $data = array();
        if(!checkMobile($phone)){
            $data['msg'] = "手机号不正确！";
            $data['status'] = 0;
        }else{
            $M_member = M('Member');
            $where['phone']  = $phone;
            $r = $M_member->where($where)->find();
            if($r){
                $data['msg'] = "此手机已经绑定过！请更换手机号";
                $data['status'] = 0;
            }else{
                $data['msg'] = "";
                $data['status'] = 1;
            }
        }
        $this->ajaxReturn($data);
    }

    /**
     * 查看个人币种
     */
    public function show(){
    	$currency = M('Currency_user');
    	$member = M('Member');
    	$member_id = I('member_id');
    	if(empty($member_id)){
    		$this->error('参数错误',U('Member/index'));
    	}
    	$where['member_id'] = $member_id;
    	$count = $currency->join(C("DB_PREFIX")."currency ON ".C("DB_PREFIX")."currency_user.currency_id = ".C("DB_PREFIX")."currency.currency_id")
    			->where($where)->count();
    	$Page = new \Think\Page ( $count,20); // 实例化分页类 传入总记录数和每页显示的记录数
    	$show = $Page->show();//分页显示输出性
    	$info = $currency->join(C("DB_PREFIX")."currency ON ".C("DB_PREFIX")."currency_user.currency_id = ".C("DB_PREFIX")."currency.currency_id")
    			->where($where)->limit($Page->firstRow.','.$Page->listRows)
    			->select();
    	$member_info = $member->field('member_id,name,phone,email')->where($where)->find();
    	$this->assign('member_info',$member_info);
    	$this->assign('info',$info);
    	$this->assign('page',$show);
    	$this->display();
    }
    //修改个人币种数量
    public function updateMemberMoney(){
    	$member_id=I('post.member_id');
    	$currency_id=I('post.currency_id');
    	$num=I('post.num');
    	$forzen_num=I('post.forzen_num');
    	if(empty($member_id)||empty($member_id)){
    		$data['info']="参数不全";
    		$data['status']=0;
    	}
    	$where['member_id']=$member_id;
    	$where['currency_id']=$currency_id;
        $list = M('Currency_user')->where($where)->find();//print_r($list);
    	$r[]=M('Currency_user')->where($where)->setField('num',$num);
    	$r[]=M('Currency_user')->where($where)->setField('forzen_num',$forzen_num);
    	if($r){
            $data1['admin'] = $this->admin['username'];
            $data1['member_id'] = $list['member_id'];
            $data1['num'] = $num-$list['num'];
            $data1['forzen_num'] = $forzen_num-$list['forzen_num'];
            $data1['addtime'] = time();//print_r($data1);exit;
            M('member_log')->add($data1);

    		$data['info']="修改成功";
    		$data['status']=1;
    	}else{
    		$data['info']="修改失败";
    		$data['status']=0;
    	}
    	$this->ajaxReturn($data);
    }
    function huge(){
        $list = M('hugemt4 h')->field('h.*,m.user_name')->join(C("DB_PREFIX")."member as m on m.member_id=h.member_id",'LEFT')->order('h.add_time desc')->select();
        $this->assign('list',$list);
        $this->display();
    }
    function hugeId(){
        $id = intval(I('post.id'));
        if(empty($id)) $this->ajaxReturn(['status'=>0,'info'=>'参数错误！']);
        if($_POST['no']==1){
                $data ['status'] = 0;
		$data ['check_time'] = time();
		$data ['note'] =  I('post.note','');
            M('hugemt4')->where(['id'=>$id])->save ($data);
        }
        else{
                $data ['status'] = 2;
		$data ['check_time'] = time();
		$data ['readpass'] =  I('post.readpass','');
            M('hugemt4')->where(['id'=>$id])->save ($data);
        }
        $this->ajaxReturn(['status'=>1,'info'=>'操作成功！']);
    }
    //会员财务统计
    function caiwu(){
        $page_size = intval(I('get.page_size'));
        if($page_size){
            cookie('page_size',$page_size);
        }else{
            $cookie_size = cookie('page_size');
            $page_size = $cookie_size?$cookie_size:20;
        }

        $email = I('email');
        $user_name = I('user_name');
        $member_id=I('member_id');
        $user_code = I('user_code');
        $new_where = '';
        if(!empty($user_name)){
            $where['user_name'] = array('like','%'.$user_name.'%');
            $new_where .= " m.user_name like '%$user_name%' ";
        }
        if(!empty($email)){
            $where['email'] = array('like','%'.$email.'%');
            $new_where .= " m.email like '%$email%' ";
        }
        if (!empty($member_id)){
            $where['member_id']=$member_id;
            $new_where .= " m.member_id=$member_id ";
        }
        if (!empty($user_code)){
            $where['user_code']=$user_code;
            $new_where .= " m.user_code='{$user_code}' ";
        }
        if(I('mt4')){
            $where['mt4'] = ['neq',''];
            $new_where .= " m.mt4 <> '' ";
        }
        if(I('j2t5000')){
            $where['huge_mt4'] = ['egt',5000];
            $new_where .= " m.huge_mt4 >= 5000 ";
        }

        $count      =  M('Member')->where($where)->count();// 查询满足要求的总记录数

        $Page       = new Page($count,$page_size);// 实例化分页类 传入总记录数和每页显示的记录数(25)

        //给分页传参数
        setPageParameter($Page, array('email'=>$email,'member_id'=>$member_id,'user_name'=>$user_name));

        $show       = $Page->show();// 分页显示输出

        $list =  M('Member')->alias('m')
            ->field("m.*,c.num as cnum")
            ->join("__CURRENCY_USER__ c on c.member_id=m.member_id and c.currency_id=30",'LEFT')
            ->where($new_where)
            ->order(" m.member_id desc ")
            ->limit($Page->firstRow.','.$Page->listRows)->select();

        if($_GET['export'] ==1){
            $levels = [0=>'无代理',1=>'基础代理',2=>'中级代理',3=>'高级代理',4=>'旗舰代理',5=>'战略代理'];
            $data = [];
            foreach ($list as $k=>$v) {
                $tmp = [];
                $tmp['id'] = $v['member_id'].'[uid:'.$v['user_id'].']';
                $tmp['user_name'] = $v['user_name'];
                $tmp['user_levels'] = $levels[$v['user_levels']];
                $tmp['send_times'] = $v['send_times'];
                $tmp['shifang'] = (string)check_jifen($v['user_id']);
                $tmp['alps'] = $v['cnum'];
                $tmp['edu'] = getMoney($v['member_id'],$v['user_levels']);
                $data[] = $tmp;
            }
            $title = ['用户ID','用户名','用户级别','积分发放','每日积分释放数','ALPS币','额度'];
            exportexcel($data,$title,'统计');exit;
        }
        $this->assign('list',$list);// 赋值数据集
        $this->assign('page',$show);// 赋值分页输出
        $this->display();
    }
    function tongji(){
        $user_name = I('user_name');
        $member_id=I('member_id');
        if(!empty($user_name)){
            $where['user_name'] = array('like','%'.$user_name.'%');

        }
        if (!empty($member_id)){
            $where['member_id']=$member_id;
        }
        $count      =  M('data')->where($where)->count();// 查询满足要求的总记录数

        $Page       = new Page($count,50);// 实例化分页类 传入总记录数和每页显示的记录数(25)

        //给分页传参数
        setPageParameter($Page, array('member_id'=>$member_id,'user_name'=>$user_name));

        $show       = $Page->show();// 分页显示输出

        $list =  M('data')
            ->where($where)
            ->order(" member_id desc ")
            ->limit($Page->firstRow.','.$Page->listRows)->select();

        if($_GET['export'] ==1 && $member_id){
            $new_list = M('data')->where(['member_id'=>$member_id])->order('id asc')->select();
            $data = [];
            foreach ($new_list as $v) {
                $tmp = [];
                $tmp['id'] = '[id:'.$v['member_id'].']'.$v['user_name'];
                $tmp['riqi'] = date('Y-m-d',$v['datetime']);
                $tmp['czjf'] = $v['czjf'];
                $tmp['sfjf'] = $v['sfjf'];
                $tmp['hdjf'] = $v['hdjf'];
                $tmp['zrjf'] = $v['zrjf'];
                $tmp['ztjf'] = $v['ztjf'];
                $tmp['kyjf'] = $v['czjf']+$v['sfjf']+$v['zrjf']+$v['ztjf']-$v['hdjf'];
                $tmp['mbgd'] = $v['mbgdj'];
                $tmp['mbjf'] = $v['mb_jifen'];
                $tmp['mbsl'] = $v['mbsl_jifen'];
                $tmp['jfye'] = $v['czjf']+$v['sfjf']+$v['zrjf']+$v['ztjf']-$v['hdjf']-$v['mb_jifen'];
                $tmp['zr_alps'] = $v['zr_alps'];
                $tmp['cz_alps'] = $v['cz_alps'];
                $tmp['km_alps'] = $v['km_alps'];
                $tmp['min_alps'] = $v['min_alps'];
                $tmp['mout_alps'] = $v['mout_alps'];
                $tmp['win_alps'] = $v['win_alps'];
                $tmp['wout_alps'] = $v['wout_alps'];
                $tmp['nbgd'] = $v['nbgdj'];
                $tmp['nbsl'] = $v['nbsl'];
                $tmp['nbje'] = $v['nbje'];
                $tmp['ye_alps'] = $v['ye_alps'];
                $tmp['mbgdr'] = $v['mbgdr'];
                $tmp['mb_rmb'] = $v['mb_rmb'];
                $tmp['mbsl_rmb'] = $v['mbsl_rmb'];
                $tmp['zr_rmb'] = $v['zr_rmb'];
                $tmp['cz_rmb'] = $v['cz_rmb'];
                $tmp['kt_rmb'] = $v['kt_rmb'];
                $tmp['tx_rmb'] = $v['tx_rmb'];
                $tmp['th_rmb'] = $v['th_rmb'];
                $data[] = $tmp;
            }
            $title = ['用户名','日期','充值积分','释放积分','还贷积分','转入积分','昨日积分','可用积分','买币挂单','买币总积分','买币总数量','积分余额','昨日ALPS','充值ALPS','可卖ALPS','会员转入ALPS','会员转出ALPS','外汇转入ALPS','外汇转出ALPS','卖币挂单','卖币总数量','卖币总金额','ALPS余额','人民币挂单','买币总人民币','人民币买币数量','昨日人民币','充值人民币','可用人民币','提现','提后余额'];
            exportexcel($data,$title,'数据统计');exit;
        }
        $this->assign('list',$list);// 赋值数据集
        $this->assign('page',$show);// 赋值分页输出
        $this->display();
    }

}