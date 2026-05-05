<?php
/**
 * mail.php
 */

if (!defined('STORAGE_MODE')) { 
    // 防止直接访问，但在单元测试或独立引用时可移除此行
    if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
        header('HTTP/1.1 403 Forbidden'); exit('Access Denied'); 
    }
}

/**
 * 统一发送入口函数
 * @param string $to 收件人邮箱
 * @param string $subject 邮件标题
 * @param string $body 邮件正文 (HTML)
 * @param array $attachments 附件路径数组 ['/path/to/file.jpg', ...]
 * @param array $options 额外配置 ['debug'=>true, 'timeout'=>30, 'verify_ssl'=>true]
 */
function soulean_send_mail($to, $subject, $body, $attachments = [], $options = []) {
    // 1. 基础校验
    $to = filter_var(trim($to), FILTER_VALIDATE_EMAIL);
    if (!$to) {
        return ['success' => false, 'message' => 'Invalid Recipient Email', 'debug_log' => []];
    }

    // 2. 加载配置 
    $data = function_exists('soulean_load_data') ? soulean_load_data() : [];
    $s = $data['settings'] ?? [];

    // 合并传入的 Options
    $smtp_host = $s['smtp_host'] ?? '';
    if (empty($smtp_host)) {
        return ['success' => false, 'message' => 'SMTP Host not configured', 'debug_log' => []];
    }

    // 3. 智能端口与协议推断
    if (empty($s['smtp_port']) || empty($s['smtp_secure'])) {
        $host_lower = strtolower($smtp_host);
        if (preg_match('/(gmail|yahoo|aliyun|qq|foxmail)\.com/', $host_lower)) {
            $s['smtp_port'] = 465; 
            $s['smtp_secure'] = 'ssl';
        } else {
            $s['smtp_port'] = 587; 
            $s['smtp_secure'] = 'tls';
        }
    }

    // 4. 实例化发送类
    $smtp = new UltimateSMTP(
        $smtp_host,
        $s['smtp_port'],
        $s['smtp_user'] ?? '',
        $s['smtp_pass'] ?? '',
        $s['smtp_secure']
    );

    // 应用高级选项
    if (isset($options['debug'])) $smtp->debug = $options['debug'];
    if (isset($options['timeout'])) $smtp->timeout = $options['timeout'];
    if (isset($options['verify_ssl'])) $smtp->verifySSL = $options['verify_ssl'];
    
    // 5. 发送执行
    $result = $smtp->send($to, $subject, $body, $attachments);

    return [
        'success'   => $result,
        'message'   => $result ? 'Email sent successfully' : $smtp->getError(),
        'debug_log' => $smtp->getLog()
    ];
}

class UltimateSMTP {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $secure; // 'ssl', 'tls', or ''
    
    public $timeout = 15;       // 连接超时时间(秒)
    public $debug = false;      // 是否开启调试
    public $verifySSL = true;   // 生产环境建议 true，开发环境自签证书可设为 false
    
    private $socket;
    private $boundary;
    private $error = '';
    private $log = [];
    private $hostname;

    public function __construct($host, $port, $user, $pass, $secure = 'tls') {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->secure = strtolower($secure);
        $this->boundary = "Soulean_Mix_" . md5(uniqid(time(), true));
        $this->hostname = $this->detectHostname();
    }

    public function getLog() { return $this->log; }
    public function getError() { return $this->error; }

    /**
     * 发送邮件主逻辑
     */
    public function send($to, $subject, $body, $attachments = []) {
        $this->log = []; // 重置日志
        $this->error = '';
        
        // 校验附件是否存在
        $validAttachments = [];
        foreach ($attachments as $file) {
            if (file_exists($file) && is_readable($file)) {
                $validAttachments[] = $file;
            } else {
                $this->addLog("Warning: Attachment skipped (not found or unreadable): $file");
            }
        }

        $maxRetries = 3;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $this->addLog("--- Transmission Attempt $attempt/$maxRetries ---");
            
            if ($this->connect()) {
                if ($this->deliver($to, $subject, $body, $validAttachments)) {
                    $this->quit();
                    return true;
                }
            }
            
            $this->quit(); // 确保清理连接
            if ($attempt < $maxRetries) {
                sleep(1); // 失败冷却
            }
        }
        
