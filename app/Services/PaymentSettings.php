<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class PaymentSettings
{
    public function credentials(): array
    {
        $state = Setting::value('payment', 'xunhu', []);

        return [...$this->defaults(), ...$state, 'secret' => filled($state['secret'] ?? null) ? Crypt::decryptString($state['secret']) : ''];
    }

    private function defaults(): array
    {
        return ['enabled' => false, 'appid' => '', 'secret' => '', 'gateway' => 'https://api.xunhuweb.com/payment/do.html', 'query_gateway' => '', 'refund_gateway' => ''];
    }

    public function formState(): array
    {
        $state = [...$this->defaults(), ...Setting::value('payment', 'xunhu', [])];
        $state['secret_saved'] = filled($state['secret']);
        $state['secret'] = '';

        return $state;
    }

    public function save(array $state, ?User $operator): void
    {
        $saved = Setting::value('payment', 'xunhu', []);
        $state = array_intersect_key($state, $this->defaults()) + $this->defaults();
        foreach (['gateway', 'query_gateway', 'refund_gateway'] as $key) {
            $state[$key] = trim((string) $state[$key]);
            if ($state[$key] !== '' && ! XunhuGateway::publicHttps($state[$key])) {
                throw ValidationException::withMessages(['payment.'.$key => '请填写渠道提供的公网 HTTPS 接口地址。']);
            }
        }
        $state['secret'] = filled($state['secret']) ? Crypt::encryptString($state['secret']) : ($saved['secret'] ?? '');
        if ($state['enabled'] && (blank($state['appid']) || blank($state['secret']) || blank($state['gateway']))) {
            throw ValidationException::withMessages(['payment.appid' => '启用收款前必须填写渠道 APPID、密钥及下单地址。']);
        }
        Setting::setValue('payment', 'xunhu', $state, $operator);
    }
}
