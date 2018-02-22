<?php
namespace Home\Controller;

use Home\Controller\HomeController;
use Think\Page;
use Think\Upload;

class UserController extends HomeController
{
    //空操作
    public function _initialize()
    {
        parent::_initialize();
    }
    public function _empty()
    {

        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }
    public function index()
    {
        $where['member_id'] = $_SESSION['USER_KEY_ID'];
        $currency_user      = M('Currency_user')
            ->join("left join " . C('DB_PREFIX') . "currency on " . C('DB_PREFIX') . "currency.currency_id=" . C('DB_PREFIX') . "currency_user.currency_id")
            ->field('' . C('DB_PREFIX') . 'currency_user.*,(' . C('DB_PREFIX') . 'currency_user.num+' . C('DB_PREFIX') . 'currency_user.forzen_num) as count,' . C('DB_PREFIX') . 'currency.currency_name,' . C('DB_PREFIX') . 'currency.currency_mark')
            ->where($where)->order('sort')->select();
        $allmoneys = null;
        foreach ($currency_user as $k => $v) {
            $Currency_message = $this->getCurrencyMessageById($v['currency_id']);
            $allmoney         = $currency_user[$k]['count'] * $Currency_message['new_price'];
            $allmoneys += $allmoney;
        }
        $member_rmb = $this->member;
        $allmoneys  = $allmoneys + $member_rmb['count'];

        $u_info = M('Member')->field('rmb,forzen_rmb,integrals,forzen_integrals,daily_inc')->where($where)->find();
        $this->assign('u_info', $u_info);
        $this->assign('allmoneys', $allmoneys);
        $this->assign('currency_user', $currency_user);
        $this->display();
    }
    //激活外汇平台
    function activeWaihui(){
         $member_id = session('USER_KEY_ID');
         /*$mems = [7,38,98,41,42,99];
         if(!in_array($member_id,$mems)) $this->ajaxReturn(['status'=>0,'info'=>'接口测试中。。。']);*/
         $info = M('Member')->field('email,alps_code,pwd,user_levels,idcard,phone,parent,login')->where(['member_id'=>$member_id])->find();
         if($info['login']) $this->ajaxReturn(['status'=>0,'info'=>'已激活，请勿重复激活！']);
         $post_pwd = I('password','');
         if(md5($post_pwd)!=$info['pwd']) $this->ajaxReturn(['status'=>0,'info'=>'密码错误！']);
         if(!$info['alps_code']) $this->ajaxReturn(['status'=>0,'info'=>'请生成alps编号！']);
         $url = 'http://member.gz628.com/index.php?r=app/OpenAccount';
         $time = time();
         $key = 'GDSL28GSJGJ2G5YH6JSGS03S';
         $data = [
            'uid'=>$member_id,
            'username'=>$info['alps_code'],
            'password'=>$post_pwd,
            'level'=>$info['user_levels'],
            'email'=>$info['email'],
            'identity'=>$info['idcard'],
            'mobile'=>$info['phone'],
            //'parent'=>$info['parent'],
            'times'=>$time,
            'md5key'=>md5($time.md5($key))
         ];

         $re = curlPost($url,$data);
         $result = json_decode($re,true);
         if($result['status']==1){
            M('Member')->where(['member_id'=>$member_id])->save(['login'=>$result['login']]);//改变激活状态
         }
         $this->ajaxReturn($result);
    }

    /**
     * 修改会员信息
     */
    public function updateMassage()
    {
        header("Content-type: text/html; charset=utf-8");
        $member_id                  = session('USER_KEY_ID');
        $M_member                   = M('Member');
        $list                       = $M_member->where(array('member_id' => $member_id))->find();
        $list['area_name_city']     = M("Areas")->where(array('area_id' => $list['city']))->find()['area_name'];
        $list['area_name_province'] = M("Areas")->where(array('area_id' => $list['province']))->find()['area_name'];
        if (IS_POST) {
            $member_id        = I('post.member_id', '', 'intval');
            $data['nick']     = I('post.nick');
            $data['province'] = I('post.province', '', 'intval');
            $data['city']     = intval(I('city'));
            $data['job']      = I('post.job');
            $data['head']     = I('post.head');
            $data['profile']  = I('post.profile', '', 'html_entity_decode');
            if ($data['nick'] != $list['nick']) {
                $where              = null;
                $where['member_id'] = array('NEQ', $member_id);
                $where['nick']      = $data['nick'];
                if ($M_member->field('nick')->where($where)->select()) {
                    $data['status'] = 2;
                    $data['info']   = '昵称重复';
                    $this->ajaxReturn($data);
                }
            }
            if (empty($data['province'])) {
                $data['status'] = 2;
                $data['info']   = '请填写所在省份';
                $this->ajaxReturn($data);
            }
            if (empty($data['city'])) {
                $data['status'] = 2;
                $data['info']   = '请填写所在城市';
                $this->ajaxReturn($data);
            }
            $r = $M_member->where(array('member_id' => $member_id))->save($data);
            if ($r === false) {
                $data['status'] = 2;
                $data['info']   = '服务器繁忙,请稍后重试';
                $this->ajaxReturn($data);
            }
            $data['status'] = 1;
            $data['info']   = '修改成功';
            $this->ajaxReturn($data);
        } else {
            $this->User_status();
            $areas = M("Areas")->where('area_type = 1')->select();
            $this->assign('areas', $areas);
            $this->assign('list', $list);
            $this->display('update_massage');
        }
    }
    /**
     * 修改账号密码
     */
    public function updatePassword()
    {
        header("Content-type: text/html; charset=utf-8");
        if (IS_POST) {
            $oldPwd   = I('post.oldpwd', '', 'md5');
            $newPwd   = I('post.pwd', '', 'md5');
            $rePwd    = I('post.repwd', '', 'md5');
            $M_member = D('Member');
            if (!$M_member->checkPwd($_POST['oldpwd']) || !$M_member->checkPwd($_POST['pwd']) || !$M_member->checkPwd($_POST['repwd'])) {
                $data['status'] = 2;
                $data['info']   = '请输入6-20位密码';
                $this->ajaxReturn($data);
            }
            if ($rePwd != $newPwd) {
                $data['status'] = 2;
                $data['info']   = '两次输入的密码不一致';
                $this->ajaxReturn($data);
            }
            $r = $M_member->where(array('member_id' => session('USER_KEY_ID'), 'pwd' => $oldPwd))->find();
            if (!$r) {
                $data['status'] = 2;
                $data['info']   = '原始密码输入错误';
                $this->ajaxReturn($data);
            }
            if ($newPwd === $oldPwd) {
                $data['status'] = 2;
                $data['info']   = '新密码不能和密码一样';
                $this->ajaxReturn($data);
            }
            $data['pwd'] = $newPwd;
            //远程请求
            $url = C('daili_url').'/api/test_007';
            $time = time();
            $key = 'GDSL28GSJGJ2G5YH6JSGS03S';
            $md5key = md5($time.md5($key));
            $post = ['uid'=>$this->member['user_id'],'pwd1'=>I('post.pwd'),'pwd2'=>'','times'=>$time,'md5key'=>$md5key];
            $data2 = curlPost($url,$post);
            $r2 = json_decode($data2,true);
            if($r2['status']<1){
                $data['status'] = 2;
                $data['info']   = '代理中心信息修改失败';
                $this->ajaxReturn($data);
            }
            //是否激活外汇平台
            if(0){//$r['login']
                $url = C('waihui_url').'/index.php?r=app/modpwd';
                $time = time();
                $key = 'GDSL28GSJGJ2G5YH6JSGS03S';
                $md5key = md5($time.md5($key));
                $post = ['uid'=>$this->member['member_id'],'pwd'=>$newPwd,'times'=>$time,'md5key'=>$md5key];
                $data2 = curlPost($url,$post);
                $r2 = json_decode($data2,true);
                if($r2['status']==0){
                    $data['status'] = 2;
                    $data['info']   = '外汇平台密码修改失败';
                    $this->ajaxReturn($data);
                }
            }
            $s = $M_member->where(array('member_id' => session('USER_KEY_ID')))->save($data);
            if (!$r) {
                $data['status'] = 2;
                $data['info']   = '服务器繁忙请稍后重试';
                $this->ajaxReturn($data);
            }
            $data['status'] = 1;
            $data['info']   = '修改成功..请重新登录';
            session_destroy();
            $this->ajaxReturn($data);
        } else {
            $this->User_status();
            $this->display('update_password');
        }
    }

