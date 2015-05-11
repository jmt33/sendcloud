<?php
/**
* @author: RunnerLee
* @email: runnerleer@gmail.com
* @blog: http://www.runnerlee.com/
* @time: 2015/5/8 11:36
*/

    header('Content-Type: text/html;charset=utf-8');

    require 'SendCloud.php';

    $send = new SendCloud('spamReported', 'get', 'post');

    $send->disabledHttps(true);

    $data = array(
        'days' => 15,
    );

    $send->addSubmitData($data);



    if(false === $result = $send->execute()) {
        echo $send->getMessage();
    }

    var_dump($result);

