<?php

namespace Database\Seeders;

use App\Models\ContentCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Cat Behavior',
                'description' => 'Explaining strange and interesting cat behaviors.',
                'color' => '#3B82F6',
            ],
            [
                'name' => 'Cat Body Language',
                'description' => 'Decoding what cat tail, ear, and body positions mean.',
                'color' => '#10B981',
            ],
            [
                'name' => 'Cat Biology',
                'description' => 'Fascinating biological traits and senses of felines.',
                'color' => '#8B5CF6',
            ],
            [
                'name' => 'Cat POV',
                'description' => 'Humorous perspectives imagined from a cat\'s point of view.',
                'color' => '#F59E0B',
            ],
            [
                'name' => 'Cat Funny Facts',
                'description' => 'Bite-sized funny and surprising trivia about cats.',
                'color' => '#EC4899',
            ],
            [
                'name' => 'Other Pets',
                'description' => 'Dogs, hamsters, rabbits, and domestic companions.',
                'color' => '#6366F1',
            ],
            [
                'name' => 'Birds',
                'description' => 'Intelligent and quirky bird species behavior.',
                'color' => '#06B6D4',
            ],
            [
                'name' => 'Wild Animals',
                'description' => 'Big cats, predators, and wildlife facts.',
                'color' => '#D97706',
            ],
            [
                'name' => 'Ocean Animals',
                'description' => 'Deep sea creatures and marine mammal mysteries.',
                'color' => '#0284C7',
            ],
            [
                'name' => 'Weird Animal Facts',
                'description' => 'Unusual evolutionary quirks across the animal kingdom.',
                'color' => '#84CC16',
            ],
        ];

        foreach ($categories as $cat) {
            ContentCategory::updateOrCreate(
                ['slug' => Str::slug($cat['name'])],
                [
                    'name' => $cat['name'],
                    'description' => $cat['description'],
                    'color' => $cat['color'],
                    'is_active' => true,
                ]
            );
        }
    }
}
