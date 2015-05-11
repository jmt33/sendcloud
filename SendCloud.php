<?php
/**
* @author: RunnerLee
* @email: runnerleer@gmail.com
* @blog: http://www.runnerlee.com/
* @time: 2015/5/7 17:57
*/
class SendCloud {

    private $_apiuser;
    private $_apikey;
    private $_conn;
    private $_interfaceModule;
    private $_interfaceAction;
    private $_format;
    private $_method;
    private $_enctype = 2;  //1为multipart/form-data，2为application/x-www-formurlencoded
    private $_submitData = array();
    private $errorMessage;
    private $_secureConnent = true; //默认以https提交
    private $_sendCloudHost = '://sendcloud.sohu.com/webapi/';



    public function __construct($module = '', $action = '', $method = 'post', $format = 'json')
    {
        if(!empty($module) && !empty($action)) {
            $this->_interfaceModule = $module;
            $this->_interfaceAction = $action;
        }
        $method = strtolower($method);
        $this->_method = $method=='post' || $method=='get' ? $method : 'post';

        $format = strtolower($format);
        $this->_format = $format=='json' || $format=='xml' ? $format : 'json';

        $this->_setUPConn();

        //测试
        $this->_apiuser = 'postmaster@subscription.linghit.com';
        $this->_apikey = 'NCshe5D71DPoakL1';
    }


    /**
     * 设置接口验证信息
     * @param string $apiuser
     * @param string $apikey
     */
    public function setAuth($apiuser, $apikey)
    {
        $this->_apiuser = $apiuser;
        $this->_apikey = $apikey;
        return true;
    }


    /**
     * 设置模块与动作
     * @param string $module
     * @param string $action
     */
    public function setInterface($module, $action)
    {
        $this->_reload();
        $this->_interfaceModule = $module;
        $this->_interfaceAction = $action;
    }


    /**
     * 启用/禁用 https
     * @param boolean $flag
     * @return bool
     */
    public function disabledHttps($flag)
    {
        $this->_secureConnent = $flag ? false : true;
        return true;
    }


    /**
     * 提供给外部调用的设置提交数据的方法，禁止通过此方法设置上传附件
     * @param string/array $arr
     * @param string $value
     * @throws Exception
     */
    public function addSubmitData($arr, $value=null)
    {
        if(!is_array($arr) && !is_null($value)) {
            $arr = array(
                $arr => $value,
            );
        }
        foreach($arr as $k=>$v) {
            if(is_numeric($k)) {
                throw new Exception('传入参数错误');
            }
            if($k === 'api_user' || $k === 'api_key') {
                throw new Exception('不允许修改Auth信息');
            }
            if(is_string($v) && substr($v, 0, 1) === '@') {
                $winFormat = substr($v, 0, 3);
                $unixFormat = substr($v, 0 ,2);
                if($unixFormat === '@/' || preg_match('/@[a-zA-Z]:/', $winFormat)) {
                    throw new Exception('带有特殊字符');
                }
            }
            $this->_setSubmitData($k, $v);
        }
    }


    /**
     * 外部调用执行
     * @throws Exception
     */
    public function execute()
    {
        if(!$this->_checkReady()) {
            throw new Exception('缺失重要的配置');
        }
        if(false === $result = $this->_exec()) {
            return false;
        }
        return $result;
    }


    /**
     * 获取错误信息
     * @return string
     */
    public function getMessage()
    {
        return 'SendCloud: ' . implode(', ', $this->errorMessage);
    }



    /**
     * 初始化curl
     * @return bool
     * @throws Exception
     */
    private function _setUPConn()
    {
        try {
            $this->_conn = curl_init();
        }catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
        $options = array(
            CURLOPT_SSL_VERIFYPEER  => 0,
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_HEADER          => 0,
        );
        curl_setopt_array($this->_conn, $options);
        return true;
    }


    /**
     * 往submitData中添加数据
     * @param string $index
     * @param string $value
     * @throws Exception
     */
    private function _setSubmitData($index, $value)
    {
        $this->_submitData[$index] = $value;
    }


    /**
     * 检查配置是否就绪
     * @return bool
     */
    private function _checkReady()
    {
        if(empty($this->_apikey) || empty($this->_apiuser)) {
            return false;
        }
        if(empty($this->_interfaceAction) || empty($this->_interfaceModule)) {
            return false;
        }
        return true;
    }


    /**
     * 拼接url
     * @return string
     */
    private function _buildUrl()
    {
        $url = $this->_secureConnent ? 'https' : 'http';
        $url .= $this->_sendCloudHost;
        $url .= $this->_interfaceModule . '.' . strtolower($this->_interfaceAction) . '.' . $this->_format; //模块名不能小写
        return $url;
    }


    /**
     * 解析请求返回结果, 统一返回数组
     * @param string $result
     * @return bool|array
     */
    private function _parseReturn($result)
    {
        if($this->_format === 'json') {
            $result = json_decode($result, true);
            if ('error' === $result['message']) {
                $this->errorMessage = $result['errors'];
                return false;
            }
        }else if($this->_format === 'xml') {
            $result = json_decode(json_encode(simplexml_load_string($result)), true);
            if('error' === $result['message']) {
                $this->errorMessage = $result['error'];
                return false;
            }
        }
        if($result['message'] === 'success') {
            unset($result['message']);
        }
        return $result;
    }


    /**
     * 执行
     * @return bool|mixed
     * @throws Exception
     */
    private function _exec()
    {
        $this->_setSubmitData('api_user', $this->_apiuser);
        $this->_setSubmitData('api_key', $this->_apikey);

        $url = $this->_buildUrl();
        if($this->_method === 'get') {
            $url .= '?' . http_build_query($this->_submitData);
            curl_setopt($this->_conn, CURLOPT_URL, $url);
        }else {
            $data = $this->_enctype === 1 ? $this->_submitData : http_build_query($this->_submitData);
            curl_setopt($this->_conn, CURLOPT_URL, $url);
            curl_setopt($this->_conn, CURLOPT_POST, 1);
            curl_setopt($this->_conn, CURLOPT_POSTFIELDS, $data);
        }
        if(false === $result = curl_exec($this->_conn)) {
            $this->errorMessage = curl_errno($this->_conn) . ':' . curl_error($this->_conn);
            return false;
        }
        return $this->_parseReturn($result);
    }


    /**
     * 重新初始化
     */
    private function _reload()
    {
        //清空所提交的数据
        $this->_submitData = array();
    }


    public function __destruct()
    {
        curl_close($this->_conn);
    }
}