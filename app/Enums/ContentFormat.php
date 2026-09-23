<?php

namespace App\Enums;

enum ContentFormat: string
{
    case Educational = 'educational';
    case POV = 'pov';
    case Storytelling = 'storytelling';
    case FunnyFact = 'funny_fact';
    case Comparison = 'comparison';
    case List = 'list';

    public function label(): string
    {
        return match ($this) {
            self::Educational => 'Educational',
            self::POV => 'POV (Point of View)',
            self::Storytelling => 'Storytelling',
            self::FunnyFact => 'Funny Fact',
            self::Comparison => 'Comparison',
            self::List => 'Listicle / Top Facts',
        };
    }
}