    /**
     * 币种与人民币兑换
     * @param number $rmb 人民币数量
     * @param number $bili 兑换比例
     * @param unknown $currency_id 兑换币种ID
     */
    public function rmbChangeCurrency($rmb = 0, $bili = 1, $currency_id)
    {
        $r[] = M('Member')->where('member_id=' . $_SESSION['USER_KEY_ID'])->setDec('rmb', $rmb);
        $r[] = M('Currency_user')->where('member_id=' . $_SESSION['USER_KEY_ID'] . ' and currency_id=' . $currency_id)->setInc('num', $rmb / $bili);
        if (!empty($r)) {
            $this->success('兑换成功');
        } else {
            $this->error('兑换失败');
        }
    }
    /**
     * 修改支付密码
     */
    public function updatePwdTrade()
    {
        if (IS_POST) {
            $M_member               = M('Member');
            $member_id              = session('USER_KEY_ID');
            $info                   = $M_member->where(array('member_id' => $member_id))->find();
            $data['pwd']            = I('post.oldpwd_b');
            $oldpwdtrade            = I('post.oldpwdtrade_b');
            $data['pwdtrade']       = I('post.pwdtrade');
            $repwdtrade             = I('post.repwdtrade');
            $data['add_time']       = time();
            $data['u_id']           = $member_id;
            $data['idcard']         = $info['idcard'];
            $data['idcardPositive'] = null; //判断后赋值
            $data['idcardSide']     = null; //判断后赋值
            $data['idcardHold']     = null; //判断后赋值
            /*$Examine                = M('Examine_pwdtrade')->where(array('u_id' => $member_id, 'status = 0'))->select();
            if ($Examine) {
                $this->error("您已提交过,正在审核中..");
                return;
            }*/
            if (!checkPwd($data['pwd'])) {
                $this->error("密码输入位数不正确");
                return;
            }
            if (!checkPwd($oldpwdtrade)) {
                $this->error("交易密码输入位数不正确");
                return;
            }
            if (!checkPwd($data['pwdtrade'])) {
                $this->error("新密码输入位数不正确");
                return;
            }
            if ($data['pwdtrade'] != $repwdtrade) {
                $this->error("两次支付密码输入不一致");
                return;
            }
            if ($info['pwd'] != md5($data['pwd'])) {
                $this->error("密码输入错误");
                return;
            }
            if ($info['pwdtrade'] != md5($oldpwdtrade)) {
                $this->error("原始支付密码输入错误");
                return;
            }
            if ($info['pwd'] == md5($data['pwdtrade'])) {
                $this->error("支付密码不能与登录密码一致");
                return;
            }
            /*$upload           = new Upload(); // 实例化上传类
            $upload->maxSize  = 3145728; // 设置附件上传大小
            $upload->exts     = array('jpg', 'gif', 'png'); // 设置附件上传类型
            $upload->rootPath = './Uploads/'; // 设置附件上传根目录
            $upload->savePath = 'User/Authentication/'; // 设置附件上传（子）目录
            $upload->saveName = array('getRandom', '15');

            // 上传文件
            $info = $upload->upload();
            if (!$info) {
            // 上传错误提示错误信息
            $this->error($upload->getError());
            return;
            }
            if (empty($info['pic_1'])) {
            $this->error('图片1' . $upload->getError());
            return;
            }
            if (empty($info['pic_2'])) {
            $this->error('图片2' . $upload->getError());
            return;
            }
            if (empty($info['pic_3'])) {
            $this->error('图片3' . $upload->getError());
            return;
            }
            $idcardPositive         = ltrim($upload->rootPath . $info['pic_1']["savepath"] . $info['pic_1']["savename"], '.');
            $idcardSide             = ltrim($upload->rootPath . $info['pic_2']["savepath"] . $info['pic_2']["savename"], '.');
            $idcardHold             = ltrim($upload->rootPath . $info['pic_3']["savepath"] . $info['pic_3']["savename"], '.');
             */
            $pwdtrade      = I('post.pwdtrade', '');
            /*$data['idcardPositive'] = ''; //$idcardPositive; //判断后赋值
            $data['idcardSide']     = ''; //$idcardSide; //判断后赋值
            $data['idcardHold']     = ''; //$idcardHold;

            $r = M('Examine_pwdtrade')->add($data);
            if ($r === false) {
                $this->error('服务器繁忙,请稍后重试');
            }
            $this->success('申请成功,审核后会以系统通知通知您', U('User/index'));*/
            //远程请求
            $url = C('daili_url').'/api/test_007';
            $time = time();
            $key = 'GDSL28GSJGJ2G5YH6JSGS03S';
            $md5key = md5($time.md5($key));
            $post = ['uid'=>$this->member['user_id'],'pwd1'=>'','pwd2'=>$pwdtrade,'times'=>$time,'md5key'=>$md5key];
            $data = curlPost($url,$post);
            $r2 = json_decode($data,true);
            if($r2['status']<1){
                $data['status'] = 2;
                $data['info']   = '代理中心信息修改失败';
                $this->error($data['info']);exit;
            }
            $re = M('Member')->where('member_id=' . $member_id)->save(['pwdtrade'=>md5($pwdtrade)]);
            if($re) $this->success('修改成功！');
            else $this->error('修改失败！');

        }
    }
    /**
     * 邀请好友
     */
    public function invit()
    {
        if (IS_POST) {
            $emails = I('post.emails');
            $list   = explode(";", $emails);
            $arr    = array();
            if ($list) {
                foreach ($list as $k => $vo) {
                    if (M('Member')->where(array('email' => $vo))->find()) {
                        $data['status'] = 0;
                        $data['info']   = "您输入的" . $vo . "邮箱已经注册";
                        $this->ajaxReturn($data);
                    } else {
                        $arr[] = $vo;
                    }
                }
                foreach ($arr as $vo) {
                    $url     = "http://" . $_SERVER['SERVER_NAME'] . U('Reg/Reg', array('Member_id' => session('USER_KEY_ID')));
                    $content = "<div>";
                    $content .= "您好，<br><br>请点击链接：<br>";
                    $content .= "<a target='_blank' href='{$url}' >完成注册邀请</a>";
                    $content .= "<br><br>如果链接无法点击，请复制并打开以下网址：<br>";
                    $content .= "<a target='_blank' href='{$url}' >{$url}</a>";
                    if (setPostEmail($this->config['EMAIL_HOST'], $this->config['EMAIL_USERNAME'], $this->config['EMAIL_PASSWORD'], $this->config['name'] . '团队', $vo, $this->config['name'] . '团队[注册邀请]', $content)) {
                        $data['status'] = 0;
                        $data['info']   = "邮箱" . $vo . "发送失败";
                        $this->ajaxReturn($data);
                    }
                }
                $data['status'] = 1;
                $data['info']   = "发送成功";
                $this->ajaxReturn($data);
            } else {
                $data['status'] = 0;
                $data['info']   = "请输入发送邮箱";
                $this->ajaxReturn($data);
            }
        }
        //我的邀请
        //         $my_invit = M('Member')->field('email,status,reg_time')->where(array('pid'=>session('USER_KEY_ID')))->select();

        $count    = M('Member')->where(array('pid' => session('USER_KEY_ID')))->count(); //根据分类查找数据数量
        $page     = new \Think\Page($count, 5); //实例化分页类，传入总记录数和每页显示数
        $show     = $page->show(); //分页显示输出性
        $my_invit = M('Member')->field('email,status,reg_time')->where(array('pid' => session('USER_KEY_ID')))->limit($page->firstRow . ',' . $page->listRows)->select(); //时间降序排列，越接近当前时间越高
        foreach ($my_invit as $k => $vo) {
            $my_invit[$k]['status_name'] = $vo['status'] ? "已填写个人信息" : "未填写个人信息";
        }

        $this->assign('page', $show);
        $this->assign('my_invit', $my_invit);

        $info  = M('Article')->where('position_id = 121')->find();
        $info1 = M('Article')->where('position_id = 122')->find();

        $info['title']    = html_entity_decode($info['title']);
        $info['content']  = html_entity_decode($info['content']);
        $info1['title']   = html_entity_decode($info1['title']);
        $info1['content'] = html_entity_decode($info1['content']);
        $this->assign('info', $info);
        $this->assign('info1', $info1);
        //邀请获得总金额
        $count = M('Finance')->where("member_id={$_SESSION['USER_KEY_ID']} and type=12")->sum('money');
        $this->assign('count', sprintf("%.2f", $count));
        $this->display();
    }
    /**
     * 系统消息
     */
    public function sysMassage()
    {
        $member_id          = session('USER_KEY_ID');
        $M_member           = M('Message');
        $where['member_id'] = $member_id;
        $count              = $M_member->where($where)->count(); // 查询满足要求的总记录数
        $Page               = new Page($count, 9); // 实例化分页类 传入总记录数和每页显示的记录数(25)
        $show               = $Page->show(); // 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = $M_member
            ->alias('a')
            ->field('a.message_id,a.message_all_id,a.title,a.type,a.add_time,a.status,b.name type_name')
            ->where($where)
            ->join('' . C('DB_PREFIX') . 'message_category as b on a.type = b.id')
            ->order(" a.status asc, a.add_time desc")
            ->limit($Page->firstRow . ',' . $Page->listRows)->select();
        //查询消息类型
        $this->assign('list', $list); // 赋值数据集
        $this->assign('page', $show); // 赋值分页输出
        $this->display('system_massage');
    }
    /**
     *显示详细系统消息界面
     */
    public function showSysTem()
    {
        $message_id              = I('message_id', '', 'intval');
        $message_all_id          = I('message_all_id', '', 'intval');
        $member_Id               = session('USER_KEY_ID');
        $where['member_id']      = $member_Id;
        $where['message_id']     = $message_id;
        $where['message_all_id'] = $message_all_id;
        $list                    = M('Message')
            ->alias('a')
            ->field('a.message_id,a.title,a.type,a.add_time,a.content,b.name type_name')
            ->where($where)
            ->join(C('DB_PREFIX') . 'message_category as b on a.type = b.id')
            ->find();
        //判断状态为0则是 未读 执行语句否则不执行标为已读
        if ($list['status'] == 0) {
            $status = M('Message')->where($where)->save(array('status' => 1));
            if ($status === false) {
                $this->error('服务器繁忙请稍后重试');
            }
        }
        if ($list == false) {
            header("HTTP/1.0 404 Not Found");
            $this->display('Public:404');
            return;
        }
        //右侧部分
        $where              = null;
        $where['member_id'] = $member_Id;
        $right              = M('Message')
            ->alias('a')
            ->field('a.message_id,a.message_all_id,a.title,a.type,a.add_time,a.content,b.name type_name')
            ->where($where)
            ->join(C('DB_PREFIX') . 'message_category as b on a.type = b.id')
            ->order(' a.add_time desc ')
            ->limit(4)
            ->select();
//下一页
        //        $after=M('Message')->where("message_id > ".$message_id. " and member_id = ".$member_Id)->order('message_id desc')->limit('1')->find();
        //        $this->assign('after',$after);
        $this->assign('list', $list);
        $this->assign('right', $right);
        $this->display();
    }

