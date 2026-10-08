<?php

namespace Tests\Feature;

use App\Models\Story;
use Tests\Concerns\BuildsMinistry;
use Tests\TestCase;

class StoryTest extends TestCase
{
    use BuildsMinistry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMinistry();
    }

    public function test_a_story_goes_from_member_to_published(): void
    {
        $writer = $this->person($this->agege, 'Opeyemi', 'Writer', password: 'secret-pass-1')->user;

        $this->actingAs($writer)->post(route('stories.store'), [
            'title' => 'I came for the drama',
            'type' => 'testimony',
            'body' => "I was selling at the market.\n\n<script>alert(1)</script>The play changed me.",
            'action' => 'submit',
        ])->assertRedirect(route('stories.mine'));

        $story = Story::firstOrFail();
        $this->assertSame('submitted', $story->status);
        $this->assertSame($this->agege->id, $story->org_unit_id);
        $this->get(route('stories'))->assertDontSee('I came for the drama');
        $this->actingAs($this->users['mushin'])->get(route('stories.show', $story))->assertNotFound();

        $this->actingAs($this->users['agege'])->post(route('stories.review', $story), ['decision' => 'publish'])->assertForbidden();

        $this->actingAs($this->users['national'])->post(route('stories.review', $story), ['decision' => 'reject'])->assertSessionHasErrors('note');
        $this->actingAs($this->users['national'])->post(route('stories.review', $story), ['decision' => 'reject', 'note' => 'Add the year.'])->assertRedirect();
        $this->assertSame('rejected', $story->fresh()->status);

        $this->actingAs($writer)->put(route('stories.update', $story), [
            'title' => 'I came for the drama', 'type' => 'testimony', 'body' => 'It was 2019. The play changed me.', 'action' => 'submit',
        ])->assertRedirect();
        $this->actingAs($this->users['national'])->post(route('stories.review', $story), ['decision' => 'publish'])->assertRedirect();
        $this->assertSame('published', $story->fresh()->status);

        auth()->logout();
        $this->get(route('stories.show', $story))->assertOk()->assertSee('It was 2019.');
        $this->actingAs($writer)->get(route('stories.edit', $story))->assertForbidden();
    }

    public function test_story_markdown_strips_html(): void
    {
        $story = new Story(['body' => "Hello **world**\n\n<script>alert(1)</script>"]);
        $html = (string) $story->bodyHtml();
        $this->assertStringContainsString('<strong>world</strong>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_submitting_needs_a_body(): void
    {
        $writer = $this->person($this->agege, 'Short', 'Writer', password: 'secret-pass-1')->user;
        $this->actingAs($writer)->post(route('stories.store'), ['title' => 'Empty', 'type' => 'impact', 'action' => 'submit'])
            ->assertSessionHasErrors('story');
        $this->assertSame('draft', Story::firstOrFail()->status);
    }

    public function test_search_keeps_members_and_reports_inside_scope(): void
    {
        $this->person($this->mokola, 'Zainab', 'Searchable');

        $this->actingAs($this->users['ibadan'])->get(route('search', ['q' => 'Searchable']))->assertSee('Zainab Searchable');
        $this->actingAs($this->users['lagos'])->get(route('search', ['q' => 'Searchable']))->assertDontSee('Zainab Searchable');
        auth()->logout();
        $this->get(route('search', ['q' => 'Searchable']))->assertDontSee('Zainab Searchable');

        $this->get(route('search', ['q' => 'majemu']))->assertOk()->assertSee('Majemu');
    }
}
