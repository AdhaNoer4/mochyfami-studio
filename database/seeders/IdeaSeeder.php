<?php

namespace Database\Seeders;

use App\Enums\ContentFormat;
use App\Enums\ContentIdeaStatus;
use App\Models\ContentCategory;
use App\Models\ContentIdea;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IdeaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $catBehavior = ContentCategory::where('slug', 'cat-behavior')->first();
        $catPov = ContentCategory::where('slug', 'cat-pov')->first();
        $catBiology = ContentCategory::where('slug', 'cat-biology')->first();
        $funnyFacts = ContentCategory::where('slug', 'cat-funny-facts')->first();

        $ideas = [
            [
                'category_id' => $catBehavior?->id,
                'title' => 'Kenapa Kucing Tiba-Tiba Lari Jam 3 Pagi?',
                'hook' => 'Pernah kebangun jam 3 pagi gara-gara kucing kamu kesurupan?',
                'concept' => 'Menjelaskan fenomena zoomies pada kucing saat malam hari karena energi berlebih.',
                'format' => ContentFormat::Educational,
                'status' => ContentIdeaStatus::Idea,
                'priority' => 'high',
                'notes' => 'Topik populer di kalangan pencinta kucing Indonesia.',
            ],
            [
                'category_id' => $catBiology?->id,
                'title' => 'Rahasia Kenapa Kucing Selalu Jatuh Berdiri',
                'hook' => 'Gimana bisa kucing jatuh dari tempat tinggi tapi tetep aman?',
                'concept' => 'Membahas refleks meluruskan (righting reflex) dan tulang belakang kucing yang fleksibel.',
                'format' => ContentFormat::Educational,
                'status' => ContentIdeaStatus::Idea,
                'priority' => 'medium',
                'notes' => 'Bisa tambahkan ilustrasi slow motion.',
            ],
            [
                'category_id' => $catPov?->id,
                'title' => 'POV: Kucing Nemuin Kardus Bekas Sepatu',
                'hook' => 'Kasur mahal 500 ribu vs Kardus bekas 0 rupiah. Mana yang dipilih?',
                'concept' => 'Sudut pandang kucing yang menganggap kardus sebagai benteng pertahanan terbaik.',
                'format' => ContentFormat::POV,
                'status' => ContentIdeaStatus::Selected,
                'priority' => 'high',
                'notes' => 'Gunakan suara narasi TTS santai dan kocak.',
            ],
            [
                'category_id' => $catBehavior?->id,
                'title' => 'Arti Gerakan Ekor Kucing yang Perlu Kamu Tahu',
                'hook' => 'Jangan asal elus! Perhatiin dulu gerak ekor kucing kamu.',
                'concept' => 'Penjelasan 5 gerakan ekor kucing dan maknanya (senang, marah, waspada).',
                'format' => ContentFormat::List,
                'status' => ContentIdeaStatus::Idea,
                'priority' => 'medium',
                'notes' => 'Bagus untuk format Shorts listicle.',
            ],
            [
                'category_id' => $funnyFacts?->id,
                'title' => '3 Bukti Bahwa Kucing Itu Benda Cair',
                'hook' => 'Kucing kamu pernah muat di dalam toples kaca?',
                'concept' => 'Fakta unik anatomi kucing yang fleksibel sehingga bisa muat di wadah sempit.',
                'format' => ContentFormat::FunnyFact,
                'status' => ContentIdeaStatus::Idea,
                'priority' => 'low',
                'notes' => 'Visual lucu kucing masuk mangkuk/jar.',
            ],
        ];

        foreach ($ideas as $ideaData) {
            ContentIdea::updateOrCreate(
                ['slug' => Str::slug($ideaData['title'])],
                [
                    'category_id' => $ideaData['category_id'],
                    'title' => $ideaData['title'],
                    'hook' => $ideaData['hook'],
                    'concept' => $ideaData['concept'],
                    'format' => $ideaData['format'],
                    'status' => $ideaData['status'],
                    'priority' => $ideaData['priority'],
                    'notes' => $ideaData['notes'],
                ]
            );
        }
    }
}