    /**
     * 添加提现银行信息
     * post
     * return ajax
     */
    public function insert()
    {
        $bank         = M('Bank');
        $member       = M('Member');
        $area         = M('Areas');
        $where['uid'] = session('USER_KEY_ID');
        //判断post是否为空
        if (IS_POST) {
            $info['bname']       = I("new_label");
            $info['address']     = I("shi");
            $info['cardnum']     = I("account");
            $info['bankname']    = I("bank");
            $info['cardname']    = $this->auth['name'];
            $info['uid']         = session('USER_KEY_ID');
            $info['bank_branch'] = I('bank_branch');

            if (empty($info['bname'])) {
                $data['status'] = 0;
                $data['info']   = '请填写标签';
                $this->ajaxReturn($data);
            }
            if (empty($info['bankname'])) {
                $data['status'] = 2;
                $data['info']   = '请选择银行';
                $this->ajaxReturn($data);
            }
            if (empty($info['address'])) {
                $data['status'] = 3;
                $data['info']   = '请选择开户地址';
                $this->ajaxReturn($data);
            }
            if (empty($info['bank_branch'])) {
                $data['status'] = 7;
                $data['info']   = '请选择开户支行';
                $this->ajaxReturn($data);
            }
            if (empty($info['cardnum'])) {
                $data['status'] = 4;
                $data['info']   = '请输入银行卡号';
                $this->ajaxReturn($data);
            }
            if (16 > strlen(I("account")) || strlen(I("account")) > 19) {

                $data['status'] = 5;
                $data['info']   = '请输入有效银行卡号';
                $this->ajaxReturn($data);
            }
            //是否已经存在提现帐号
            $re = $bank->where($where)->count();
            if ($re>=10) {
                $data['status'] = 8;
                $data['info']   = '提现地址限制10条。';
                $this->ajaxReturn($data);
            }
            //是否已经存在提现银行卡号
            /*$re = $bank->where(array('cardnum' => $info['cardnum']))->find();
            if ($re) {
                $data['status'] = 9;
                $data['info']   = '提现银行卡已存在。';
                $this->ajaxReturn($data);
            }*/
            $re = $bank->add($info);
            if ($re > 0) {
                $data['status'] = 1;
                $data['info']   = '操作成功';
                $this->ajaxReturn($data);
            } else {
                $data['status'] = 6;
                $data['info']   = '服务器繁忙,请稍后重试';
                $this->ajaxReturn($data);
            }
        }
    }

