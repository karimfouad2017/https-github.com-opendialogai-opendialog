<?php

namespace Tests\Feature;

use App\Template;
use App\TemplateCollection;
use App\User;
use OpenDialogAi\Core\Components\Configuration\ComponentConfiguration;
use Tests\TestCase;

class TemplateCollectionTest extends TestCase
{
    protected $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
    }

    public function testView()
    {
        /** @var TemplateCollection $collection */
        $collection = TemplateCollection::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            Template::factory()->create([
                'template_collection_id' => $collection->id
            ]);
        }

        // Add one inactive template to ensure its not returned
        Template::factory()->create([
            'template_collection_id' => $collection->id,
            'active' => false
        ]);

        $this->get('/admin/api/template-collections/' . $collection->id)
            ->assertStatus(302);

        $platforms = Template::where('active', 1)
            ->get()
            ->transform(fn(Template $template) => $template->platform_id)
            ->toArray();

        $templates = Template::where('active', 1)
            ->select(['id', 'name', 'description', 'platform_id', 'active', 'template_collection_id'])
            ->get()
            ->toArray();

        $this->actingAs($this->user, 'api')
            ->json('GET', '/admin/api/template-collections/' . $collection->id)
            ->assertStatus(200)
            ->assertJsonFragment([
                'name' => $collection->name,
                'description' => $collection->description,
                'preview' => $collection->preview,
                'active' => $collection->active,
                'platforms' => array_values($platforms),
                'templates' => $templates
            ])
            ->assertJsonCount(10, 'data.templates');
    }

    public function testViewAll()
    {
        /** @var TemplateCollection $collection1 */
        $collection1 = TemplateCollection::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            Template::factory()->create([
                'template_collection_id' => $collection1->id
            ]);
        }
        // Add inactive template
        Template::factory()->create([
            'template_collection_id' => $collection1->id,
            'active' => 0
        ]);

        /** @var TemplateCollection $collection2 */
        $collection2 = TemplateCollection::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            Template::factory()->create([
                'template_collection_id' => $collection2->id
            ]);
        }

        // Inactive collection shouldn't show
        TemplateCollection::factory()->create(
            ['active' => 0]
        );

        $this->get('/admin/api/template-collections/')
            ->assertStatus(302);

        $platforms1 = Template::where('id', '<', '11')->where('active', 1)->get()
            ->transform(fn(Template $template) => $template->platform_id)->sort();

        $platforms2 = Template::where('id', '>', '10')->where('active', 1)->get()
            ->transform(fn(Template $template) => $template->platform_id)->sort();

        $this->actingAs($this->user, 'api')
            ->json('GET', '/admin/api/template-collections/')
            ->assertStatus(200)
            ->assertJsonFragment([
                'name' => $collection1->name,
                'description' => $collection1->description,
                'preview' => $collection1->preview,
                'active' => $collection1->active,
                'platforms' => array_values($platforms1->toArray())
            ])
            ->assertJsonCount(10, 'data.0.templates')
            ->assertJsonFragment([
                'name' => $collection2->name,
                'description' => $collection2->description,
                'preview' => $collection2->preview,
                'active' => $collection2->active,
                'platforms' => array_values($platforms2->toArray())
            ])
            ->assertJsonCount(2, 'data');
    }

    public function testAllPlatforms()
    {
        /** @var TemplateCollection $collection */
        $collection = TemplateCollection::factory()->create();

        Template::factory()->create([
            'template_collection_id' => $collection->id,
            'platform_id' => 'platform.core.webchat',
        ]);

        Template::factory()->create([
            'template_collection_id' => $collection->id,
            'platform_id' => 'platform.core.alexa',
        ]);

        factory(ComponentConfiguration::class)->create([
            'component_id' => 'platform.core.webchat'
        ]);

        factory(ComponentConfiguration::class)->create([
            'component_id' => 'platform.core.alexa'
        ]);

        $this->actingAs($this->user, 'api')
            ->json('GET', '/admin/api/template-collections/' . $collection->id)
            ->assertStatus(200)
            ->assertJsonFragment([
                'all' => true
            ]);

        // Add another platform component so 'all' should now be false

        factory(ComponentConfiguration::class)->create([
            'component_id' => 'platform.core.facebook'
        ]);

        $this->actingAs($this->user, 'api')
            ->json('GET', '/admin/api/template-collections/' . $collection->id)
            ->assertStatus(200)
            ->assertJsonFragment([
                'all' => false
            ]);
    }
}
