<?php

namespace App\Http\Controllers\WeChat;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Model\UserModel;
use Illuminate\Support\Facades\Redis;

class WeController extends Controller
{
    public function valid(){
        echo $_GET['echostr'];
    }

    public function wxPostEvent(){
        //接受微信服务器推送
        $content = file_get_contents("php://input");

        $time = date("Y-m-d H:i:s") . $content . "\n";

        file_put_contents('logs/wx_event.log',$time,FILE_APPEND);

        $objxml = simplexml_load_string($content);  //吧XML格式转为成对象格式
        $ToUserName=$objxml->ToUserName;     //开发者微信号
        $FromUserName=$objxml->FromUserName;   //用户的微信号
        $CreateTime=$objxml->CreateTime;     //时间
        $MsgType=$objxml->MsgType;        //消息类型
        $Event=$objxml->Event;          //事件


        if($MsgType=="event"){ //判断数据类型
            if($Event=="subscribe"){ //判断事件类型

                $userInfo=$this->userInfo($FromUserName);//获取用户昵称

                $one=UserModel::where(['openid'=>$FromUserName])->first();//查询数据库
                if($one){//判断用户是否是第一次关注
                    $xml="<xml>
                      <ToUserName><![CDATA[$FromUserName]]></ToUserName>
                      <FromUserName><![CDATA[$ToUserName]]></FromUserName>
                      <CreateTime>time()</CreateTime>
                      <MsgType><![CDATA[text]]></MsgType>
                      <Content><![CDATA[你好,欢迎".$userInfo['nickname']."回归]]></Content>
                    </xml>";//设置发送的xml格式
                    echo $xml;//返回结果
                }else{//如果是第一次关注
                    $array=[
                        "openid"=>$userInfo['openid'],
                        "nickname"=>$userInfo['nickname'],
                        "city"=>$userInfo['city'],
                        "province"=>$userInfo['province'],
                        "country"=>$userInfo['country'],
                        "headimgurl"=>$userInfo['headimgurl'],
                        "subscribe_time"=>$userInfo['subscribe_time'],
                        "sex"=>$userInfo['sex'],
                    ];//设置数组形式的数据类型
                    $res=UserModel::insertGetId($array);//存入数据库
                    if($res){//判断是否入库成功
                        $xml="<xml>
                      <ToUserName><![CDATA[$FromUserName]]></ToUserName>
                      <FromUserName><![CDATA[$ToUserName]]></FromUserName>
                      <CreateTime>time()</CreateTime>
                      <MsgType><![CDATA[text]]></MsgType>
                      <Content><![CDATA[你好,欢迎".$userInfo['nickname']."]]></Content>
                    </xml>";//设置xml格式的数据
                        echo $xml;//返回结果
                    }
                }
            }
        }

        echo "SUCCESS";
    }


    //获取用户的基本信息
    public function userInfo($FromUserName){
        $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$this->getAccessToken().'&openid='.$FromUserName.'&lang=zh_CN';
        $count = file_get_contents($url); //调用URL接口
        $info = json_decode($count,true); //XML格式转换成数组
        return $info;
    }



    //获取Access_token
    public function getAccessToken(){
        //是否有缓存
        $key = 'wx_access_token';
        $token = Redis::get($key);

        if($token){
            return $token;
        }else{
            $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.env('WX_APPID').'&secret='.env('WX_SECRET');

            $response = file_get_contents($url);

            $arr = json_decode($response,true);
            //缓存 access_token
            Redis::set($key,$arr['access_token']);
            Redis::expire($key,3600); //缓存一小时
            $token = $arr['access_token'];
        }
        return $token;
    }


    public function menu(){
        $accessToken = $this->getAccessToken();
        $url="https://api.weixin.qq.com/cgi-bin/menu/create?access_token=$accessToken";
        $arr = array(
            "button"=> array(
                array(
                    "name"=>"发送位置",
                    "type"=> "location_select",
                    "key"=> "rselfmenu_2_0"
                ),
                array(
                    'name'=>"发图",
                    "sub_button"=>array(
                        array(
                            "type"=>"pic_sysphoto",
                            "name"=>"系统拍照发图",
                            "key"=>"rselfmenu_1_0",
                            "sub_button"=>[ ]
                        ),
                        array(
                            "type"=>"pic_photo_or_album",
                            "name"=>"拍照或者相册发图",
                            "key"=>"rselfmenu_1_1",
                            "sub_button"=>[ ]
                        ),
                        array(
                            "type"=>"pic_weixin",
                            "name"=>"微信相册发图",
                            "key"=>"rselfmenu_1_2",
                            "sub_button"=>[ ]
                        ),
                    ),

                ),
                array(
                    'name'=>"玩具",
                    "type"=>"click",
                    "key"=>"bbb",
                    "sub_button"=>array(
                        array(
                            "type"=>"click",
                            "name"=>"店铺",
                            "key"=>"iii"
                        ),
                        array(
                            "type"=>"view",
                            "name"=>"百度",
                            "url"=>"https://www.baidu.com/"
                        ),

                    ),
                ),
                array(
                    'name'=>"推广",
                    "type"=>"click",
                    "key"=>"bbb",
                    "sub_button"=>array(
                        array(
                            "type"=>"scancode_waitmsg",
                            "name"=>"微信扫码",
                            "key"=>"iii"
                        ),
                    ),

                ),
            ),
        );
        $strjson = json_encode($arr,JSON_UNESCAPED_UNICODE);
        $clinet = new Client();
        $response = $clinet ->request("POST",$url,[
            'body'=>$strjson
        ]);
        $res_str = $response->getBody();
        echo $res_str;
    }

}
