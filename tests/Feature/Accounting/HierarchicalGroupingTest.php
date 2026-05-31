<?php

namespace Tests\Feature\Accounting;

use App\DTO\DocumentDTO;
use App\Models\Accounting\DocumentLineItemGroup;
use App\Models\Accounting\Estimate;
use App\Models\Common\Offering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HierarchicalGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_items_can_be_grouped_into_two_levels()
    {
        $this->withOfferings();

        $estimate = Estimate::factory()->for($this->testCompany)->create();
        $estimate->lineItems()->delete();

        $offering = Offering::first();

        // Level 1 Group
        $parentGroup = DocumentLineItemGroup::create([
            'company_id' => $this->testCompany->id,
            'documentable_type' => $estimate->getMorphClass(),
            'documentable_id' => $estimate->id,
            'name' => 'Parent Group',
        ]);

        // Level 2 Group
        $childGroup = DocumentLineItemGroup::create([
            'company_id' => $this->testCompany->id,
            'documentable_type' => $estimate->getMorphClass(),
            'documentable_id' => $estimate->id,
            'name' => 'Child Group',
            'parent_id' => $parentGroup->id,
        ]);

        // Add items
        $estimate->lineItems()->create([
            'company_id' => $this->testCompany->id,
            'offering_id' => $offering->id,
            'group_id' => $parentGroup->id,
            'description' => 'Item in Parent',
            'quantity' => 1,
            'unit_price' => 1000,
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        $estimate->lineItems()->create([
            'company_id' => $this->testCompany->id,
            'offering_id' => $offering->id,
            'group_id' => $childGroup->id,
            'description' => 'Item in Child',
            'quantity' => 1,
            'unit_price' => 2000,
            'subtotal' => 2000,
            'total' => 2000,
        ]);

        // Verify structure
        $this->assertEquals(2, $estimate->lineItemGroups()->count());
        $this->assertEquals(1, $parentGroup->children()->count());
        $this->assertEquals(1, $parentGroup->items()->count());
        $this->assertEquals(1, $childGroup->items()->count());

        // Verify DTO transformation

        $dto = DocumentDTO::fromModel($estimate);

        $this->assertCount(2, $dto->lineItemGroups);
        $parentDto = $dto->lineItemGroups[0];
        $this->assertEquals('Parent Group', $parentDto->name);
        $this->assertCount(1, $parentDto->items);

        $childDto = $dto->lineItemGroups[1];
        $this->assertEquals('Child Group', $childDto->name);
        $this->assertCount(1, $childDto->items);
    }

    public function test_document_dto_respects_main_group_header_config()
    {
        $this->withOfferings();

        $estimate = Estimate::factory()->for($this->testCompany)->create();

        // 1. Without config, it should use defaults/settings
        config(['erp.main_group_header_bg_color' => null]);
        config(['erp.main_group_header_font_color' => null]);
        $dto = DocumentDTO::fromModel($estimate);

        $settings = $estimate->company->documentDefaults()
            ->withoutGlobalScopes()
            ->type($estimate::documentType())
            ->first();
        $expectedBg = $settings?->color_group_bg ?? '#d4b896';
        $expectedText = $settings?->color_group_bg_text ?? '#293834';

        $this->assertEquals($expectedBg, $dto->colorGroupBg);
        $this->assertEquals($expectedText, $dto->colorGroupBgText);

        // 2. With config set, it should use the config values
        config(['erp.main_group_header_bg_color' => '#123456']);
        config(['erp.main_group_header_font_color' => '#ffffff']);
        $dto = DocumentDTO::fromModel($estimate);
        $this->assertEquals('#123456', $dto->colorGroupBg);
        $this->assertEquals('#ffffff', $dto->colorGroupBgText);
    }
}
