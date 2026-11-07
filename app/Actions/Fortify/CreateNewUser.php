<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'phone' => [
                'required',
                'string',
                'regex:/^1[3-9]\d{9}$/',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ], [
            'email.required' => '请填写邮箱地址，邮箱地址为必填项。',
            'email.email' => '邮箱地址格式不正确，请填写有效的邮箱地址。',
            'email.unique' => '该邮箱地址已注册，请更换邮箱或直接登录。',
            'phone.required' => '请填写手机号码，手机号码为必填项。',
            'phone.regex' => '手机号码格式不正确，请填写以1开头、第二位为3至9的11位中国大陆手机号码。',
            'phone.unique' => '该手机号码已注册，请更换号码或直接登录。',
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'],
            'password' => $input['password'],
        ]);
    }
}
