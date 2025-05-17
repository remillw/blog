<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $languages = [
            ['name' => 'Français', 'slug' => 'francais', 'flag_code' => 'fr'],
            ['name' => 'English', 'slug' => 'english', 'flag_code' => 'gb'],
            ['name' => 'Español', 'slug' => 'espanol', 'flag_code' => 'es'],
            ['name' => 'Deutsch', 'slug' => 'deutsch', 'flag_code' => 'de'],
            ['name' => 'Italiano', 'slug' => 'italiano', 'flag_code' => 'it'],
            ['name' => 'Nederlands', 'slug' => 'nederlands', 'flag_code' => 'nl'],
            ['name' => 'Português', 'slug' => 'portugues', 'flag_code' => 'pt'],
            ['name' => 'Polski', 'slug' => 'polski', 'flag_code' => 'pl'],
            ['name' => 'Русский', 'slug' => 'russian', 'flag_code' => 'ru'],
            ['name' => '中文', 'slug' => 'chinese', 'flag_code' => 'cn'],
            ['name' => '日本語', 'slug' => 'japanese', 'flag_code' => 'jp'],
            ['name' => '한국어', 'slug' => 'korean', 'flag_code' => 'kr'],
        ];

        foreach ($languages as $language) {
            Language::create([
                'name' => $language['name'],
                'slug' => $language['slug'],
                'flag_code' => $language['flag_code'],
                'is_active' => true,
            ]);
        }
    }
}