    /**
     *  提现显示信息及添加信息
     */
    public function draw()
    {
        $bank     = M('Bank');
        $member   = M('Member');
        $article  = M('article');
        $area     = M('Areas');
        $withdraw = M('Withdraw');

        $where['uid'] = session('USER_KEY_ID');
        //提示文章显示
        $art['content'] = html_entity_decode($article->where(C('DB_PREFIX') . 'article.position_id = 120')->find()['content']);
        //查找省份
        $province = $area->where('parent_id = 1')->select();
        //查找当前登录人的提现地址
        $field     = C('DB_PREFIX') . "bank.*,b.area_name as barea_name,a.area_name as aarea_name";
        $bank_info = $bank->field($field)->join(C('DB_PREFIX') . "areas as b ON b.area_id =" . C('DB_PREFIX') . "bank.address")
            ->join(C('DB_PREFIX') . "areas as a ON a.area_id = b.parent_id ")->where($where)->select();
        //检测是否有10个地址
        $count = $bank->where($where)->count();
        if ($count < 10) {
            $this->assign("num", 1);
        } else {
            $this->assign("num", 2);
        }
        //显示提现记录
        $draw_info = $withdraw
            ->field(C('DB_PREFIX') . "withdraw.withdraw_id, " . C('DB_PREFIX') . "bank.cardnum, " . C('DB_PREFIX') . "withdraw.all_money, " . C('DB_PREFIX') . "withdraw.money, " . C('DB_PREFIX') . "withdraw.add_time, " . C('DB_PREFIX') . "withdraw.status,". C('DB_PREFIX') . "withdraw.note")
            ->join(C('DB_PREFIX') . "bank ON " . C('DB_PREFIX') . "withdraw.bank_id =" . C('DB_PREFIX') . "bank.id")
            ->where(C('DB_PREFIX') . "withdraw.uid ={$_SESSION['USER_KEY_ID']}")
            ->order(C('DB_PREFIX') . "withdraw.add_time desc")
            ->limit(10)
            ->select();
        //显示可用余额
        $rmb = $member->field('rmb')->where("member_id ={$_SESSION['USER_KEY_ID']}")->find();

        $this->assign('rmb', $rmb);
        $this->assign('draw_info', $draw_info);
        $this->assign('bank_info', $bank_info);
        $this->assign('auth', $this->auth['name']); //传递真实姓名
        $this->assign('areas', $province);
        $this->assign('art', $art);
        $this->display();
    }
    /**
     * 积分兑换人民币
     * @return [type] [description]
     */
    function jifen(){
        $member = M('Member');
        $jifen = $member->where("member_id ={$_SESSION['USER_KEY_ID']}")->getField('integrals');
        if(IS_AJAX){
            $post = I('post.');
            $money = intval($post['money']);
            if($money>$jifen) $this->ajaxReturn(['info'=>'积分余额不足！']);
            $data['rmb'] = array('exp','rmb+'.$money);
            $data['integrals'] = array('exp','integrals-'.$money);
            $member->where("member_id ={$_SESSION['USER_KEY_ID']}")->save($data);
            $this->ajaxReturn(['info'=>'操作成功！']);
            exit;
        }
        $this->assign('integrals',$jifen);
        $this->display();
    }
    //积分日志
    function integrals(){
    	$where = [];
    	$where['member_id'] = $_SESSION['USER_KEY_ID'];
    	$log = M('integrals_log');
    	$count = $log->where($where)->count(); // 查询满足要求的总记录数
        $Page  = new \Think\Page($count, 10); // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $Page->show(); // 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = $log->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->order('id desc')->select();//dump($list);
        $this->assign('page', $show); // 赋值分页输出
        $this->assign('list', $list);
        $this->assign('count',$count);
        $pv = (($_GET['p']-1)<0)?0:($_GET['p']-1);
        $this->assign('pv',$pv);
    	$this->display();
    }
    //大盘释放积分管理
    function dapan(){
        $where = [];
        $where['user_id'] = $this->member['user_id'];
        $baodan = M('Baodan');
        $count = $baodan->where($where)->count(); // 查询满足要求的总记录数
        $Page  = new \Think\Page($count, 10); // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $Page->show(); // 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = $baodan->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->order('posttime desc')->select();//dump($list);
        $this->assign('page', $show); // 赋值分页输出
        $this->assign('list', $list);
        $this->assign('count',$count);
        $pv = (($_GET['p']-1)<0)?0:($_GET['p']-1);
        $this->assign('pv',$pv);
        $this->display();
    }
    //alps币转到商城
    function alps(){
        //如果需要添加财务日志，调用addFinance();
        $member_id = $_SESSION['USER_KEY_ID'];
        //查询可用alps币
        $num = M('currency_user')->where("member_id=$member_id")->getField('num');
        //兑出比例
        $currency_info = M('currency')->field('price_up,price_down')->where(['currency_id'=>30])->find();
        $radio = ($currency_info['price_up']+$currency_info['price_down'])/2;
        //转出记录
        $info = M('alps_log')->where(['member_id'=>$member_id,'platform'=>'alpsemall','status'=>1])->order('addtime desc')->limit(20)->select();
        foreach ($info as &$v) {
            if($v['platform']=='alpsemall') $v['money'].='人民币';
            if($v['platform']=='waihui' && $v['type']==2) $v['money'].='积分';
            if($v['platform']=='waihui' && $v['type']==1) $v['money'].='美元';
        }
        $this->assign('alps_info',$info);
        $this->assign('radio',$radio);
        $this->assign('num',$num);
        $this->display();
    }
    //alps币转到外汇
    function alps2(){
        //如果需要添加财务日志，调用addFinance();
        $member_id = $_SESSION['USER_KEY_ID'];
        //查询可用alps币
        $num = M('currency_user')->where("member_id=$member_id")->getField('num');
        //兑出比例
        $currency_info = M('currency')->field('price_up,price_down')->where(['currency_id'=>30])->find();
        $radio = ($currency_info['price_up']+$currency_info['price_down'])/2;
        //转出记录
        $info = M('alps_log')->where(['member_id'=>$member_id,'platform'=>'waihui','status'=>1])->order('addtime desc')->limit(20)->select();
        foreach ($info as &$v) {
            if($v['platform']=='alpsemall') $v['money'].='人民币';
            if($v['platform']=='waihui' && $v['type']==2) $v['money'].='积分';
            if($v['platform']=='waihui' && $v['type']==1) $v['money'].='美元';
        }
        $this->assign('alps_info',$info);
        $this->assign('radio',$radio);
        $this->assign('num',$num+$this->auth['alps_mt4']);
        $this->assign('utr',$this->config['utr']);
        $this->display();
    }
    function doAlps(){
        $member_id = $_SESSION['USER_KEY_ID'];
        $post = I('post.');
        $data['amount'] = intval($post['money']);
        $data['member_id'] = $member_id;

        //获取交易密码
        $uinfo = M('member')->field('pwdtrade,user_id,login,alps_code')->where(['member_id'=>$member_id])->find();
        //获取可用余额
        $currency_u = M('currency_user')->where("member_id=$member_id")->find();

        //验证密码
        if (md5($post['pwdtrade']) != $uinfo['pwdtrade']) {
            $info['status'] = 0;
            $info['info']   = "交易密码不正确";
            $this->ajaxReturn($info);
        }
        if($post['platform']=='alpsemall'){
            if ($data['amount'] > $currency_u['num']) {
                $info['status'] = 0;
                $info['info']   = "交易数量大于账户余额";
                $this->ajaxReturn($info);
            }
            if (empty($uinfo['user_id'])) {
                $info['status'] = 0;
                $info['info']   = "您不是代理会员，不能转出";
                $this->ajaxReturn($info);
            }
        }
        elseif($post['platform']=='waihui'){
            if ($data['amount'] > ($currency_u['num']+$this->auth['alps_mt4'])) {
                $info['status'] = 0;
                $info['info']   = "交易数量大于账户余额";
                $this->ajaxReturn($info);
            }
            if (empty($uinfo['login'])) {
                $info['status'] = 0;
                $info['info']   = "您还没有激活外汇平台帐号！";
                $this->ajaxReturn($info);
            }
        }else $this->ajaxReturn(['status'=>0,'info'=>'平台参数错误！']);

        //兑出比例
        $currency_info = M('currency')->field('price_up,price_down')->where(['currency_id'=>30])->find();
        $radio = ($currency_info['price_up']+$currency_info['price_down'])/2;

        //data信息
        $data['uid'] = $uinfo['user_id'];
        $data['money'] = floatval($data['amount']*$radio);
        $data['addtime'] = time();
        $data['platform'] = $post['platform'];
        //检查alps币是否等值2500美金
        $worth = $data['money']/$this->config['utr'];
        if ($worth < 2500) {
            $info['status'] = 0;
            $info['info']   = "alps币价值少于2500美金！";
            $this->ajaxReturn($info);
        }
        //alps商城
        if($post['platform']=='alpsemall'){
            //请求接口
            $url = C('alps_url');
            $req = [
                'uid'=>$data['uid'],
                'money'=>$data['money'],
                'times'=>$data['addtime'],
                'md5key'=>md5($data['addtime'].md5('GDSL28GSJGJ2G5YH6JSGS03S')),
                'message'=>'转入alps币'.$data['amount']
            ];
        }
        //外汇平台
        if($post['platform']=='waihui'){
            //请求接口
            $url = C('waihui_url').'/index.php?r=app/deposit';
            $req = [
                'login'=>$uinfo['login'],
                'username'=>$uinfo['alps_code'],
                'money'=>floatval($data['money']/$this->config['utr']*2),//大盘100%赠送
                'times'=>$data['addtime'],
                'md5key'=>md5($data['addtime'].md5('GDSL28GSJGJ2G5YH6JSGS03S')),
            ];
        }
        $re = curlPost($url,$req);
        $return = json_decode($re,true);
        if($return['status']==1){
            //插入alps_log表
            if($post['platform']=='waihui') $data['money'] = floatval($data['money']/$this->config['utr']*2);
            else $data['money'] = floatval($data['money']/$this->config['utr']);
            M('alps_log')->add($data);
            //帐号alps币扣减,alps_mt4优先扣减
            if($data['amount']>$this->auth['alps_mt4'] && $this->auth['alps_mt4']>0){
                M('member')->where(['member_id'=>$member_id])->setDec('alps_mt4',$this->auth['alps_mt4']);
                M('currency_user')->where("member_id=$member_id")->setDec('num',$data['amount']-$this->auth['alps_mt4']);
            }elseif($data['amount']<=$this->auth['alps_mt4']){
                M('member')->where(['member_id'=>$member_id])->setDec('alps_mt4',$data['amount']);
            }else M('currency_user')->where("member_id=$member_id")->setDec('num',$data['amount']);

            $info['status'] = 1;
            $info['info']   = "操作成功！";
            $this->ajaxReturn($info);
        }else{
            $info['status'] = 0;
            $info['info']   = "操作失败！";
            $this->ajaxReturn($info);
        }
    }
    /**
     * 删除提现地址
     */
    public function delete()
    {
        $bank = M('Bank');
        $id   = intval(I('post.id'));
        //查询选择地址是否有提现记录
        $count = M('Withdraw')->where(" bank_id = {$id}")->count();
        //有记录，不许删除
        if ($count != 0) {
            $arr['status'] = -1;
            $arr['info']   = "该地址尚有提现记录，无法删除！";
            $this->ajaxReturn($arr);
        }

        $re = $bank->delete($id);

        if ($re) {
            $arr['status'] = 1;
            $arr['info']   = "操作成功";
            $this->ajaxReturn($arr);
        } else {
            $arr['status'] = 0;
            $arr['info']   = "服务器繁忙,请稍后重试";
            $this->ajaxReturn($arr);
        }
    }

