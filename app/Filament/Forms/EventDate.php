<?php

namespace App\Filament\Forms;

use App\Models\Photo;
use Filament\Forms\Components\TextInput;
use InvalidArgumentException;

class EventDate
{
    public static function normalize(?string $value): ?string
    {
        $value = trim($value ?? '');
        if ($value === '') {
            return null;
        }
        if (preg_match('/^(\d{4})年?$/u', $value, $m) && (int) $m[1] >= 1900 && (int) $m[1] <= 2100) {
            return $m[1];
        }
        if (preg_match('/^(\d{4})(?:-|年)(\d{1,2})(?:-|月)(\d{1,2})日?$/u', $value, $m)
            && (int) $m[1] >= 1900 && (int) $m[1] <= 2100 && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        throw new InvalidArgumentException('请输入1900—2100年的年份或真实日期，例如2022年、2022年12月18日或2022-12-18。');
    }

    public static function field(): TextInput
    {
        return TextInput::make('event_date')->label('事件日期')->placeholder('例如：2022年 或 2022年12月18日')
            ->helperText('可只填写年份；留空表示待补充，与上传、发布日期无关。')
            ->formatStateUsing(fn ($state, ?Photo $record) => $record?->eventDateLabel() ?? $state)
            ->rules([fn () => function ($attribute, $value, $fail) {
                try {
                    self::normalize($value);
                } catch (InvalidArgumentException $e) {
                    $fail($e->getMessage());
                }
            }])->dehydrateStateUsing(fn ($state) => self::normalize($state));
    }
}
