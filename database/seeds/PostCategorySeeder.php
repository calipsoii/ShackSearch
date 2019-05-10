<?php

use Illuminate\Database\Seeder;

class PostCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        DB::table('post_category')->insert([
            'id' => '1',
            'category' => 'ontopic',
        ]);
        DB::table('post_category')->insert([
            'id' => '2',
            'category' => 'nws',
        ]);
        DB::table('post_category')->insert([
            'id' => '3',
            'category' => 'stupid',
        ]);
        DB::table('post_category')->insert([
            'id' => '4',
            'category' => 'political',
        ]);
        DB::table('post_category')->insert([
            'id' => '5',
            'category' => 'tangent',
        ]);
        DB::table('post_category')->insert([
            'id' => '6',
            'category' => 'informative',
        ]);
        DB::table('post_category')->insert([
            'id' => '7',
            'category' => 'nuked',
        ]);
    }
}
