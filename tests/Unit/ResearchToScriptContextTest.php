<?php

namespace Tests\Unit;

use App\Enums\ResearchClaimStatus;
use App\Services\Research\DTO\ResearchToScriptContext;
use App\Services\Research\ResearchToScriptContextService;
use App\Services\ResearchPipelineService;
use App\Services\ResearchQualityService;
use InvalidArgumentException;
use Tests\TestCase;

class ResearchToScriptContextTest extends TestCase
{
    private function service(): ResearchToScriptContextService
    {
        return new ResearchToScriptContextService(
            new ResearchQualityService,
            new ResearchPipelineService(new ResearchQualityService)
        );
    }

    public function test_classification_is_deterministic_and_based_on_state(): void
    {
        $service = $this->service();

        $this->assertSame(
            ResearchToScriptContext::CLASSIFICATION_USABLE,
            $service->classify(ResearchClaimStatus::Supported, true)
        );
        $this->assertSame(
            ResearchToScriptContext::CLASSIFICATION_UNSUPPORTED,
            $service->classify(ResearchClaimStatus::Supported, false)
        );
        $this->assertSame(
            ResearchToScriptContext::CLASSIFICATION_REQUIRES_VERIFICATION,
            $service->classify(ResearchClaimStatus::Unverified, true)
        );
        $this->assertSame(
            ResearchToScriptContext::CLASSIFICATION_REQUIRES_VERIFICATION,
            $service->classify(ResearchClaimStatus::Unverified, false)
        );
        $this->assertSame(
            ResearchToScriptContext::CLASSIFICATION_REQUIRES_VERIFICATION,
            $service->classify(ResearchClaimStatus::Uncertain, true)
        );
        $this->assertSame(
            ResearchToScriptContext::CLASSIFICATION_CONTRADICTED,
            $service->classify(ResearchClaimStatus::Contradicted, false)
        );
        $this->assertSame(
            ResearchToScriptContext::CLASSIFICATION_CONTRADICTED,
            $service->classify(ResearchClaimStatus::Contradicted, true)
        );
    }

    public function test_to_array_groups_claims_into_buckets_and_counts(): void
    {
        $context = new ResearchToScriptContext(
            report: [
                'id' => 1,
                'summary' => null,
                'status' => 'pending',
                'quality_ready' => false,
            ],
            claims: [
                [
                    'id' => 1,
                    'claim' => 'Supported with evidence.',
                    'importance' => 'high',
                    'status' => 'supported',
                    'classification' => ResearchToScriptContext::CLASSIFICATION_USABLE,
                    'has_evidence' => true,
                    'sources' => [
                        ['id' => 10, 'title' => 'Sumber A', 'domain' => 'cats.example', 'url' => 'https://cats.example/a', 'source_type' => 'article'],
                    ],
                ],
                [
                    'id' => 2,
                    'claim' => 'Still unverified.',
                    'importance' => 'high',
                    'status' => 'unverified',
                    'classification' => ResearchToScriptContext::CLASSIFICATION_REQUIRES_VERIFICATION,
                    'has_evidence' => true,
                    'sources' => [],
                ],
                [
                    'id' => 3,
                    'claim' => 'Contradicted.',
                    'importance' => 'low',
                    'status' => 'contradicted',
                    'classification' => ResearchToScriptContext::CLASSIFICATION_CONTRADICTED,
                    'has_evidence' => false,
                    'sources' => [],
                ],
                [
                    'id' => 4,
                    'claim' => 'Supported without evidence.',
                    'importance' => 'low',
                    'status' => 'supported',
                    'classification' => ResearchToScriptContext::CLASSIFICATION_UNSUPPORTED,
                    'has_evidence' => false,
                    'sources' => [],
                ],
            ],
            quality: ['ready' => false, 'score' => 25, 'summary' => [], 'blockers' => [], 'warnings' => []],
            pipeline: ['stage' => 'reviewing', 'stage_label' => 'Reviewing', 'progress' => 75, 'ready_for_script' => false, 'next_actions' => []],
        );

        $data = $context->toArray();

        $this->assertCount(4, $data['claims']);
        $this->assertCount(1, $data['usable_claims']);
        $this->assertSame(1, $data['usable_claims'][0]['id']);
        $this->assertCount(1, $data['claims_requiring_verification']);
        $this->assertSame(2, $data['claims_requiring_verification'][0]['id']);
        $this->assertCount(1, $data['contradicted_claims']);
        $this->assertSame(3, $data['contradicted_claims'][0]['id']);
        $this->assertCount(1, $data['unsupported_claims']);
        $this->assertSame(4, $data['unsupported_claims'][0]['id']);
        $this->assertFalse($data['report']['quality_ready']);
        $this->assertSame('pending', $data['report']['status']);
        $this->assertSame(4, $data['report']['counts']['total_claims']);
        $this->assertSame(2, $data['report']['counts']['evidence_backed_claims']);
        $this->assertSame(1, $data['report']['counts']['usable_claims']);
        $this->assertSame(1, $data['report']['counts']['claims_requiring_verification']);
        $this->assertSame(1, $data['report']['counts']['contradicted_claims']);
        $this->assertSame(1, $data['report']['counts']['unsupported_claims']);
    }

    public function test_source_summaries_keep_traceability_fields(): void
    {
        $context = new ResearchToScriptContext(
            report: ['id' => 1, 'summary' => null, 'status' => 'pending', 'quality_ready' => true],
            claims: [
                [
                    'id' => 1,
                    'claim' => 'Supported claim.',
                    'importance' => 'high',
                    'status' => 'supported',
                    'classification' => ResearchToScriptContext::CLASSIFICATION_USABLE,
                    'has_evidence' => true,
                    'sources' => [
                        ['id' => 5, 'title' => 'National Geographic', 'domain' => 'natgeo.example', 'url' => 'https://natgeo.example/cats', 'source_type' => 'news'],
                    ],
                ],
            ],
            quality: ['ready' => true, 'score' => 100, 'summary' => [], 'blockers' => [], 'warnings' => []],
            pipeline: ['stage' => 'ready_for_script', 'stage_label' => 'Ready for Script', 'progress' => 100, 'ready_for_script' => true, 'next_actions' => []],
        );

        $source = $context->claims[0]['sources'][0];

        $this->assertSame(5, $source['id']);
        $this->assertSame('National Geographic', $source['title']);
        $this->assertSame('natgeo.example', $source['domain']);
        $this->assertSame('https://natgeo.example/cats', $source['url']);
        $this->assertSame('news', $source['source_type']);
    }

    public function test_rejects_claim_with_invalid_classification(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ResearchToScriptContext(
            report: ['id' => 1, 'summary' => null, 'status' => 'pending', 'quality_ready' => false],
            claims: [
                ['id' => 1, 'claim' => 'Anything.', 'importance' => 'high', 'status' => 'supported', 'classification' => 'bogus', 'has_evidence' => false],
            ],
            quality: [],
            pipeline: [],
        );
    }

    public function test_rejects_claim_with_missing_field(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ResearchToScriptContext(
            report: ['id' => 1, 'summary' => null, 'status' => 'pending', 'quality_ready' => false],
            claims: [
                ['id' => 1, 'claim' => 'Anything.', 'status' => 'supported'],
            ],
            quality: [],
            pipeline: [],
        );
    }
}
