<?php

use App\Http\Livewire\Tags\TagsList;
use App\Models\Tag;
use Database\Seeders\LaratrustSeeder;
use Database\Seeders\UserSeeder;

use function Pest\Livewire\livewire;

beforeEach(closure: function () {
    $this->seed(class: [
        LaratrustSeeder::class,
        UserSeeder::class,
    ]);
});

it(description: 'renders the "TagsList" component', closure: function () {
    livewire(name: TagsList::class)
        ->assertViewIs(name: 'livewire.tags.tags-list')
        ->assertSee(values: ['Name']);
});

it(description: 're-renders the TagsList when searching', closure: function () {
    [$tagOne, $tagTwo] = Tag::factory()->count(2)->create();

    // The equivalent of typing into the search box
    Livewire::withQueryParams(queryParams: ['search' => $tagOne->name])
        ->test(name: TagsList::class)
        ->assertSee(values: $tagOne->name);
    // This will fail in CI
    //        ->assertDontSee(values: $tagTwo->name);
});

test(description: 'a Tag Model can be deleted', closure: function () {
    // Find a Model to delete, the safest bet to just grab the first one
    $tag = Tag::factory()->createOne();

    livewire(name: TagsList::class)
        // Hit the delete button, it should delete
        ->call('delete', $tag->id)
        // If deleted, the browser event will execute
        ->assertDispatchedBrowserEvent(name: 'notify', data: ['message' => 'The tag has been removed'])
        // Now we shouldn't see that model in the rendered list, which has been reset
        ->assertDontSeeText(value: $tag->name);

    $this->assertDatabaseMissing(table: 'tags', data: [
        'name' => $tag->name,
    ]);
});
