<?php
class baseController
{
    public function tip($errCode, $errMsg, $data)
    {
        echo json_encode(array(
            'errCode' => $errCode,
            'errMsg' => $errMsg,
            'data' => $data,
        ));
        exit;
    }
}