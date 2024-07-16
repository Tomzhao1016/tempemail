<?php
// 设置页面为UTF-8编码，以便正确显示中文字符
header('Content-Type: text/html; charset=utf-8');

// POP3服务器参数
$hostname = 'pop3.aliyun.com'; // POP3服务器地址
$port = 995; // POP3端口（通常为110，如果使用SSL则为995）
$username = 'xxx@aliyun.com'; // 邮箱地址
$password = '#'; // 邮箱密码

// 连接到POP3服务器
$connection = fsockopen("ssl://$hostname", $port, $errno, $errstr, 30);
if (!$connection) {
    die("无法连接到POP3服务器: $errstr ($errno)\n");
}

// 获取服务器响应
$response = fgets($connection);
if (strpos($response, '+OK') !== 0) {
    die("POP3服务器错误（初始连接）: $response\n");
}

// 发送用户认证信息
fputs($connection, "USER $username\r\n");
$response = fgets($connection);
if (strpos($response, '+OK') !== 0) {
    die("POP3服务器错误（USER命令）: $response\n");
}

fputs($connection, "PASS $password\r\n");
$response = fgets($connection);
if (strpos($response, '+OK') !== 0) {
    die("POP3服务器错误（PASS命令）: $response\n");
}

// 获取邮件数量
fputs($connection, "STAT\r\n");
$response = fgets($connection);
if (strpos($response, '+OK') !== 0) {
    die("POP3服务器错误（STAT命令）: $response\n");
}
list($status, $emailCount, $mailboxSize) = explode(' ', $response);

// 获取最新的20封邮件编号
$start = max(1, $emailCount - 19);
$end = $emailCount;

$emails = [];

for ($i = $end; $i >= $start; $i--) {
    // 获取邮件头部和内容
    fputs($connection, "RETR $i\r\n");
    $response = "";
    while (($line = fgets($connection)) !== false) {
        if (trim($line) === '.') {
            break;
        }
        $response .= $line;
    }

    // 获取邮件头部信息
    $headers = [];
    $headerPart = strstr($response, "\r\n\r\n", true);
    foreach (explode("\r\n", $headerPart) as $header) {
        if (strpos($header, ':') !== false) {
            list($key, $value) = explode(': ', $header, 2);
            $headers[$key] = $value;
        }
    }

    // 获取邮件内容
    $body = strstr($response, "\r\n\r\n");
    $body = trim($body);

    // 解析邮件内容
    // 检查邮件内容是否是 quoted-printable 编码
    if (strpos($body, 'Content-Transfer-Encoding: quoted-printable') !== false) {
        $mail_content = quoted_printable_decode($body);
    } else {
        $mail_content = $body;
    }

    $emails[] = [
        'subject' => isset($headers['Subject']) ? htmlspecialchars(mb_decode_mimeheader($headers['Subject'])) : 'N/A',
        'from' => isset($headers['From']) ? htmlspecialchars($headers['From']) : 'N/A',
        'date' => isset($headers['Date']) ? htmlspecialchars($headers['Date']) : 'N/A',
        'content' => nl2br(htmlspecialchars_decode($mail_content)),
        'index' => $emailCount - $i + 1
    ];
}

// 关闭连接
fputs($connection, "QUIT\r\n");
fclose($connection);

echo json_encode($emails);
?>