        return false;
    }

    // --- 内部核心方法 ---

    private function connect() {
        $protocol = ($this->secure === 'ssl') ? 'ssl://' : 'tcp://';
        $remoteSocket = $protocol . $this->host . ':' . $this->port;
        
        $sslContext = [
            'ssl' => [
                'verify_peer'       => $this->verifySSL,
                'verify_peer_name'  => $this->verifySSL,
                'allow_self_signed' => !$this->verifySSL,
            ]
        ];

        $this->addLog("Connecting to $remoteSocket");
        
        $this->socket = @stream_socket_client(
            $remoteSocket, 
            $errno, 
            $errstr, 
            $this->timeout, 
            STREAM_CLIENT_CONNECT, 
            stream_context_create($sslContext)
        );

        if (!$this->socket) {
            $this->setError("Connection failed: $errstr ($errno)");
            return false;
        }

        stream_set_timeout($this->socket, $this->timeout);

        if (!$this->expect(220)) return false;

        // 握手
        if (!$this->sendCommand("EHLO " . $this->hostname, 250)) {
            if (!$this->sendCommand("HELO " . $this->hostname, 250)) return false;
        }

        // STARTTLS 升级
        if ($this->secure === 'tls') {
            if (!$this->sendCommand("STARTTLS", 220)) return false;
            
            // 智能加密算法选择 (兼容 PHP 5.6 - 8.x)
            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }

            if (!stream_socket_enable_crypto($this->socket, true, $cryptoMethod)) {
                $this->setError("TLS handshake failed. Server might not support TLS 1.2+");
                return false;
            }
            
            // TLS 后需要重新打招呼
            if (!$this->sendCommand("EHLO " . $this->hostname, 250)) return false;
        }

        // 认证
        if (!empty($this->user) && !empty($this->pass)) {
            if (!$this->sendCommand("AUTH LOGIN", 334)) return false;
            if (!$this->sendCommand(base64_encode($this->user), 334)) return false;
            if (!$this->sendCommand(base64_encode($this->pass), 235)) {
                $this->setError("Authentication failed. Check username/password.");
                return false;
            }
        }

        return true;
    }

    private function deliver($to, $subject, $body, $attachments) {
        // 生成 From 地址
        $fromEmail = !empty($this->user) ? $this->user : 'noreply@' . $this->hostname;
        
        // 邮件信封 (Envelope)
        if (!$this->sendCommand("MAIL FROM:<$fromEmail>", 250)) return false;
        if (!$this->sendCommand("RCPT TO:<$to>", 250)) return false;
        if (!$this->sendCommand("DATA", 354)) return false;

        // 构建 Header
        $headers = [];
        $headers[] = "Date: " . date('r');
        $headers[] = "To: <$to>";
        $headers[] = "From: Soulean <$fromEmail>"; // 可根据需求参数化 From Name
        $headers[] = "Subject: " . $this->encodeHeader($subject);
        $headers[] = "Message-ID: <" . md5(uniqid(time())) . "@" . $this->hostname . ">";
        $headers[] = "X-Mailer: Soulean Mailer v3.0";
        $headers[] = "MIME-Version: 1.0";
        
        if (!empty($attachments)) {
            $headers[] = "Content-Type: multipart/mixed; boundary=\"{$this->boundary}\"";
        } else {
            $headers[] = "Content-Type: text/html; charset=UTF-8";
            $headers[] = "Content-Transfer-Encoding: quoted-printable";
        }

        // 发送 Headers
        $this->socketWrite(implode("\r\n", $headers) . "\r\n\r\n");

        // 发送 Body
        if (!empty($attachments)) {
            // -- 正文部分 --
            $this->socketWrite("--{$this->boundary}\r\n");
            $this->socketWrite("Content-Type: text/html; charset=UTF-8\r\n");
            $this->socketWrite("Content-Transfer-Encoding: quoted-printable\r\n\r\n");
            $this->socketWrite($this->encodeQP($body) . "\r\n\r\n");

            // -- 附件部分 --
            foreach ($attachments as $file) {
                $filename = basename($file);
                $mimeType = $this->getMimeType($file);
                $content = chunk_split(base64_encode(file_get_contents($file)));

                $this->socketWrite("--{$this->boundary}\r\n");
                $this->socketWrite("Content-Type: $mimeType; name=\"$filename\"\r\n");
                $this->socketWrite("Content-Transfer-Encoding: base64\r\n");
                $this->socketWrite("Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n");
                $this->socketWrite($content . "\r\n");
            }
            $this->socketWrite("--{$this->boundary}--\r\n");
        } else {
            // 纯 HTML 模式
            $this->socketWrite($this->encodeQP($body) . "\r\n");
        }

        // 结束传输
        if (!$this->sendCommand(".", 250)) return false;
        
        return true;
    }

    private function quit() {
        if (is_resource($this->socket)) {
            $this->sendCommand("QUIT", 221);
            fclose($this->socket);
            $this->socket = null;
        }
    }

    // --- 辅助工具 ---

    private function sendCommand($cmd, $expectCode) {
        $logCmd = $cmd;
        // 隐藏日志中的密码
        if (strpos($cmd, 'AUTH') !== 0 && $this->pass && strpos($cmd, base64_encode($this->pass)) !== false) {
            $logCmd = '***PASSWORD***';
        }
        $this->addLog("CLIENT: $logCmd");
        
        if (!fwrite($this->socket, $cmd . "\r\n")) {
            $this->setError("Failed to write to socket");
            return false;
        }

        return $this->expect($expectCode);
    }

    private function expect($code) {
        $response = '';
        while ($str = fgets($this->socket, 515)) {
            $response .= $str;
            $this->addLog("SERVER: " . trim($str));
            // 处理多行响应 (第4位为连字符 '-' 表示还有后续)
            if (isset($str[3]) && $str[3] == ' ') break;
        }
        
        if (substr($response, 0, 3) != $code) {
            $this->setError("SMTP Error (Expected $code): " . trim($response));
            return false;
        }
        return true;
    }

    private function socketWrite($data) {
        // 确保符合 RFC 2821 长度限制，虽然 chunk_split 已经处理了大部分
        // 此处直接写入，不做日志以防日志过大
        fwrite($this->socket, $data);
    }

    private function detectHostname() {
        // 优先使用系统主机名，兼容 CLI
        $host = gethostname(); 
        if (!$host) {
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        }
        // 过滤非法的 HELO 字符
        return preg_replace('/[^a-zA-Z0-9.-]/', '', $host);
    }

    /**
     * 安全地编码邮件头，防止注入
     */
    private function encodeHeader($str) {
        // 移除换行符防止注入
        $str = str_replace(["\r", "\n"], '', $str);
        return '=?UTF-8?B?' . base64_encode($str) . '?=';
    }

    /**
     * 规范化 Quoted-Printable 编码
     * 自动处理每行 76 字符的软换行
     */
    private function encodeQP($string) {
        return quoted_printable_encode($string);
    }

    private function getMimeType($file) {
        // 1. 尝试使用 fileinfo 扩展 (最准确)
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file);
            finfo_close($finfo);
            if ($mime) return $mime;
        }

        // 2. 回退到基于后缀名的判断
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mimes = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
            'pdf' => 'application/pdf', 'zip' => 'application/zip', 'txt' => 'text/plain',
            'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        
        return $mimes[$ext] ?? 'application/octet-stream';
    }

    private function addLog($msg) {
        if ($this->debug) {
            $this->log[] = "[" . date('H:i:s') . "] " . $msg;
        }
    }

    private function setError($msg) {
        $this->error = $msg;
        $this->addLog("ERROR: $msg");
    }
}
?>