    /**
     * 提现金额
     */
    public function withdraw()
    {
        $withdraw  = M('Withdraw');
        $member    = M('Member');
        $da['key'] = "fee";
        //查询手续率所在表
        $list = M("Config")->where($da)->find();
        //查找member_id对应的交易密码
        $where['member_id'] = session('USER_KEY_ID');
        //查找member表uid对应信息（交易密码，可以金额，冻结金额）
        $mem_data = $member->field('pwdtrade,rmb,forzen_rmb')->where($where)->find();
        //交易密码
        if (IS_POST) {
            //一天只允许提现一次
            $start_time = strtotime(date('Y-m-d',time()));
            $end_time = $start_time+86400;
            $re = $withdraw->where(['uid'=>session('USER_KEY_ID'),'add_time'=>['between',$start_time.','.$end_time]])->find();
            if($re){
                $info['status'] = 0;
                $info['info']   = "每天只允许提现一次";
                $this->ajaxReturn($info);
            }
            $data['bank_id']   = I('post.select_bank');
            $data['all_money'] = floatval(I('post.money')); //提现金额
            $data['pwdtrade']  = md5(I('post.pwdtrade'));
            /*if ($_POST['code'] != $_SESSION['code']) {
                $info['status'] = 11;
                $info['info']   = '验证码不正确';
                $this->ajaxReturn($info);
            }*/
            if (empty($data['all_money'])) {
                $info['status'] = 0;
                $info['info']   = "请填写提现金额";
                $this->ajaxReturn($info);
            }
            //单笔在100至100000在之间
            if ($data['all_money'] < 100 || $data['all_money'] > 100000) {
                $info['status'] = 2;
                $info['info']   = "提现金额超出限制";
                $this->ajaxReturn($info);
            }
            //单日是否超出10W限制
            $res = $this->maxwithdeawOneday(floatval(I('post.money')));
            if ($res == false) {
                $info['status'] = 3;
                $info['info']   = "本次提现金额超出单日提现金额最大金额";
                $this->ajaxReturn($info);
            }
            //验证密码
            if (empty($data['pwdtrade'])) {
                $info['status'] = 4;
                $info['info']   = "请填写交易密码";
                $this->ajaxReturn($info);
            }
            if ($data['pwdtrade'] != $mem_data['pwdtrade']) {
                $info['status'] = 5;
                $info['info']   = "交易密码填写错误";
                $this->ajaxReturn($info);
            }

            //验证是否选取地址
            if (empty($data['bank_id'])) {
                $info['status'] = 6;
                $info['info']   = "请选择提现地址";
                $this->ajaxReturn($info);
            }

            if ($data['all_money'] > $mem_data['rmb']) {
                $info['status'] = 6;
                $info['info']   = "账户余额不足";
                $this->ajaxReturn($info);
            }

            if ($mem_data['rmb'] < 100) {
                $info['status'] = 7;
                $info['info']   = "现金少于100，不能提现";
                $this->ajaxReturn($info);
            }
            //应付手续费
            $data['withdraw_fee'] = floatval(I('post.money')) * $list['value'] * 0.01;
            //转出金额
            $data['all_money'] = $data['all_money']/2;
            //实际金额
            $data['money'] = (floatval(I('post.money')) - $data['withdraw_fee'])/2;
            //加时间
            $data['add_time'] = time();
            //加订单号
            $data['order_num'] = session('USER_KEY_ID') . "-" . $data['add_time'];
            //加uid辨明身份
            $data['uid'] = session('USER_KEY_ID');
            //保存可用金额修改信息
            $data_mem['rmb'] = $mem_data['rmb'] - floatval(I('post.money'));
            //保存冻结金额的修改信息
            $data_mem['forzen_rmb'] = $mem_data['forzen_rmb'] + floatval(I('post.money'));

            $res = $member->where($where)->save($data_mem);

            $re = $withdraw->add($data);

            if ($re) {
                if ($res) {
                    $info['status'] = 1;
                    $info['info']   = "提现成功，24小时内到账 ";
                    $this->ajaxReturn($info);
                } else {
                    $info['status'] = 8;
                    $info['info']   = "服务器繁忙,请稍后重试";
                    $this->ajaxReturn($info);
                }
            } else {
                $info['status'] = 9;
                $info['info']   = "服务器繁忙,请稍后重试";
                $this->ajaxReturn($info);
            }
        }
    }
    /**
     * 限制单日最多提现50万
     * @param float $num
     * @return boolean
     */
    private function maxwithdeawOneday($num)
    {
        //单日0时0分
        $time = strtotime(date('Y-M-d', time()));
        //从0时0分到当前时间
        $where['add_time'] = array("between", array($time, time()));

        $where['uid'] = $_SESSION['USER_KEY_ID']; //
        //总钱数
        $money = M('Withdraw')->where($where)->sum('all_money');
        //之前的总提现数是否超过了500000
        if ($money >= 500000) {
            return false;
        }
        //本次提现是否会超出500000
        $money_now = $money + $num;
        if ($money_now >= 500000) {
            return false;
        }
        return true;
    }

