<?php 
namespace Workerman\Protocols;
/**
 * WebSocket 协议服务端解包和打包
 * @author walkor <walkor@workerman.net>
 */

use Workerman\Connection\ConnectionInterface;

class Websocket implements \Workerman\Protocols\ProtocolInterface
{
    /**
     * websocket头部最小长度
     * @var int
     */
    const MIN_HEAD_LEN = 6;
    
    /**
     * websocket blob类型
     * @var char
     */
    const BINARY_TYPE_BLOB = "\x81";

    /**
     * websocket arraybuffer类型
     * @var char
     */
    const BINARY_TYPE_ARRAYBUFFER = "\x82";
    
    /**
     * 检查包的完整性
     * @param string $buffer
     */
    public static function input($buffer, ConnectionInterface $connection)
    {
        // 数据长度
        $recv_len = strlen($buffer);
        // 长度不够
        if($recv_len < self::MIN_HEAD_LEN)
        {
            return 0;
        }
        
        // 还没有握手
        if(empty($connection->handshake))
        {
            return self::dealHandshake($buffer, $connection);
        }
        
        $data_len = ord($buffer[1]) & 127;
        
        $opcode = ord($buffer[0]) & 0xf;
        switch($opcode)
        {
            // 附加数据帧 @todo 实现附加数据帧
            case 0x0:
                break;
            // 文本数据帧
            case 0x1:
                break;
            // 二进制数据帧
            case 0x2:
                break;
            // 关闭的包
            case 0x8:
                // 如果有设置onWebSocketClose回调，尝试执行
                if(isset($connection->onWebSocketClose))
                {
                    call_user_func($connection->onWebSocketClose, $connection);
                }
                // 默认行为是关闭连接
                else
                {
                    $connection->close();
                }
                return 0;
            // ping的包
            case 0x9:
                // 如果有设置onWebSocketPing回调，尝试执行
                if(isset($connection->onWebSocketPing))
                {
                    call_user_func($connection->onWebSocketPing, $connection);
                }
                // 默认发送pong
                else 
                {
                    $connection->send(pack('H*', '8a00'), true);
                }
                // 从接受缓冲区中消费掉该数据包
                if(!$data_len)
                {
                    $connection->consumeRecvBuffer(self::MIN_HEAD_LEN);
                    return 0;
                }
                break;
            // pong的包
            case 0xa:
                // 如果有设置onWebSocketPong回调，尝试执行
                if(isset($connection->onWebSocketPong))
                {
                    call_user_func($connection->onWebSocketPong, $connection);
                }
                // 从接受缓冲区中消费掉该数据包
                if(!$data_len)
                {
                    $connection->consumeRecvBuffer(self::MIN_HEAD_LEN);
                    return 0;
                }
                break;
            // 错误的opcode 
            default :
                $connection->close();
                return 0;
        }
        
        // websocket二进制数据
        $head_len = self::MIN_HEAD_LEN;
        if ($data_len === 126) {
            $pack = unpack('ntotal_len', substr($buffer, 2, 2));
            $data_len = $pack['total_len'];
            $head_len = 8;
        } else if ($data_len === 127) {
            $arr = unpack('N2', substr($buffer, 2, 8));
            $data_len = $arr[1]*4294967296 + $arr[2];
            $head_len = 14;
        }
        return $head_len + $data_len;
    }
    
    /**
     * 打包
     * @param string $buffer
     * @return string
     */
    public static function encode($buffer, ConnectionInterface $connection)
    {
        $len = strlen($buffer);
        $first_byte = $connection->protocolData['binaryType'];
        if($len<=125)
        {
            return $first_byte.chr($len).$buffer;
        }
        else if($len<=65535)
        {
            return $first_byte.chr(126).pack("n", $len).$buffer;
        }
        else
        {
            return $first_byte.chr(127).pack("xxxxN", $len).$buffer;
        }
    }
    
    /**
     * 解包
     * @param string $buffer
     * @return string
     */
    public static function decode($buffer, ConnectionInterface $connection)
    {
        $len = $masks = $data = $decoded = null;
        $len = ord($buffer[1]) & 127;
        if ($len === 126) {
            $masks = substr($buffer, 4, 4);
            $data = substr($buffer, 8);
        } else if ($len === 127) {
            $masks = substr($buffer, 10, 4);
            $data = substr($buffer, 14);
        } else {
            $masks = substr($buffer, 2, 4);
            $data = substr($buffer, 6);
        }
        for ($index = 0; $index < strlen($data); $index++) {
            $decoded .= $data[$index] ^ $masks[$index % 4];
        }
        return $decoded;
    }
    
