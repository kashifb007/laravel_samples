<?php

namespace App\Http\Livewire\Tags;

use App\Http\Livewire\BaseComponent;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Livewire\WithPagination;

class TagsList extends BaseComponent
{
    use WithPagination;

    public int $user_id = 0;

    public function delete(int $tagId)
    {
        Tag::find(id: $tagId)
            ->delete();

        $message = sprintf(__('notification._removed'), __('models.tag'));
        $this->dispatchBrowserEvent(event: 'notify', data: [
            'message' => $message,
        ]);

        $this->reset('search');
    }

    public function render(): View
    {
        $tags = $this->search
            ? Tag::search($this->search)->paginate(perPage: config(key: 'app.per_page'))
            : Tag::paginate(perPage: config(key: 'app.per_page'));

        return view('livewire.tags.tags-list', data: [
            'tags' => $tags,
        ]);
    }
}