    public function chexiaoByid()
    {
        $withdraw = M('Withdraw');
        $member   = M('Member');
        $id       = I("post.id");
        if (empty($id)) {
            $data['status'] = 0;
            $data['info']   = "参数错误";
            $this->ajaxReturn($data);
        }
        //查询出对应id的提现金额,对应会员的会员id
        $money = $withdraw->field('uid,all_money,status')->where("withdraw_id = {$id}")->find();
        //对应会员的可用金额和冻结金额
        $rmb = $member->field('rmb,forzen_rmb')->where("member_id = {$money['uid']}")->find();
        //判断用户冻结金额是否出现负数
        if ($rmb['forzen_rmb'] < 0) {
            $data['status'] = 4;
            $data['info']   = "撤销失败，冻结金额有误，请联系管理员处理";
            $this->ajaxReturn($data);
        }
        //查状态是否在可操作的状态
        if ($money['status'] != 3) {
            $data['status'] = 5;
            $data['info']   = "请勿重复操作";
            $this->ajaxReturn($data);
        }
        //加回可用金额
        $money_back['rmb'] = $rmb['rmb'] + $money['all_money'];
        //减去冻结金额
        $money_back['forzen_rmb'] = $rmb['forzen_rmb'] - $money['all_money'];

        //修改数据库
        $re = $member->where("member_id = {$money['uid']}")->save($money_back);

        if (!$re) {
            $data['status'] = 2;
            $data['info']   = "撤销失败";
            $this->ajaxReturn($data);
        }
        $res = $withdraw->where("withdraw_id = {$id}")->delete();
        if (!$res) {
            $data['status'] = 3;
            $data['info']   = "撤销失败";
            $this->ajaxReturn($data);
        }
        $data['status'] = 1;
        $data['info']   = "撤销成功";
        $this->ajaxReturn($data);
    }

