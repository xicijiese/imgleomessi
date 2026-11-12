<?php

namespace App\Filament\Forms;

use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryOption
{
    public static function options(Category $root): array
    {
        $children = $root->children()->where('visibility', 'public')->get();
        if (in_array($root->slug, ['season', 'year'], true)) {
            $children = $children->sortByDesc(function (Category $category): int {
                if (! preg_match('/(\d{4}|\d{2})/u', $category->name, $matches)) {
                    return PHP_INT_MIN;
                }
                $year = (int) $matches[1];
                $year = $year < 100 ? 2000 + $year : $year;

                return $year * 10 + (str_contains($category->name, '之后') ? 1 : (str_contains($category->name, '之前') ? -1 : 0));
            });
        }

        return $children->pluck('name', 'id')->all();
    }

    public static function allowCreation(Select $select, Category $root): Select
    {
        if (! in_array($root->slug, ['scene', 'image-type', 'person-relation'], true)) {
            return $select;
        }

        return $select
            ->createOptionModalHeading('新增'.$root->name)
            ->createOptionForm([
                TextInput::make('name')->label($root->name.'名称')->required()->maxLength(255)
                    ->rules(['regex:/\S/u'])
                    ->validationMessages(['regex' => '名称不能只包含空格。']),
            ])
            ->createOptionUsing(function (array $data) use ($root): int {
                abort_unless(auth()->user()?->canAccessAdminPanel(), 403);
                $name = trim($data['name']);
                $existing = $root->children()->where('name', $name)->first();
                if ($existing) {
                    if ($existing->visibility !== 'public') {
                        throw ValidationException::withMessages(['name' => '同名分类已隐藏，请先在子分类管理中检查。']);
                    }

                    return (int) $existing->id;
                }

                $base = substr($root->slug.'-'.Str::slug(Str::transliterate($name)), 0, 220);
                $slug = $base;
                while (Category::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.Str::lower(Str::random(8));
                }

                return (int) $root->children()->create([
                    'name' => $name, 'slug' => $slug, 'visibility' => 'public', 'is_system' => false,
                ])->id;
            });
    }
}