    /**
     * 处理websocket握手
     * @param string $buffer
     * @param TcpConnection $connection
     * @return int
     */
    protected static function dealHandshake($buffer, $connection)
    {
        // 握手阶段客户端发送HTTP协议
        if(0 === strpos($buffer, 'GET'))
        {
            // 判断\r\n\r\n边界
            $heder_end_pos = strpos($buffer, "\r\n\r\n");
            if(!$heder_end_pos)
            {
                return 0;
            }
            
            // 解析Sec-WebSocket-Key
            $Sec_WebSocket_Key = '';
            if(preg_match("/Sec-WebSocket-Key: *(.*?)\r\n/", $buffer, $match))
            {
                $Sec_WebSocket_Key = $match[1];
            }
            else
            {
                $connection->close("HTTP/1.1 400 Bad Request\r\n\r\n400 Bad Request");
                return 0;
            }
            $new_key = base64_encode(sha1($Sec_WebSocket_Key."258EAFA5-E914-47DA-95CA-C5AB0DC85B11",true));
            // 握手返回的数据
            $new_message = "HTTP/1.1 101 Switching Protocols\r\n";
            $new_message .= "Upgrade: websocket\r\n";
            $new_message .= "Sec-WebSocket-Version: 13\r\n";
            $new_message .= "Connection: Upgrade\r\n";
            $new_message .= "Sec-WebSocket-Accept: " . $new_key . "\r\n\r\n";
            $connection->handshake = true;
            $connection->consumeRecvBuffer(strlen($buffer));
            $connection->send($new_message, true);
            $connection->protocolData = array(
                    'binaryType' => self::BINARY_TYPE_BLOB, // blob or arraybuffer
            );
            // 如果有设置onWebSocketConnect回调，尝试执行
            if(isset($connection->onWebSocketConnect))
            {
                self::parseHttpHeader($buffer);
                try
                {
                    call_user_func($connection->onWebSocketConnect, $connection, $buffer);
                }
                catch(\Exception $e)
                {
                    echo $e;
                }
                $_GET = $_COOKIE = $_SERVER = array();
            }
            return 0;
        }
        // 如果是flash的policy-file-request
        elseif(0 === strpos($buffer,'<polic'))
        {
            if('>' != $buffer[strlen($buffer) - 1])
            {
                return 0;
            }
            $policy_xml = '<?xml version="1.0"?><cross-domain-policy><site-control permitted-cross-domain-policies="all"/><allow-access-from domain="*" to-ports="*"/></cross-domain-policy>'."\0";
            $connection->send($policy_xml, true);
            $connection->consumeRecvBuffer(strlen($buffer));
            return 0;
        }
        // 出错
        $connection->close();
        return 0;
    }
    
    /**
     * 从header中获取
     * @param string $buffer
     * @return void
     */
    protected static function parseHttpHeader($buffer)
    {
        $header_data = explode("\r\n", $buffer);
        $_SERVER = array();
        list($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['SERVER_PROTOCOL']) = explode(' ', $header_data[0]);
        unset($header_data[0]);
        foreach($header_data as $content)
        {
            // \r\n\r\n
            if(empty($content))
            {
                continue;
            }
            list($key, $value) = explode(':', $content, 2);
            $key = strtolower($key);
            $value = trim($value);
            switch($key)
            {
                // HTTP_HOST
                case 'host':
                    $_SERVER['HTTP_HOST'] = $value;
                    $tmp = explode(':', $value);
                    $_SERVER['SERVER_NAME'] = $tmp[0];
                    if(isset($tmp[1]))
                    {
                        $_SERVER['SERVER_PORT'] = $tmp[1];
                    }
                    break;
                // HTTP_COOKIE
                case 'cookie':
                    $_SERVER['HTTP_COOKIE'] = $value;
                    parse_str(str_replace('; ', '&', $_SERVER['HTTP_COOKIE']), $_COOKIE);
                    break;
                // HTTP_USER_AGENT
                case 'user-agent':
                    $_SERVER['HTTP_USER_AGENT'] = $value;
                    break;
                // HTTP_REFERER
                case 'referer':
                    $_SERVER['HTTP_REFERER'] = $value;
                    break;
                case 'origin':
                    $_SERVER['HTTP_ORIGIN'] = $value;
                    break;
            }
        }
        
        // QUERY_STRING
        $_SERVER['QUERY_STRING'] = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        if($_SERVER['QUERY_STRING'])
        {
            // $GET
            parse_str($_SERVER['QUERY_STRING'], $_GET);
        }
        else
        {
            $_SERVER['QUERY_STRING'] = '';
        }
    }
}
