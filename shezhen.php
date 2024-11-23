<?php
// 基础配置
$host = 'https://ali-market-tongue-detect-v2.macrocura.com';
$path = '/diagnose/face-tongue/result/';
$report_path = '/diagnose/face-tongue/report/';
$appcode = 'e82b9c284ef44e5cb134910838573274'; // 更新后的 appcode
$url = $host . $path;

// 全局定义请求头
$headers = array(
    'Authorization: APPCODE ' . $appcode,
    'Content-Type: application/json; charset=UTF-8'
);

// 处理请求数据
$step = '';
$session_id = '';
$answers = array();

// 判断是否为 POST 请求
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 检查 Content-Type
    $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Content-Type: $contentType\n", FILE_APPEND);
    
    if (strpos($contentType, 'application/json') !== false) {
        // 解析 JSON 输入
        $input = trim(file_get_contents("php://input"));
        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Raw Input: $input\n", FILE_APPEND);
        $data = json_decode($input, true);
        if (!is_array($data)) {
            echo json_encode(array('success' => false, 'msg' => '无效的 JSON 数据'));
            exit;
        }
        $step = isset($data['step']) ? $data['step'] : '';
        $session_id = isset($data['session_id']) ? $data['session_id'] : '';
        if ($step === 'answer_questions') {
            $answers = isset($data['answers']) ? $data['answers'] : array();
        }
    } else {
        // 解析 form-data
        $step = isset($_POST['step']) ? $_POST['step'] : '';
        $session_id = isset($_POST['session_id']) ? $_POST['session_id'] : '';
    }

    // 日志记录请求步骤和参数
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Step: $step\n", FILE_APPEND);
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Session ID: $session_id\n", FILE_APPEND);
    if ($step === 'answer_questions') {
        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Answers Data: " . print_r($answers, true) . "\n", FILE_APPEND);
    } else {
        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Request Data: " . print_r($_POST, true) . "\n", FILE_APPEND);
        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - FILES DATA: " . print_r($_FILES, true) . "\n", FILE_APPEND);
    }

    switch ($step) {
        case 'upload_tf_image':
        case 'upload_ff_image':
        case 'upload_tb_image':
            // 检查文件上传情况
            if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['image']['tmp_name'];
                $image_base64 = base64_encode(file_get_contents($tmp_name));

                $body = array(
                    'scene' => 1,
                    $step => $image_base64
                );

                if ($session_id) {
                    $body['session_id'] = $session_id;
                }

                $post_data = json_encode($body);
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Sending request to $url with data: $post_data\n", FILE_APPEND);

                // 发起请求
                $response = send_request($url, $post_data, $headers);
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Received response: $response\n", FILE_APPEND);

                if ($response === '') {
                    echo json_encode(array('success' => false, 'msg' => '外部 API 未返回任何数据'));
                    exit;
                }

                echo $response;
                exit;
            } else {
                echo json_encode(array('success' => false, 'msg' => '上传文件失败或未接收到文件'));
                exit;
            }
            break;

        case 'answer_questions':
            // 处理回答并生成报告
            if (empty($session_id)) {
                echo json_encode(array('success' => false, 'msg' => '缺少 session_id，请重新开始'));
                exit;
            }

            if (empty($answers)) {
                echo json_encode(array('success' => false, 'msg' => '缺少回答内容'));
                exit;
            }

            // 将 'answers' 转换为数组的格式
            $answers_array = array();
            foreach ($answers as $name => $value) {
                $answers_array[] = array('name' => $name, 'value' => $value);
            }
            
            $body = array(
                'session_id' => $session_id,
                'answers' => $answers_array
            );
            $post_data = json_encode($body);
            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Prepared report request data: $post_data\n", FILE_APPEND);


            // 准备请求
            $report_url = $host . $report_path;

            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Sending request to $report_url with data: $post_data\n", FILE_APPEND);

            // 发起请求
            $response = send_request($report_url, $post_data, $headers);
            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Received response: $response\n", FILE_APPEND);

            if ($response === '') {
                echo json_encode(array('success' => false, 'msg' => '外部 API 未返回任何数据'));
                exit;
            }

            echo $response;
            exit;
            break;

        default:
            echo json_encode(array('success' => false, 'msg' => '无效的操作步骤'));
            exit;
            break;
    }
} else {
    echo json_encode(array('success' => false, 'msg' => '请求方法错误'));
    exit;
}

// 发送请求的函数
function send_request($url, $post_data, $headers) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_HEADER, true); // 获取响应头

    $response = curl_exec($ch);
    if ($response === false) {
        $error_msg = "请求失败: " . curl_error($ch);
        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - send_request error: $error_msg\n", FILE_APPEND);
        curl_close($ch);
        return json_encode(array('success' => false, 'msg' => $error_msg));
    }

    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - HTTP Code: $http_code\n", FILE_APPEND);
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Response Headers: $header\n", FILE_APPEND);
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Response Body: $body\n", FILE_APPEND);

    curl_close($ch);

    return $body;
}
?>