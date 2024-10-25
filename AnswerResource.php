<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnswerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'comment' => $this->comment,
            'question_id' => $this->question_id,
            'question_option_id' => $this->question_option_id,
            'score' => $this->score,
            'test_id' => $this->test_id,
            'submitted_at' => $this->created_at,
            'user_id' => $this->user_id,
        ];
    }
}
