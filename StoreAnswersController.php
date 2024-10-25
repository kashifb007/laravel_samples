<?php

namespace App\Http\Controllers\API;

use App\Enums\TestStatus;
use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Question;
use App\Models\Test;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StoreAnswersController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        if ($request->has(key: 'answers')) {
            $answerData = $this->formatRequestData(request: $request);

            $this->validateData(answerData: $answerData);

            foreach ($answerData as $answerItem) {
                $questionId = $answerItem['question_id'];
                if (blank($questionId)) {
                    $questionId = 1;
                }
                $answer = Answer::firstOrNew(attributes: [
                    'test_id' => $answerItem['test_id'],
                    'question_id' => $questionId,
                    'user_id' => $answerItem['user_id'],
                ]);

                $answer->fill(attributes: [
                    'comment' => $answerItem['comment'],
                    'question_option_id' => $answerItem['question_option_id'],
                    'score' => $answerItem['score'],
                ])->save();

                if ($answer->wasRecentlyCreated) {
                    $test = Test::find(id: $answerItem['test_id']);

                    $test->update(attributes: ['test_status_id' => TestStatus::VisitInProgress]);
                    $test->increment(column: 'total_answer_count');
                }
            }
        }

        if ($request->hasFile(key: 'photo')) {
            $questionId = $request->input(key: 'question_id');

            $file = $request->file(key: 'photo');

            $testId = $request->input(key: 'test_id');

            $media = Question::find(id: $questionId)
                ->addMedia($file)
                ->withCustomProperties(customProperties: ['test_id' => (int)$testId, 'env' => app()->environment()])
                ->toMediaCollection(collectionName: 'questions');

            $media->test_id = $testId;
            $media->save();
        }

        return response()->json(data: ['success' => true]);
    }

    private function formatRequestData(Request $request): array
    {
        return array_map(callback: fn ($item) => json_decode(json: $item, associative: true), array: $request->input(key: 'answers'));
    }

    private function validateData(array $answerData): void
    {
        $validator = Validator::make($answerData, rules: [
            '*.test_id' => 'required',
            '*.question_id' => 'required',
            '*.user_id' => 'required',
            '*.comment' => 'required',
            '*.question_option_id' => 'required',
            '*.score' => 'required',
            // 'photos.*' => 'required',
        ]);

        if ($validator->fails()) {
            response()->json(data: ['errors' => $validator->errors()], status: 400);
        }
    }
}
