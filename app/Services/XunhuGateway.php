<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class XunhuGateway
{
    public static function sign(array $data, string $secret): string
    {
        unset($data['hash']);
        $data = array_filter($data, fn ($value) => $value !== '' && $value !== null);
        ksort($data, SORT_STRING);
        $parts = [];
        foreach ($data as $key => $value) {
            // 公开协议未说明嵌套对象的签名规则，拒绝猜测并阻止误入账。
            if (! is_scalar($value)) {
                throw new RuntimeException('渠道返回嵌套签名数据，需要核实渠道签名协议。');
            }
            $parts[] = $key.'='.$value;
        }

        return md5(implode('&', $parts).$secret);
    }

    public static function verify(array $data, string $secret): bool
    {
        return is_string($data['hash'] ?? null) && hash_equals(self::sign($data, $secret), strtolower($data['hash']));
    }

    public static function cents(mixed $amount): int
    {
        if (! is_scalar($amount) || ! preg_match('/^(0|[1-9]\d{0,7})(?:\.(\d{1,2}))?$/D', (string) $amount, $match)) {
            throw new RuntimeException('渠道金额格式不正确。');
        }

        return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
    }

    public static function publicHttps(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https'
            && is_string($host) && ! parse_url($url, PHP_URL_USER) && ! parse_url($url, PHP_URL_PASS)
            && ! in_array(strtolower($host), ['localhost', 'localhost.localdomain'])
            && (filter_var($host, FILTER_VALIDATE_IP) ? (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) : str_contains($host, '.'));
    }

    public function request(array $credentials, string $operation, array $data): array
    {
        $key = match ($operation) {
            'create' => 'gateway', 'query' => 'query_gateway', 'refund' => 'refund_gateway'
        };
        $url = (string) ($credentials[$key] ?? '');
        if (! self::publicHttps($url)) {
            throw new RuntimeException('尚未配置并核实该渠道的'.$operation.'接口。');
        }
        $data += ['appid' => $credentials['appid'], 'time' => time(), 'nonce_str' => Str::random(24)];
        $data['hash'] = self::sign($data, $credentials['secret']);
        // 不自动重试下单或退款；超时并不代表渠道未执行。
        $response = Http::asJson()->timeout(15)->connectTimeout(5)->withoutRedirecting()->post($url, $data);
        if (! $response->successful()) {
            throw new RuntimeException('渠道请求未确认，请稍后核对订单。');
        }
        $result = $response->json();
        if (! is_array($result) || ! self::verify($result, $credentials['secret']) || (string) ($result['errcode'] ?? '') !== '0') {
            throw new RuntimeException('渠道返回未通过验签或未确认成功，请核对订单。');
        }

        return $result;
    }
}