    /**
     * 充值
     */
    public function pay()
    {
        $config    = $this->config;
        $member    = $this->member;
        $order_num = $this->getPaycountByName($member['name']);
        //随机数
        $num = 0.01 * rand(10, 99);
        $fee = floatval($config['pay_fee'] + 0.01 * $order_num + $num);
        //支付表
        $where['member_name'] = $this->member['name'];
        $where['member_id']   = $this->member['member_id'];
        $where['type']        = array('NEQ', 3);
        //分页
        $pay   = M('Pay'); // 实例化User对象
        $count = $pay->where($where)->count(); // 查询满足要求的总记录数
        $Page  = new \Think\Page($count, 5); // 实例化分页类 传入总记录数和每页显示的记录数(25)
        $show  = $Page->show(); // 分页显示输出
        $list  = $pay->where($where)->order('pay_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        foreach ($list as $k => $v) {
            $list[$k]['status'] = payStatus($v['status']);
        }

        $bank = M('Website_bank')->where('status = 1')->select();
        //充值说明
        $art            = M('Article')->where('article_id=102')->find();
        $art['content'] = html_entity_decode($art['content']);
        $this->assign('art', $art);
        $this->assign('page', $show);
        $this->assign('bank', $bank);
        $this->assign('list', $list);
        $this->assign('fee', $fee);
        $this->display();
    }
    //支付宝充值
    public function alipay()
    {
        $config    = $this->config;
        $member    = $this->member;
        $order_num = $this->getPaycountByName($member['name']);
        //随机数
        $num = 0.01 * rand(10, 99);
        $fee = floatval($config['pay_fee'] + 0.01 * $order_num + $num);
        //支付表
        $where['member_name'] = $this->member['name'];
        $where['member_id']   = $this->member['member_id'];
        $where['type']        = array('NEQ', 3);
        //分页
        $pay   = M('Pay'); // 实例化User对象
        $count = $pay->where($where)->count(); // 查询满足要求的总记录数
        $Page  = new \Think\Page($count, 5); // 实例化分页类 传入总记录数和每页显示的记录数(25)
        $show  = $Page->show(); // 分页显示输出
        $list  = $pay->where($where)->order('pay_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        foreach ($list as $k => $v) {
            $list[$k]['status'] = payStatus($v['status']);
        }

        $bank = M('Website_bank')->where('status = 1')->select();
        //充值说明
        $art            = M('Article')->where('article_id=102')->find();
        $art['content'] = html_entity_decode($art['content']);
        $this->assign('art', $art);
        $this->assign('page', $show);
        $this->assign('bank', $bank);
        $this->assign('list', $list);
        $this->assign('fee', $fee);
        $this->display();
    }
    public function done()
    {
        //$num = $_POST['num'];
        $num = intval($_POST['num']);
        if ($num < 1) {
            $this->error('数值不能小于1！');
        }

        $price = $num;
        //添加订单
        $data = array(
            'member_name' => $this->member['name'],
            'add_time'    => time(),
            'status'      => 0,
            'account'     => date('YmdHis', time()) . uniqid('', true),
            'type'        => 2,
            'money'       => $price,
            'count'       => $price,
            'member_id'   => $this->member['member_id'],
        );

        M('Pay')->add($data);

        $pay_config['out_trade_no']      = $data['account'];
        $pay_config['subject']           = '人民币充值';
        $pay_config['logistics_payment'] = 'SELLER_PAY';
        $pay_config['price']             = $price;

        $pay_config1 = array(
            'seller_email' => 'qd_hitop@126.com',
            'partner'      => '2088421445811561',
            'key'          => 'ow6cmwbg59s2459rax0kia5q6l7d6qtm',
            'service'      => 'create_direct_pay_by_user',
        );
        $pay_config = array_merge($pay_config, $pay_config1);
        $pay        = new \Org\Util\Alipay($pay_config);
        $paybutton  = $pay->getCode();
        $this->assign('paybutton', $paybutton);

        $this->display();
    }
    public function respond()
    {
        $pay_config = array(
            'seller_email' => 'qd_hitop@126.com',
            'partner'      => '2088421445811561',
            'key'          => 'ow6cmwbg59s2459rax0kia5q6l7d6qtm',
            'service'      => 'create_direct_pay_by_user',
        );
        $pay = new \Org\Util\Alipay($pay_config);
        $r   = $pay->respond();
        $this->assign('jumpUrl', U('User/index'));
        if ($r) {
            $this->success('支付成功！');
        } else {
            $this->error('支付失败！');
        }
    }
    /**
     * 省级联动
     */
    public function getCity()
    {
        $area    = M("Areas");
        $area_id = intval($_POST['id']);
        if (!empty($area_id)) {
            $city_list = $area->where("parent_id='$area_id'")->select();
//           foreach ($city_list as $vo) {
            //               $op[] = '<option value="'.$vo['area_id'].'">'.$vo['area_name'].'</option>'; ;
            //           }
            //           $this->ajaxReturn($op);
            $this->ajaxReturn($city_list);
        }
    }
    /**
     * ajax上传图片方法
     */
    public function addPicForAjax()
    {
        //头像上传
        $upload           = new Upload(); // 实例化上传类
        $upload->maxSize  = 3145728; // 设置附件上传大小
        $upload->exts     = array('jpg', 'gif', 'png', 'jpeg'); // 设置附件上传类型
        $upload->rootPath = './Uploads/'; // 设置附件上传根目录
        $upload->savePath = 'Member/Head/'; // 设置附件上传（子）目录
        // 上传文件
        $info = $upload->upload();
        if (!$info) {
// 上传错误提示错误信息
            $arr['status'] = 0;
            $arr['info']   = $upload->getError();
            $this->ajaxReturn($arr);
        } else {
            // 上传成功
            $pic = ltrim($upload->rootPath . $info['Filedata']["savepath"] . $info['Filedata']["savename"], '.');
//            $pic_1=$info['head']['savepath'].$info['head']['savename'];
            //            $pic=ltrim($pic_1,".");
            $arr['status'] = 1;
            $arr['info']   = $pic;
            $this->ajaxReturn($arr);
        }
    }

    /**
     *  我的众筹显示页面
     */
    public function zhongchou()
    {

        $member_id = $_SESSION['USER_KEY_ID'];

        $count = M('Issue_log')->where("uid = {$member_id}")->count(); // 查询满足要求的总记录数
        $Page  = new \Think\Page($count, 10); // 实例化分页类 传入总记录数和每页显示的记录数
        $show  = $Page->show(); // 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性

        $list = M('Issue_log')
            ->field(C('DB_PREFIX') . 'issue_log.*,' . C('DB_PREFIX') . 'issue.id,' . C('DB_PREFIX') . 'issue.currency_id,' . C('DB_PREFIX') . 'issue.title')
            ->join(C('DB_PREFIX') . 'issue on ' . C('DB_PREFIX') . 'issue.id = ' . C('DB_PREFIX') . 'issue_log.iid')
            ->where(C('DB_PREFIX') . "issue_log.uid = {$member_id}")->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $this->assign('page', $show); // 赋值分页输出
        $this->assign('list', $list);
        $this->display();
    }

    public function substitutionCenter()
    {

        $this->display();
    }
    public function duihuan()
    {

        $this->display();
    }
}
