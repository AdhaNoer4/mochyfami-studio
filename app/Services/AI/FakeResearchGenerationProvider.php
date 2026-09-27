<?php

namespace App\Services\AI;

use App\Enums\ResearchClaimImportance;
use App\Services\AI\Contracts\ResearchGenerationProvider;
use App\Services\AI\DTO\ResearchGenerationRequest;
use App\Services\AI\DTO\ResearchGenerationResponse;

/**
 * Deterministic, fully offline demo provider.
 *
 * This provider produces clearly-labelled research CANDIDATES only. It never
 * fabricates URLs, never invents verification, and every claim is returned
 * as unverified. It exists for tests and local development; wiring a real
 * provider is an explicit future step behind the same contract.
 */
class FakeResearchGenerationProvider implements ResearchGenerationProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function generate(ResearchGenerationRequest $request): ResearchGenerationResponse
    {
        $count = min($request->maxClaims, 4);

        $claims = [];
        $importances = [ResearchClaimImportance::High, ResearchClaimImportance::Medium, ResearchClaimImportance::Low];

        for ($i = 0; $i < $count; $i++) {
            $claims[] = [
                'claim' => 'Kandidat klaim ke-'.($i + 1)." untuk pertanyaan '{$request->question}' tentang '{$request->topic}'; belum diverifikasi secara manual.",
                'importance' => $importances[$i % count($importances)],
            ];
        }

        return new ResearchGenerationResponse(
            provider: 'fake',
            summary: "Ringkasan sementara untuk '{$request->topic}'. Kandidat ini harus diverifikasi terhadap sumber yang dapat dipercaya sebelum dianggap fakta.",
            claims: $claims,
            sources: [],
        );
    }
}
